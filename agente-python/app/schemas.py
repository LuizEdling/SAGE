from typing import Optional

from pydantic import BaseModel, Field


class IngestRequest(BaseModel):
    knowledge_id: int
    content: str
    component_id: int
    modulo: str
    tipo: str
    fonte: str = "manual"
    valid_until: Optional[str] = None
    data_aprovacao: Optional[str] = None
    # Funções vinculadas ao conhecimento (§4.3): vão no payload de cada trecho
    # e são o que o Agente pode usar a partir desse material.
    function_ids: list[int] = []


class FuncoesVinculadasRequest(BaseModel):
    function_ids: list[int]


class IngestResponse(BaseModel):
    knowledge_id: int
    point_ids: list[str]
    chunks: int


class SearchSimilarRequest(BaseModel):
    component_id: int
    content: str
    limit: int = 5


class DuplicateCandidate(BaseModel):
    knowledge_id: int
    content: str
    score: float


class SearchSimilarResponse(BaseModel):
    duplicates: list[DuplicateCandidate]


class ModuloImagem(BaseModel):
    url: str
    caption: Optional[str] = None


class ModuloInfo(BaseModel):
    """Catálogo de módulos cadastrados, enviado pelo Painel a cada pergunta.
    O MySQL é a fonte de verdade desses dados (nome/rota/prints), então em vez
    de duplicar isso no Qdrant o Painel manda junto no request."""

    id: int
    nome: str
    descricao: Optional[str] = None
    rota: Optional[str] = None
    imagens: list[ModuloImagem] = []


class MensagemHistorico(BaseModel):
    """Turno anterior da conversa. Necessário para o Agente poder pedir um
    esclarecimento e depois entender a resposta do usuário — que isolada
    ("é pessoa jurídica") não significa nada para a busca."""

    autor: str  # usuario | agente
    conteudo: str


class ParametroFuncao(BaseModel):
    nome: str
    tipo: str  # string | integer | boolean | date | float
    obrigatorio: bool = False
    descricao: Optional[str] = None


class FuncaoInfo(BaseModel):
    """Função do sistema legado aprovada e ativa no Painel. Assim como os
    módulos, chega a cada pergunta: o MySQL é a fonte de verdade de status e
    parâmetros, e desativar uma função vale na hora."""

    id: int
    codigo: str
    nome: str
    descricao: str
    tipo: str  # consulta | alteracao
    component_id: int
    parametros: list[ParametroFuncao] = []


class ContextoErp(BaseModel):
    """Quem está perguntando no sistema legado. Sem contexto (simulação sem
    usuário, bateria de avaliação), o Agente não usa funções."""

    usuario_login: str
    usuario_nome: Optional[str] = None
    perfil: Optional[str] = None
    interface: Optional[str] = None
    entidade_id: Optional[int] = None
    entidade_nome: Optional[str] = None


class ExecucaoRealizada(BaseModel):
    """O que o Painel executou no sistema legado a pedido do Agente."""

    execution_id: Optional[int] = None
    function_id: int
    codigo: str
    tipo: str  # consulta | alteracao
    parametros: dict = {}
    status: str  # executada | falhou | negada
    resultado: dict = {}
    erro_codigo: Optional[str] = None


class Continuacao(BaseModel):
    """Segunda fase de um diagnóstico que pediu uma função: o material da
    primeira fase volta junto com o resultado, para o grafo não refazer
    classificação, recuperação e avaliação."""

    fontes: list["FonteItem"] = []
    modulo_id: Optional[int] = None
    consultas: list[str] = []
    iteracoes: int = 1
    sustentado: Optional[bool] = None
    funcoes_candidatas: list[int] = []
    execucao: ExecucaoRealizada


class RecomendarFuncoesRequest(BaseModel):
    conhecimento: str
    funcoes: list[FuncaoInfo]


class FuncaoRecomendada(BaseModel):
    function_id: int
    motivo: Optional[str] = None


