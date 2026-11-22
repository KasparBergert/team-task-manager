<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\ProjectService;
use App\Services\TaskService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

function fillTaskForTest(Task $task, Project $project, array $attributes): void
{
    $service = new TaskService(app(ProjectService::class));
    (new ReflectionMethod(TaskService::class, 'fillTask'))->invoke($service, $task, $project, $attributes);
}

test('fillTask changes only the supplied editable fields', function (array $input, array $changes) {
    $original = [
        'title' => 'Draft',
        'description' => 'Original description',
        'status' => 'not_started',
        'due_date' => '2026-11-30',
        'project_id' => 8,
        'assigned_to' => 12,
    ];
    $task = (new Task)->setRawAttributes($original);

    fillTaskForTest($task, new Project, $input);

    expect($task->getAttributes())->toBe(array_replace($original, $changes));
})->with([
    'empty patch' => [[], []],
    'title only' => [['title' => 'Reviewed'], ['title' => 'Reviewed']],
    'description and status' => [
        ['description' => 'Ready for review', 'status' => 'in_progress'],
        ['description' => 'Ready for review', 'status' => 'in_progress'],
    ],
    'new due date' => [['dueDate' => '2026-12-01'], ['due_date' => '2026-12-01']],
    'clear description' => [['description' => null], ['description' => null]],
    'clear due date' => [['dueDate' => null], ['due_date' => null]],
    'raw foreign keys' => [['project_id' => 99, 'assigned_to' => 99], []],
]);

test('fillTask can assign the same user to tasks in both of their projects', function () {
    $first = Project::forceCreate(['name' => 'First']);
    $second = Project::forceCreate(['name' => 'Second']);
    $member = User::factory()->create();
    $member->projects()->attach([$first->id, $second->id]);

    foreach ([$first, $second] as $project) {
        $task = $project->tasks()->make();
        fillTaskForTest($task, $project, ['assigneeId' => $member->id]);

        expect($task->assigned_to)->toBe($member->id);
    }
});

test('fillTask rejects an assignee who is missing from the selected project', function () {
    $project = Project::forceCreate(['name' => 'Selected']);
    $other = Project::forceCreate(['name' => 'Other']);
    $user = User::factory()->create();
    $other->members()->attach($user);
    $task = new Task;

    expect(fn () => fillTaskForTest($task, $project, ['assigneeId' => $user->id]))
        ->toThrow(function (ValidationException $exception) {
            expect($exception->errors())->toHaveKey('assigneeId');
        });
    expect($task->assigned_to)->toBeNull();
});
