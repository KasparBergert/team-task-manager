<?php

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

// Display the public welcome page.
Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::get('/projects', [ProjectController::class, 'showProjects']);
    Route::post('/projects', [ProjectController::class, 'createProject']);
    Route::patch('/projects/{projectId}', [ProjectController::class, 'updateProject']);
    Route::delete('/projects/{projectId}', [ProjectController::class, 'deleteProject']);

    // Grant project membership to the specified user.
    Route::put('/projects/{projectId}/members/{userId}', [ProjectController::class, 'addMember']);
    // Revoke the specified user's project membership.
    Route::delete('/projects/{projectId}/members/{userId}', [ProjectController::class, 'removeMember']);

    Route::post('/projects/{projectId}/tasks', [TaskController::class, 'createTask']);
    Route::get('/projects/{projectId}/tasks', [TaskController::class, 'listTasks']);
    Route::get('/projects/{projectId}/tasks/{taskId}', [TaskController::class, 'showTask']);
    Route::patch('/projects/{projectId}/tasks/{taskId}', [TaskController::class, 'updateTask']);
    Route::delete('/projects/{projectId}/tasks/{taskId}', [TaskController::class, 'deleteTask']);

    // List changes recorded for this task.
    Route::get('/projects/{projectId}/tasks/{taskId}/activity', [TaskController::class, 'listTaskActivity']);
});
