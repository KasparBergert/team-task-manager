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

test('shows the requested project', function () {
    $project = Project::forceCreate(['name' => 'Website launch']);
    $member = User::factory()->create(['project_id' => $project->id]);

    $this->actingAs($member)->getJson("/projects/{$project->id}")
        ->assertOk()
        ->assertJsonFragment(['id' => $project->id, 'name' => $project->name]);
});

test('renames a project', function () {
    $project = Project::forceCreate(['name' => 'Website launch']);
    $member = User::factory()->create(['project_id' => $project->id]);

    $this->actingAs($member)->patchJson("/projects/{$project->id}", ['name' => 'Spring launch'])
        ->assertSuccessful();

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Spring launch']);
});

test('deletes a project', function () {
    $project = Project::forceCreate(['name' => 'Website launch']);
    $member = User::factory()->create(['project_id' => $project->id]);

    $this->actingAs($member)->deleteJson("/projects/{$project->id}")
        ->assertSuccessful();

    $this->assertModelMissing($project);
});
