<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Knowledge\UpdateKnowledgeFunctionsRequest;
use App\Models\Knowledge;
use App\Services\Knowledge\IngestionDispatcherInterface;
use App\Support\ApiResponse;
use App\Transformers\KnowledgeTransformer;
use Illuminate\Http\JsonResponse;

/**
 * Vincula funções a um conhecimento (§4.3). Só o gestor: o vínculo decide o
 * que o Agente pode executar a partir daquele conhecimento.
 */
class KnowledgeFunctionController extends Controller
{
    public function __construct(
        private readonly IngestionDispatcherInterface $ingestion,
        private readonly KnowledgeTransformer $transformer,
    ) {}

    public function update(UpdateKnowledgeFunctionsRequest $request, Knowledge $knowledge): JsonResponse
    {
        $knowledge->functions()->sync($request->validated('function_ids'));

        // Já indexado: atualiza os metadados dos trechos no Qdrant agora.
        $this->ingestion->updateFunctions($knowledge);

        $knowledge->load(['component', 'approver', 'images', 'functions']);

        return ApiResponse::data($this->transformer->item($knowledge));
    }
}
