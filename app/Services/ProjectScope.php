<?php

namespace App\Services;

enum ProjectScope: string
{
    case Create = 'create';
    case Read = 'read';
    case Update = 'update';
    case Delete = 'delete';    
}
