<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AgentFunction\RecommendFunctionsRequest;
use App\Models\AgentFunction;
use App\Models\Knowledge;
use App\Services\Agent\AgentClient;
use App\Support\ApiResponse;
use App\Transformers\AgentFunctionTransformer;
use Illuminate\Http\JsonResponse;

/**
 * "Recomendar função" (caso de uso «extend» de Cadastrar função, sob
 * solicitação): sugere ao gestor quais funções do módulo do conhecimento
 * ajudam no problema descrito. Nada é vinculado aqui — só sugerido.
 */
class FunctionRecommendationController extends Controller
{
    public function __construct(
        private readonly AgentClient $agent,
        private readonly AgentFunctionTransformer $transformer,
    ) {}

    public function recommend(RecommendFunctionsRequest $request): JsonResponse
    {
        $knowledge = Knowledge::findOrFail($request->integer('knowledge_id'));
        $funcoes = AgentFunction::with(['component', 'parameters'])
            ->where('component_id', $knowledge->component_id)
            ->get()
            ->keyBy('id');

        $resposta = $this->agent->recommendFunctions($knowledge->content, $funcoes->map(fn (AgentFunction $f) => [
            'id' => $f->id,
            'codigo' => $f->code ?? '',
            'nome' => $f->name,
            'descricao' => $f->description,
            'tipo' => $f->operation_type->value,
            'component_id' => $f->component_id,
        ])->values()->all());

        return ApiResponse::data([
            'available' => (bool) ($resposta['disponivel'] ?? false),
            'recommendations' => collect($resposta['recomendadas'] ?? [])
                ->filter(fn (array $item) => $funcoes->has($item['function_id']))
                ->map(fn (array $item) => [
                    'function' => $this->transformer->item($funcoes[$item['function_id']]),
                    'reason' => $item['motivo'] ?? null,
                ])
                ->values()
                ->all(),
        ]);
    }
}
