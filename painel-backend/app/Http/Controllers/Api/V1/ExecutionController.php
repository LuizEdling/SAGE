<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Execution;
use App\Repositories\ExecutionRepository;
use App\Support\ApiResponse;
use App\Transformers\ExecutionTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Auditoria das funções executadas ou propostas no sistema legado (painel de supervisão). */
class ExecutionController extends Controller
{
    public function __construct(
        private readonly ExecutionRepository $repository,
        private readonly ExecutionTransformer $transformer,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Execution::class);

        return ApiResponse::data($this->transformer->paginated($this->repository->paginateFiltered($request)));
    }

    public function show(Execution $execution): JsonResponse
    {
        $this->authorize('view', $execution);

        $execution->load(['function', 'message.conversation']);

        return ApiResponse::data($this->transformer->item($execution));
    }
}
