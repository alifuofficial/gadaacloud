<?php

namespace Workdo\Hrm\Providers;

use Illuminate\Support\ServiceProvider;
use Workdo\Hrm\Console\ProcessDueSeparationsCommand;

class HrmServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $routesPath = __DIR__.'/../Routes/web.php';
        if (file_exists($routesPath)) {
            $this->loadRoutesFrom($routesPath);
        }
        
        $apiRoutesPath = __DIR__.'/../Routes/api.php';
        if (file_exists($apiRoutesPath)) {
            $this->loadRoutesFrom($apiRoutesPath);
        }
        
        $migrationsPath = __DIR__.'/../Database/Migrations';
        if (is_dir($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessDueSeparationsCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->app->register(EventServiceProvider::class);
    }
}