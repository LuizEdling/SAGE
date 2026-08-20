import re
from datetime import datetime, timezone

from app.config import settings
from app.embeddings import get_embedding_service
from app.graph.state import AgentState
from app.funcoes import (
    ALTERACAO,
    CONSULTA,
    analisar_parametros,
    candidatas,
    consultas_pendentes,
    do_tipo,
    leitura_sem_llm,
    resumo_verificado,
)
from app.llm import (
    APRESENTACAO,
    DESPEDIDA,
    anexar_tela_a_consulta,
    apresentacao_com_modulos,
    pergunta_se_refere_a_tela,
    avaliar_sustentacao,
    classificar_modulo,
    contextualizar_pergunta,
    decidir_funcao,
    formulate_response,
    reformular_query,
    tipo_de_conversa,
)
from app.rerank import rerank
from app.vector_store import get_vector_store


def _ultima_pergunta_do_usuario(historico: list[dict]) -> str | None:
    for mensagem in reversed(historico):
        if mensagem.get("autor") == "usuario":
            return mensagem.get("conteudo")

    return None


def node_montar_query(state: AgentState) -> AgentState:
    """Nó 0 — decide o texto que será usado na busca.

    Normalmente é a própria pergunta. Mas quando o usuário está respondendo a
    um esclarecimento pedido pelo Agente, a mensagem sozinha ("é pessoa
    jurídica", "sim") não tem conteúdo recuperável: a busca precisa carregar
    junto a pergunta original que gerou o esclarecimento."""
    pergunta = state["question"]

    if state.get("respondendo_esclarecimento"):
        original = _ultima_pergunta_do_usuario(state.get("historico", []))

        if original:
            pergunta = f"{original} {pergunta}"
    elif state.get("historico"):
        # Continuação da conversa ("você poderia resolver isso?"): a busca precisa
        # do assunto, que está na fala anterior.
        # O nome da tela só ajuda a reescrita quando a mensagem fala da tela; senão o modelo a
        # enfia numa pergunta que não é sobre ela ("para que servem esses chamados?").
        tela_da_pergunta = state.get("tela") if pergunta_se_refere_a_tela(pergunta) else None
        pergunta = contextualizar_pergunta(pergunta, state["historico"], tela_da_pergunta)

    # "O que essa tela mostra?" só é recuperável sabendo qual é a tela.
    pergunta = anexar_tela_a_consulta(pergunta, state.get("tela"))

    return {
        **state,
        "query_busca": pergunta,
        "query_original": pergunta,
        "queries_tentadas": [pergunta],
        "iteracao": 0,
    }


def node_classificar_modulo(state: AgentState) -> AgentState:
    """Nó 0 — enquadra a pergunta em um dos módulos cadastrados, usando o LLM.

    Roda antes da recuperação porque o módulo identificado é usado como filtro
    de metadado no Qdrant: sem isso, uma pergunta sobre Faturamento pode
    recuperar conhecimento de Estoque só por proximidade semântica, e com a
    base crescendo esse tipo de erro aumenta. Quando não dá pra identificar
    com segurança, segue sem filtro (recall > precisão nesse caso)."""
    modulo_id = state.get("component_id_hint") or classificar_modulo(
        state.get("query_busca") or state["question"], state.get("modulos", []), state.get("tela")
    )

    return {**state, "modulo_id": modulo_id}


