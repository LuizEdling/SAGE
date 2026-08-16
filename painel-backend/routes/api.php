<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChatAccessKeyController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\ChatIntegrationController;
use App\Http\Controllers\Api\V1\ComponentController;
use App\Http\Controllers\Api\V1\ComponentImageController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ErpActiveExecutionKeyController;
use App\Http\Controllers\Api\V1\ErpExecutionKeyController;
use App\Http\Controllers\Api\V1\ExecutionController;
use App\Http\Controllers\Api\V1\ExecutionDecisionController;
use App\Http\Controllers\Api\V1\FunctionApprovalController;
use App\Http\Controllers\Api\V1\FunctionController;
use App\Http\Controllers\Api\V1\FunctionManifestController;
use App\Http\Controllers\Api\V1\FunctionRecommendationController;
use App\Http\Controllers\Api\V1\KnowledgeApprovalController;
use App\Http\Controllers\Api\V1\KnowledgeController;
use App\Http\Controllers\Api\V1\KnowledgeFunctionController;
use App\Http\Controllers\Api\V1\KnowledgeImageController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WidgetChatController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Sem isto, força bruta no login é ilimitada — e um token acertado não expira.
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    // Chat embutido no sistema legado: autenticado pela chave de chat (servidor
    // do plugin → Painel), não por token de usuário do Painel.
    Route::middleware(['chat.key', 'throttle:chat'])->group(function () {
        Route::post('/chat/messages', [WidgetChatController::class, 'store']);
        Route::get('/chat/conversations/current', [WidgetChatController::class, 'current']);
        Route::post('/chat/conversations/{conversation}/close', [WidgetChatController::class, 'close']);

        // "Autorizar execução de função": a decisão do usuário sobre uma
        // alteração proposta pelo Agente, tomada no widget.
        Route::post('/executions/{execution}/authorize', [ExecutionDecisionController::class, 'autorizar']);
        Route::post('/executions/{execution}/deny', [ExecutionDecisionController::class, 'negar']);
    });

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::put('/auth/me', [AuthController::class, 'updateMe']);

        Route::apiResource('components', ComponentController::class);
        Route::post('/components/{component}/images', [ComponentImageController::class, 'store']);
        Route::delete('/components/{component}/images/{image}', [ComponentImageController::class, 'destroy'])
            ->scopeBindings();

        Route::apiResource('knowledges', KnowledgeController::class);
        Route::post('/knowledges/check-duplicate', [KnowledgeController::class, 'checkDuplicate']);
        Route::post('/knowledges/import', [KnowledgeController::class, 'import']);
        Route::post('/knowledges/{knowledge}/approve', [KnowledgeApprovalController::class, 'approve']);
        Route::post('/knowledges/{knowledge}/reject', [KnowledgeApprovalController::class, 'reject']);
        Route::post('/knowledges/{knowledge}/images', [KnowledgeImageController::class, 'store']);
        Route::delete('/knowledges/{knowledge}/images/{image}', [KnowledgeImageController::class, 'destroy'])
            ->scopeBindings();

        // Funções nascem no sistema legado e entram pela importação do manifesto:
        // o Painel só lista, mostra, aprova, ativa e remove.
        Route::apiResource('functions', FunctionController::class)->only(['index', 'show', 'destroy']);
        Route::post('/functions/{function}/approve', [FunctionApprovalController::class, 'approve']);
        Route::post('/functions/{function}/reject', [FunctionApprovalController::class, 'reject']);
        Route::patch('/functions/{function}/active', [FunctionController::class, 'toggleActive']);

        Route::middleware('master')->group(function () {
            Route::apiResource('users', UserController::class)->except(['show', 'destroy']);
            Route::patch('/users/{user}/active', [UserController::class, 'toggleActive']);

            Route::apiResource('chat-access-keys', ChatAccessKeyController::class)
                ->except(['show'])
                ->parameters(['chat-access-keys' => 'key']);
            Route::get('/chat-integration', ChatIntegrationController::class);
            Route::apiResource('erp-execution-keys', ErpExecutionKeyController::class)
                ->except(['show'])
                ->parameters(['erp-execution-keys' => 'key']);
            Route::post('/erp-execution-keys/{key}/test-connection', [ErpExecutionKeyController::class, 'testConnection']);

            // Funções do sistema legado: importar o que o plugin declara e
            // vincular funções a conhecimentos são decisões do gestor.
            Route::post('/functions/import-manifest', [FunctionManifestController::class, 'import']);
            Route::post('/functions/recommend', [FunctionRecommendationController::class, 'recommend']);
            Route::get('/erp/execution-key', ErpActiveExecutionKeyController::class);
            Route::put('/knowledges/{knowledge}/functions', [KnowledgeFunctionController::class, 'update']);
        });

        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

        Route::get('/conversations', [ConversationController::class, 'index']);
        Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);

        Route::get('/executions', [ExecutionController::class, 'index']);
        Route::get('/executions/{execution}', [ExecutionController::class, 'show']);

        Route::post('/chat/simulate', [ChatController::class, 'simulate']);

    });
});
