<?php

use App\Models\Project;
use App\Models\User;
use App\Services\PermissionsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('adding a member directly on the model grants read access', function () {
    $project = Project::forceCreate(['name' => 'Shared']);
    $member = User::factory()->create();

    expect($project->addMember($member->id))->toBeTrue();

    $this->assertDatabaseHas('project_user', ['user_id' => $member->id, 'project_id' => $project->id]);
    expect($member->permissions($project->id))->toBe(['read']);
});

test('removing a member directly on the model clears their project grants', function () {
    $project = Project::forceCreate(['name' => 'Shared']);
    $member = User::factory()->create();
    $project->members()->attach($member->id);
    $permissions = app(PermissionsService::class);
    $taskGrant = ['user_id' => $member->id, 'project_id' => $project->id, 'task_id' => 1];
    $permissions->addScopes($taskGrant, ['update']);

    $project->removeMember($member->id);

    $this->assertDatabaseMissing('project_user', ['user_id' => $member->id, 'project_id' => $project->id]);
    expect($permissions->getScopes($taskGrant))->toBe([]);
});

test('adding a member rolls back the membership if granting scopes fails', function () {
    $project = Project::forceCreate(['name' => 'Shared']);
    $member = User::factory()->create();

    expect(fn () => $project->addMember($member->id, ['unregistered-scope']))
        ->toThrow(InvalidArgumentException::class, 'All scopes must be registered for this program.');

    $this->assertDatabaseMissing('project_user', ['user_id' => $member->id, 'project_id' => $project->id]);
    expect($member->permissions($project->id))->toBe([]);
});

test('removing a member rolls back the membership and grants if scope cleanup fails', function () {
    $project = Project::forceCreate(['name' => 'Shared']);
    $member = User::factory()->create();
    $project->members()->attach($member->id);
    app(PermissionsService::class)->addScopes(['user_id' => $member->id, 'project_id' => $project->id], ['read']);
    $scopeId = DB::table('scopes')->where('program_identifier', config('permissions.program_identifier'))
        ->where('name', 'read')->value('id');
    // Sort after the valid JSON so cleanup deletes the matching grant before it fails.
    DB::table('scope_grants')->insert(['identifier' => '~invalid-json', 'scope_id' => $scopeId]);

    expect(fn () => $project->removeMember($member->id))->toThrow(JsonException::class);

    $this->assertDatabaseHas('project_user', ['user_id' => $member->id, 'project_id' => $project->id]);
    expect($member->permissions($project->id))->toBe(['read']);
});

test('project creation and membership changes maintain project scope grants', function () {
    $creator = User::factory()->create();
    $member = User::factory()->create();
    $projectId = $this->actingAs($creator)->postJson('/projects', ['name' => 'Shared'])->assertCreated()->json('id');
    $permissions = app(PermissionsService::class);
    $creatorKey = ['user_id' => $creator->id, 'project_id' => $projectId];
    $memberKey = ['user_id' => $member->id, 'project_id' => $projectId];

    expect($permissions->getScopes($creatorKey))->toBe(['create', 'delete', 'read', 'update']);
    expect($permissions->getScopes($memberKey))->toBeEmpty();

    $this->putJson("/projects/{$projectId}/members/{$member->id}")->assertNoContent();
    expect($permissions->getScopes($memberKey))->toBe(['read']);

    $this->deleteJson("/projects/{$projectId}/members/{$member->id}")->assertNoContent();
    expect($permissions->getScopes($memberKey))->toBeEmpty();
    expect($permissions->getScopes($creatorKey))->toBe(['create', 'delete', 'read', 'update']);
});

