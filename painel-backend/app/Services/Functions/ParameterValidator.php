<?php

namespace App\Services\Functions;

use App\Enums\ParameterType;
use App\Exceptions\InvalidFunctionParametersException;
use App\Models\AgentFunction;

/**
 * Confere os parâmetros pedidos pelo Agente contra os parâmetros cadastrados
 * da função (`function_parameter`). É a segunda das três validações (Agente,
 * Painel, sistema legado): o que o modelo de linguagem sugeriu nunca chega ao
 * sistema legado sem passar por aqui.
 */
class ParameterValidator
{
    /**
     * @param  array<string, mixed>  $pedidos
     * @return array<string, mixed> parâmetros convertidos, só os declarados
     *
     * @throws InvalidFunctionParametersException
     */
    public function validate(AgentFunction $function, array $pedidos): array
    {
        $validos = [];

        foreach ($function->parameters as $parameter) {
            $valor = $pedidos[$parameter->name] ?? null;

            if ($valor === null || $valor === '') {
                if ($parameter->required) {
                    throw new InvalidFunctionParametersException("Parâmetro obrigatório ausente: {$parameter->name}.");
                }

                continue;
            }

            $convertido = $this->convert($valor, $parameter->type);

            if ($convertido === null) {
                throw new InvalidFunctionParametersException("Valor inválido para {$parameter->name}.");
            }

            $validos[$parameter->name] = $convertido;
        }

        return $validos;
    }

    private function convert(mixed $valor, ParameterType $tipo): mixed
    {
        return match ($tipo) {
            ParameterType::Integer => is_bool($valor) ? null : filter_var($valor, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
            ParameterType::Float => is_bool($valor) ? null : filter_var($valor, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE),
            ParameterType::Boolean => is_bool($valor) ? $valor : filter_var($valor, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ParameterType::Date => is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) ? $valor : null,
            ParameterType::String => is_scalar($valor) && mb_strlen((string) $valor) > 0 && mb_strlen((string) $valor) <= 255
                ? (string) $valor
                : null,
        };
    }
}
