<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Erp\ErpGateway;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * O que a tela de simulação precisa saber: a chave de execução que o Painel usa
 * (a ativa mais recente) e o login do usuário de teste padrão — nunca a senha.
 */
class ErpActiveExecutionKeyController extends Controller
{
    public function __construct(private readonly ErpGateway $erp) {}

    public function __invoke(): JsonResponse
    {
        $chave = $this->erp->activeKey();

        return ApiResponse::data([
            'execution_key' => $chave ? ['id' => $chave->id, 'description' => $chave->description] : null,
            // Só vale se login e senha estiverem configurados.
            'simulation_login' => config('services.legacy_system.simulation_password') ? config('services.legacy_system.simulation_login') : null,
        ]);
    }
}
