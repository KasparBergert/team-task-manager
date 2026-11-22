<?php

namespace App\Models;

use App\Services\PermissionsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * @param  list<string>  $scopeNames
     */
    public function addMember(int $userId, array $scopeNames = ['read']): bool
    {
        return $this->getConnection()->transaction(function () use ($userId, $scopeNames): bool {
            $changes = $this->members()->syncWithoutDetaching([$userId]);
            if ($changes['attached'] === []) {
                return false;
            }

            app(PermissionsService::class)->addScopes(
                ['user_id' => $userId, 'project_id' => $this->getKey()],
                $scopeNames,
            );

            return true;
        });
    }

    public function removeMember(int $userId): void
    {
        $this->getConnection()->transaction(function () use ($userId): void {
            $this->members()->detach($userId);
            app(PermissionsService::class)->deleteScopes(['user_id' => $userId, 'project_id' => $this->getKey()]);
        });
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
