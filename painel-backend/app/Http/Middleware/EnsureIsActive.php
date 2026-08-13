<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// O login já barra conta desativada, mas o token do Sanctum sobrevive à
// desativação — sem isto, desativar um usuário não encerra o acesso que ele
// já tinha em mãos. Revalida a cada requisição autenticada.
class EnsureIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->active) {
            return ApiResponse::error('ACCOUNT_INACTIVE', 'Esta conta está desativada.', 403);
        }

        return $next($request);
    }
}
