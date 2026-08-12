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
