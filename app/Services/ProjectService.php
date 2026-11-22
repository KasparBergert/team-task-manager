<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ProjectService
{
    private function projectsFor(int $actorId): Builder
    {
        return Project::query()->whereHas('members', fn (Builder $members) => $members->whereKey($actorId));
    }

    public function showProjects(int $actorId): Collection
    {
        return $this->projectsFor($actorId)->orderBy('id')->get();
    }

    public function createProject(int $actorId, string $name): Project
    {
        return DB::transaction(function () use ($actorId, $name) {
            $user = User::query()->lockForUpdate()->findOrFail($actorId);

            $project = new Project;
            $project->name = $name;
            $project->save();
            $project->addMember($user->getKey(), config('permissions.scopes'));

            return $project;
        });
    }

    public function requireMembership(int $actorId, int $projectId): Project
    {
        return $this->projectsFor($actorId)->findOrFail($projectId);
    }

    public function requireOwnership(int $actorId, int $projectId): Project
    {
        return $this->projectsFor($actorId)->findOrFail($projectId);
    }

    public function updateProject(int $actorId, int $projectId, string $name): Project
    {
        $project = $this->requireOwnership($actorId, $projectId);
        $project->name = $name;
        $project->save();

        return $project;
    }

    public function deleteProject(int $actorId, int $projectId): void
    {
        $this->requireOwnership($actorId, $projectId)->delete();
    }

    public function addMember(int $actorId, int $projectId, int $userId): void
    {
        DB::transaction(function () use ($actorId, $projectId, $userId): void {
            $project = $this->requireMembership($actorId, $projectId);
            $user = User::query()->lockForUpdate()->findOrFail($userId);

            $project->addMember($user->getKey());
        }, attempts: 3);
    }

    public function removeMember(int $actorId, int $projectId, int $userId): void
    {
        DB::transaction(function () use ($actorId, $projectId, $userId): void {
            $project = $this->requireMembership($actorId, $projectId);
            $user = User::query()->lockForUpdate()->findOrFail($userId);

            $project->removeMember($user->getKey());
        }, attempts: 3);
    }
}
