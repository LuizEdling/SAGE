<?php

namespace App\Services\Agent;

use App\Models\Component;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente HTTP do Agente de diagnóstico (agente-python). Única porta de
 * saída do Painel para o Agente no fluxo de chat — o backend não faz
 * operação vetorial nem chama o modelo de linguagem diretamente (§4.2).
 */
class AgentClient
{
    /**
     * @param  list<array{autor:string, conteudo:string}>  $historico
     * @param  array<string, mixed>  $funcoes  `funcoes`, `contexto_erp` e `continuacao` (uso de funções do sistema legado)
     * @return array{0: array, 1: string} [diagnóstico, texto da resposta]
     */
    public function diagnose(
        string $question,
        array $historico = [],
        bool $respondendoEsclarecimento = false,
        array $funcoes = [],
    ): array {
        try {
            $response = Http::baseUrl(config('services.sage_agent.url'))
                ->timeout(90)
                ->post('/diagnose', [
                    'question' => $question,
                    'modulos' => $this->catalogoModulos(),
                    'historico' => $historico,
                    'respondendo_esclarecimento' => $respondendoEsclarecimento,
                ] + $funcoes);

            if ($response->successful()) {
                $data = $response->json();

                return [$data, $data['resposta_texto']];
            }

            Log::warning('AgentClient: agente retornou erro.', ['status' => $response->status()]);
        } catch (\Throwable $e) {
            Log::warning('AgentClient: agente indisponível.', ['error' => $e->getMessage()]);
        }

        return [
            ['resumo' => null, 'causa_provavel' => null, 'modulo_afetado' => null, 'confianca' => 0, 'fontes' => []],
            'O agente de diagnóstico está indisponível no momento. Tente novamente em instantes.',
        ];
    }

    /**
     * "Recomendar função": o Agente sugere quais das funções informadas
     * ajudam no problema do conhecimento. A decisão de vincular é do gestor.
     *
     * @param  list<array<string, mixed>>  $funcoes  no formato `FuncaoInfo` do Agente
     * @return array{disponivel: bool, recomendadas: list<array{function_id:int, motivo:?string}>}
     */
    public function recommendFunctions(string $conhecimento, array $funcoes): array
    {
        try {
            $response = Http::baseUrl(config('services.sage_agent.url'))
                ->timeout(60)
                ->post('/recommend-functions', ['conhecimento' => $conhecimento, 'funcoes' => $funcoes]);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Throwable $e) {
            Log::warning('AgentClient: agente indisponível para recomendar funções.', ['error' => $e->getMessage()]);
        }

        return ['disponivel' => false, 'recomendadas' => []];
    }

    /**
     * Catálogo de módulos enviado ao Agente a cada pergunta. O MySQL é a
     * fonte de verdade de nome/rota/prints (o Qdrant guarda só o texto do
     * conhecimento), então em vez de duplicar isso na collection o Painel
     * manda junto: o Agente usa pra classificar a pergunta num módulo (que
     * vira filtro de metadado na busca) e pra saber que existe material
     * visual que ele pode referenciar na resposta.
     */
    private function catalogoModulos(): array
    {
        return Component::with('images')->get()->map(fn (Component $component) => [
            'id' => $component->id,
            'nome' => $component->name,
            // Usada pelo classificador do Agente para separar módulos cujo
            // assunto se sobrepõe pelo nome.
            'descricao' => $component->description,
            'rota' => $component->route,
            'imagens' => $component->images->map(fn ($image) => [
                'url' => $image->url,
                'caption' => $image->caption,
            ])->values()->all(),
        ])->values()->all();
    }
}
