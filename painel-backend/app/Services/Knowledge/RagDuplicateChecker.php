<?php

namespace App\Services\Knowledge;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fase 2: busca semântica real via agente Python + Qdrant, no lugar da
 * comparação textual (LIKE) da Fase 1 (NaiveDuplicateChecker).
 */
class RagDuplicateChecker implements DuplicateCheckerInterface
{
    public function findDuplicates(int $componentId, string $content): Collection
    {
        try {
            $response = Http::baseUrl(config('services.sage_agent.url'))
                ->timeout(10)
                ->post('/search-similar', [
                    'component_id' => $componentId,
                    'content' => $content,
                ]);

            if (! $response->successful()) {
                return collect();
            }

            return collect($response->json('duplicates', []));
        } catch (\Throwable $e) {
            // Verificação de duplicidade é um "alerta", não um bloqueio — se o
            // agente estiver fora do ar, o cadastro de conhecimento não pode
            // travar por causa disso.
            Log::warning('RagDuplicateChecker: agente indisponível.', ['error' => $e->getMessage()]);

            return collect();
        }
    }
}
