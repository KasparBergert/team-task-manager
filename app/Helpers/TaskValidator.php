<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Validator;

class TaskValidator
{
    public static function fieldRules(bool $creating): array
    {
        $rules = [
            'title' => [$creating ? 'required' : 'sometimes', 'filled', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'in:not_started,in_progress,completed'],
            'dueDate' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ];

        if (! $creating) {
            $rules['assigneeId'] = ['sometimes', 'required', 'integer', 'min:1'];
        }

        return $rules;
    }

    public static function id(string $value): int
    {
        Validator::make(['taskId' => $value], ['taskId' => ['required', 'integer', 'min:1']])->validate();

        return (int) $value;
    }
}
