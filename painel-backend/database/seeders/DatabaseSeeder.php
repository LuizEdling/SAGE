<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Em produção não há dados de exemplo nem a senha padrão da factory:
        // só o primeiro gestor, com e-mail e senha vindos do ambiente.
        if (app()->isProduction()) {
            $this->call(ProductionAdminSeeder::class);

            return;
        }

        User::factory()->master()->create([
            'name' => 'Gerente SAGE',
            'email' => 'gerente@sage.local',
        ]);

        User::factory()->create([
            'name' => 'Usuário SAGE',
            'email' => 'usuario@sage.local',
        ]);

        $this->call(ErpExecutionKeySeeder::class);
    }
}
