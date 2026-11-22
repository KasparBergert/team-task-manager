<?php

namespace App\Observers;

use App\Models\User;
use App\Services\PermissionsService;

class UserObserver
{
    public function deleted(User $user): void
    {
        app(PermissionsService::class)->deleteScopes(['user_id' => $user->getKey()]);
    }
}
