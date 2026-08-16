<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Reforço de segurança em profundidade nas rotas de Gerente — a fonte de
// verdade continua sendo a Policy, isto é só uma segunda barreira.
class EnsureIsMaster
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->is_master) {
            return ApiResponse::error('FORBIDDEN', 'Ação restrita ao Gerente.', 403);
        }

        return $next($request);
    }
}
