<?php

namespace App\Providers;

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
