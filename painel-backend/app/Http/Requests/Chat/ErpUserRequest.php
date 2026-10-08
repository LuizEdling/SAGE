<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

/** Identifica o usuário do sistema legado nas consultas e no encerramento da conversa. */
class ErpUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'erp_user_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
