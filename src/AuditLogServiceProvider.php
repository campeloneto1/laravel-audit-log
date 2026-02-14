<?php

namespace Campelo\AuditLog;

use Campelo\AuditLog\Console\Commands\CleanupAuditLogs;
use Campelo\AuditLog\Listeners\SlowQueryListener;
use Campelo\AuditLog\Middleware\AuditLogMiddleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AuditLogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/audit-log.php', 'audit-log');

        $this->app->singleton(AuditLogService::class, function ($app) {
            return new AuditLogService();
        });
    }

    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/config/audit-log.php' => config_path('audit-log.php'),
        ], 'audit-log-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/database/migrations/' => database_path('migrations'),
        ], 'audit-log-migrations');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        // Load routes
        if (config('audit-log.routes_enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/routes/api.php');
        }

        // Register middleware
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('audit-log', AuditLogMiddleware::class);

        // Auto-register middleware if enabled
        if (config('audit-log.auto_register_middleware', true)) {
            $router->pushMiddlewareToGroup('web', AuditLogMiddleware::class);
            $router->pushMiddlewareToGroup('api', AuditLogMiddleware::class);
        }

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                CleanupAuditLogs::class,
            ]);
        }

        // Schedule automatic cleanup if enabled
        if (config('audit-log.cleanup.enabled', false)) {
            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);
                $time = config('audit-log.cleanup.schedule', '02:00');

                $schedule->command('audit-log:cleanup')
                    ->dailyAt($time)
                    ->withoutOverlapping()
                    ->runInBackground();
            });
        }

        // Register slow query listener if performance logging is enabled
        if (config('audit-log.performance.enabled', false) &&
            config('audit-log.performance.slow_queries.enabled', true)) {
            Event::listen(QueryExecuted::class, SlowQueryListener::class);
        }
    }
}