class RecomendarFuncoesResponse(BaseModel):
    recomendadas: list[FuncaoRecomendada]
    # False quando não há modelo de linguagem disponível para recomendar.
    disponivel: bool = True


class AcaoFuncao(BaseModel):
    """O Agente nunca executa: ele pede. Leitura o Painel executa e devolve
    (continuação); alteração vira proposta que o usuário precisa autorizar."""

    tipo: str  # executar_leitura | propor_alteracao
    function_id: int
    parametros: dict = {}
    motivo: Optional[str] = None


class ExecucaoAnterior(BaseModel):
    """Uma execução já feita nesta conversa: a memória do que o Agente conferiu e
    alterou, para a conversa continuar de onde parou em vez de recomeçar."""

    function_id: int
    tipo: str  # consulta | alteracao
    status: str = "executada"
    parametros: dict = {}
    dados: dict = {}
    antes: Optional[dict] = None
    depois: Optional[dict] = None


class TelaAtual(BaseModel):
    """Tela do sistema legado em que o chat está aberto quando a pergunta é feita.
    Serve para o Agente entender "esta tela", "aqui" e para desempatar o módulo."""

    caminho: Optional[str] = None
    titulo: Optional[str] = None


class DiagnoseRequest(BaseModel):
    question: str
    tela: Optional[TelaAtual] = None
    component_id_hint: Optional[int] = None
    modulos: list[ModuloInfo] = []
    historico: list[MensagemHistorico] = []
    # True quando a última fala do Agente foi uma pergunta de esclarecimento:
    # a mensagem atual é a resposta a ela, não uma pergunta nova.
    respondendo_esclarecimento: bool = False
    funcoes: list[FuncaoInfo] = []
    contexto_erp: Optional[ContextoErp] = None
    continuacao: Optional[Continuacao] = None
    # Consultas e alterações já executadas nesta conversa (turnos anteriores).
    execucoes_anteriores: list[ExecucaoAnterior] = []


class FonteItem(BaseModel):
    knowledge_id: int
    modulo: str
    trecho: str
    score: float = 0.0


class ModuloRecurso(BaseModel):
    modulo: str
    rota: Optional[str] = None
    imagens: list[str] = []


class DiagnoseResponse(BaseModel):
    resumo: str
    causa_provavel: str
    modulo_afetado: str
    confianca: float = Field(ge=0, le=1)
    fontes: list[FonteItem]
    resposta_texto: str
    modulo_id: Optional[int] = None
    modulo_recurso: Optional[ModuloRecurso] = None
    # O Agente devolveu uma pergunta em vez de um diagnóstico: a próxima
    # mensagem do usuário deve ser tratada como resposta a ela.
    aguardando_esclarecimento: bool = False
    # Quantas passadas de recuperação sustentaram o diagnóstico (1 = o
    # material da primeira busca bastou) e quais consultas foram usadas.
    # Alimentam a trilha de evidência do Painel e a avaliação do fluxo.
    iteracoes: int = 1
    consultas: list[str] = Field(default_factory=list)
    # Veredito do agente avaliador sobre o material recuperado (None = não
    # avaliado). False com resposta de recusa = recusa deliberada.
    sustentado: Optional[bool] = None
    # Função que o Agente pede ao Painel (None = nenhuma).
    acao: Optional[AcaoFuncao] = None
    # Funções vinculadas ao material recuperado e ativas — voltam na
    # continuação para a segunda fase escolher dentro do mesmo conjunto.
    funcoes_candidatas: list[int] = []
    # Função cujo resultado fundamentou esta resposta (trilha de evidência).
    funcao_utilizada: Optional[dict] = None
    # O Agente escolheu uma função, mas faltou (ou veio inválido) um parâmetro e a
    # resposta é uma pergunta ao usuário sobre ele. Alimenta a trilha de evidência.
    parametro_solicitado: Optional[dict] = None


class FormatKnowledgeRequest(BaseModel):
    content: str


class FormatKnowledgeResponse(BaseModel):
    content_md: str


Continuacao.model_rebuild()
