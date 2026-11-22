<?php

namespace Workdo\EIMS\Providers;

use Illuminate\Support\ServiceProvider;
use Workdo\EIMS\Console\FlushOfflineQueueCommand;
use Workdo\EIMS\Services\EimsClientService;
use Workdo\EIMS\Services\EimsPayloadBuilderService;
use Workdo\EIMS\Services\EimsOfflineResiliencyService;
use Workdo\EIMS\Services\EimsAuditLoggingService;
use Workdo\EIMS\Services\EimsQrCodeService;

class EimsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $routesPath = __DIR__ . '/../Routes/web.php';
        if (file_exists($routesPath)) {
            $this->loadRoutesFrom($routesPath);
        }

        $migrationsPath = __DIR__ . '/../Database/Migrations';
        if (is_dir($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                FlushOfflineQueueCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->app->singleton(EimsClientService::class, function ($app) {
            return new EimsClientService();
        });

        $this->app->singleton(EimsPayloadBuilderService::class, function ($app) {
            return new EimsPayloadBuilderService();
        });

        $this->app->singleton(EimsOfflineResiliencyService::class, function ($app) {
            return new EimsOfflineResiliencyService(
                $app->make(EimsClientService::class),
                $app->make(EimsAuditLoggingService::class)
            );
        });

        $this->app->singleton(EimsAuditLoggingService::class, function ($app) {
            return new EimsAuditLoggingService();
        });

        $this->app->singleton(EimsQrCodeService::class, function ($app) {
            return new EimsQrCodeService();
        });
    }
}