def node_recuperar_conhecimento(state: AgentState) -> AgentState:
    """Nó 1 — 'recuperação de informações' (TCC §4.3), primeiro estágio.

    Busca deliberadamente ampla: limiar baixo e mais candidatos do que serão
    usados. Quem faz o corte fino é o re-rank do nó seguinte.

    É também o ponto de retorno do ciclo de refinamento. Nas voltas seguintes
    a consulta já vem reformulada, e o filtro de módulo é abandonado: a
    primeira passada aposta em precisão (busca dentro do módulo classificado),
    e o refinamento aposta em recall, porque um dos motivos plausíveis de a
    primeira ter falhado é justamente o módulo ter sido classificado errado.

    Os candidatos de todas as iterações são acumulados, então uma volta do
    ciclo nunca piora o material disponível — só pode acrescentar."""
    embedding = get_embedding_service().embed_one(state.get("query_busca") or state["question"])
    refinando = state.get("iteracao", 0) > 0
    modulo_id = None if refinando else state.get("modulo_id")

    results = get_vector_store().search(
        vector=embedding,
        component_id=modulo_id,
        limit=settings.top_k * settings.recall_multiplier,
        score_threshold=settings.recall_threshold,
    )

    # Se o filtro por módulo não trouxe nada, o módulo classificado pode estar
    # errado (ou aquele módulo ainda não tem conhecimento cadastrado): repete
    # sem filtro em vez de responder "não sei" indevidamente.
    if not results and modulo_id is not None:
        results = get_vector_store().search(
            vector=embedding,
            component_id=None,
            limit=settings.top_k * settings.recall_multiplier,
            score_threshold=settings.recall_threshold,
        )

    novos = [
        {
            "knowledge_id": r.payload["knowledge_id"],
            "modulo": r.payload["modulo"],
            "component_id": r.payload.get("component_id"),
            "content": r.payload["content"],
            "chunk_index": r.payload.get("chunk_index", 0),
            "score": r.score,
            "valid_until": r.payload.get("valid_until"),
            "function_ids": r.payload.get("function_ids", []),
        }
        for r in results
    ]

    chunks = _acumular_candidatos(state.get("candidatos", []), novos)

    return {
        **state,
        "candidatos": chunks,
        "chunks": chunks,
        "houve_candidatos": state.get("houve_candidatos", False) or bool(novos),
    }


def _acumular_candidatos(anteriores: list[dict], novos: list[dict]) -> list[dict]:
    """Une os candidatos das iterações, sem duplicar chunk.

    O mesmo chunk pode ser recuperado por consultas diferentes com scores
    diferentes; nesse caso vale o maior, que é o da consulta que melhor o
    representou."""
    por_chave: dict[tuple[int, str], dict] = {}

    for chunk in [*anteriores, *novos]:
        chave = (chunk["knowledge_id"], chunk["content"])
        existente = por_chave.get(chave)

        if existente is None or chunk["score"] > existente["score"]:
            por_chave[chave] = chunk

    return list(por_chave.values())


def node_reranquear(state: AgentState) -> AgentState:
    """Nó 2 — segundo estágio da recuperação: re-pontua os candidatos com
    fusão densa + léxica + aderência ao módulo e aplica o corte final.

    Pontua sempre pela consulta original, mesmo quando os candidatos vieram de
    uma consulta reformulada: o que decide se um trecho é relevante é a
    pergunta do usuário, não o texto que o próprio Agente escreveu para
    buscá-la.

    Nas voltas do refinamento o módulo classificado não ganha bônus, pelo
    mesmo motivo que a recuperação deixa de filtrar por ele: um dos motivos
    plausíveis da primeira passada ter falhado é o módulo estar errado, e o
    bônus manteria os trechos dele na frente do conhecimento certo."""
    refinando = state.get("iteracao", 0) > 0

    chunks = rerank(
        pergunta=state.get("query_original") or state.get("query_busca") or state["question"],
        candidatos=state.get("chunks", []),
        modulo_id=None if refinando else state.get("modulo_id"),
        limite=settings.top_k,
        corte=settings.score_threshold,
    )

    return {**state, "chunks": chunks}


def node_aplicar_regras_negocio(state: AgentState) -> AgentState:
    """Nó 3 — 'interpretação de regras de negócio'. Descarta conhecimento
    temporário expirado e reúne por knowledge_id os chunks do mesmo registro.

    Um registro longo é indexado em mais de um chunk, e o que vence o re-rank
    pode ser só um pedaço dele (ex.: a nota final de um guia, sem o passo a
    passo). Em vez de manter só esse pedaço, o registro é remontado com todos
    os seus chunks recuperados, na ordem original, e fica na posição do
    melhor deles."""
    now = datetime.now(timezone.utc)
    regras: list[dict] = []
    por_registro: dict[int, dict] = {}

    for chunk in state.get("chunks", []):
        valid_until = chunk.get("valid_until")

        if valid_until:
            try:
                if datetime.fromisoformat(valid_until.replace("Z", "+00:00")) < now:
                    continue
            except ValueError:
                pass

        if chunk["knowledge_id"] in por_registro:
            continue

        regra = dict(chunk)
        por_registro[chunk["knowledge_id"]] = regra
        regras.append(regra)

    # Irmãos de cada registro: os do re-rank e os do recall (que pode ter
    # trazido um chunk do mesmo registro que ficou fora do corte final).
    irmaos: dict[int, dict[int, str]] = {}
    for chunk in [*state.get("candidatos", []), *state.get("chunks", [])]:
        if chunk["knowledge_id"] in por_registro:
            irmaos.setdefault(chunk["knowledge_id"], {})[chunk.get("chunk_index", 0)] = chunk["content"]

    for knowledge_id, regra in por_registro.items():
        partes = irmaos.get(knowledge_id, {})
        if len(partes) > 1:
            regra["content"] = "\n\n".join(partes[i] for i in sorted(partes))

    return {**state, "regras": regras}


