<?php

namespace App\Services\Functions;

use App\Enums\ApprovalStatus;
use App\Models\AgentFunction;
use Illuminate\Database\Eloquent\Collection;

/**
 * Funções que o Agente pode pedir: aprovadas pelo gestor, ativas e com
 * implementação no sistema legado (`code`). Enviado ao Agente a cada pergunta,
 * como o catálogo de módulos: o MySQL é a fonte de verdade, e desativar uma
 * função vale na hora, sem reindexar nada.
 */
class FunctionCatalog
{
    /** @return Collection<int, AgentFunction> */
    public function usable(): Collection
    {
        return AgentFunction::with('parameters')
            ->where('status', ApprovalStatus::Aprovado)
            ->where('active', true)
            ->whereNotNull('code')
            ->get();
    }

    public function find(int $id): ?AgentFunction
    {
        return $this->usable()->firstWhere('id', $id);
    }

    /** Formato do `FuncaoInfo` do Agente. */
    public function forAgent(): array
    {
        return $this->usable()->map(fn (AgentFunction $function) => [
            'id' => $function->id,
            'codigo' => $function->code,
            'nome' => $function->name,
            'descricao' => $function->description,
            'tipo' => $function->operation_type->value,
            'component_id' => $function->component_id,
            'parametros' => $function->parameters->map(fn ($parameter) => [
                'nome' => $parameter->name,
                'tipo' => $parameter->type->value,
                'obrigatorio' => $parameter->required,
                'descricao' => $parameter->description,
            ])->values()->all(),
        ])->values()->all();
    }
}
