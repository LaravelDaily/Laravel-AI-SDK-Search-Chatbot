<?php

namespace App\Policies;

use App\Models\AiSearchLog;
use App\Models\User;

class AiSearchLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, AiSearchLog $aiSearchLog): bool
    {
        return $user->isAdmin() && $user->company_id === $aiSearchLog->company_id;
    }
}