def node_gerar_diagnostico(state: AgentState) -> AgentState:
    """Nó 4 — 'geração de diagnósticos'. Consolida no formato de
    diagnostic-report-format. Esse material é interno (alimenta a trilha de
    evidência do Painel), não é o que o usuário lê no chat."""
    regras = state.get("regras", [])

    modulo_contagem: dict[str, int] = {}
    for r in regras:
        modulo_contagem[r["modulo"]] = modulo_contagem.get(r["modulo"], 0) + 1
    modulo_afetado = max(modulo_contagem, key=modulo_contagem.get) if modulo_contagem else "Não identificado"

    causa_provavel = regras[0]["content"] if regras else "Não foi possível determinar uma causa provável."
    confianca = sum(r["score"] for r in regras) / len(regras) if regras else 0.0

    resumo = (
        f"Encontrados {len(regras)} registro(s) de conhecimento relacionados ao módulo \"{modulo_afetado}\"."
        if regras
        else "Nenhum conhecimento suficientemente relevante foi encontrado para essa pergunta."
    )

    fontes = [
        {"knowledge_id": r["knowledge_id"], "modulo": r["modulo"], "trecho": r["content"], "score": round(r["score"], 4)}
        for r in regras
    ]

    diagnostico = {
        "resumo": resumo,
        "causa_provavel": causa_provavel,
        "modulo_afetado": modulo_afetado,
        "confianca": round(min(confianca, 1.0), 4),
        "fontes": fontes,
        # Quantas passadas de recuperação sustentaram este diagnóstico.
        # Registrado na trilha de evidência do Painel.
        "iteracoes": state.get("iteracao", 0) + 1,
        "consultas": list(state.get("queries_tentadas", [])),
    }

    return {**state, "diagnostico": diagnostico}


def node_avaliar_diagnostico(state: AgentState) -> AgentState:
    """Nó 5 — avalia o próprio diagnóstico antes de entregá-lo.

    É este nó que fecha o ciclo do grafo: em vez de responder com o primeiro
    material recuperado, o Agente julga se ele sustenta um diagnóstico e, se
    não sustentar, devolve o fluxo à recuperação com a consulta reformulada.
    Não altera o estado — a decisão em si é tomada em `avaliar_suficiencia`,
    que o LangGraph consulta para escolher a aresta de saída. O nó existe
    para que a avaliação seja um passo explícito do grafo, e não um efeito
    colateral escondido dentro de outro nó.

    O julgamento é feito sobre o conteúdo (o material responde à pergunta?),
    não só sobre o score: ver `avaliar_sustentacao`. O veredito fica no estado
    e no diagnóstico, para a trilha de evidência e a avaliação do fluxo."""
    regras = state.get("regras", [])

    if not regras:
        return {**state, "sustentado": None}

    sustentado = avaliar_sustentacao(
        state.get("query_original") or state["question"],
        [r["content"] for r in regras],
    )

    diagnostico = {**state.get("diagnostico", {}), "sustentado": sustentado}

    return {**state, "sustentado": sustentado, "diagnostico": diagnostico}


def node_refinar_busca(state: AgentState) -> AgentState:
    """Nó 6 — reformula a consulta e prepara a próxima volta do ciclo.

    Sai daqui de volta para `recuperar_conhecimento`. Quando a reformulação
    não é possível (provedor indisponível, ou o LLM devolveu uma consulta já
    tentada), sinaliza a falha em `refinamento_ok`: buscar de novo com a mesma
    consulta devolveria exatamente o mesmo resultado, então o grafo encerra
    com o material que já tem em vez de gastar outra recuperação."""
    tentativas = list(state.get("queries_tentadas", []))

    nova_query = reformular_query(
        pergunta=state.get("query_original") or state["question"],
        tentativas=tentativas,
        trechos=[c["content"] for c in state.get("chunks", [])],
    )

    if not nova_query:
        return {**state, "refinamento_ok": False}

    tentativas.append(nova_query)

    return {
        **state,
        "query_busca": nova_query,
        "queries_tentadas": tentativas,
        "iteracao": state.get("iteracao", 0) + 1,
        "refinamento_ok": True,
    }


