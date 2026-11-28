<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    if (DB::connection()->getDriverTitle() !== 'MariaDB') {
        $this->markTestSkipped('System version history requires a MariaDB test database.');
    }

    $this->artisan('migrate:fresh')->assertSuccessful();
});

test('preserves the original and updated values after deletion', function (
    Closure $createRecord, string $field, string $original, string $updated,
) {
    $record = $createRecord($original);
    $id = $record->getKey();
    $table = $record->getTable();
    $grammar = DB::connection()->getQueryGrammar();
    $historyTable = $grammar->wrapTable($table);
    $column = $grammar->wrap($field);
    DB::statement("SET @before_update = (SELECT ROW_START FROM {$historyTable} WHERE id = ?)", [$id]);

    $record->forceFill([$field => $updated])->save();
    $this->assertDatabaseHas($table, ['id' => $id, $field => $updated]);

    $record->delete();
    $this->assertDatabaseMissing($table, ['id' => $id]);

    $history = DB::select(
        "SELECT {$column} AS value FROM {$historyTable} FOR SYSTEM_TIME ALL WHERE id = ? ORDER BY ROW_START",
        [$id],
    );
    expect(array_column($history, 'value'))->toBe([$original, $updated]);

    $earlierValue = DB::scalar(
        "SELECT {$column} FROM {$historyTable} FOR SYSTEM_TIME AS OF TIMESTAMP @before_update WHERE id = ?",
        [$id],
    );
    expect($earlierValue)->toBe($original);
})->with([
    'users' => [
        fn (string $name) => User::factory()->create(['name' => $name]),
        'name', 'Alex Reed', 'Alex Morgan',
    ],
    'projects' => [
        fn (string $name) => Project::forceCreate(['name' => $name]),
        'name', 'Website redesign', 'Website launch',
    ],
    'tasks' => [
        fn (string $status) => Task::forceCreate([
            'project_id' => Project::forceCreate(['name' => 'Website launch'])->id,
            'title' => 'Publish the homepage',
            'status' => $status,
        ]),
        'status', 'not_started', 'completed',
    ],
]);
