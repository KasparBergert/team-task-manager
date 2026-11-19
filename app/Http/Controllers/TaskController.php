<?php

namespace App\Http\Controllers;

use App\Helpers\ProjectValidator;
use App\Helpers\TaskValidator;
use App\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TaskController extends Controller
{
    public function __construct(private readonly TaskService $service) {}

    public function createTask(Request $request, string $projectId): Response
    {
        $data = $request->validate(TaskValidator::fieldRules(creating: true));

        $task = $this->service->createTask(
            (int) $request->user()->getKey(),
            ProjectValidator::id($projectId),
            $data,
        );

        return response($task, 201);
    }

    public function listTasks(Request $request, string $projectId): Response
    {
        $filters = $request->validate([
            'q' => ['sometimes', 'string'],
            // the status values should be some shraed enum inside the migration and here.
            'status' => ['sometimes', 'in:not_started,in_progress,completed'],
        ]);

        $tasks = $this->service->listTasks(
            (int) $request->user()->getKey(),
            ProjectValidator::id($projectId),
            $filters,
        );

        return response($tasks);
    }

    public function showTask(Request $request, string $projectId, string $taskId): Response
    {
        $task = $this->service->showTask(
            (int) $request->user()->getKey(),
            ProjectValidator::id($projectId),
            TaskValidator::id($taskId),
        );

        return response($task);
    }

    public function updateTask(Request $request, string $projectId, string $taskId): Response
    {
        $data = $request->validate(TaskValidator::fieldRules(creating: false));
        if (isset($data['assigneeId'])) {
            $data['assigneeId'] = (int) $data['assigneeId'];
        }

        $task = $this->service->updateTask(
            (int) $request->user()->getKey(),
            ProjectValidator::id($projectId),
            TaskValidator::id($taskId),
            $data,
        );

        return response($task);
    }

    public function deleteTask(Request $request, string $projectId, string $taskId): Response
    {
        $this->service->deleteTask(
            (int) $request->user()->getKey(),
            ProjectValidator::id($projectId),
            TaskValidator::id($taskId),
        );

        return response()->noContent();
    }

    // Audit log of changes to the task.
    public function listTaskActivity(Request $request, string $projectId, string $taskId): Response
    {
        $this->service->listTaskActivity(
            (int) $request->user()->getKey(),
            ProjectValidator::id($projectId),
            TaskValidator::id($taskId),
        );

        return response()->noContent(501);
    }
}