def _modulo_das_fontes(state: AgentState) -> dict | None:
    """Módulo a que o conhecimento recuperado pertence — é dele que saem a
    rota e os prints oferecidos ao LLM. Prefere o módulo das fontes (o que de
    fato fundamentou a resposta) ao módulo classificado na entrada."""
    regras = state.get("regras", [])
    modulos = state.get("modulos", [])

    if not modulos:
        return None

    component_id = next((r.get("component_id") for r in regras if r.get("component_id")), None) or state.get(
        "modulo_id"
    )

    return next((m for m in modulos if m["id"] == component_id), None)


def node_formular_resposta(state: AgentState) -> AgentState:
    """Nó 5 — 'formulação da resposta em linguagem natural'."""
    modulo = _modulo_das_fontes(state)
    resultado = state.get("resultado_funcao")
    acao = state.get("acao")
    faltante = state.get("parametro_faltante")

    resposta_texto, usar_imagens, aguardando = formulate_response(
        pergunta=state["question"],
        fontes=state["diagnostico"]["fontes"],
        modulo=modulo,
        historico=state.get("historico", []),
        dados_sistema=_dados_para_resposta(state, resultado) if resultado else None,
        proposta=_proposta_para_resposta(state, acao) if acao and acao["tipo"] == "propor_alteracao" else None,
        parametro_faltante=faltante,
        ja_verificado=_ja_verificado(state),
        tela=state.get("tela"),
    )

    if faltante:
        # A pergunta do parâmetro é o que importa agora. O modelo sinaliza que o texto é
        # uma pergunta com o marcador `PERGUNTA: SIM` (o "?" não serve: ele costuma pedir
        # com "preciso que você me diga"). Sem o marcador, o texto era uma resposta
        # completa e é trocado pela pergunta padrão, montada em código.
        if not aguardando:
            resposta_texto = _pergunta_do_parametro(faltante)

        aguardando = True
        usar_imagens = False

    modulo_recurso = None

    # Quando o Agente devolve uma pergunta, ainda não há orientação nenhuma
    # para ilustrar — anexar prints nesse momento só confunde.
    if usar_imagens and modulo and not aguardando:
        modulo_recurso = {
            "modulo": modulo["nome"],
            "rota": modulo.get("rota"),
            "imagens": [img["url"] for img in modulo.get("imagens", [])],
        }

    return {
        **state,
        "resposta_texto": resposta_texto,
        "modulo_recurso": modulo_recurso,
        "aguardando_esclarecimento": aguardando,
    }


def node_diagnostico_insuficiente(state: AgentState) -> AgentState:
    """Terminal — nenhum chunk sobreviveu ao re-rank, ou o avaliador julgou que
    o material recuperado não sustenta uma resposta mesmo após o refinamento.
    Nunca arriscar alucinar uma causa/módulo nesse caso.

    Exceção: saudação, pergunta sobre o próprio assistente ("como você pode me
    ajudar?") e agradecimento não são dúvidas sem base — o Agente se apresenta ou
    se despede, sem recusar."""
    tipo = tipo_de_conversa(state["question"])

    if tipo != "duvida":
        resposta = (
            DESPEDIDA
            if tipo == "agradecimento"
            else apresentacao_com_modulos(state.get("modulos", []), com_funcoes=bool(state.get("funcoes")))
        )

        return {
            **state,
            "diagnostico": {
                "resumo": "Saudação, agradecimento ou pergunta sobre o assistente: sem problema relatado.",
                "causa_provavel": "Não se aplica — não há problema relatado.",
                "modulo_afetado": "Não identificado",
                "confianca": 0.0,
                "fontes": [],
                "iteracoes": state.get("iteracao", 0) + 1,
                "consultas": list(state.get("queries_tentadas", [])),
                "sustentado": state.get("sustentado"),
            },
            "modulo_recurso": None,
            "aguardando_esclarecimento": False,
            "resposta_texto": resposta,
        }

    diagnostico = {
        "resumo": "Não há conhecimento cadastrado suficiente para responder essa pergunta com segurança.",
        "causa_provavel": "Desconhecida — nenhum conteúdo relevante encontrado na base de conhecimento.",
        "modulo_afetado": "Não identificado",
        "confianca": 0.0,
        "fontes": [],
        "iteracoes": state.get("iteracao", 0) + 1,
        "consultas": list(state.get("queries_tentadas", [])),
        "sustentado": state.get("sustentado"),
    }

    return {
        **state,
        "diagnostico": diagnostico,
        "modulo_recurso": None,
        "aguardando_esclarecimento": False,
        "resposta_texto": (
            f"{APRESENTACAO}\n\n"
            "Sobre essa pergunta específica eu ainda não tenho a informação necessária "
            "para te orientar com segurança. O melhor caminho é abrir um chamado para "
            "que alguém da equipe possa ajudar."
        ),
    }


