<?php

namespace App\Policies;

use App\Enums\ApprovalStatus;
use App\Models\AgentFunction;
use App\Models\User;

class AgentFunctionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AgentFunction $function): bool
    {
        return true;
    }

    /** Pendente qualquer usuário remove; depois de decidida, só o gestor. */
    public function delete(User $user, AgentFunction $function): bool
    {
        return $function->status === ApprovalStatus::Pendente || $user->is_master;
    }

    public function approve(User $user): bool
    {
        return $user->is_master;
    }

    public function reject(User $user): bool
    {
        return $user->is_master;
    }

    public function toggleActive(User $user): bool
    {
        return $user->is_master;
    }
}
