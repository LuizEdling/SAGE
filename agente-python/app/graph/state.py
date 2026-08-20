from typing import Optional, TypedDict


class Chunk(TypedDict):
    knowledge_id: int
    modulo: str
    component_id: int
    content: str
    score: float
    valid_until: Optional[str]
    # Funções vinculadas ao conhecimento de origem do trecho.
    function_ids: list[int]


class ModuloDict(TypedDict, total=False):
    id: int
    nome: str
    rota: Optional[str]
    imagens: list[dict]


class MensagemHistoricoDict(TypedDict):
    autor: str
    conteudo: str


class AgentState(TypedDict, total=False):
    question: str
    component_id_hint: Optional[int]
    historico: list[MensagemHistoricoDict]
    respondendo_esclarecimento: bool
    # Texto efetivamente usado na busca vetorial. Difere de `question` quando
    # a mensagem é resposta a um esclarecimento: aí a busca precisa da
    # pergunta original junto, senão procura por "sim, é pessoa jurídica".
    # Muda a cada refinamento; `query_original` guarda a primeira.
    query_busca: str
    # Consulta da primeira iteração. O re-rank pontua sempre por ela, para o
    # critério de relevância continuar ancorado no que o usuário perguntou
    # mesmo depois de uma reformulação.
    query_original: str
    # Todas as consultas já usadas, na ordem. Alimenta a reformulação (para
    # não repetir) e vira registro do ciclo na trilha de evidência.
    queries_tentadas: list[str]
    # Quantas voltas o ciclo de refinamento já deu. 0 = primeira passada.
    iteracao: int
    # A busca ampla chegou a devolver algum candidato? Se nem o estágio de
    # recall achou nada, reformular não cria conhecimento que não existe na
    # base — o ciclo encerra sem gastar chamada de LLM.
    houve_candidatos: bool
    # A reformulação da volta atual produziu uma consulta nova? Se não, não
    # adianta buscar de novo — o resultado seria idêntico.
    refinamento_ok: bool
    aguardando_esclarecimento: bool
    # Catálogo de módulos cadastrados, vindo do Painel junto com a pergunta.
    modulos: list[ModuloDict]
    # Módulo em que o Gemini classificou a pergunta (None = não identificado).
    modulo_id: Optional[int]
    # União bruta dos candidatos de todas as iterações, antes do re-rank. É o
    # que faz uma volta do ciclo só poder acrescentar material, nunca perder.
    candidatos: list[Chunk]
    # Candidatos que sobreviveram ao re-rank da iteração corrente.
    chunks: list[Chunk]
    regras: list[Chunk]
    # Veredito do agente avaliador: o material sustenta a resposta?
    # None = não avaliado (sem material ou provedor indisponível).
    sustentado: Optional[bool]
    # --- Funções do sistema legado (Fase 4) ---
    # Catálogo de funções aprovadas e ativas, vindo do Painel.
    funcoes: list[dict]
    # Tela do sistema legado em que o chat estava aberto ({caminho, titulo}).
    tela: Optional[dict]
    # Quem pergunta no sistema legado; sem isso o Agente não usa funções.
    contexto_erp: Optional[dict]
    # Segunda fase: o Painel executou a função pedida e devolve o resultado.
    continuacao: Optional[dict]
    # Funções vinculadas ao material recuperado ∩ catálogo ativo.
    funcoes_candidatas: list[int]
    # O que o Agente pede ao Painel: executar_leitura | propor_alteracao.
    acao: Optional[dict]
    # Consultas e alterações já executadas nesta conversa, vindas do Painel.
    execucoes_anteriores: list[dict]
    # Função que o modelo apontou mas que não pôde ser usada porque faltou (ou veio
    # inválido) um parâmetro: o Agente pergunta isso ao usuário.
    parametro_faltante: Optional[dict]
    # Execução que o Painel fez e devolveu na continuação.
    resultado_funcao: Optional[dict]
    diagnostico: dict
    resposta_texto: str
    modulo_recurso: Optional[dict]