def _encerramento(state: AgentState) -> str:
    """Como terminar quando não há (mais) refinamento a fazer: responder com o
    que foi recuperado, ou admitir que não há base para responder.

    Material que o avaliador julgou não sustentar a resposta não vira
    resposta: é a recusa deliberada do TCC (§4.3), principal defesa contra
    alucinação. Sem veredito (provedor indisponível), responde com o que há."""
    if state.get("regras") and state.get("sustentado") is not False:
        return "formular_resposta"

    return "diagnostico_insuficiente"


def avaliar_suficiencia(state: AgentState) -> str:
    """Condição de saída do ciclo, avaliada depois do diagnóstico.

    Três respostas possíveis: refinar (voltar à recuperação com outra
    consulta), formular (o material sustenta a resposta) ou admitir
    insuficiência. A terminação do ciclo é garantida por três cortes
    independentes, qualquer um deles suficiente para encerrar:

      1. o orçamento de refinamentos (`max_refinamentos`) se esgota;
      2. o estágio de recall não devolveu nenhum candidato em iteração
         alguma — reformular não inventa conhecimento que não está na base,
         e evita gastar chamada de LLM com saudação ou assunto fora do
         escopo;
      3. o diagnóstico já atingiu a confiança mínima e o avaliador não
         reprovou o material.
    """
    confiante = state.get("diagnostico", {}).get("confianca", 0.0) >= settings.confianca_minima

    # Responde direto só se o avaliador não reprovou o material. Confiança alta
    # com veredito negativo acontece: trechos de outro assunto do sistema
    # pontuam bem por terem palavras e estrutura parecidas com a pergunta.
    if confiante and state.get("sustentado") is not False:
        return "formular_resposta"

    orcamento_disponivel = state.get("iteracao", 0) < settings.max_refinamentos

    if orcamento_disponivel and state.get("houve_candidatos"):
        return "refinar_busca"

    return _encerramento(state)


def refinamento_produziu_query(state: AgentState) -> str:
    """Só volta à recuperação se a reformulação gerou uma consulta nova."""
    return "recuperar_conhecimento" if state.get("refinamento_ok") else _encerramento(state)


# --- Funções do sistema legado (Fase 4) -------------------------------------
#
# O Agente nunca executa nada: ele pede. Na primeira fase, se o material
# recuperado indica uma função de consulta, o grafo termina pedindo ao Painel
# que a execute; o Painel devolve o resultado numa segunda chamada
# (continuação), que entra direto em `incorporar_resultado` — sem refazer
# classificação, recuperação e avaliação. Alteração nunca é executada no
# fluxo do diagnóstico: vira proposta, e só o clique do usuário em
# "Autorizar" a executa. Isso também garante a terminação: no máximo uma
# consulta e uma proposta por pergunta.


def rotear_entrada(state: AgentState) -> str:
    """Pergunta nova começa pela composição da consulta; continuação (o Painel
    devolvendo o resultado de uma função) retoma o diagnóstico já feito."""
    return "incorporar_resultado" if state.get("continuacao") else "montar_query"


def _funcao_por_id(state: AgentState, function_id: int) -> dict | None:
    return next((f for f in state.get("funcoes", []) if f["id"] == function_id), None)


def _ja_verificado(state: AgentState) -> str | None:
    """O que a conversa já conferiu e alterou (turnos anteriores), para os prompts."""
    return resumo_verificado(state.get("execucoes_anteriores") or [], state.get("funcoes") or [])


