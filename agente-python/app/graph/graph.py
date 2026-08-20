from functools import lru_cache

from langgraph.graph import END, StateGraph

from app.config import settings
from app.graph.nodes import (
    avaliar_suficiencia,
    node_montar_query,
    node_aplicar_regras_negocio,
    node_avaliar_diagnostico,
    node_classificar_modulo,
    node_diagnostico_insuficiente,
    node_formular_resposta,
    node_gerar_diagnostico,
    node_recuperar_conhecimento,
    node_refinar_busca,
    node_reranquear,
    node_incorporar_resultado,
    node_relatar_execucao,
    node_selecionar_funcao,
    node_solicitar_execucao,
    refinamento_produziu_query,
    rota_continuacao,
    rota_funcao,
    rotear_entrada,
)
from app.graph.state import AgentState


def build_graph():
    """Monta o grafo de estados que coordena os agentes especializados.

    O fluxo não é uma linha reta: depois de gerar o diagnóstico, o Agente
    Avaliador julga se o material recuperado o sustenta. Quando não sustenta,
    o Agente de Refinamento reescreve a consulta e devolve o fluxo à
    recuperação — é o ciclo de refinamento sucessivo, e é o que diferencia
    esta arquitetura de um pipeline RAG de passagem única.

        montar_query → classificar_modulo → recuperar_conhecimento
             → reranquear → aplicar_regras_negocio → gerar_diagnostico
             → avaliar_diagnostico ─┬─ suficiente ──→ selecionar_funcao ─┬─→ formular_resposta → FIM
                                    │                                    └─→ solicitar_execucao → FIM (Painel executa a consulta)
                                    ├─ sem base ────→ diagnostico_insuficiente → FIM
                                    └─ insuficiente → refinar_busca ─┬─→ recuperar_… (ciclo)
                                                                     └─→ encerra

    Continuação (o Painel devolve o resultado da função pedida):

        incorporar_resultado ─┬─ consulta  → formular_resposta (com o valor real e talvez uma proposta) → FIM
                              └─ alteração → relatar_execucao → FIM

    A terminação é garantida por `max_refinamentos` (teto de voltas), pela
    ausência de candidatos no recall e pela falha de reformulação — os três
    cortes descritos em `avaliar_suficiencia`.
    """
    workflow = StateGraph(AgentState)

    workflow.add_node("montar_query", node_montar_query)
    workflow.add_node("classificar_modulo", node_classificar_modulo)
    workflow.add_node("recuperar_conhecimento", node_recuperar_conhecimento)
    workflow.add_node("reranquear", node_reranquear)
    workflow.add_node("aplicar_regras_negocio", node_aplicar_regras_negocio)
    workflow.add_node("gerar_diagnostico", node_gerar_diagnostico)
    workflow.add_node("avaliar_diagnostico", node_avaliar_diagnostico)
    workflow.add_node("refinar_busca", node_refinar_busca)
    workflow.add_node("formular_resposta", node_formular_resposta)
    workflow.add_node("diagnostico_insuficiente", node_diagnostico_insuficiente)

    workflow.add_node("selecionar_funcao", node_selecionar_funcao)
    workflow.add_node("solicitar_execucao", node_solicitar_execucao)
    workflow.add_node("incorporar_resultado", node_incorporar_resultado)
    workflow.add_node("relatar_execucao", node_relatar_execucao)

    # Pergunta nova começa pela composição da consulta; a continuação (o
    # Painel devolvendo o resultado de uma função) retoma o diagnóstico.
    workflow.set_conditional_entry_point(
        rotear_entrada,
        {"montar_query": "montar_query", "incorporar_resultado": "incorporar_resultado"},
    )

    workflow.add_edge("montar_query", "classificar_modulo")
    workflow.add_edge("classificar_modulo", "recuperar_conhecimento")
    workflow.add_edge("recuperar_conhecimento", "reranquear")
    workflow.add_edge("reranquear", "aplicar_regras_negocio")
    workflow.add_edge("aplicar_regras_negocio", "gerar_diagnostico")
    workflow.add_edge("gerar_diagnostico", "avaliar_diagnostico")

    # Fecha o ciclo: o diagnóstico fraco volta para a recuperação em vez de
    # ser entregue ao usuário.
    workflow.add_conditional_edges(
        "avaliar_diagnostico",
        avaliar_suficiencia,
        {
            # Material aprovado: antes de responder, decide se confere uma
            # configuração no sistema legado.
            "formular_resposta": "selecionar_funcao",
            "refinar_busca": "refinar_busca",
            "diagnostico_insuficiente": "diagnostico_insuficiente",
        },
    )

    workflow.add_conditional_edges(
        "refinar_busca",
        refinamento_produziu_query,
        {
            "recuperar_conhecimento": "recuperar_conhecimento",
            "formular_resposta": "selecionar_funcao",
            "diagnostico_insuficiente": "diagnostico_insuficiente",
        },
    )

    workflow.add_conditional_edges(
        "selecionar_funcao",
        rota_funcao,
        {"solicitar_execucao": "solicitar_execucao", "formular_resposta": "formular_resposta"},
    )

    workflow.add_conditional_edges(
        "incorporar_resultado",
        rota_continuacao,
        {"relatar_execucao": "relatar_execucao", "formular_resposta": "formular_resposta"},
    )

    workflow.add_edge("formular_resposta", END)
    workflow.add_edge("diagnostico_insuficiente", END)
    workflow.add_edge("solicitar_execucao", END)
    workflow.add_edge("relatar_execucao", END)

    # Trava de segurança do LangGraph, independente das condições de parada do
    # próprio fluxo: mesmo que uma delas falhe, a execução não fica presa.
    # Cada volta consome no máximo 4 passos (recuperar → reranquear → regras →
    # diagnóstico) mais o avaliador e o refinamento.
    return workflow.compile().with_config(
        recursion_limit=14 + settings.max_refinamentos * 6,
    )


@lru_cache
def get_graph():
    return build_graph()
