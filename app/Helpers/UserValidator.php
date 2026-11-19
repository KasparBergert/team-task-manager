<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Validator;

class UserValidator
{
    public static function id(string $value): int
    {
        Validator::make(['userId' => $value], ['userId' => ['required', 'integer', 'min:1']])->validate();

        return (int) $value;
    }
}