_PEDE_DE_NOVO = re.compile(r"de novo|novamente|outra vez|reconfer|confira outra|atualiz", re.IGNORECASE)


def _repete_consulta(state: AgentState, funcao: dict, parametros: dict) -> bool:
    """A consulta (mesma função, mesmos parâmetros) já foi feita nesta conversa e a
    pessoa não pediu para refazê-la."""
    if funcao["tipo"] != "consulta" or _PEDE_DE_NOVO.search(state["question"]):
        return False

    return any(
        e.get("function_id") == funcao["id"]
        and e.get("tipo") == "consulta"
        and e.get("status", "executada") == "executada"
        and (e.get("parametros") or {}) == parametros
        for e in state.get("execucoes_anteriores") or []
    )


def _decidir(
    state: AgentState, opcoes: list[dict], resultado: dict | None = None
) -> tuple[tuple[dict, dict, str | None] | None, dict | None]:
    """Pede ao modelo a escolha entre `opcoes` e valida a resposta em código.

    Devolve (escolha, faltante). `escolha` é (função, parâmetros, motivo) quando
    a função foi escolhida e os parâmetros são válidos. `faltante` descreve o
    que impediu a escolha quando o modelo apontou uma função mas faltou (ou
    veio inválido) um parâmetro: o Agente pergunta isso ao usuário em vez de
    desistir em silêncio. Nunca se pergunta duas vezes na mesma conversa: numa
    resposta a esclarecimento, a função que ainda não puder ser usada é só
    descartada.
    """
    contexto = state.get("contexto_erp") or {}

    pergunta = state.get("query_original") or state["question"]

    # A pergunta reescrita carrega o problema inteiro; o que a pessoa acabou de pedir
    # (ex.: "e a conta dele?") é o que diz qual consulta falta fazer.
    if state.get("execucoes_anteriores") and pergunta != state["question"]:
        pergunta = f"{pergunta}\nÚltima mensagem do usuário: {state['question']}"

    decisao = decidir_funcao(
        pergunta=pergunta,
        trechos=[r["content"] for r in state.get("regras", [])],
        candidatas=opcoes,
        contexto_erp=contexto,
        resultado_leitura=resultado,
        ja_verificado=_ja_verificado(state),
    )

    if decisao is None:
        # Sem modelo de linguagem, só a consulta inequívoca; alteração nunca.
        if resultado is None and settings.llm_provider == "template":
            escolha = leitura_sem_llm(opcoes, contexto)
            return ((escolha[0], escolha[1], None) if escolha else None), None
        return None, None

    funcao = next((f for f in opcoes if f["id"] == decisao["function_id"]), None)

    if funcao is None:
        return None, None

    validos, problemas = analisar_parametros(funcao, decisao["parametros"], contexto)

    if not problemas:
        if resultado is None and _repete_consulta(state, funcao, validos):
            # O modelo escolheu de novo o que a conversa já conferiu (e a pessoa não pediu
            # para conferir outra vez): tenta de novo só com o que ainda não foi feito.
            restantes = [o for o in opcoes if o is not funcao]

            if restantes:
                return _decidir(state, restantes, resultado)

            return None, None

        return (funcao, validos, decisao["motivo"]), None

    if state.get("respondendo_esclarecimento"):
        return None, None

    return None, {
        "function_id": funcao["id"],
        "codigo": funcao.get("codigo"),
        "funcao": funcao.get("nome"),
        "tipo": funcao["tipo"],
        "parametros": problemas,
    }


def _pergunta_do_parametro(faltante: dict) -> str:
    """Pergunta montada em código, usada quando o modelo não devolve uma.

    O texto vem da descrição que o próprio sistema legado declara para o
    parâmetro, então serve para qualquer função sem frases por função."""
    acao = "propor essa alteração" if faltante["tipo"] == ALTERACAO else "conferir isso no sistema"
    pedidos = []

    for problema in faltante["parametros"]:
        descricao = (problema.get("descricao") or problema["nome"]).strip().rstrip(".")
        descricao = descricao[:1].lower() + descricao[1:]
        pedidos.append(
            f"o valor informado para {descricao} não parece válido"
            if problema["motivo"] == "invalido"
            else descricao
        )

    return f"Para {acao}, preciso saber: {'; '.join(pedidos)}. Pode me informar?"


