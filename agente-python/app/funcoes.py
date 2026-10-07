"""Regras determinísticas do uso de funções do sistema legado pelo Agente.

O modelo de linguagem só *sugere* qual função usar e com quais parâmetros.
Quais funções podem ser consideradas, e se os parâmetros são válidos, é
decidido aqui, em código — a sugestão do modelo nunca é executada sem passar
por esta validação (e o Painel e o sistema legado ainda validam de novo).
"""

import json
import re

CONSULTA = "consulta"
ALTERACAO = "alteracao"

_DATA = re.compile(r"^\d{4}-\d{2}-\d{2}$")
_VERDADEIRO = {"true", "1", "sim", "s", "yes"}
_FALSO = {"false", "0", "nao", "não", "n", "no"}


def candidatas(regras: list[dict], funcoes: list[dict]) -> list[dict]:
    """Funções vinculadas ao conhecimento efetivamente recuperado (payload do
    Qdrant, §4.3) que estão no catálogo ativo enviado pelo Painel.

    O vínculo vem do conhecimento, não do módulo: o Agente só considera uma
    função quando o material que fundamenta a resposta a indica."""
    vinculadas: list[int] = []
    for regra in regras:
        for function_id in regra.get("function_ids") or []:
            if function_id not in vinculadas:
                vinculadas.append(function_id)

    por_id = {f["id"]: f for f in funcoes}

    return [por_id[i] for i in vinculadas if i in por_id]


def do_tipo(funcoes: list[dict], tipo: str) -> list[dict]:
    return [f for f in funcoes if f["tipo"] == tipo]


def analisar_parametros(funcao: dict, parametros: dict, contexto_erp: dict) -> tuple[dict, list[dict]]:
    """Converte e confere os parâmetros contra a declaração da função.

    Devolve (validos, problemas). Um problema é um parâmetro obrigatório que
    faltou (`motivo: "ausente"`) ou um valor que não converte para o tipo
    declarado (`motivo: "invalido"`); cada um carrega o nome, o tipo e a
    descrição declarados, o que basta para pedir o dado ao usuário.

    Parâmetro não declarado é descartado. `entidade_id` ausente assume a
    entidade ativa de quem pergunta — é a entidade sobre a qual ele está
    perguntando. Login de usuário nunca é presumido: alterar "a própria conta"
    por engano seria pior do que não propor nada.
    """
    validos: dict = {}
    problemas: list[dict] = []

    for declarado in funcao.get("parametros", []):
        nome = declarado["nome"]
        valor = parametros.get(nome)

        if (valor is None or valor == "") and nome == "entidade_id" and contexto_erp.get("entidade_id") is not None:
            valor = contexto_erp["entidade_id"]

        descricao = {"nome": nome, "tipo": declarado["tipo"], "descricao": declarado.get("descricao")}

        if valor is None or valor == "":
            if declarado.get("obrigatorio"):
                problemas.append({**descricao, "motivo": "ausente"})
            continue

        convertido = _converter(valor, declarado["tipo"])

        if convertido is None:
            problemas.append({**descricao, "motivo": "invalido"})
            continue

        validos[nome] = convertido

    return validos, problemas


def validar_parametros(funcao: dict, parametros: dict, contexto_erp: dict) -> dict | None:
    """Parâmetros convertidos, ou None se faltar um obrigatório ou algum valor for inválido."""
    validos, problemas = analisar_parametros(funcao, parametros, contexto_erp)

    return None if problemas else validos


def _converter(valor, tipo: str):
    if tipo == "integer":
        if isinstance(valor, bool):
            return None
        try:
            return int(str(valor).strip())
        except ValueError:
            return None

    if tipo == "float":
        if isinstance(valor, bool):
            return None
        try:
            return float(str(valor).strip().replace(",", "."))
        except ValueError:
            return None

    if tipo == "boolean":
        if isinstance(valor, bool):
            return valor
        texto = str(valor).strip().lower()
        if texto in _VERDADEIRO:
            return True
        if texto in _FALSO:
            return False
        return None

    if tipo == "date":
        texto = str(valor).strip()
        return texto if _DATA.match(texto) else None

    texto = str(valor).strip()
    return texto if 0 < len(texto) <= 255 else None


def leitura_sem_llm(leituras: list[dict], contexto_erp: dict) -> tuple[dict, dict] | None:
    """Escolha determinística para o modo sem modelo de linguagem: só quando há
    exatamente uma consulta candidata e os parâmetros obrigatórios dela podem
    ser preenchidos pelo contexto. Na dúvida, nenhuma função."""
    if len(leituras) != 1:
        return None

    parametros = validar_parametros(leituras[0], {}, contexto_erp)

    return (leituras[0], parametros) if parametros is not None else None


def _curto(valor, limite: int = 300) -> str:
    texto = json.dumps(valor, ensure_ascii=False) if valor is not None else "—"

    return texto if len(texto) <= limite else texto[:limite] + "…"


def resumo_verificado(execucoes: list[dict], funcoes: list[dict]) -> str | None:
    """O que já foi conferido e alterado nesta conversa, em linhas curtas para os
    prompts. Sem isso o Agente não sabe que uma configuração já foi lida ou
    corrigida e pode repeti-la; com isso a conversa segue de onde parou."""
    nomes = {f["id"]: f["nome"] for f in funcoes}
    linhas = []

    for execucao in execucoes:
        if execucao.get("status") != "executada":
            continue

        nome = nomes.get(execucao["function_id"], f"função #{execucao['function_id']}")
        parametros = _curto(execucao.get("parametros") or {}, 120)

        if execucao["tipo"] == ALTERACAO:
            linhas.append(
                f"- Alteração feita «{nome}» {parametros}: antes {_curto(execucao.get('antes'))} → "
                f"depois {_curto(execucao.get('depois'))}"
            )
        else:
            linhas.append(f"- Consulta feita «{nome}» {parametros}: {_curto(execucao.get('dados'))}")

    return "\n".join(linhas) or None


def consultas_pendentes(candidatas_ids: list[int], funcoes: list[dict], execucoes: list[dict]) -> list[dict]:
    """Consultas candidatas (vinculadas ao mesmo material) que ainda não foram
    feitas nesta conversa, na ordem das candidatas."""
    feitas = {e["function_id"] for e in execucoes if e.get("tipo") == CONSULTA and e.get("status") == "executada"}
    por_id = {f["id"]: f for f in funcoes}

    return [
        por_id[i]
        for i in candidatas_ids
        if i in por_id and por_id[i]["tipo"] == CONSULTA and i not in feitas
    ]
