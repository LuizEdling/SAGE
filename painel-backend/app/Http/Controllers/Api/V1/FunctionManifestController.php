<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Functions\ManifestImportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * "Importar do sistema legado": cadastra as funções que o plugin do sistema
 * legado declara. Todas entram pendentes — a aprovação continua com o gestor.
 */
class FunctionManifestController extends Controller
{
    public function __construct(private readonly ManifestImportService $import) {}

    public function import(): JsonResponse
    {
        $itens = $this->import->import();

        return ApiResponse::data([
            'items' => $itens,
            'created' => count(array_filter($itens, fn ($item) => $item['status'] === 'criada')),
            'updated' => count(array_filter($itens, fn ($item) => $item['status'] === 'atualizada')),
            'failed' => count(array_filter($itens, fn ($item) => $item['status'] === 'erro')),
        ]);
    }
}