def node_selecionar_funcao(state: AgentState) -> AgentState:
    """Decide se o diagnóstico deve conferir uma configuração no sistema antes
    de responder.

    Só considera funções vinculadas ao conhecimento recuperado e só a partir
    de uma CONSULTA: sem consulta vinculada, não se propõe alteração às cegas
    — o Agente só sugere mudar o que acabou de ler. Sem usuário do sistema
    legado (simulação sem usuário, bateria de avaliação), não usa funções."""
    base = {**state, "acao": None, "funcoes_candidatas": [], "parametro_faltante": None}

    if not state.get("contexto_erp") or not state.get("funcoes") or not state.get("regras"):
        return base

    opcoes = candidatas(state["regras"], state["funcoes"])
    base["funcoes_candidatas"] = [f["id"] for f in opcoes]

    leituras = do_tipo(opcoes, CONSULTA)

    if not leituras:
        return base

    escolha, faltante = _decidir(base, leituras)

    if escolha is None:
        return {**base, "parametro_faltante": faltante}

    funcao, parametros, motivo = escolha

    return {
        **base,
        "acao": {"tipo": "executar_leitura", "function_id": funcao["id"], "parametros": parametros, "motivo": motivo},
    }


def rota_funcao(state: AgentState) -> str:
    return "solicitar_execucao" if state.get("acao") else "formular_resposta"


def node_solicitar_execucao(state: AgentState) -> AgentState:
    """Terminal da primeira fase: não há texto para o usuário ainda — o Painel
    executa a consulta e chama o Agente de novo com o resultado."""
    return {**state, "resposta_texto": "", "modulo_recurso": None, "aguardando_esclarecimento": False}


def node_incorporar_resultado(state: AgentState) -> AgentState:
    """Entrada da segunda fase. Restaura o material da primeira e, se a
    consulta mostrou que a configuração é a causa e o usuário pode alterá-la,
    escolhe a alteração que a corrige (proposta, nunca execução)."""
    continuacao = state["continuacao"]
    execucao = continuacao["execucao"]

    regras = [
        {
            "knowledge_id": f["knowledge_id"],
            "modulo": f["modulo"],
            "component_id": continuacao.get("modulo_id"),
            "content": f["trecho"],
            "score": f["score"],
            "valid_until": None,
            "function_ids": [],
        }
        for f in continuacao.get("fontes", [])
    ]

    restaurado = node_gerar_diagnostico(
        {
            **state,
            "regras": regras,
            "modulo_id": continuacao.get("modulo_id"),
            "iteracao": max(continuacao.get("iteracoes", 1) - 1, 0),
            "queries_tentadas": continuacao.get("consultas", []),
            # A pergunta autônoma da primeira fase é a primeira consulta tentada;
            # sem ela, a continuação usaria só a mensagem crua.
            "query_original": (continuacao.get("consultas") or [state["question"]])[0],
        }
    )
    restaurado["diagnostico"]["sustentado"] = continuacao.get("sustentado")
    restaurado.update(
        {
            "resultado_funcao": execucao,
            "funcoes_candidatas": continuacao.get("funcoes_candidatas", []),
            "acao": None,
            "parametro_faltante": None,
        }
    )

    leitura_ok = execucao["tipo"] == CONSULTA and execucao["status"] == "executada"

    # `pode_alterar` vem do próprio sistema legado: só se propõe o que o
    # GLPI aceitaria daquele usuário.
    if leitura_ok and execucao.get("resultado", {}).get("pode_alterar") is True:
        alteracoes = do_tipo(
            [f for f in restaurado.get("funcoes", []) if f["id"] in restaurado["funcoes_candidatas"]],
            ALTERACAO,
        )

        if alteracoes:
            escolha, faltante = _decidir(restaurado, alteracoes, resultado=execucao["resultado"].get("dados", {}))
            restaurado["parametro_faltante"] = faltante

            if escolha is not None:
                funcao, parametros, motivo = escolha
                restaurado["acao"] = {
                    "tipo": "propor_alteracao",
                    "function_id": funcao["id"],
                    "parametros": parametros,
                    "motivo": motivo,
                }

    return restaurado


def rota_continuacao(state: AgentState) -> str:
    return "relatar_execucao" if state["resultado_funcao"]["tipo"] == ALTERACAO else "formular_resposta"


