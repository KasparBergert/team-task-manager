<?php

namespace App\Observers;

use App\Models\Project;
use App\Services\PermissionsService;

class ProjectObserver
{
    public function deleting(Project $project): void
    {
        $permissions = app(PermissionsService::class);
        $project->tasks()->select('id')->eachById(function ($task) use ($permissions): void {
            $permissions->deleteScopes(['task_id' => $task->getKey()]);
        });
    }

    public function deleted(Project $project): void
    {
        app(PermissionsService::class)->deleteScopes(['project_id' => $project->getKey()]);
    }
}
