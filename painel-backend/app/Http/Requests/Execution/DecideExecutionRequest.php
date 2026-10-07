<?php

namespace App\Http\Requests\Execution;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Decisão do usuário sobre uma alteração proposta, enviada pelo servidor do
 * plugin no sistema legado (identidade lida da sessão do usuário lá). A
 * autorização exige o token emitido pelo sistema legado no clique.
 */
class DecideExecutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'usuario_erp' => ['required', 'array'],
            'usuario_erp.id' => ['required', 'integer', 'min:1'],
            'usuario_erp.login' => ['required', 'string', 'max:255'],
            'usuario_erp.perfil_id' => ['nullable', 'integer', 'min:0'],
            'usuario_erp.entidade_id' => ['nullable', 'integer', 'min:0'],
            'usuario_erp.recursivo' => ['nullable', 'boolean'],
            'token_autorizacao' => [
                $this->route()?->getActionMethod() === 'autorizar' ? 'required' : 'nullable', 'string', 'max:500',
            ],
        ];
    }
}
