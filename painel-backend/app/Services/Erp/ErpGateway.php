<?php

namespace App\Services\Erp;

use App\Exceptions\ErpUnavailableException;
use App\Exceptions\NoActiveExecutionKeyException;
use App\Models\ErpExecutionKey;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Única porta de saída do Painel para o sistema legado (o plugin S.A.G.E. que
 * o sistema implementa). O endereço da API vem da própria chave de execução
 * (api_url), cadastrada no Painel; a chave autentica no header (§4.4: "chave
 * própria dessa API"). Sem nenhuma chave ativa, nenhuma função é executada:
 * desativar a chave no Painel suspende toda execução de uma vez.
 */
class ErpGateway
{
    private const TIMEOUT = 15;

    /** @return list<array<string, mixed>> funções declaradas pelo sistema legado */
    public function manifest(): array
    {
        $resposta = $this->send(fn (PendingRequest $http) => $http->get('manifest'));

        if ($resposta['status'] !== 200) {
            throw new ErpUnavailableException('O sistema legado recusou a consulta das funções (HTTP '.$resposta['status'].').');
        }

        return $resposta['body']['funcoes'] ?? [];
    }

    /**
     * Confere se a chave informada alcança o plugin: lê o manifesto (o único
     * endpoint de leitura obrigatório do contrato) com o endereço e a chave dela.
     *
     * @return array{ok: bool, http_status: ?int, message: string, functions: ?int}
     */
    public function testConnection(ErpExecutionKey $chave): array
    {
        try {
            $resposta = $this->send(fn (PendingRequest $http) => $http->get('manifest'), $chave);
        } catch (ErpUnavailableException) {
            return ['ok' => false, 'http_status' => null, 'functions' => null,
                'message' => 'Não foi possível conectar a esse endereço. Confira a URL e se o sistema legado está no ar.'];
        }

        $status = $resposta['status'];
        $funcoes = $resposta['body']['funcoes'] ?? null;

        return match (true) {
            $status === 200 && is_array($funcoes) => ['ok' => true, 'http_status' => 200, 'functions' => count($funcoes),
                'message' => 'Conexão funcionando: o plugin declara '.count($funcoes).' função(ões).'],
            $status === 200 => ['ok' => false, 'http_status' => 200, 'functions' => null,
                'message' => 'O endereço respondeu, mas não com o manifesto esperado. Confira se a URL aponta para a API do plugin.'],
            $status === 401 => ['ok' => false, 'http_status' => 401, 'functions' => null,
                'message' => 'O plugin recusou a chave. Configure o hash desta chave no sistema legado.'],
            $status === 404 => ['ok' => false, 'http_status' => 404, 'functions' => null,
                'message' => 'Endereço não encontrado (HTTP 404). Confira o caminho da API do plugin.'],
            default => ['ok' => false, 'http_status' => $status, 'functions' => null,
                'message' => 'O sistema legado respondeu com erro (HTTP '.$status.').'],
        };
    }

    /** A chave que as chamadas usam: a mais recente ativa (trocar a chave é criar outra e desativar a antiga). */
    public function activeKey(): ?ErpExecutionKey
    {
        return ErpExecutionKey::where('active', true)->latest('id')->first();
    }

    /**
     * @param  array{id:int, login?:string, perfil_id?:?int, entidade_id?:?int, recursivo?:?bool}  $usuario
     * @return array{status:int, body:array<string, mixed>}
     */
    public function execute(string $code, int $executionId, array $usuario, array $parametros, ?string $tokenAutorizacao = null): array
    {
        return $this->send(fn (PendingRequest $http) => $http->post("functions/{$code}/execute", array_filter([
            'execution_id' => $executionId,
            'usuario' => $usuario,
            'parametros' => (object) $parametros,
            'token_autorizacao' => $tokenAutorizacao,
        ], fn ($valor) => $valor !== null)));
    }

    /**
     * @param  callable(PendingRequest): Response  $chamada
     * @param  ErpExecutionKey|null  $chave  chave específica (teste de conexão); padrão: a ativa mais recente
     * @return array{status:int, body:array<string, mixed>}
     */
    private function send(callable $chamada, ?ErpExecutionKey $chave = null): array
    {
        $chave ??= $this->activeKey();

        if ($chave === null) {
            throw new NoActiveExecutionKeyException;
        }

        try {
            $resposta = $chamada(
                Http::baseUrl(rtrim($chave->api_url, '/').'/')
                    ->timeout(self::TIMEOUT)
                    ->acceptJson()
                    ->withHeaders(['X-Sage-Execution-Key' => $chave->access_key])
            );
        } catch (ConnectionException $e) {
            throw new ErpUnavailableException;
        }

        return ['status' => $resposta->status(), 'body' => is_array($resposta->json()) ? $resposta->json() : []];
    }
}
