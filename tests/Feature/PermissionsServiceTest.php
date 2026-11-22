<?php

use App\Models\Project;
use App\Models\User;
use App\Services\PermissionsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('startup registers the configured scopes after migration', function () {
    expect(DB::table('scopes')->where('program_identifier', config('permissions.program_identifier'))->pluck('name')->all())
        ->toContain('create', 'read', 'update', 'delete');
});

test('grants are isolated by user, project, and program and can be revoked', function () {
    $user = User::factory()->create();
    $service = app(PermissionsService::class);
    $permissionIdentifierKey = ['user_id' => (string) $user->id, 'project_id' => '11'];

    $service->addScopes($permissionIdentifierKey, ['read', 'update']);
    $service->addScopes(array_reverse($permissionIdentifierKey, true), ['read']);

    expect($user->permissions(11))->toBe(['read', 'update'])
        ->and($user->permissions(12))->toBe([])
        ->and($service->getScopes(['user_id' => (string) ($user->id + 1), 'project_id' => '11']))->toBe([])
        ->and((new PermissionsService('another-program'))->getScopes($permissionIdentifierKey))->toBe([]);

    $service->removeScopes($permissionIdentifierKey, ['update']);
    expect($user->permissions(11))->toBe(['read']);
});

test('an unknown scope rejects the whole grant', function () {
    $service = app(PermissionsService::class);
    $permissionIdentifierKey = ['user_id' => '1', 'project_id' => '2'];

    try {
        $service->addScopes($permissionIdentifierKey, ['read', 'unknown']);
        $this->fail('Expected an invalid scope to be rejected.');
    } catch (InvalidArgumentException $exception) {
        expect($service->getScopes($permissionIdentifierKey))->toBe([]);
    }
});

test('deleting a user cleans up only grants referencing that user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $service = app(PermissionsService::class);
    $deleted = ['user_id' => $user->id, 'project_id' => 42];
    $retained = ['user_id' => $other->id, 'project_id' => 42];
    $service->addScopes($deleted, ['read']);
    $service->addScopes($retained, ['read']);

    $user->delete();

    expect($service->getScopes($deleted))->toBe([])
        ->and($service->getScopes($retained))->toBe(['read']);
});

test('deleting a project cleans up grants referencing it and its cascaded tasks', function () {
    $project = new Project;
    $project->name = 'Example';
    $project->save();
    $task = $project->tasks()->make();
    $task->title = 'Example';
    $task->save();
    $service = app(PermissionsService::class);
    $projectScope = ['user_id' => 1, 'project_id' => $project->id];
    $taskScope = ['task_id' => $task->id];
    $unrelated = ['project_id' => $project->id + 1];
    $service->addScopes($projectScope, ['read']);
    $service->addScopes($taskScope, ['read']);
    $service->addScopes($unrelated, ['read']);

    $project->delete();

    expect($service->getScopes($projectScope))->toBe([])
        ->and($service->getScopes($taskScope))->toBe([])
        ->and($service->getScopes($unrelated))->toBe(['read']);
});

test('deleting a task cleans up scopes referencing that task', function () {
    $project = new Project;
    $project->name = 'Example';
    $project->save();
    $task = $project->tasks()->make();
    $task->title = 'Example';
    $task->save();
    $service = app(PermissionsService::class);
    $permissionIdentifierKey = ['task_id' => $task->id];
    $service->addScopes($permissionIdentifierKey, ['read']);

    $task->delete();

    expect($service->getScopes($permissionIdentifierKey))->toBe([]);
});

test('scope cleanup is rolled back with a model deletion', function () {
    $user = User::factory()->create();
    $service = app(PermissionsService::class);
    $permissionIdentifierKey = ['user_id' => $user->id];
    $service->addScopes($permissionIdentifierKey, ['read']);

    DB::beginTransaction();
    $user->delete();
    DB::rollBack();

    expect($service->getScopes($permissionIdentifierKey))->toBe(['read']);
    $this->assertDatabaseHas('users', ['id' => $user->id]);
});

test('grants store canonical identifiers and deduplicate each identifier and scope pair', function () {
    $service = app(PermissionsService::class);
    $permissionIdentifierKey = ['user_id' => 1, 'project_id' => 2];
    $service->addScopes($permissionIdentifierKey, ['read']);
    $service->addScopes(['project_id' => '2', 'user_id' => '1'], ['read', 'update']);

    $identifier = $service->createIdentifier($permissionIdentifierKey);

    expect(DB::table('scope_grants')->pluck('identifier')->all())->toBe([$identifier, $identifier]);
    expect($service->getScopes($permissionIdentifierKey))->toBe(['read', 'update']);
});

test('identifiers too long for the unique storage column are rejected', function () {
    app(PermissionsService::class)->addScopes(['external_id' => str_repeat('x', 701)], ['read']);
})->throws(InvalidArgumentException::class);
