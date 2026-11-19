<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function addMember(int $userId): void
    {
        $this->members()->syncWithoutDetaching([$userId]);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
