<?php

use App\Models\Project;
use App\Models\User;
use App\Services\PermissionsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('creates a project', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/projects', ['name' => 'Website launch'])
        ->assertSuccessful();

    $this->assertDatabaseHas('projects', ['name' => 'Website launch']);
});

test('shows every project the signed-in user belongs to after creating a second project', function () {
    $member = User::factory()->create();
    $shared = Project::forceCreate(['name' => 'Shared project']);
    $shared->members()->attach($member);
    Project::forceCreate(['name' => 'Private project']);

    $this->actingAs($member)->postJson('/projects', ['name' => 'First project'])->assertCreated();
    $this->postJson('/projects', ['name' => 'Second project'])->assertCreated();

    $this->getJson('/projects')->assertOk()->assertJsonCount(3)
        ->assertJsonFragment(['name' => 'Shared project'])
        ->assertJsonFragment(['name' => 'First project'])
        ->assertJsonFragment(['name' => 'Second project']);
});

test('renames a project with the update scope', function () {
    $project = Project::forceCreate(['name' => 'Website launch']);
    $member = User::factory()->create();
    $project->addMember($member->id, ['update']);

    $this->actingAs($member)->patchJson("/projects/{$project->id}", ['name' => 'Spring launch'])
        ->assertSuccessful();

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Spring launch']);
});

test('deletes a project with the delete scope', function () {
    $project = Project::forceCreate(['name' => 'Website launch']);
    $member = User::factory()->create();
    $project->addMember($member->id, ['delete']);

    $this->actingAs($member)->deleteJson("/projects/{$project->id}")
        ->assertSuccessful();

    $this->assertModelMissing($project);
});

test('a member with the update scope can add another user and repeat the request', function () {
    $project = Project::forceCreate(['name' => 'Website launch']);
    $member = User::factory()->create();
    $newMember = User::factory()->create();
    $project->addMember($member->id, ['update']);

    $url = "/projects/{$project->id}/members/{$newMember->id}";
    $this->actingAs($member)->putJson($url)->assertNoContent();
    $this->putJson($url)->assertNoContent();

    $this->assertDatabaseHas('project_user', ['project_id' => $project->id, 'user_id' => $member->id]);
    $this->assertDatabaseHas('project_user', ['project_id' => $project->id, 'user_id' => $newMember->id]);
    $this->assertDatabaseCount('project_user', 2);
});

test('returns 404 when a non-member tries to add themselves', function () {
    $project = Project::forceCreate(['name' => 'Private project']);
    $outsider = User::factory()->create();

    $this->actingAs($outsider)->putJson("/projects/{$project->id}/members/{$outsider->id}")->assertNotFound();

    $this->assertDatabaseMissing('project_user', ['project_id' => $project->id, 'user_id' => $outsider->id]);
});

test('returns 404 when the requested member does not exist', function () {
    $project = Project::forceCreate(['name' => 'Website launch']);
    $member = User::factory()->create();
    $project->addMember($member->id, ['update']);
    $missingId = $member->id + 1;

    $this->actingAs($member)->putJson("/projects/{$project->id}/members/{$missingId}")->assertNotFound();

    $this->assertDatabaseMissing('project_user', ['project_id' => $project->id, 'user_id' => $missingId]);
});

test('explains a 403 when the required scope exists only on another project', function (string $method, string $scope, string $otherScope, string $action) {
    $member = User::factory()->create();
    $project = Project::forceCreate(['name' => 'Shared']);
    $otherProject = Project::forceCreate(['name' => 'Other']);
    $project->addMember($member->id, [$otherScope]);
    $otherProject->addMember($member->id, [$scope]);

    $this->actingAs($member)->json($method, "/projects/{$project->id}", ['name' => 'Changed'])
        ->assertForbidden()
        ->assertJsonPath('message', "You need the '{$scope}' scope to {$action}.");

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Shared']);
})->with([
    'rename requires update' => ['PATCH', 'update', 'delete', 'rename this project'],
    'delete requires delete' => ['DELETE', 'delete', 'update', 'delete this project'],
]);

test('returns 404 when a non-member holds the required scope', function () {
    $outsider = User::factory()->create();
    $project = Project::forceCreate(['name' => 'Private']);
    app(PermissionsService::class)->addScopes(['user_id' => $outsider->id, 'project_id' => $project->id], ['update']);

    $this->actingAs($outsider)->patchJson("/projects/{$project->id}", ['name' => 'Changed'])->assertNotFound();

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Private']);
});
