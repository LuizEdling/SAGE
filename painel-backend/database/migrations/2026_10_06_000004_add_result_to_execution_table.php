<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O que a função devolveu no sistema legado (dados lidos, ou antes/depois de
 * uma alteração) e o código do erro quando falhou — base da auditoria das
 * execuções no painel de supervisão.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('execution', function (Blueprint $table) {
            $table->json('result')->nullable()->after('status');
            $table->string('error_code', 50)->nullable()->after('result');

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('execution', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['result', 'error_code']);
        });
    }
};
