<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O endereço da API do plugin do sistema legado deixa de ser configuração do
 * servidor (.env) e passa a ser cadastrado no Painel, junto com a chave de
 * execução: cada chave diz com qual sistema ela fala. Assim o S.A.G.E. serve
 * a qualquer sistema legado que implemente o contrato, sem mexer no servidor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('erp_execution_key', function (Blueprint $table) {
            $table->string('api_url', 2048)->nullable()->after('access_key');
        });

        // Chaves já existentes continuam falando com o endereço que o servidor
        // usava antes (variáveis antigas do .env, ou o padrão de desenvolvimento).
        $url = rtrim((string) env('GLPI_URL', 'http://host.docker.internal:8080'), '/')
            .'/'.trim((string) env('GLPI_API_PATH', '/plugins/sage/api/'), '/');

        DB::table('erp_execution_key')->whereNull('api_url')->update(['api_url' => $url]);

        Schema::table('erp_execution_key', function (Blueprint $table) {
            $table->string('api_url', 2048)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('erp_execution_key', function (Blueprint $table) {
            $table->dropColumn('api_url');
        });
    }
};
