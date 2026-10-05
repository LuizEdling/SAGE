<?php

namespace App\Services\Knowledge;

use App\Exceptions\KnowledgeFormattingFailedException;
use Illuminate\Support\Facades\Http;

class KnowledgeFormatterService
{
    public function formatToMarkdown(string $content): string
    {
        try {
            $response = Http::baseUrl(config('services.sage_agent.url'))
                ->timeout(30)
                ->post('/format-knowledge', ['content' => $content]);
        } catch (\Throwable) {
            throw new KnowledgeFormattingFailedException;
        }

        if (! $response->successful()) {
            throw new KnowledgeFormattingFailedException;
        }

        return $response->json('content_md');
    }
}