test('adding a member makes the project visible and grants access to its tasks', function () {
    $creator = User::factory()->create();
    $member = User::factory()->create();
    $projectId = $this->actingAs($creator)->postJson('/projects', ['name' => 'Shared'])->assertCreated()->json('id');
    $tasks = "/projects/{$projectId}/tasks";

    $this->actingAs($member)->getJson($tasks)->assertNotFound();
    $this->actingAs($creator)->putJson("/projects/{$projectId}/members/{$member->id}")->assertNoContent();

    $this->actingAs($member)->getJson('/projects')->assertOk()->assertJsonFragment(['id' => $projectId]);
    $taskId = $this->postJson($tasks, ['title' => 'Shared task'])->assertCreated()->json('id');
    $this->actingAs($creator)->getJson("{$tasks}/{$taskId}")->assertOk()->assertJsonPath('assigned_to', $member->id);
});

test('removing a member revokes access to just the selected project', function () {
    $creator = User::factory()->create();
    $member = User::factory()->create();
    $personalId = $this->actingAs($member)->postJson('/projects', ['name' => 'Personal'])->assertCreated()->json('id');
    $sharedId = $this->actingAs($creator)->postJson('/projects', ['name' => 'Shared'])->assertCreated()->json('id');
    $membership = "/projects/{$sharedId}/members/{$member->id}";
    $this->putJson($membership)->assertNoContent();
    $this->actingAs($member)->getJson("/projects/{$sharedId}/tasks")->assertOk();
    $permissions = app(PermissionsService::class);
    $taskGrant = ['user_id' => $member->id, 'project_id' => $sharedId, 'task_id' => 1];
    $otherProjectGrant = ['user_id' => $member->id, 'project_id' => $personalId];
    $permissions->addScopes($taskGrant, ['update']);
    $permissions->addScopes($otherProjectGrant, ['read']);

    $this->actingAs($creator)->deleteJson($membership)->assertNoContent();

    expect($permissions->getScopes($taskGrant))->toBe([]);
    expect($permissions->getScopes($otherProjectGrant))->toContain('read');
    $this->actingAs($member)->getJson('/projects')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $personalId);
    $this->getJson("/projects/{$sharedId}/tasks")->assertNotFound();
    $this->getJson("/projects/{$personalId}/tasks")->assertOk();
    $this->actingAs($creator)->getJson("/projects/{$sharedId}/tasks")->assertOk();
});

test('adding an existing member preserves their explicitly revoked scopes', function () {
    $creator = User::factory()->create();
    $member = User::factory()->create();
    $projectId = $this->actingAs($creator)->postJson('/projects', ['name' => 'Shared'])->assertCreated()->json('id');
    $membership = "/projects/{$projectId}/members/{$member->id}";
    $this->putJson($membership)->assertNoContent();
    $permissions = app(PermissionsService::class);
    $memberKey = ['user_id' => $member->id, 'project_id' => $projectId];
    $permissions->removeScopes($memberKey, ['read']);

    $this->putJson($membership)->assertNoContent();

    expect($permissions->getScopes($memberKey))->toBe([]);
});

test('an outsider cannot remove a project member', function () {
    $creator = User::factory()->create();
    $outsider = User::factory()->create();
    $projectId = $this->actingAs($creator)->postJson('/projects', ['name' => 'Private'])->assertCreated()->json('id');

    $this->actingAs($outsider)->deleteJson("/projects/{$projectId}/members/{$creator->id}")->assertNotFound();

    $this->actingAs($creator)->getJson("/projects/{$projectId}/tasks")->assertOk();
});

test('returns 403 when a read-only member tries to change project membership', function (string $method, bool $targetIsMember) {
    $actor = User::factory()->create();
    $target = User::factory()->create();
    $project = Project::forceCreate(['name' => 'Shared']);
    $project->addMember($actor->id);
    if ($targetIsMember) {
        $project->addMember($target->id);
    }

    $this->actingAs($actor)->json($method, "/projects/{$project->id}/members/{$target->id}")->assertForbidden();

    expect($project->members()->whereKey($target->id)->exists())->toBe($targetIsMember);
    expect($target->permissions($project->id))->toBe($targetIsMember ? ['read'] : []);
})->with([
    'add member' => ['PUT', false],
    'remove member' => ['DELETE', true],
]);

test('a guest cannot change project membership', function (string $method) {
    $this->json($method, '/projects/1/members/2')->assertUnauthorized();
})->with(['PUT', 'DELETE']);
