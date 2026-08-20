<?php

namespace App\Services\Knowledge;

use App\Jobs\IngestKnowledgeJob;
use App\Models\Knowledge;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RagIngestionDispatcher implements IngestionDispatcherInterface
{
    public function dispatch(Knowledge $knowledge): void
    {
        IngestKnowledgeJob::dispatch($knowledge->id);
    }

    public function remove(Knowledge $knowledge): void
    {
        if (! $knowledge->qdrant_point_id) {
            return;
        }

        try {
            Http::baseUrl(config('services.sage_agent.url'))
                ->timeout(10)
                ->delete("/ingest/{$knowledge->id}");
        } catch (\Throwable $e) {
            Log::warning('RagIngestionDispatcher: falha ao remover do Qdrant.', [
                'knowledge_id' => $knowledge->id,
                'error' => $e->getMessage(),
            ]);
        }

        $knowledge->update(['qdrant_point_id' => null]);
    }

    public function updateFunctions(Knowledge $knowledge): void
    {
        // Ainda não indexado: as funções vão junto quando o job de ingestão rodar.
        if (! $knowledge->qdrant_point_id) {
            return;
        }

        try {
            Http::baseUrl(config('services.sage_agent.url'))
                ->timeout(10)
                ->put("/ingest/{$knowledge->id}/funcoes", [
                    'function_ids' => $knowledge->functions()->pluck('function.id')->all(),
                ])
                ->throw();
        } catch (\Throwable $e) {
            Log::warning('RagIngestionDispatcher: falha ao atualizar as funções no Qdrant.', [
                'knowledge_id' => $knowledge->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
