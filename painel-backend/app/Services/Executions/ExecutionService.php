<?php

namespace App\Services\Executions;

use App\Enums\ExecutionStatus;
use App\Exceptions\ErpUnavailableException;
use App\Exceptions\ExecutionExpiredException;
use App\Exceptions\ExecutionNotPendingException;
use App\Exceptions\NoActiveExecutionKeyException;
use App\Models\AgentFunction;
use App\Models\Execution;
use App\Models\Message;
use App\Services\Erp\ErpGateway;
use Illuminate\Support\Facades\DB;

/**
 * Execução das funções do sistema legado pedidas pelo Agente — o Painel é o
 * único executor ("Executar função", caso de uso do Sistema/Agente).
 *
 * Consulta roda direto, autorizada por política (decisão 1 do plano da Fase
 * 4: conferir uma configuração não exige supervisão). Alteração nunca roda no
 * fluxo do diagnóstico: vira proposta pendente, e só a autorização do usuário
 * no widget a executa.
 */
class ExecutionService
{
    public function __construct(private readonly ErpGateway $erp) {}

    /**
     * Executa uma consulta e devolve o resultado no formato da continuação do
     * Agente. Sem mensagem de origem (simulação), executa sem registrar.
     *
     * @param  array<string, mixed>  $parametros  já validados
     * @param  array<string, mixed>  $usuario  usuário do sistema legado (id/login, perfil, entidade)
     */
    public function runRead(AgentFunction $function, array $parametros, array $usuario, ?Message $origem): array
    {
        $execution = $origem
            ? $this->criar($function, $parametros, $origem, ExecutionStatus::Autorizada, ['authorized_at' => now()])
            : null;

        $resultado = $this->chamarSistemaLegado($function, $execution?->id ?? 0, $usuario, $parametros);

        $execution?->update([
            'status' => $resultado['status'] === 'executada' ? ExecutionStatus::Executada : ExecutionStatus::Falhou,
            'result' => $resultado['resultado'] + ['parametros' => $parametros],
            'error_code' => $resultado['erro_codigo'],
            'executed_at' => now(),
        ]);

        return [
            'execution_id' => $execution?->id,
            'function_id' => $function->id,
            'codigo' => $function->code,
            'tipo' => $function->operation_type->value,
            'parametros' => $parametros,
        ] + $resultado;
    }

    /**
     * Registra uma alteração proposta pelo Agente, à espera do usuário. Uma
     * proposta nova substitui a pendente anterior da mesma conversa.
     *
     * @param  array<string, mixed>  $parametros  já validados
     */
    public function propose(AgentFunction $function, array $parametros, Message $mensagemDoAgente): Execution
    {
        return DB::transaction(function () use ($function, $parametros, $mensagemDoAgente) {
            Execution::where('status', ExecutionStatus::Pendente)
                ->whereHas('message', fn ($q) => $q->where('conversation_id', $mensagemDoAgente->conversation_id))
                ->get()
                ->each(fn (Execution $anterior) => $anterior->update([
                    'status' => ExecutionStatus::Negada,
                    'result' => ['motivo' => 'substituida'],
                ]));

            // Os parâmetros validados ficam guardados: a execução autorizada usa
            // exatamente o que foi mostrado ao usuário no cartão.
            return $this->criar($function, $parametros, $mensagemDoAgente, ExecutionStatus::Pendente, [
                'result' => ['parametros' => $parametros],
            ]);
        });
    }

    /**
     * A proposta ainda pode ser decidida? Pendente e dentro do prazo — a que
     * passou do prazo é encerrada como negada (motivo: expirada).
     *
     * @throws ExecutionNotPendingException
     * @throws ExecutionExpiredException
     */
    public function assertDecidable(Execution $execution): void
    {
        if ($execution->status !== ExecutionStatus::Pendente) {
            throw new ExecutionNotPendingException;
        }

        if ($execution->created_at->addMinutes(config('services.legacy_system.execution_ttl'))->isPast()) {
            $execution->update([
                'status' => ExecutionStatus::Negada,
                'result' => ($execution->result ?? []) + ['motivo' => 'expirada'],
            ]);

            throw new ExecutionExpiredException;
        }
    }

