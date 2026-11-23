<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ProjectService
{
    public function __construct(private readonly PermissionsService $permissions) {}

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

    public function requireScope(int $actorId, int $projectId, string $scope, string $action): Project
    {
        $project = $this->requireMembership($actorId, $projectId);
        $scopes = $this->permissions->getScopes(['user_id' => $actorId, 'project_id' => $projectId]);

        if (! in_array($scope, $scopes, true)) {
            throw new AuthorizationException("You need the '{$scope}' scope to {$action}.");
        }

        return $project;
    }

    public function updateProject(int $actorId, int $projectId, string $name): Project
    {
        $project = $this->requireScope($actorId, $projectId, 'update', 'rename this project');
        $project->name = $name;
        $project->save();

        return $project;
    }

    public function deleteProject(int $actorId, int $projectId): void
    {
        $this->requireScope($actorId, $projectId, 'delete', 'delete this project')->delete();
    }

    public function addMember(int $actorId, int $projectId, int $userId): void
    {
        $project = $this->requireScope($actorId, $projectId, 'update', 'add a member to this project');
        User::query()->findOrFail($userId);

        $project->addMember($userId);
    }

    public function removeMember(int $actorId, int $projectId, int $userId): void
    {
        $project = $this->requireScope($actorId, $projectId, 'update', 'remove a member from this project');

        $project->removeMember($userId);
    }
}
