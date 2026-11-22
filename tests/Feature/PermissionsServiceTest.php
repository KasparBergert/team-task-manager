<?php

use App\Models\User;
use App\Services\PermissionsService;
use App\Services\ProjectScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('startup registers the configured scopes after migration', function () {
    expect(DB::table('scopes')->where('program_identifier', config('permissions.program_identifier'))->pluck('name')->all())
        ->toContain('create', 'read', 'update', 'delete', 'add_role');
});

test('grants are isolated by user, project, and program and can be revoked', function () {
    $user = User::factory()->create();
    $service = app(PermissionsService::class);
    $object = ['user_id' => (string) $user->id, 'project_id' => '11'];

    $service->addScopes($object, [ProjectScope::Read->value, ProjectScope::Update->value]);
    $service->addScopes(array_reverse($object, true), [ProjectScope::Read->value]);

    expect($user->permissions(11))->toBe(['read', 'update'])
        ->and($user->permissions(12))->toBe([])
        ->and($service->getScopes(['user_id' => (string) ($user->id + 1), 'project_id' => '11']))->toBe([])
        ->and((new PermissionsService('another-program'))->getScopes($object))->toBe([]);

    $service->removeScopes($object, ['update']);
    expect($user->permissions(11))->toBe(['read']);
});

test('an unknown scope rejects the whole grant', function () {
    $service = app(PermissionsService::class);
    $object = ['user_id' => '1', 'project_id' => '2'];

    try {
        $service->addScopes($object, ['read', 'unknown']);
        $this->fail('Expected an invalid scope to be rejected.');
    } catch (InvalidArgumentException $exception) {
        expect($service->getScopes($object))->toBe([]);
    }
});

test('identifier sorts named fields and normalizes scalar values to strings', function () {
    $service = app(PermissionsService::class);

    // Equivalent values use the same identifier even when their PHP types differ.
    expect($service->createIdentifier(['b' => true, 'a' => 1]))
        ->toBe($service->createIdentifier(['a' => '1', 'b' => '1']))
        ->and($service->createIdentifier(['value' => 1.5]))
        ->toBe($service->createIdentifier(['value' => '1.5']))
        ->and($service->createIdentifier(['value' => false]))
        ->toBe($service->createIdentifier(['value' => '']));
});

test('identifier rejects non-scalar values', function (mixed $value) {
    app(PermissionsService::class)->createIdentifier(['value' => $value]);
})->with([[null], [[]], [new stdClass]])->throws(InvalidArgumentException::class);

test('identifier rejects numeric keys', function (array $object) {
    app(PermissionsService::class)->createIdentifier($object);
})->with([[['value']], [[3 => 'value']], [['name' => 'value', 2 => 'other']]])->throws(InvalidArgumentException::class);
