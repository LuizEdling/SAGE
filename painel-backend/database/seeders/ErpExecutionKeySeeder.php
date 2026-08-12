<?php

namespace Database\Seeders;

use App\Models\ErpExecutionKey;
use App\Services\AccessKeyGenerator;
use Illuminate\Database\Seeder;

/**
 * Cadastra por padrão a chave de execução do sistema legado de desenvolvimento
 * (GLPI com o plugin S.A.G.E.), com a URL da API do plugin.
 *
 * A chave vem de SEED_ERP_EXECUTION_KEY, a mesma configurada no plugin, que
 * guarda só o hash dela. Sem essa variável, gera uma chave nova e mostra o
 * valor para ser configurado no plugin. Rodar de novo não duplica a chave.
 */
class ErpExecutionKeySeeder extends Seeder
{
    public function run(AccessKeyGenerator $generator): void
    {
        $chave = config('services.legacy_system.seed_execution_key');
        $url = config('services.legacy_system.seed_api_url');
        $gerada = ! $chave;

        if ($gerada && ErpExecutionKey::where('api_url', $url)->exists()) {
            return;
        }

        $registro = ErpExecutionKey::firstOrCreate(
            ['access_key' => $chave ?: $generator->generate()],
            ['api_url' => $url, 'description' => 'GLPI - ambiente de validação (plugin sage)', 'active' => true],
        );

        if ($gerada) {
            $this->command?->warn('Chave de execução gerada. Configure no plugin S.A.G.E. do GLPI: '.$registro->access_key);
        }
    }
}
