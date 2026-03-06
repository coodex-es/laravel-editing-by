<?php

namespace CoodexEs\LaravelEditingBy;

use CoodexEs\LaravelEditingBy\Commands\PruneExpiredEditingsCommand;
use CoodexEs\LaravelEditingBy\Commands\RecreateEditingsTableCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class LaravelEditingByServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/editing-by.php', 'editing-by');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/editing-by.php' => config_path('editing-by.php'),
        ], 'editing-by-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'editing-by-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                PruneExpiredEditingsCommand::class,
                RecreateEditingsTableCommand::class,
            ]);
        }

        $this->app->booted(function (): void {
            if (! config('editing-by.prune_expired_schedule', true)) {
                return;
            }

            $schedule = $this->app->make(Schedule::class);
            $schedule->command('editing-by:prune-expired')->everyMinute()->withoutOverlapping();
        });
    }
}
