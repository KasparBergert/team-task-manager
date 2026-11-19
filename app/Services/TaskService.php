<?php

namespace App\Services;

class TaskService
{
    public function createTask(int $actorId, int $projectId, array $attributes): void {}

    public function listTasks(int $actorId, int $projectId, array $filters): void {}

    public function showTask(int $actorId, int $taskId): void {}

    public function updateTask(int $actorId, int $taskId, array $attributes): void {}

    public function deleteTask(int $actorId, int $taskId): void {}

    public function listTaskActivity(int $actorId, int $taskId): void {}
}
