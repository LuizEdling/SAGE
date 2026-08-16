<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Formato de resposta padrão da API (skill api-contract-standards):
 * sucesso -> {data, message?}; erro -> {error:{code,message}}.
 */
class ApiResponse
{
    public static function data(mixed $data, ?string $message = null, int $status = 200): JsonResponse
    {
        $payload = ['data' => $data];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        return response()->json($payload, $status);
    }

    public static function message(string $message, int $status = 200): JsonResponse
    {
        return response()->json(['data' => null, 'message' => $message], $status);
    }

    public static function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'error' => ['code' => $code, 'message' => $message],
        ], $status);
    }
}
