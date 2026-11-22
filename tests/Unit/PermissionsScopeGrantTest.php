<?php

use App\Services\PermissionsService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

test('adding the same scopes repeatedly creates one grant per scope', function () {
    $service = app(PermissionsService::class);
    $permissionIdentifierKey = ['user_id' => 1, 'project_id' => 42];

    $service->addScopes($permissionIdentifierKey, ['read', 'read', 'update']);
    $service->addScopes(['project_id' => '42', 'user_id' => '1'], ['read', 'update']);

    expect($service->getScopes($permissionIdentifierKey))->toBe(['read', 'update']);
    $this->assertDatabaseCount('scope_grants', 2);
});

test('adding a scope registered only for another program rejects the whole request', function () {
    $service = app(PermissionsService::class);
    $permissionIdentifierKey = ['project_id' => 42];
    $service->addScopes($permissionIdentifierKey, ['create']);
    DB::table('scopes')->insert(['program_identifier' => 'another-program', 'name' => 'publish']);

    expect(fn () => $service->addScopes($permissionIdentifierKey, ['read', 'publish']))
        ->toThrow(InvalidArgumentException::class, 'All scopes must be registered for this program.');

    expect($service->getScopes($permissionIdentifierKey))->toBe(['create']);
    $this->assertDatabaseCount('scope_grants', 1);
});

test('adding an empty scope list creates no grants', function () {
    $service = app(PermissionsService::class);

    $service->addScopes(['project_id' => 42], []);

    $this->assertDatabaseCount('scope_grants', 0);
});

test('revocation removes only the named grants for the exact identifier in its own program', function () {
    $service = app(PermissionsService::class);
    $other = new PermissionsService('another-program');
    DB::table('scopes')->insert(['program_identifier' => 'another-program', 'name' => 'read']);
    $permissionIdentifierKey = ['user_id' => 1, 'project_id' => 42];
    $moreSpecificKey = $permissionIdentifierKey + ['task_id' => 3];
    $service->addScopes($permissionIdentifierKey, ['create', 'read', 'update']);
    $service->addScopes($moreSpecificKey, ['read']);
    $other->addScopes($permissionIdentifierKey, ['read']);

    $service->removeScopes(['project_id' => '42', 'user_id' => '1'], ['read', 'update']);
    $service->removeScopes($permissionIdentifierKey, ['read', 'update']);

    expect($service->getScopes($permissionIdentifierKey))->toBe(['create']);
    expect($service->getScopes($moreSpecificKey))->toBe(['read']);
    expect($other->getScopes($permissionIdentifierKey))->toBe(['read']);
    $this->assertDatabaseCount('scope_grants', 3);
});

test('revocation leaves grants alone when no requested scope is granted', function (array $scopeNames) {
    $service = app(PermissionsService::class);
    $permissionIdentifierKey = ['project_id' => 42];
    $service->addScopes($permissionIdentifierKey, ['read']);

    $service->removeScopes($permissionIdentifierKey, $scopeNames);

    expect($service->getScopes($permissionIdentifierKey))->toBe(['read']);
    $this->assertDatabaseCount('scope_grants', 1);
})->with([
    'empty list' => [[]],
    'unknown scope' => [['publish']],
    'registered but ungranted scope' => [['delete']],
]);
