<?php

namespace Tests\Feature\Erp;

use App\Models\ErpExecutionKey;
use Database\Seeders\ErpExecutionKeySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Chave de execução do GLPI de desenvolvimento cadastrada por padrão. */
class ErpExecutionKeySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_cadastra_a_chave_configurada_com_a_url_do_glpi_sem_duplicar(): void
    {
        config([
            'services.legacy_system.seed_execution_key' => str_repeat('a', 64),
            'services.legacy_system.seed_api_url' => 'http://host.docker.internal:8080/plugins/sage/api',
        ]);

        $this->seed(ErpExecutionKeySeeder::class);
        $this->seed(ErpExecutionKeySeeder::class);

        $this->assertSame(1, ErpExecutionKey::count());
        $chave = ErpExecutionKey::first();
        $this->assertSame(str_repeat('a', 64), $chave->access_key);
        $this->assertSame('http://host.docker.internal:8080/plugins/sage/api', $chave->api_url);
        $this->assertTrue($chave->active);
    }

    public function test_sem_chave_configurada_gera_uma_nova_uma_unica_vez(): void
    {
        config(['services.legacy_system.seed_execution_key' => null]);

        $this->seed(ErpExecutionKeySeeder::class);
        $this->seed(ErpExecutionKeySeeder::class);

        $this->assertSame(1, ErpExecutionKey::count());
        $this->assertSame(64, strlen(ErpExecutionKey::first()->access_key));
    }
}
