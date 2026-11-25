<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Cria o primeiro gestor do Painel em produção, com SAGE_ADMIN_EMAIL e
 * SAGE_ADMIN_PASSWORD. Os demais usuários são cadastrados pela tela Usuários.
 * Rodar de novo não duplica nem troca a senha de um gestor já existente.
 */
class ProductionAdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = config('services.sage_admin');

        if (! $admin['email'] || ! $admin['password']) {
            throw new RuntimeException('Defina SAGE_ADMIN_EMAIL e SAGE_ADMIN_PASSWORD no .env para criar o primeiro gestor.');
        }

        if (strlen($admin['password']) < 12) {
            throw new RuntimeException('SAGE_ADMIN_PASSWORD precisa ter pelo menos 12 caracteres.');
        }

        $user = User::firstOrCreate(
            ['email' => $admin['email']],
            ['name' => $admin['name'], 'password' => $admin['password'], 'is_master' => true, 'active' => true],
        );

        $this->command?->info($user->wasRecentlyCreated
            ? "Gestor criado: {$user->email}"
            : "Gestor já existia: {$user->email} (senha mantida)");
    }
}
