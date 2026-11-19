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

        $this->service->createTask(
            (int) $request->user()->getKey(),
            ProjectValidator::id($projectId),
            $data,
        );

        return response()->noContent(501);
    }

    public function listTasks(Request $request, string $projectId): Response
    {
        $filters = $request->validate([
            'q' => ['sometimes', 'string'],
            'status' => ['sometimes', 'in:not_started,in_progress,completed'],
        ]);

        $this->service->listTasks(
            (int) $request->user()->getKey(),
            ProjectValidator::id($projectId),
            $filters,
        );

        return response()->noContent(501);
    }

    public function showTask(Request $request, string $taskId): Response
    {
        $this->service->showTask(
            (int) $request->user()->getKey(),
            TaskValidator::id($taskId),
        );

        return response()->noContent(501);
    }

    public function updateTask(Request $request, string $taskId): Response
    {
        $data = $request->validate(TaskValidator::fieldRules(creating: false));
        if (isset($data['assigneeId'])) {
            $data['assigneeId'] = (int) $data['assigneeId'];
        }

        $this->service->updateTask(
            (int) $request->user()->getKey(),
            TaskValidator::id($taskId),
            $data,
        );

        return response()->noContent(501);
    }

    public function deleteTask(Request $request, string $taskId): Response
    {
        $this->service->deleteTask(
            (int) $request->user()->getKey(),
            TaskValidator::id($taskId),
        );

        return response()->noContent(501);
    }

    // Audit log of changes to the task.
    public function listTaskActivity(Request $request, string $taskId): Response
    {
        $this->service->listTaskActivity(
            (int) $request->user()->getKey(),
            TaskValidator::id($taskId),
        );

        return response()->noContent(501);
    }
}
