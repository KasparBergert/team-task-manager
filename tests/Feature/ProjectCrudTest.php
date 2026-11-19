<?php

use App\Models\Project;
use App\Models\User;
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

test('renames a project', function () {
    $project = Project::forceCreate(['name' => 'Website launch']);
    $member = User::factory()->create();
    $project->members()->attach($member);

    $this->actingAs($member)->patchJson("/projects/{$project->id}", ['name' => 'Spring launch'])
        ->assertSuccessful();

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Spring launch']);
});

test('deletes a project', function () {
    $project = Project::forceCreate(['name' => 'Website launch']);
    $member = User::factory()->create();
    $project->members()->attach($member);

    $this->actingAs($member)->deleteJson("/projects/{$project->id}")
        ->assertSuccessful();

    $this->assertModelMissing($project);
});

test('a member can add another user to a project and repeat the request', function () {
    $project = Project::forceCreate(['name' => 'Website launch']);
    $member = User::factory()->create();
    $newMember = User::factory()->create();
    $project->members()->attach($member);

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
    $project->members()->attach($member);
    $missingId = $member->id + 1;

    $this->actingAs($member)->putJson("/projects/{$project->id}/members/{$missingId}")->assertNotFound();

    $this->assertDatabaseMissing('project_user', ['project_id' => $project->id, 'user_id' => $missingId]);
});
