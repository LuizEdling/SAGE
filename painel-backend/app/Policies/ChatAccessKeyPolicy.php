<?php

namespace App\Policies;

use App\Models\User;

// Caso de uso "Gerenciar chave de acesso da API" só liga no ator Gerente —
// todos os métodos exigem is_master, sem exceção.
class ChatAccessKeyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_master;
    }

    public function view(User $user): bool
    {
        return $user->is_master;
    }

    public function create(User $user): bool
    {
        return $user->is_master;
    }

    public function update(User $user): bool
    {
        return $user->is_master;
    }

    public function delete(User $user): bool
    {
        return $user->is_master;
    }
}
