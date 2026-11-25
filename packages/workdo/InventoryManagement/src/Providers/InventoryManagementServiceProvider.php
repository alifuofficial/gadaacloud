<?php

namespace Workdo\InventoryManagement\Providers;

use App\Models\InventoryAdjustment;
use App\Services\InventoryService;
use Illuminate\Support\ServiceProvider;

class InventoryManagementServiceProvider extends ServiceProvider
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

        // Apply stock mutation & Account ledger journal when a pending adjustment is approved
        // (supports GadaacloudStudio multi-step approval workflows seamlessly)
        InventoryAdjustment::updated(function (InventoryAdjustment $adjustment) {
            if (
                $adjustment->wasChanged('status')
                && $adjustment->getOriginal('status') === 'pending'
                && $adjustment->status === 'approved'
            ) {
                InventoryService::applyAdjustment($adjustment);
            }
        });
    }

    public function register(): void
    {
        //
    }
}
