<?php

namespace App\Http\Middleware;

use App\Models\ChatAccessKey;
use App\Services\AccessKeyGenerator;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica o sistema legado pela chave de chat (credencial do widget,
 * DBML). Quem chama é o servidor do plugin no GLPI — a chave nunca vai ao
 * navegador — então esta rota não usa token de usuário do Painel.
 */
class EnsureChatAccessKey
{
    public function __construct(private readonly AccessKeyGenerator $keys) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->bearerToken();

        $chatAccessKey = $key
            ? ChatAccessKey::where('key_hash', $this->keys->hash($key))->where('active', true)->first()
            : null;

        if ($chatAccessKey === null) {
            throw new AuthenticationException('Chave de chat ausente, inválida ou desativada.');
        }

        $request->attributes->set('chatAccessKey', $chatAccessKey);

        return $next($request);
    }
}
