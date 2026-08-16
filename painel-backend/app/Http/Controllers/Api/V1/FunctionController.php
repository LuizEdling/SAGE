<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AgentFunction;
use App\Repositories\AgentFunctionRepository;
use App\Support\ApiResponse;
use App\Transformers\AgentFunctionTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FunctionController extends Controller
{
    public function __construct(
        private readonly AgentFunctionRepository $repository,
        private readonly AgentFunctionTransformer $transformer,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AgentFunction::class);

        return ApiResponse::data($this->transformer->paginated($this->repository->paginateFiltered($request)));
    }

    public function show(AgentFunction $function): JsonResponse
    {
        $this->authorize('view', $function);

        $function->load(['component', 'approver', 'parameters']);

        return ApiResponse::data($this->transformer->item($function));
    }

    public function destroy(AgentFunction $function): JsonResponse
    {
        $this->authorize('delete', $function);

        $this->repository->delete($function);

        return ApiResponse::message('Função removida.');
    }

    public function toggleActive(AgentFunction $function): JsonResponse
    {
        $this->authorize('toggleActive', AgentFunction::class);

        // Atribuição explícita: `active` fica fora do #[Fillable] de propósito
        // (só este endpoint, restrito ao gestor, pode ligar uma função), e
        // update([...]) descartaria o campo em silêncio.
        $function->active = ! $function->active;
        $function->save();

        return ApiResponse::data($this->transformer->item($function->fresh(['component', 'approver', 'parameters'])));
    }
}
