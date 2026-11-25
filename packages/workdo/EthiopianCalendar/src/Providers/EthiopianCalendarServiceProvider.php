<?php

namespace Workdo\EthiopianCalendar\Providers;

use Illuminate\Support\ServiceProvider;
use Workdo\EthiopianCalendar\Services\EthiopianDateService;

class EthiopianCalendarServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $routesPath = __DIR__ . '/../Routes/web.php';
        if (file_exists($routesPath)) {
            $this->loadRoutesFrom($routesPath);
        }

        $langPath = __DIR__ . '/../Resources/lang';
        if (is_dir($langPath)) {
            $this->loadJsonTranslationsFrom($langPath);
        }
    }

    public function register(): void
    {
        $this->app->singleton(EthiopianDateService::class, function () {
            return new EthiopianDateService();
        });

        $helperPath = __DIR__ . '/../Helpers/ethiopian_calendar_helpers.php';
        if (file_exists($helperPath)) {
            require_once $helperPath;
        }
    }
}
