<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Validator;

class ProjectValidator
{
    public static function id(string $value): int
    {
        Validator::make(['projectId' => $value], ['projectId' => ['required', 'integer', 'min:1']])->validate();

        return (int) $value;
    }
}
