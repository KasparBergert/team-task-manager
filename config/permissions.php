<?php

namespace App\Services;

if (! enum_exists(ProjectScope::class, false)) {
    enum ProjectScope: string
    {
        case Create = 'create';
        case Read = 'read';
        case Update = 'update';
        case Delete = 'delete';
    }
}


return [
    'program_identifier' => env('PERMISSIONS_PROGRAM_IDENTIFIER', 'team-task-manager'),
    'scopes' => array_column(ProjectScope::cases(), 'value'),
];
