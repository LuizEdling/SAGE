<?php

namespace App\Policies;

use App\Enums\ApprovalStatus;
use App\Models\Knowledge;
use App\Models\User;

class KnowledgePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Knowledge $knowledge): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    // DBML não tem "created_by" em knowledge, então não dá pra restringir a
    // edição ao autor. Regra da Fase 1: qualquer autenticado edita item
    // pendente; item já decidido (aprovado/reprovado) só o Gerente edita.
    public function update(User $user, Knowledge $knowledge): bool
    {
        if ($knowledge->status === ApprovalStatus::Pendente) {
            return true;
        }

        return $user->is_master;
    }

    public function delete(User $user, Knowledge $knowledge): bool
    {
        return $this->update($user, $knowledge);
    }

    public function approve(User $user): bool
    {
        return $user->is_master;
    }

    public function reject(User $user): bool
    {
        return $user->is_master;
    }
}
