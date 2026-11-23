<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\PermissionsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('creates a task for a member with the create scope', function () {
    $project = Project::forceCreate(['name' => 'Website launch']);
    $member = User::factory()->create();
    $project->addMember($member->id, ['create']);

    $this->actingAs($member)->postJson("/projects/{$project->id}/tasks", ['title' => 'Draft the proposal'])
        ->assertSuccessful();

    $this->assertDatabaseHas('tasks', [
        'project_id' => $project->id,
        'assigned_to' => $member->id,
        'title' => 'Draft the proposal',
    ]);
});

test('shows the requested task to a member with the read scope', function () {
    $project = Project::forceCreate(['name' => 'Website launch']);
    $member = User::factory()->create();
    $project->addMember($member->id, ['read']);
    $task = Task::forceCreate([
        'project_id' => $project->id,
        'assigned_to' => $member->id,
        'title' => 'Draft the proposal',
    ]);

    $this->actingAs($member)->getJson("/projects/{$project->id}/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonFragment(['id' => $task->id, 'title' => $task->title]);
});

test('marks a task as in progress for a member with the update scope', function () {
    $project = Project::forceCreate(['name' => 'Website launch']);
    $member = User::factory()->create();
    $project->addMember($member->id, ['update']);
    $task = Task::forceCreate([
        'project_id' => $project->id,
        'assigned_to' => $member->id,
        'title' => 'Draft the proposal',
    ]);

    $this->actingAs($member)->patchJson("/projects/{$project->id}/tasks/{$task->id}", ['status' => 'in_progress'])
        ->assertSuccessful();

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'in_progress', 'assigned_to' => $member->id]);
});

test('deletes a task for a member with the delete scope', function () {
    $project = Project::forceCreate(['name' => 'Website launch']);
    $member = User::factory()->create();
    $project->addMember($member->id, ['delete']);
    $task = Task::forceCreate([
        'project_id' => $project->id,
        'assigned_to' => $member->id,
        'title' => 'Draft the proposal',
    ]);

    $this->actingAs($member)->deleteJson("/projects/{$project->id}/tasks/{$task->id}")
        ->assertSuccessful();

    $this->assertModelMissing($task);
});

test('returns 403 when a read-only member tries to create a task', function () {
    $project = Project::forceCreate(['name' => 'Website launch']);
    $member = User::factory()->create();
    $project->addMember($member->id, ['read']);

    $this->actingAs($member)->postJson("/projects/{$project->id}/tasks", ['title' => 'Draft the proposal'])
        ->assertForbidden();

    $this->assertDatabaseCount('tasks', 0);
});

test('returns 403 when a member can update tasks only in another project', function () {
    $project = Project::forceCreate(['name' => 'Website launch']);
    $otherProject = Project::forceCreate(['name' => 'Other project']);
    $member = User::factory()->create();
    $project->addMember($member->id, ['read']);
    $otherProject->addMember($member->id, ['update']);
    $task = Task::forceCreate(['project_id' => $project->id, 'title' => 'Original']);

    $this->actingAs($member)->patchJson("/projects/{$project->id}/tasks/{$task->id}", ['title' => 'Changed'])
        ->assertForbidden();

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Original']);
});

test('returns 403 when a read-only member tries to delete a task', function () {
    $project = Project::forceCreate(['name' => 'Website launch']);
    $member = User::factory()->create();
    $project->addMember($member->id, ['read']);
    $task = Task::forceCreate(['project_id' => $project->id, 'title' => 'Draft the proposal']);

    $this->actingAs($member)->deleteJson("/projects/{$project->id}/tasks/{$task->id}")
        ->assertForbidden();

    $this->assertModelExists($task);
});

test('returns 403 for task reads after the project read scope is revoked', function (string $suffix) {
    $project = Project::forceCreate(['name' => 'Website launch']);
    $member = User::factory()->create();
    $project->addMember($member->id, ['read', 'update']);
    $task = Task::forceCreate(['project_id' => $project->id, 'title' => 'Draft the proposal']);
    app(PermissionsService::class)->removeScopes(['user_id' => $member->id, 'project_id' => $project->id], ['read']);
    $path = '/projects/'.$project->id.'/tasks'.str_replace('{task}', (string) $task->id, $suffix);

    $this->actingAs($member)->getJson($path)->assertForbidden();
})->with([
    'task list' => [''],
    'task details' => ['/{task}'],
    'task activity' => ['/{task}/activity'],
]);

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
    $first->addMember($member->id, ['read', 'update', 'delete']);
    $second->addMember($member->id, ['read', 'update', 'delete']);
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
    $selected->addMember($actor->id, ['update']);
    $other->addMember($actor->id, ['update']);
    $other->addMember($assignee->id);
    $task = Task::forceCreate(['project_id' => $selected->id, 'title' => 'Original', 'assigned_to' => $actor->id]);

    $this->actingAs($actor)->patchJson("/projects/{$selected->id}/tasks/{$task->id}", ['assigneeId' => $assignee->id])
        ->assertUnprocessable()->assertJsonValidationErrors(['assigneeId']);

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'assigned_to' => $actor->id]);
});

test('assigns the task to an existing project member', function () {
    $project = Project::forceCreate(['name' => 'Website launch']);
    $actor = User::factory()->create();
    $member = User::factory()->create();
    $project->addMember($actor->id, ['update']);
    $project->addMember($member->id);
    $task = Task::forceCreate(['project_id' => $project->id, 'title' => 'Draft', 'assigned_to' => $actor->id]);

    $this->actingAs($actor)->patchJson("/projects/{$project->id}/tasks/{$task->id}", ['assigneeId' => $member->id])
        ->assertOk()->assertJsonPath('assigned_to', $member->id);

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'assigned_to' => $member->id]);
});

test('rejects the whole task update for a null or nonexistent assignee', function (?int $assigneeId) {
    $project = Project::forceCreate(['name' => 'Website launch']);
    $actor = User::factory()->create();
    $project->addMember($actor->id, ['update']);
    $task = Task::forceCreate(['project_id' => $project->id, 'title' => 'Draft', 'assigned_to' => $actor->id]);

    $this->actingAs($actor)->patchJson("/projects/{$project->id}/tasks/{$task->id}", [
        'title' => 'Changed',
        'assigneeId' => $assigneeId,
    ])->assertUnprocessable()->assertJsonValidationErrors(['assigneeId']);

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Draft', 'assigned_to' => $actor->id]);
})->with(['null' => [null], 'nonexistent' => [999999]]);
