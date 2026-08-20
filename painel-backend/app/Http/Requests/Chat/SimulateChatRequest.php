<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimulateChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:4000'],
            // A simulação não persiste conversa, então o histórico vem do
            // próprio navegador — é o que permite o Agente pedir um
            // esclarecimento e entender a resposta seguinte.
            'historico' => ['sometimes', 'array', 'max:20'],
            'historico.*.autor' => ['required', 'string', 'in:usuario,agente'],
            'historico.*.conteudo' => ['required', 'string', 'max:4000'],
            'respondendo_esclarecimento' => ['sometimes', 'boolean'],
            // Tela do sistema legado em que o chat estaria aberto (o widget real envia as duas).
            'pagina' => ['sometimes', 'nullable', 'string', 'max:500'],
            'pagina_titulo' => ['sometimes', 'nullable', 'string', 'max:200'],
            // "Simular como usuário do sistema legado": o Agente passa a usar
            // funções de consulta com os direitos desse usuário. Só o gestor, e
            // sempre com credenciais: o usuário de teste padrão (guardado no
            // servidor) ou login e senha de outro usuário, conferidos pelo
            // próprio sistema legado.
            'usuario_erp' => ['sometimes', 'array'],
            // O que as perguntas anteriores da simulação já conferiram. A simulação não
            // grava nada, então o navegador devolve essas execuções a cada pergunta.
            'execucoes_anteriores' => ['sometimes', 'array', 'max:20'],
            'execucoes_anteriores.*.function_id' => ['required', 'integer'],
            'execucoes_anteriores.*.tipo' => ['required', 'string', 'in:consulta,alteracao'],
            'execucoes_anteriores.*.status' => ['sometimes', 'string', 'max:30'],
            'execucoes_anteriores.*.parametros' => ['sometimes', 'array'],
            'execucoes_anteriores.*.dados' => ['sometimes', 'array'],
            'execucoes_anteriores.*.antes' => ['sometimes', 'nullable', 'array'],
            'execucoes_anteriores.*.depois' => ['sometimes', 'nullable', 'array'],
            'usuario_erp.padrao' => ['sometimes', 'boolean'],
            'usuario_erp.login' => [Rule::requiredIf(fn () => $this->has('usuario_erp') && ! $this->boolean('usuario_erp.padrao')), 'prohibited_if:usuario_erp.padrao,true', 'string', 'max:255'],
            'usuario_erp.senha' => [Rule::requiredIf(fn () => $this->has('usuario_erp') && ! $this->boolean('usuario_erp.padrao')), 'prohibited_if:usuario_erp.padrao,true', 'string', 'max:255'],
        ];
    }
}
