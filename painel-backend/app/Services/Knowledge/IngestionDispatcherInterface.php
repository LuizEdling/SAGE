<?php

namespace App\Services\Knowledge;

use App\Models\Knowledge;

interface IngestionDispatcherInterface
{
    /**
     * Disparado quando um conhecimento é aprovado. Fase 1: no-op (log).
     * Fase 2: enfileira job que ingere no Qdrant e grava qdrant_point_id.
     */
    public function dispatch(Knowledge $knowledge): void;

    /**
     * Disparado quando um conhecimento aprovado deixa de estar disponível
     * pro RAG (removido, ou editado e voltou pra "pendente"). Remove os
     * pontos do Qdrant e limpa qdrant_point_id.
     */
    public function remove(Knowledge $knowledge): void;

    /**
     * Disparado quando muda o vínculo conhecimento ↔ função. Atualiza só os
     * metadados dos trechos já indexados (sem refazer embedding nem
     * formatação): as funções vinculadas viajam no payload do Qdrant (§4.3).
     */
    public function updateFunctions(Knowledge $knowledge): void;
}
