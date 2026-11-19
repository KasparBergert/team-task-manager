<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TaskController extends Controller
{
    public function createTask(Request $request, int $projectId): Response
    {
        return response()->noContent(501);
    }

    public function listTasks(Request $request, int $projectId): Response
    {
        return response()->noContent(501);
    }

    public function showTask(Request $request, int $taskId): Response
    {
        return response()->noContent(501);
    }

    public function updateTask(Request $request, int $taskId): Response
    {
        return response()->noContent(501);
    }

    public function deleteTask(Request $request, int $taskId): Response
    {
        return response()->noContent(501);
    }

    // Audit log of changes to the task.
    public function listTaskActivity(Request $request, int $taskId): Response
    {
        return response()->noContent(501);
    }
}
