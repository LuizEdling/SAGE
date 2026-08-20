<?php

namespace App\Jobs;

use App\Models\Knowledge;
use App\Services\Knowledge\KnowledgeFormatterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Assíncrono de propósito: a aprovação de conhecimento (ApprovalService) não
// pode ficar esperando o agente Python gerar embedding e escrever no Qdrant
// antes de responder ao usuário no Painel.
class IngestKnowledgeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(private readonly int $knowledgeId) {}

    public function handle(KnowledgeFormatterService $formatter): void
    {
        $knowledge = Knowledge::with(['component', 'functions'])->find($this->knowledgeId);

        if (! $knowledge || ! $knowledge->component) {
            return;
        }

        // O conteúdo só é convertido pra Markdown aqui, na aprovação — nunca no
        // cadastro/edição — porque é o único momento em que ele de fato vira
        // parte da collection do Qdrant. O `knowledge.content` no MySQL
        // permanece intocado, em linguagem normal.
        $response = Http::baseUrl(config('services.sage_agent.url'))
            ->timeout(30)
            ->post('/ingest', [
                'knowledge_id' => $knowledge->id,
                'content' => $this->formatForIndexing($knowledge->content, $formatter),
                'component_id' => $knowledge->component_id,
                'modulo' => $knowledge->component->name,
                'tipo' => $knowledge->type->value,
                'fonte' => 'manual',
                'valid_until' => $knowledge->valid_until?->toIso8601String(),
                'data_aprovacao' => $knowledge->approved_at?->toIso8601String(),
                // Funções indicadas para este conhecimento: o Agente só
                // considera funções ligadas ao material que recuperou.
                'function_ids' => $knowledge->functions->pluck('id')->all(),
            ]);

        if (! $response->successful()) {
            Log::warning('IngestKnowledgeJob: falha ao ingerir no agente RAG.', [
                'knowledge_id' => $knowledge->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return;
        }

        $pointIds = $response->json('point_ids', []);

        $knowledge->update(['qdrant_point_id' => implode(',', $pointIds)]);
    }

    // Se o agente Python estiver fora do ar ou a formatação falhar por
    // qualquer motivo, cai de volta pro texto original em vez de derrubar a
    // ingestão inteira — melhor indexar em linguagem normal do que não
    // indexar nada.
    private function formatForIndexing(string $content, KnowledgeFormatterService $formatter): string
    {
        try {
            return $formatter->formatToMarkdown($content);
        } catch (\Throwable $e) {
            Log::warning('IngestKnowledgeJob: falha ao formatar conteúdo em Markdown, indexando em linguagem normal.', [
                'knowledge_id' => $this->knowledgeId,
                'error' => $e->getMessage(),
            ]);

            return $content;
        }
    }
}
