<?php

namespace App\Providers;

use App\Services\Knowledge\DuplicateCheckerInterface;
use App\Services\Knowledge\IngestionDispatcherInterface;
use App\Services\Knowledge\RagDuplicateChecker;
use App\Services\Knowledge\RagIngestionDispatcher;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Fase 2: RAG real via agente Python + Qdrant. Fase 1 usava
        // NaiveDuplicateChecker/NullIngestionDispatcher — trocar de volta
        // exige só mudar este binding, nenhum Controller/Service muda.
        $this->app->bind(DuplicateCheckerInterface::class, RagDuplicateChecker::class);
        $this->app->bind(IngestionDispatcherInterface::class, RagIngestionDispatcher::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Por chave de chat + usuário do sistema legado: um usuário insistente
        // não esgota o limite dos demais que usam a mesma chave.
        RateLimiter::for('chat', function (Request $request) {
            $chave = $request->attributes->get('chatAccessKey')?->id ?? $request->ip();

            return Limit::perMinute(config('services.sage_chat.rate_limit_per_minute'))->by($chave.'|'.$request->input('usuario_erp.id', $request->input('erp_user_id')));
        });
    }
}