    /**
     * Executa a alteração autorizada pelo usuário (caso de uso "Autorizar
     * execução de função"). O token vem do sistema legado, emitido no clique.
     *
     * @param  array<string, mixed>  $usuario  usuário do sistema legado (id, perfil, entidade)
     */
    public function authorize(Execution $execution, Message $confirmacao, array $usuario, string $token): array
    {
        $this->assertDecidable($execution);

        $parametros = $execution->result['parametros'] ?? [];

        $execution->update([
            'status' => ExecutionStatus::Autorizada,
            'confirmation_message_id' => $confirmacao->id,
            'authorized_at' => now(),
        ]);

        $resultado = $this->chamarSistemaLegado($execution->function, $execution->id, $usuario, $parametros, $token);

        $execution->update([
            'status' => $resultado['status'] === 'executada' ? ExecutionStatus::Executada : ExecutionStatus::Falhou,
            'result' => $resultado['resultado'] + ['parametros' => $parametros],
            'error_code' => $resultado['erro_codigo'],
            'executed_at' => now(),
        ]);

        return $this->paraAgente($execution, $parametros, $resultado);
    }

    /** O usuário negou a alteração: nada é enviado ao sistema legado. */
    public function deny(Execution $execution, Message $confirmacao): array
    {
        $this->assertDecidable($execution);

        $parametros = $execution->result['parametros'] ?? [];

        $execution->update([
            'status' => ExecutionStatus::Negada,
            'confirmation_message_id' => $confirmacao->id,
            'result' => ['parametros' => $parametros, 'motivo' => 'negada_pelo_usuario'],
        ]);

        return $this->paraAgente($execution, $parametros, ['status' => 'negada', 'resultado' => [], 'erro_codigo' => null]);
    }

    /** Execução no formato da continuação do Agente (`ExecucaoRealizada`). */
    private function paraAgente(Execution $execution, array $parametros, array $resultado): array
    {
        return [
            'execution_id' => $execution->id,
            'function_id' => $execution->function_id,
            'codigo' => $execution->function->code,
            'tipo' => $execution->function->operation_type->value,
            'parametros' => $parametros,
        ] + $resultado;
    }

    /**
     * @param  array<string, mixed>  $parametros
     * @param  array<string, mixed>  $extra
     */
    private function criar(AgentFunction $function, array $parametros, Message $origem, ExecutionStatus $status, array $extra = []): Execution
    {
        $execution = Execution::create([
            'message_id' => $origem->id,
            'function_id' => $function->id,
            'status' => $status,
        ] + $extra);

        $declarados = $function->parameters->keyBy('name');

        foreach ($parametros as $nome => $valor) {
            $execution->parameters()->create([
                'function_parameter_id' => $declarados[$nome]->id,
                'value' => is_string($valor) ? $valor : json_encode($valor),
            ]);
        }

        return $execution;
    }

    /**
     * @return array{status: 'executada'|'falhou', resultado: array, erro_codigo: ?string}
     */
    public function chamarSistemaLegado(
        AgentFunction $function,
        int $executionId,
        array $usuario,
        array $parametros,
        ?string $tokenAutorizacao = null,
    ): array {
        try {
            $resposta = $this->erp->execute($function->code, $executionId, $usuario, $parametros, $tokenAutorizacao);
        } catch (ErpUnavailableException) {
            return ['status' => 'falhou', 'resultado' => [], 'erro_codigo' => 'ERP_UNAVAILABLE'];
        } catch (NoActiveExecutionKeyException) {
            return ['status' => 'falhou', 'resultado' => [], 'erro_codigo' => 'NO_ACTIVE_EXECUTION_KEY'];
        }

        if ($resposta['status'] === 200) {
            return ['status' => 'executada', 'resultado' => $resposta['body'], 'erro_codigo' => null];
        }

        return [
            'status' => 'falhou',
            'resultado' => $resposta['body'],
            'erro_codigo' => strtoupper((string) ($resposta['body']['status'] ?? 'erro')),
        ];
    }
}
