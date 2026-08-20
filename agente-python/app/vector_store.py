"""Camada de acesso ao Qdrant (nome do arquivo evita colidir com o pacote
`qdrant_client` instalado via pip — se este arquivo se chamasse
`qdrant_client.py`, o `import qdrant_client` abaixo importaria a si mesmo)."""

from datetime import datetime, timezone
from functools import lru_cache

from qdrant_client import QdrantClient
from qdrant_client.http import models as qm

from app.config import settings


class VectorStore:
    def __init__(self, url: str, collection: str, dim: int):
        self.collection = collection
        self.client = QdrantClient(url=url)
        self._ensure_collection(dim)

    def _ensure_collection(self, dim: int) -> None:
        existing = [c.name for c in self.client.get_collections().collections]

        if self.collection not in existing:
            self.client.create_collection(
                collection_name=self.collection,
                vectors_config=qm.VectorParams(size=dim, distance=qm.Distance.COSINE),
            )
            self.client.create_payload_index(
                collection_name=self.collection,
                field_name="knowledge_id",
                field_schema=qm.PayloadSchemaType.INTEGER,
            )
            self.client.create_payload_index(
                collection_name=self.collection,
                field_name="component_id",
                field_schema=qm.PayloadSchemaType.INTEGER,
            )

    def upsert_chunks(
        self,
        knowledge_id: int,
        chunks: list[str],
        vectors: list[list[float]],
        component_id: int,
        modulo: str,
        tipo: str,
        fonte: str,
        valid_until: str | None,
        data_aprovacao: str | None,
        function_ids: list[int] | None = None,
    ) -> list[str]:
        import uuid

        point_ids = [str(uuid.uuid4()) for _ in chunks]

        points = [
            qm.PointStruct(
                id=point_ids[i],
                vector=vectors[i],
                payload={
                    "knowledge_id": knowledge_id,
                    "component_id": component_id,
                    "modulo": modulo,
                    "tipo": tipo,
                    "fonte": fonte,
                    "valid_until": valid_until,
                    "data_aprovacao": data_aprovacao or datetime.now(timezone.utc).isoformat(),
                    "chunk_index": i,
                    "content": chunk,
                    "function_ids": function_ids or [],
                },
            )
            for i, chunk in enumerate(chunks)
        ]

        self.client.upsert(collection_name=self.collection, points=points)

        return point_ids

    def set_function_ids(self, knowledge_id: int, function_ids: list[int]) -> None:
        """Atualiza só o vínculo com funções de todos os trechos do conhecimento.

        Mudar o vínculo não muda o texto: refazer embedding (e a formatação
        por LLM que vem antes dela) seria custo sem ganho nenhum."""
        self.client.set_payload(
            collection_name=self.collection,
            payload={"function_ids": function_ids},
            points=qm.Filter(
                must=[qm.FieldCondition(key="knowledge_id", match=qm.MatchValue(value=knowledge_id))]
            ),
        )

    def delete_by_knowledge_id(self, knowledge_id: int) -> None:
        self.client.delete(
            collection_name=self.collection,
            points_selector=qm.FilterSelector(
                filter=qm.Filter(
                    must=[qm.FieldCondition(key="knowledge_id", match=qm.MatchValue(value=knowledge_id))]
                )
            ),
        )

    def search(
        self,
        vector: list[float],
        component_id: int | None = None,
        limit: int = 5,
        score_threshold: float | None = None,
        exclude_knowledge_id: int | None = None,
    ) -> list[qm.ScoredPoint]:
        must: list[qm.Condition] = []
        must_not: list[qm.Condition] = []

        if component_id is not None:
            must.append(qm.FieldCondition(key="component_id", match=qm.MatchValue(value=component_id)))

        if exclude_knowledge_id is not None:
            must_not.append(qm.FieldCondition(key="knowledge_id", match=qm.MatchValue(value=exclude_knowledge_id)))

        query_filter = qm.Filter(must=must, must_not=must_not) if (must or must_not) else None

        return self.client.search(
            collection_name=self.collection,
            query_vector=vector,
            query_filter=query_filter,
            limit=limit,
            score_threshold=score_threshold,
        )


@lru_cache
def get_vector_store() -> VectorStore:
    return VectorStore(settings.qdrant_url, settings.qdrant_collection, settings.embedding_dim)
