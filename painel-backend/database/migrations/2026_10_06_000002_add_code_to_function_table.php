<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identificador técnico da função no sistema legado (ex.:
 * `entidade.fechamento_automatico.ler`). Liga a função cadastrada no Painel à
 * implementação no plugin sem depender do nome exibido, que o gestor pode
 * editar. Nullable: funções cadastradas à mão ainda não têm implementação.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('function', function (Blueprint $table) {
            $table->string('code', 100)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('function', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
