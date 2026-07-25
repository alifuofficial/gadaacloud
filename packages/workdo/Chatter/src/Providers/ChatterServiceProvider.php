<?php

namespace Workdo\Chatter\Providers;

use Illuminate\Support\ServiceProvider;

class ChatterServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }

    public function register(): void
    {
        //
    }
}
