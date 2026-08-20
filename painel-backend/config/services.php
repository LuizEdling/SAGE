<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'sage_agent' => [
        'url' => env('SAGE_AGENT_URL', 'http://sage-agent:8000'),
    ],

    // Sistema legado. O endereço da API do plugin não fica aqui: é cadastrado
    // no Painel, em cada chave de execução (erp_execution_key.api_url), para o
    // S.A.G.E. servir a qualquer sistema que implemente o contrato de funções.
    'legacy_system' => [
        // Prazo para o usuário autorizar uma alteração proposta pelo Agente.
        'execution_ttl' => (int) env('SAGE_EXECUTION_TTL_MINUTES', 10),
        // Usuário de teste do sistema legado para "Simular atendimento" (padrão).
        'simulation_login' => env('SIMULATION_ERP_LOGIN'),
        'simulation_password' => env('SIMULATION_ERP_PASSWORD'),
        // Chave de execução cadastrada por padrão pelo seeder (ambiente de desenvolvimento com o GLPI).
        'seed_execution_key' => env('SEED_ERP_EXECUTION_KEY'),
        'seed_api_url' => env('SEED_ERP_API_URL', 'http://host.docker.internal:8080/plugins/sage/api'),
    ],

    'sage_chat' => [
        // Endereço da API do Painel como o SERVIDOR do sistema legado o enxerga:
        // é para onde ele manda as mensagens do chat (mostrado em Chaves de acesso).
        // Mensagens por minuto, por chave de chat e usuário do sistema legado.
        'rate_limit_per_minute' => (int) env('SAGE_CHAT_RATE_LIMIT', 20),
        'public_api_url' => env('SAGE_PUBLIC_API_URL', rtrim((string) env('APP_URL', 'http://localhost:8000'), '/').'/api/v1'),
        // Conversa sem mensagem há mais que isso é encerrada; a próxima
        // pergunta do usuário abre uma conversa nova.
        'idle_minutes' => (int) env('SAGE_CHAT_IDLE_MINUTES', 120),
    ],

];
