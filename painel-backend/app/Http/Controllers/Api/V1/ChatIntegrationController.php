<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * O que o gestor precisa para ligar o chat de um sistema legado ao Painel:
 * o endereço da API para onde o servidor do sistema legado manda as mensagens
 * (autenticadas pela chave de chat). Exibido em Chaves de acesso.
 */
class ChatIntegrationController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::data([
            'api_url' => rtrim((string) config('services.sage_chat.public_api_url'), '/'),
            'rate_limit_per_minute' => (int) config('services.sage_chat.rate_limit_per_minute'),
            'execution_ttl_minutes' => (int) config('services.legacy_system.execution_ttl'),
        ]);
    }
}
