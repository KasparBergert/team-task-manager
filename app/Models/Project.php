<?php

namespace App\Models;

use App\Services\PermissionsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Project extends Model
{
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * @param  list<string>  $scopeNames
     */
    public function addMember(int $userId, array $scopeNames = ['read']): void
    {
        $this->getConnection()->transaction(function () use ($userId, $scopeNames): void {
            try {
                $this->members()->attach($userId);
            } catch (UniqueConstraintViolationException $exception) {
                throw new ConflictHttpException('User is already a member of this project.', $exception);
            }

            app(PermissionsService::class)->addScopes(
                ['user_id' => $userId, 'project_id' => $this->getKey()],
                $scopeNames,
            );
        }, attempts: 3);
    }

    public function removeMember(int $userId): void
    {
        $this->getConnection()->transaction(function () use ($userId): void {
            if ($this->members()->detach($userId) === 0) {
                $message = User::query()->whereKey($userId)->exists()
                    ? 'User does not exist in this project.'
                    : 'User does not exist.';

                throw new NotFoundHttpException($message);
            }

            app(PermissionsService::class)->deleteScopes(['user_id' => $userId, 'project_id' => $this->getKey()]);
        }, attempts: 3);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
