<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identifica o usuário do sistema legado dono de cada conversa. Sem isso a
 * conversa era aberta só pela chave de chat, e todos os usuários do GLPI que
 * usam a mesma chave cairiam na mesma conversa. Nullable porque conversas
 * anteriores à integração não têm usuário.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation', function (Blueprint $table) {
            $table->unsignedInteger('erp_user_id')->nullable()->after('chat_access_key_id');
            $table->string('erp_user_login')->nullable()->after('erp_user_id');
            $table->string('erp_user_name')->nullable()->after('erp_user_login');
            // Base do encerramento por inatividade.
            $table->dateTime('last_message_at')->nullable()->after('closed_at');

            $table->index(['chat_access_key_id', 'erp_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('conversation', function (Blueprint $table) {
            $table->dropIndex(['chat_access_key_id', 'erp_user_id', 'status']);
            $table->dropColumn(['erp_user_id', 'erp_user_login', 'erp_user_name', 'last_message_at']);
        });
    }
};
