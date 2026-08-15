<?php

namespace App\Policies;

use App\Models\User;

class ErpExecutionKeyPolicy
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
