<?php

namespace App\Policies;

use App\Models\User;

/**
 * Supervisão das execuções: todo usuário do Painel acompanha. Não existe
 * "aprovar execução" no Painel — quem autoriza uma alteração é o usuário do
 * sistema legado, no widget.
 */
class ExecutionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user): bool
    {
        return true;
    }
}
