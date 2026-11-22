<?php

use App\Services\ProjectScope;

return [
    'program_identifier' => env('PERMISSIONS_PROGRAM_IDENTIFIER', 'team-task-manager'),
    'scopes' => array_column(ProjectScope::cases(), 'value'),
];
