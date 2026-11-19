<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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

    $this->actingAs($creator)->deleteJson($membership)->assertNoContent();

    $this->actingAs($member)->getJson('/projects')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $personalId);
    $this->getJson("/projects/{$sharedId}/tasks")->assertNotFound();
    $this->getJson("/projects/{$personalId}/tasks")->assertOk();
    $this->actingAs($creator)->getJson("/projects/{$sharedId}/tasks")->assertOk();
});

test('an outsider cannot remove a project member', function () {
    $creator = User::factory()->create();
    $outsider = User::factory()->create();
    $projectId = $this->actingAs($creator)->postJson('/projects', ['name' => 'Private'])->assertCreated()->json('id');

    $this->actingAs($outsider)->deleteJson("/projects/{$projectId}/members/{$creator->id}")->assertNotFound();

    $this->actingAs($creator)->getJson("/projects/{$projectId}/tasks")->assertOk();
});

test('a guest cannot change project membership', function (string $method) {
    $this->json($method, '/projects/1/members/2')->assertUnauthorized();
})->with(['PUT', 'DELETE']);
