<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccessKey\StoreErpExecutionKeyRequest;
use App\Models\ErpExecutionKey;
use App\Repositories\ErpExecutionKeyRepository;
use App\Services\AccessKeyGenerator;
use App\Services\Erp\ErpGateway;
use App\Support\ApiResponse;
use App\Transformers\ErpExecutionKeyTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ErpExecutionKeyController extends Controller
{
    public function __construct(
        private readonly ErpExecutionKeyRepository $repository,
        private readonly ErpExecutionKeyTransformer $transformer,
        private readonly AccessKeyGenerator $keyGenerator,
        private readonly ErpGateway $erp,
    ) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', ErpExecutionKey::class);

        return ApiResponse::data($this->transformer->paginated($this->repository->paginate()));
    }

    public function store(StoreErpExecutionKeyRequest $request): JsonResponse
    {
        $this->authorize('create', ErpExecutionKey::class);

        $key = $this->repository->create([
            ...$request->validated(),
            'access_key' => $this->keyGenerator->generate(),
        ]);

        // A chave só é mostrada completa na criação; depois a listagem a mascara.
        return ApiResponse::data($this->transformer->itemWithFullKey($key), status: 201);
    }

    public function update(Request $request, ErpExecutionKey $key): JsonResponse
    {
        $this->authorize('update', $key);

        $data = $request->validate([
            'active' => ['required_without:api_url', 'boolean'],
            'api_url' => ['required_without:active', 'string', 'max:2048', 'url:http,https'],
        ]);
        $this->repository->update($key, $data);

        return ApiResponse::data($this->transformer->item($key));
    }

    /** Confere se a chave alcança o plugin no endereço cadastrado, sem executar nada. */
    public function testConnection(ErpExecutionKey $key): JsonResponse
    {
        $this->authorize('update', $key);

        return ApiResponse::data($this->erp->testConnection($key));
    }

    public function destroy(ErpExecutionKey $key): JsonResponse
    {
        $this->authorize('delete', $key);

        $this->repository->delete($key);

        return ApiResponse::message('Chave removida.');
    }
}
