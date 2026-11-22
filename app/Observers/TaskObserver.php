<?php

namespace App\Observers;

use App\Models\Task;
use App\Services\PermissionsService;

class TaskObserver
{
    public function deleted(Task $task): void
    {
        app(PermissionsService::class)->deleteScopes(['task_id' => $task->getKey()]);
    }
}
