from fastapi import FastAPI

from app.chunking import chunk_text
from app.config import settings
from app.embeddings import get_embedding_service
from app.graph.graph import get_graph
from app.llm import format_knowledge_markdown, recomendar_funcoes
from app.schemas import (
    RecomendarFuncoesRequest,
    RecomendarFuncoesResponse,
    AcaoFuncao,
    DiagnoseRequest,
    DiagnoseResponse,
    DuplicateCandidate,
    FonteItem,
    FormatKnowledgeRequest,
    FormatKnowledgeResponse,
    FuncoesVinculadasRequest,
    IngestRequest,
    IngestResponse,
    ModuloRecurso,
    SearchSimilarRequest,
    SearchSimilarResponse,
)
from app.vector_store import get_vector_store

app = FastAPI(title="S.A.G.E. — Agente de Diagnóstico")


@app.get("/health")
def health():
    return {"status": "ok"}


@app.post("/ingest", response_model=IngestResponse)
def ingest(payload: IngestRequest):
    chunks = chunk_text(payload.content, settings.chunk_max_chars)

    if not chunks:
        return IngestResponse(knowledge_id=payload.knowledge_id, point_ids=[], chunks=0)

    vectors = get_embedding_service().embed(chunks)

    # Reingestão (ex: conhecimento editado e reaprovado) — remove os pontos
    # antigos antes de inserir os novos, para não deixar chunks obsoletos.
    get_vector_store().delete_by_knowledge_id(payload.knowledge_id)

    point_ids = get_vector_store().upsert_chunks(
        knowledge_id=payload.knowledge_id,
        chunks=chunks,
        vectors=vectors,
        component_id=payload.component_id,
        modulo=payload.modulo,
        tipo=payload.tipo,
        fonte=payload.fonte,
        valid_until=payload.valid_until,
        data_aprovacao=payload.data_aprovacao,
        function_ids=payload.function_ids,
    )

    return IngestResponse(knowledge_id=payload.knowledge_id, point_ids=point_ids, chunks=len(chunks))


@app.put("/ingest/{knowledge_id}/funcoes")
def update_function_ids(knowledge_id: int, payload: FuncoesVinculadasRequest):
    get_vector_store().set_function_ids(knowledge_id, payload.function_ids)
    return {"knowledge_id": knowledge_id, "function_ids": payload.function_ids}


@app.delete("/ingest/{knowledge_id}")
def remove_ingested(knowledge_id: int):
    get_vector_store().delete_by_knowledge_id(knowledge_id)
    return {"knowledge_id": knowledge_id, "removed": True}


@app.post("/search-similar", response_model=SearchSimilarResponse)
def search_similar(payload: SearchSimilarRequest):
    vector = get_embedding_service().embed_one(payload.content)

    results = get_vector_store().search(
        vector=vector,
        component_id=payload.component_id,
        limit=payload.limit,
        score_threshold=settings.score_threshold,
    )

    duplicates = [
        DuplicateCandidate(
            knowledge_id=r.payload["knowledge_id"],
            content=r.payload["content"],
            score=r.score,
        )
        for r in results
    ]

    return SearchSimilarResponse(duplicates=duplicates)


@app.post("/format-knowledge", response_model=FormatKnowledgeResponse)
def format_knowledge(payload: FormatKnowledgeRequest):
    return FormatKnowledgeResponse(content_md=format_knowledge_markdown(payload.content))


@app.post("/recommend-functions", response_model=RecomendarFuncoesResponse)
def recommend_functions(payload: RecomendarFuncoesRequest):
    recomendadas = recomendar_funcoes(payload.conhecimento, [f.model_dump() for f in payload.funcoes])

    if recomendadas is None:
        return RecomendarFuncoesResponse(recomendadas=[], disponivel=False)

    return RecomendarFuncoesResponse(recomendadas=recomendadas)


@app.post("/diagnose", response_model=DiagnoseResponse)
def diagnose(payload: DiagnoseRequest):
    graph = get_graph()

    result = graph.invoke(
        {
            "question": payload.question,
            "tela": payload.tela.model_dump() if payload.tela else None,
            "component_id_hint": payload.component_id_hint,
            "modulos": [m.model_dump() for m in payload.modulos],
            "historico": [h.model_dump() for h in payload.historico],
            "respondendo_esclarecimento": payload.respondendo_esclarecimento,
            "funcoes": [f.model_dump() for f in payload.funcoes],
            "contexto_erp": payload.contexto_erp.model_dump() if payload.contexto_erp else None,
            "continuacao": payload.continuacao.model_dump() if payload.continuacao else None,
        "execucoes_anteriores": [e.model_dump() for e in payload.execucoes_anteriores],
        }
    )

    diagnostico = result["diagnostico"]
    recurso = result.get("modulo_recurso")

    return DiagnoseResponse(
        resumo=diagnostico["resumo"],
        causa_provavel=diagnostico["causa_provavel"],
        modulo_afetado=diagnostico["modulo_afetado"],
        confianca=diagnostico["confianca"],
        fontes=[FonteItem(**f) for f in diagnostico["fontes"]],
        resposta_texto=result["resposta_texto"],
        modulo_id=result.get("modulo_id"),
        modulo_recurso=ModuloRecurso(**recurso) if recurso else None,
        aguardando_esclarecimento=result.get("aguardando_esclarecimento", False),
        iteracoes=diagnostico.get("iteracoes", 1),
        consultas=diagnostico.get("consultas", []),
        sustentado=diagnostico.get("sustentado"),
        acao=AcaoFuncao(**result["acao"]) if result.get("acao") else None,
        funcoes_candidatas=result.get("funcoes_candidatas", []),
        funcao_utilizada=_funcao_utilizada(result.get("resultado_funcao")),
        parametro_solicitado=result.get("parametro_faltante"),
    )


def _funcao_utilizada(execucao: dict | None) -> dict | None:
    """Resumo da função que fundamentou a resposta, para a trilha de evidência."""
    if not execucao:
        return None

    return {
        "codigo": execucao["codigo"],
        "tipo": execucao["tipo"],
        "status": execucao["status"],
        "parametros": execucao.get("parametros", {}),
    }
