<?php

namespace App\Services\Knowledge;

use App\Models\Knowledge;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Fase 1: normaliza o texto e busca conteúdo parecido via LIKE no mesmo
 * componente. Alerta o usuário, não bloqueia o cadastro. Fase 2 troca esta
 * implementação por busca semântica no Qdrant (mesma interface).
 */
class NaiveDuplicateChecker implements DuplicateCheckerInterface
{
    public function findDuplicates(int $componentId, string $content): Collection
    {
        $normalized = Str::squish(Str::lower($content));
        $needle = Str::limit($normalized, 60, '');

        return Knowledge::query()
            ->where('component_id', $componentId)
            ->where('content', 'like', '%'.$needle.'%')
            ->get(['id', 'content'])
            ->map(fn (Knowledge $k) => [
                'knowledge_id' => $k->id,
                'content' => $k->content,
                'score' => 1.0,
            ]);
    }
}
