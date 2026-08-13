<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Models\User;
use App\Support\ApiResponse;
use App\Transformers\UserTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(private readonly UserTransformer $transformer) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            return ApiResponse::error('INVALID_CREDENTIALS', 'E-mail ou senha inválidos.', 401);
        }

        if (! $user->active) {
            return ApiResponse::error('ACCOUNT_INACTIVE', 'Esta conta está desativada.', 403);
        }

        $token = $user->createToken('painel')->plainTextToken;

        return ApiResponse::data([
            'token' => $token,
            'user' => $this->transformer->item($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::message('Sessão encerrada.');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::data($this->transformer->item($request->user()));
    }

    public function updateMe(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($request->filled('password') && ! Hash::check($request->string('current_password'), $user->password)) {
            return ApiResponse::error('INVALID_CURRENT_PASSWORD', 'Senha atual incorreta.', 422);
        }

        $user->name = $request->string('name');
        $user->email = $request->string('email');

        if ($request->filled('password')) {
            $user->password = $request->string('password');
        }

        $user->save();

        return ApiResponse::data($this->transformer->item($user));
    }
}