def _dados_para_resposta(state: AgentState, resultado: dict) -> dict:
    funcao = _funcao_por_id(state, resultado["function_id"]) or {}

    return {
        "funcao": funcao.get("nome", resultado.get("codigo")),
        "consultada_com_sucesso": resultado["status"] == "executada",
        "dados": resultado.get("resultado", {}).get("dados", {}),
        # Direito de alterar, segundo o próprio sistema legado, e quem pergunta:
        # os dados podem ser de outra pessoa ou item citado na pergunta.
        "pode_alterar": resultado.get("resultado", {}).get("pode_alterar"),
        "quem_pergunta": (state.get("contexto_erp") or {}).get("usuario_login"),
        "sem_permissao": resultado.get("erro_codigo") == "SEM_PERMISSAO",
    }


def _proposta_para_resposta(state: AgentState, acao: dict) -> dict:
    funcao = _funcao_por_id(state, acao["function_id"]) or {}

    return {"funcao": funcao.get("nome"), "descricao": funcao.get("descricao"), "parametros": acao["parametros"]}


def _formatar_valor(valor) -> str:
    if isinstance(valor, bool):
        return "Sim" if valor else "Não"
    return str(valor)


def _diferencas(antes, depois, rotulo: str = "") -> list[str]:
    """Linhas "campo: antes → depois" do que mudou, descendo em listas/dicts
    (ex.: várias notificações do mesmo evento)."""
    if isinstance(depois, dict) and isinstance(antes, dict):
        linhas = []
        nome = depois.get("nome") or depois.get("regra") or depois.get("categoria") or rotulo
        for chave, valor in depois.items():
            linhas += _diferencas(antes.get(chave), valor, chave.replace("_", " ") if not nome else f"{nome} — {chave.replace('_', ' ')}")
        return linhas

    if isinstance(depois, list) and isinstance(antes, list):
        linhas = []
        for item_antes, item_depois in zip(antes, depois):
            linhas += _diferencas(item_antes, item_depois, rotulo)
        return linhas

    if antes != depois:
        return [f"{rotulo}: {_formatar_valor(antes)} → {_formatar_valor(depois)}"]

    return []


def _ainda_falta_conferir(state: AgentState) -> str:
    """Depois de uma alteração, avisa quais consultas do mesmo material ainda não
    foram feitas nesta conversa. É o que deixa a pessoa saber que o problema pode
    ter outra causa e o que o assistente pode conferir em seguida."""
    pendentes = consultas_pendentes(
        state.get("funcoes_candidatas") or [], state.get("funcoes") or [], state.get("execucoes_anteriores") or []
    )[:2]

    if not pendentes:
        return ""

    nomes = " e ".join(f"«{f['nome']}»" for f in pendentes)

    return f"\n\nSe o problema continuar, ainda posso conferir: {nomes}. Quer que eu confira?"


def node_relatar_execucao(state: AgentState) -> AgentState:
    """Resposta depois que o usuário decidiu sobre uma alteração proposta.

    Texto montado em código, sem modelo de linguagem: é um relato do que de
    fato aconteceu no sistema (antes → depois), e não pode haver variação
    nem "já está feito" quando falhou."""
    execucao = state["resultado_funcao"]
    funcao = _funcao_por_id(state, execucao["function_id"]) or {}
    nome = funcao.get("nome") or execucao.get("codigo")
    resultado = execucao.get("resultado", {})
    modulo = _modulo_das_fontes(state)
    caminho = f" O caminho no sistema é {modulo['rota']}." if modulo and modulo.get("rota") else ""

    if execucao["status"] == "executada":
        mudancas = _diferencas(resultado.get("antes", {}), resultado.get("depois", {}))
        detalhe = ("\n" + "\n".join(f"- {linha}" for linha in mudancas)) if mudancas else ""
        texto = f"Pronto, fiz a alteração: {nome}.{detalhe}"
        texto += _ainda_falta_conferir(state)
    elif execucao["status"] == "negada":
        texto = f"Tudo bem, não alterei nada.{caminho}"
    elif execucao.get("erro_codigo") == "SEM_PERMISSAO":
        texto = (
            "Não consegui fazer essa alteração: o seu perfil no sistema não tem permissão para isso. "
            f"Um administrador pode fazer a alteração.{caminho}"
        )
    else:
        texto = (
            "Não consegui concluir a alteração no sistema agora — nada foi alterado. "
            "Tente de novo em instantes ou abra um chamado."
        )

    return {**state, "resposta_texto": texto, "modulo_recurso": None, "aguardando_esclarecimento": False}
