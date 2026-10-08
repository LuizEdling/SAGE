<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mensagem enviada pelo widget do sistema legado. Quem chama é o servidor do
 * plugin, autenticado pela chave de chat; a identidade em `usuario_erp` é lida
 * da sessão do usuário no sistema legado, nunca do navegador.
 */
class StoreWidgetMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:4000'],
            'usuario_erp' => ['required', 'array'],
            'usuario_erp.id' => ['required', 'integer', 'min:1'],
            'usuario_erp.login' => ['required', 'string', 'max:255'],
            'usuario_erp.nome' => ['nullable', 'string', 'max:255'],
            'usuario_erp.perfil_id' => ['nullable', 'integer', 'min:0'],
            'usuario_erp.perfil' => ['nullable', 'string', 'max:255'],
            // Interface/tela do sistema legado em que o usuário está (texto livre, ex.: "central", "portal").
            'usuario_erp.interface' => ['nullable', 'string', 'max:50'],
            'usuario_erp.entidade_id' => ['nullable', 'integer', 'min:0'],
            'usuario_erp.entidade' => ['nullable', 'string', 'max:255'],
            'usuario_erp.recursivo' => ['nullable', 'boolean'],
            'pagina' => ['nullable', 'string', 'max:500'],
            'pagina_titulo' => ['nullable', 'string', 'max:200'],
        ];
    }
}
