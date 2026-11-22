<?php

use App\Services\PermissionsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('cleanup removes all grants matching identifier fields only in its own program', function () {
    $service = app(PermissionsService::class);
    $other = new PermissionsService('another-program');
    DB::table('scopes')->insert(['program_identifier' => 'another-program', 'name' => 'read']);
    $first = ['user_id' => 1, 'project_id' => 42];
    $second = ['user_id' => 2, 'project_id' => 42];
    $unrelated = ['user_id' => 1, 'project_id' => 43];
    $service->addScopes($first, ['read', 'update']);
    $service->addScopes($second, ['read']);
    $service->addScopes($unrelated, ['read']);
    $other->addScopes($first, ['read']);
    $scopeCount = DB::table('scopes')->count();

    $service->deleteScopes(['project_id' => 42]);
    $service->deleteScopes(['project_id' => 42]);

    expect($service->getScopes($first))->toBe([])
        ->and($service->getScopes($second))->toBe([])
        ->and($service->getScopes($unrelated))->toBe(['read'])
        ->and($other->getScopes($first))->toBe(['read']);
    $this->assertDatabaseCount('scopes', $scopeCount);
});

test('cleanup skips invalid identifiers belonging to other programs', function () {
    $service = app(PermissionsService::class);
    $permissionIdentifierKey = ['project_id' => 42];
    $service->addScopes($permissionIdentifierKey, ['read']);
    $otherScopeId = DB::table('scopes')->insertGetId(['program_identifier' => 'another-program', 'name' => 'read']);
    $otherGrant = ['identifier' => 'not-json', 'scope_id' => $otherScopeId];
    DB::table('scope_grants')->insert($otherGrant);

    $service->deleteScopes($permissionIdentifierKey);

    expect($service->getScopes($permissionIdentifierKey))->toBe([]);
    $this->assertDatabaseHas('scope_grants', $otherGrant);
});

test('cleanup requires all provided fields to match', function () {
    $service = app(PermissionsService::class);
    $first = ['user_id' => 1, 'project_id' => 42];
    $second = ['user_id' => 2, 'project_id' => 42];
    $service->addScopes($first, ['read']);
    $service->addScopes($second, ['read']);

    $service->deleteScopes(['user_id' => 1, 'project_id' => 42]);

    expect($service->getScopes($first))->toBe([])
        ->and($service->getScopes($second))->toBe(['read']);
});

test('cleanup matches partial identifiers at the supplied nested paths', function () {
    $service = app(PermissionsService::class);
    $project = ['team' => ['id' => 3, 'region' => 'east'], 'id' => 42, 'name' => 'Example'];
    $permissionIdentifierKey = ['user_id' => 1, 'project' => $project];
    $other = ['user_id' => 1, 'project' => ['id' => 42, 'team' => ['id' => 4]], 'team' => ['id' => 3]];
    $scalarTeam = ['user_id' => 1, 'project' => ['id' => 42, 'team' => 3]];
    $service->addScopes($permissionIdentifierKey, ['read']);
    $service->addScopes($other, ['read']);
    $service->addScopes($scalarTeam, ['read']);
    $projectFilter = ['project' => (object) ['id' => '42', 'team' => ['id' => '3']]];

    $service->deleteScopes($projectFilter);

    expect($service->getScopes($permissionIdentifierKey))->toBe([]);
    expect($service->getScopes($other))->toBe(['read']);
    expect($service->getScopes($scalarTeam))->toBe(['read']);
});

test('an empty nested filter matches only an empty object', function () {
    $service = app(PermissionsService::class);
    $empty = ['project' => []];
    $nonempty = ['project' => ['id' => 42]];
    $service->addScopes($empty, ['read']);
    $service->addScopes($nonempty, ['read']);

    $service->deleteScopes($empty);

    expect($service->getScopes($empty))->toBe([]);
    expect($service->getScopes($nonempty))->toBe(['read']);
});

test('empty cleanup identifiers are rejected without deleting grants', function () {
    $service = app(PermissionsService::class);
    $permissionIdentifierKey = ['project_id' => 42];
    $service->addScopes($permissionIdentifierKey, ['read']);

    expect(fn () => $service->deleteScopes([]))
        ->toThrow(InvalidArgumentException::class, 'At least one identifier field is required.');

    expect($service->getScopes($permissionIdentifierKey))->toBe(['read']);
});

test('identifier fields match exact values rather than substrings', function () {
    $service = app(PermissionsService::class);
    $first = ['project_id' => 4];
    $second = ['project_id' => 42];
    $service->addScopes($first, ['read']);
    $service->addScopes($second, ['read']);

    $service->deleteScopes(['project_id' => 4]);

    expect($service->getScopes($first))->toBe([])
        ->and($service->getScopes($second))->toBe(['read']);
});

test('cleanup deletes matching grants across multiple batches without skipping any', function () {
    $service = app(PermissionsService::class);
    foreach (range(1, 1005) as $userId) {
        $service->addScopes(['project_id' => 42, 'user_id' => $userId], ['read']);
    }
    $retained = ['project_id' => 43, 'user_id' => 1];
    $service->addScopes($retained, ['read']);

    $service->deleteScopes(['project_id' => 42]);

    $this->assertDatabaseCount('scope_grants', 1);
    expect($service->getScopes($retained))->toBe(['read']);
});
