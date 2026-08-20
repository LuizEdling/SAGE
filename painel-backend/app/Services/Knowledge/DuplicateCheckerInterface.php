<?php

namespace App\Services\Knowledge;

use Illuminate\Support\Collection;

interface DuplicateCheckerInterface
{
    /**
     * Retorna candidatos a duplicidade para o conteúdo informado no mesmo componente.
     * Fase 1: comparação textual ingênua. Fase 2: busca semântica no Qdrant.
     *
     * @return Collection<int, array{knowledge_id:int, content:string, score:float}>
     */
    public function findDuplicates(int $componentId, string $content): Collection;
}
