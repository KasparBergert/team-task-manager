<?php

use App\Services\PermissionsService;

test('identifier sorts named fields and normalizes scalar values to strings', function () {
    $service = new PermissionsService('test-program');

    // Equivalent values use the same identifier even when their PHP types differ.
    expect($service->createIdentifier(['b' => true, 'a' => 1]))
        ->toBe($service->createIdentifier(['a' => '1', 'b' => '1']))
        ->and($service->createIdentifier(['value' => 1.5]))
        ->toBe($service->createIdentifier(['value' => '1.5']))
        ->and($service->createIdentifier(['value' => false]))
        ->toBe($service->createIdentifier(['value' => '']));
});

test('identifier rejects values other than scalars and named objects', function (mixed $value) {
    (new PermissionsService('test-program'))->createIdentifier(['value' => $value]);
})->with([[null], [new DateTimeImmutable('2026-01-01')]])->throws(InvalidArgumentException::class);

test('identifier rejects numeric keys', function (array $permissionIdentifierKey) {
    (new PermissionsService('test-program'))->createIdentifier($permissionIdentifierKey);
})->with([[['value']], [[3 => 'value']], [['name' => 'value', 2 => 'other']], [['nested' => ['value']]]])
    ->throws(InvalidArgumentException::class);

test('identifier is canonical JSON containing the program and named fields', function () {
    $service = new PermissionsService('test-program');

    expect(json_decode($service->createIdentifier(['user_id' => 1, 'project_id' => 42]), true))
        ->toBe(['program_identifier' => 'test-program', 'object' => ['project_id' => '42', 'user_id' => '1']]);
});

test('identifier normalizes nested maps regardless of their key order or representation', function () {
    $service = new PermissionsService('test-program');

    $identifier = $service->createIdentifier([
        'member' => (object) ['user_id' => 3, 'project' => ['team_id' => 2, 'id' => 1]],
        'metadata' => [],
    ]);

    expect($identifier)
        ->toBe('{"program_identifier":"test-program","object":{"member":{"project":{"id":"1","team_id":"2"},"user_id":"3"},"metadata":{}}}')
        ->toBe($service->createIdentifier([
            'metadata' => new stdClass,
            'member' => ['project' => (object) ['id' => '1', 'team_id' => '2'], 'user_id' => '3'],
        ]));
});

test('identifier rejects cyclic objects', function () {
    $cyclicObject = new stdClass;
    $cyclicObject->nested = $cyclicObject;

    (new PermissionsService('test-program'))->createIdentifier(['object' => $cyclicObject]);
})->throws(InvalidArgumentException::class, 'Identifier nesting is too deep.');

test('identifier preserves punctuation and Unicode in keys and values', function () {
    $service = new PermissionsService('test-program');
    $fields = ['team "name"' => 'Käru / A'];

    $identifier = $service->createIdentifier($fields);

    expect(json_decode($identifier, true, flags: JSON_THROW_ON_ERROR)['object'])->toBe($fields);
});

test('the identifier size limit applies to the complete encoded value', function () {
    $service = new PermissionsService('test-program');
    $envelopeLength = strlen('{"program_identifier":"test-program","object":{"external_id":""}}');
    $externalId = str_repeat('x', 700 - $envelopeLength);

    expect(strlen($service->createIdentifier(['external_id' => $externalId])))->toBe(700);
    expect(fn () => $service->createIdentifier(['external_id' => $externalId.'x']))
        ->toThrow(InvalidArgumentException::class, 'Identifier exceeds the maximum length of 700 characters.');
});
