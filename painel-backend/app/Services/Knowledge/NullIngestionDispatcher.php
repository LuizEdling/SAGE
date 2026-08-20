<?php

namespace App\Services\Knowledge;

use App\Models\Knowledge;
use Illuminate\Support\Facades\Log;

/**
 * Fase 1: prova de que o seam é chamado, sem falar com o Qdrant ainda.
 * Substituído por RagIngestionDispatcher na Fase 2 — mantido aqui só como
 * referência/fallback caso o binding seja revertido.
 */
class NullIngestionDispatcher implements IngestionDispatcherInterface
{
    public function dispatch(Knowledge $knowledge): void
    {
        Log::info('NullIngestionDispatcher: conhecimento aprovado, ingestão real desativada.', [
            'knowledge_id' => $knowledge->id,
        ]);
    }

    public function remove(Knowledge $knowledge): void
    {
        Log::info('NullIngestionDispatcher: remoção solicitada, ingestão real desativada.', [
            'knowledge_id' => $knowledge->id,
        ]);
    }

    public function updateFunctions(Knowledge $knowledge): void
    {
        Log::info('NullIngestionDispatcher: vínculo de funções alterado, ingestão real desativada.', [
            'knowledge_id' => $knowledge->id,
        ]);
    }
}
