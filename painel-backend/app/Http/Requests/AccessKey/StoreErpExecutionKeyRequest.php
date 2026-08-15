<?php

namespace App\Http\Requests\AccessKey;

use Illuminate\Foundation\Http\FormRequest;

class StoreErpExecutionKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            // Endereço base da API do plugin no sistema legado (onde ficam /manifest e /functions).
            'api_url' => ['required', 'string', 'max:2048', 'url:http,https'],
            'active' => ['boolean'],
        ];
    }
}
