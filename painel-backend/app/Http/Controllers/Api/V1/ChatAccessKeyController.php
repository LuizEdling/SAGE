<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccessKey\StoreChatAccessKeyRequest;
use App\Models\ChatAccessKey;
use App\Repositories\ChatAccessKeyRepository;
use App\Services\AccessKeyGenerator;
use App\Support\ApiResponse;
use App\Transformers\ChatAccessKeyTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatAccessKeyController extends Controller
{
    public function __construct(
        private readonly ChatAccessKeyRepository $repository,
        private readonly ChatAccessKeyTransformer $transformer,
        private readonly AccessKeyGenerator $keyGenerator,
    ) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', ChatAccessKey::class);

        return ApiResponse::data($this->transformer->paginated($this->repository->paginate()));
    }

    public function store(StoreChatAccessKeyRequest $request): JsonResponse
    {
        $this->authorize('create', ChatAccessKey::class);

        $plainKey = $this->keyGenerator->generate();

        $key = $this->repository->create([
            ...$request->validated(),
            'key_hash' => $this->keyGenerator->hash($plainKey),
            'created_by_user_id' => $request->user()->id,
        ]);

        // Valor completo só existe nesta resposta: o banco guarda apenas o hash.
        return ApiResponse::data($this->transformer->itemWithPlainKey($key, $plainKey), status: 201);
    }

    public function update(Request $request, ChatAccessKey $key): JsonResponse
    {
        $this->authorize('update', $key);

        $data = $request->validate(['active' => ['required', 'boolean']]);
        $this->repository->update($key, $data);

        return ApiResponse::data($this->transformer->item($key));
    }

    public function destroy(ChatAccessKey $key): JsonResponse
    {
        $this->authorize('delete', $key);

        $this->repository->delete($key);

        return ApiResponse::message('Chave removida.');
    }
}
