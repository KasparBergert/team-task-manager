<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('creates a task for the signed-in project member', function () {
    $project = Project::forceCreate(['name' => 'Website launch']);
    $member = User::factory()->create(['project_id' => $project->id]);

    $this->actingAs($member)->postJson("/projects/{$project->id}/tasks", ['title' => 'Draft the proposal'])
        ->assertSuccessful();

    $this->assertDatabaseHas('tasks', [
        'project_id' => $project->id,
        'assigned_to' => $member->id,
        'title' => 'Draft the proposal',
    ]);
});

test('shows the requested task', function () {
    $project = Project::forceCreate(['name' => 'Website launch']);
    $member = User::factory()->create(['project_id' => $project->id]);
    $task = Task::forceCreate([
        'project_id' => $project->id,
        'assigned_to' => $member->id,
        'title' => 'Draft the proposal',
    ]);

    $this->actingAs($member)->getJson("/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonFragment(['id' => $task->id, 'title' => $task->title]);
});

test('marks a task as in progress', function () {
    $project = Project::forceCreate(['name' => 'Website launch']);
    $member = User::factory()->create(['project_id' => $project->id]);
    $task = Task::forceCreate([
        'project_id' => $project->id,
        'assigned_to' => $member->id,
        'title' => 'Draft the proposal',
    ]);

    $this->actingAs($member)->patchJson("/tasks/{$task->id}", ['status' => 'in_progress'])
        ->assertSuccessful();

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'in_progress']);
});

test('deletes a task', function () {
    $project = Project::forceCreate(['name' => 'Website launch']);
    $member = User::factory()->create(['project_id' => $project->id]);
    $task = Task::forceCreate([
        'project_id' => $project->id,
        'assigned_to' => $member->id,
        'title' => 'Draft the proposal',
    ]);

    $this->actingAs($member)->deleteJson("/tasks/{$task->id}")
        ->assertSuccessful();

    $this->assertModelMissing($task);
});
