<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('creates a task for the signed-in project member', function () {
    $project = Project::forceCreate(['name' => 'Website launch']);
    $member = User::factory()->create();
    $project->members()->attach($member);

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
    $member = User::factory()->create();
    $project->members()->attach($member);
    $task = Task::forceCreate([
        'project_id' => $project->id,
        'assigned_to' => $member->id,
        'title' => 'Draft the proposal',
    ]);

    $this->actingAs($member)->getJson("/projects/{$project->id}/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonFragment(['id' => $task->id, 'title' => $task->title]);
});

test('marks a task as in progress', function () {
    $project = Project::forceCreate(['name' => 'Website launch']);
    $member = User::factory()->create();
    $project->members()->attach($member);
    $task = Task::forceCreate([
        'project_id' => $project->id,
        'assigned_to' => $member->id,
        'title' => 'Draft the proposal',
    ]);

    $this->actingAs($member)->patchJson("/projects/{$project->id}/tasks/{$task->id}", ['status' => 'in_progress'])
        ->assertSuccessful();

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'in_progress', 'assigned_to' => $member->id]);
});

test('deletes a task', function () {
    $project = Project::forceCreate(['name' => 'Website launch']);
    $member = User::factory()->create();
    $project->members()->attach($member);
    $task = Task::forceCreate([
        'project_id' => $project->id,
        'assigned_to' => $member->id,
        'title' => 'Draft the proposal',
    ]);

    $this->actingAs($member)->deleteJson("/projects/{$project->id}/tasks/{$task->id}")
        ->assertSuccessful();

    $this->assertModelMissing($task);
});

test('creates and lists tasks in the selected project', function () {
    $user = User::factory()->create();
    $first = $this->actingAs($user)->postJson('/projects', ['name' => 'First'])->assertCreated()->json('id');
    $second = $this->postJson('/projects', ['name' => 'Second'])->assertCreated()->json('id');

    $task = $this->postJson("/projects/{$second}/tasks", ['title' => 'Second project task'])
        ->assertCreated()->assertJsonPath('project_id', $second)->json('id');

    $this->getJson("/projects/{$first}/tasks")->assertOk()->assertExactJson([]);
    $this->getJson("/projects/{$second}/tasks")->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $task);
});

test('returns 404 for a task under the wrong project even when the user belongs to both', function (string $method, string $suffix) {
    $member = User::factory()->create();
    $first = Project::forceCreate(['name' => 'First']);
    $second = Project::forceCreate(['name' => 'Second']);
    $member->projects()->attach([$first->id, $second->id]);
    $task = Task::forceCreate(['project_id' => $second->id, 'title' => 'Original']);

    $this->actingAs($member)->json($method, "/projects/{$first->id}/tasks/{$task->id}{$suffix}", ['title' => 'Changed'])
        ->assertNotFound();

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Original']);
})->with([['GET', ''], ['PATCH', ''], ['DELETE', ''], ['GET', '/activity']]);

test('returns 404 when a non-member selects a project', function () {
    $user = User::factory()->create();
    $project = Project::forceCreate(['name' => 'Private']);
    $tasks = "/projects/{$project->id}/tasks";

    $this->actingAs($user)->getJson($tasks)->assertNotFound();
    $this->postJson($tasks, ['title' => 'New'])->assertNotFound();
    $this->patchJson("/projects/{$project->id}", ['name' => 'Changed'])->assertNotFound();
    $this->deleteJson("/projects/{$project->id}")->assertNotFound();

    $this->assertDatabaseCount('tasks', 0);
    $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Private']);
});

test('returns 422 when a task assignee only belongs to another project', function () {
    $actor = User::factory()->create();
    $assignee = User::factory()->create();
    $selected = Project::forceCreate(['name' => 'Selected']);
    $other = Project::forceCreate(['name' => 'Other']);
    $actor->projects()->attach([$selected->id, $other->id]);
    $assignee->projects()->attach($other);
    $task = Task::forceCreate(['project_id' => $selected->id, 'title' => 'Original', 'assigned_to' => $actor->id]);

    $this->actingAs($actor)->patchJson("/projects/{$selected->id}/tasks/{$task->id}", ['assigneeId' => $assignee->id])
        ->assertUnprocessable()->assertJsonValidationErrors(['assigneeId']);

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'assigned_to' => $actor->id]);
});

test('assigns the task to an existing project member', function () {
    $project = Project::forceCreate(['name' => 'Website launch']);
    $actor = User::factory()->create();
    $member = User::factory()->create();
    $project->members()->attach([$actor->id, $member->id]);
    $task = Task::forceCreate(['project_id' => $project->id, 'title' => 'Draft', 'assigned_to' => $actor->id]);

    $this->actingAs($actor)->patchJson("/projects/{$project->id}/tasks/{$task->id}", ['assigneeId' => $member->id])
        ->assertOk()->assertJsonPath('assigned_to', $member->id);

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'assigned_to' => $member->id]);
});

test('rejects the whole task update for a null or nonexistent assignee', function (?int $assigneeId) {
    $project = Project::forceCreate(['name' => 'Website launch']);
    $actor = User::factory()->create();
    $project->members()->attach($actor);
    $task = Task::forceCreate(['project_id' => $project->id, 'title' => 'Draft', 'assigned_to' => $actor->id]);

    $this->actingAs($actor)->patchJson("/projects/{$project->id}/tasks/{$task->id}", [
        'title' => 'Changed',
        'assigneeId' => $assigneeId,
    ])->assertUnprocessable()->assertJsonValidationErrors(['assigneeId']);

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Draft', 'assigned_to' => $actor->id]);
})->with(['null' => [null], 'nonexistent' => [999999]]);
