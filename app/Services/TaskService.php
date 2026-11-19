<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class TaskService
{
    public function __construct(private readonly ProjectService $projects) {}

    public function createTask(int $actorId, int $projectId, array $attributes): Task
    {
        $project = $this->projects->requireMembership($actorId, $projectId);
        $task = $project->tasks()->make();
        $task->assigned_to = $actorId;
        $this->fillTask($task, $project, $attributes);
        $task->save();

        return $project->tasks()->findOrFail($task->getKey());
    }

    public function listTasks(int $actorId, int $projectId, array $filters): Collection
    {
        $tasks = $this->projects->requireMembership($actorId, $projectId)->tasks();

        if (isset($filters['status'])) {
            $tasks->where('status', $filters['status']);
        }

        if (isset($filters['q'])) {
            $pattern = '%'.$filters['q'].'%';
            $tasks->where(function (Builder $query) use ($pattern) {
                $query->whereLike('title', $pattern)->orWhereLike('description', $pattern);
            });
        }

        return $tasks->orderBy('id')->get();
    }

    public function showTask(int $actorId, int $projectId, int $taskId): Task
    {
        return $this->projects->requireMembership($actorId, $projectId)->tasks()->findOrFail($taskId);
    }

    public function updateTask(int $actorId, int $projectId, int $taskId, array $attributes): Task
    {
        $project = $this->projects->requireMembership($actorId, $projectId);
        $task = $project->tasks()->findOrFail($taskId);

        $this->fillTask($task, $project, $attributes);
        $task->save();

        return $task;
    }

    private function fillTask(Task $task, Project $project, array $attributes): void
    {
        if (array_key_exists('assigneeId', $attributes)) {
            $member = $project->members()->find($attributes['assigneeId'], ['users.id']);
            if ($member === null) {
                throw ValidationException::withMessages(['assigneeId' => 'The assignee must belong to this project.']);
            }

            $task->assigned_to = $member->getKey();
        }

        if (isset($attributes['title'])) {
            $task->title = $attributes['title'];
        }
        if (array_key_exists('description', $attributes)) {
            $task->description = $attributes['description'];
        }
        if (isset($attributes['status'])) {
            $task->status = $attributes['status'];
        }
        if (array_key_exists('dueDate', $attributes)) {
            $task->due_date = $attributes['dueDate'];
        }
    }

    public function deleteTask(int $actorId, int $projectId, int $taskId): void
    {
        $this->showTask($actorId, $projectId, $taskId)->delete();
    }

    public function listTaskActivity(int $actorId, int $projectId, int $taskId): void
    {
        $this->showTask($actorId, $projectId, $taskId);
    }
}
