<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Support\ApiResponse;
use App\Transformers\UserTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly UserRepository $repository,
        private readonly UserTransformer $transformer,
    ) {}

    public function index(): JsonResponse
    {
        return ApiResponse::data($this->transformer->paginated($this->repository->paginate(50)));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->repository->create($request->validated());

        return ApiResponse::data($this->transformer->item($user), status: 201);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id && $request->has('is_master') && ! $request->boolean('is_master')) {
            return ApiResponse::error('CANNOT_SELF_DEMOTE', 'Você não pode remover seu próprio acesso de Gerente.', 422);
        }

        $this->repository->update($user, $request->validated());

        return ApiResponse::data($this->transformer->item($user));
    }

    public function toggleActive(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return ApiResponse::error('CANNOT_SELF_DEACTIVATE', 'Você não pode desativar sua própria conta.', 422);
        }

        $this->repository->update($user, ['active' => ! $user->active]);

        // Desativar precisa encerrar o acesso que o usuário já tem em mãos: o
        // token do Sanctum não expira sozinho e sobreviveria à desativação.
        if (! $user->active) {
            $user->tokens()->delete();
        }

        return ApiResponse::data($this->transformer->item($user));
    }
}
