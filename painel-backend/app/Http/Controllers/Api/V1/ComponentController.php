<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Component\StoreComponentRequest;
use App\Http\Requests\Component\UpdateComponentRequest;
use App\Models\Component;
use App\Repositories\ComponentRepository;
use App\Support\ApiResponse;
use App\Transformers\ComponentTransformer;
use Illuminate\Http\JsonResponse;

class ComponentController extends Controller
{
    public function __construct(
        private readonly ComponentRepository $repository,
        private readonly ComponentTransformer $transformer,
    ) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Component::class);

        return ApiResponse::data($this->transformer->paginated($this->repository->paginateWithImages()));
    }

    public function store(StoreComponentRequest $request): JsonResponse
    {
        $this->authorize('create', Component::class);

        $component = $this->repository->create($request->validated());
        $component->load('images');

        return ApiResponse::data($this->transformer->item($component), status: 201);
    }

    public function show(Component $component): JsonResponse
    {
        $this->authorize('view', $component);

        $component->load('images');

        return ApiResponse::data($this->transformer->item($component));
    }

    public function update(UpdateComponentRequest $request, Component $component): JsonResponse
    {
        $this->authorize('update', $component);

        $this->repository->update($component, $request->validated());
        $component->load('images');

        return ApiResponse::data($this->transformer->item($component));
    }

    public function destroy(Component $component): JsonResponse
    {
        $this->authorize('delete', $component);

        $this->repository->delete($component);

        return ApiResponse::message('Componente removido.');
    }
}
