<?php

namespace App\Providers;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Observers\ProjectObserver;
use App\Observers\TaskObserver;
use App\Observers\UserObserver;
use App\Services\PermissionsService;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class PermissionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PermissionsService::class, fn () => new PermissionsService(config('permissions.program_identifier')));
    }

    public function boot(): void
    {
        User::observe(UserObserver::class);
        Project::observe(ProjectObserver::class);
        Task::observe(TaskObserver::class);

        Event::listen(MigrationsEnded::class, function (MigrationsEnded $event): void {
            if ($event->method === 'up' && ! ($event->options['pretend'] ?? false)) {
                $this->registerScopes();
            }
        });

        if (Schema::hasTable('scopes')) {
            $this->registerScopes();
        }
    }

    private function registerScopes(): void
    {
        foreach (config('permissions.scopes') as $scope) {
            DB::table('scopes')->insertOrIgnore([
                'program_identifier' => config('permissions.program_identifier'),
                'name' => $scope,
            ]);
        }
    }
}
