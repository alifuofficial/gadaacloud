<?php

namespace Tests\Unit;

use App\Http\Controllers\ImportExportController;
use App\Http\Controllers\InventoryController;
use App\Models\DjiboutiPortContainer;
use App\Models\EccCustomsDuty;
use App\Models\EcxExportContract;
use App\Models\InventoryAdjustment;
use App\Models\InventoryAuditLog;
use App\Models\InventoryBatch;
use App\Models\InventoryReorderRule;
use App\Models\InventorySerial;
use App\Models\NbeForexQueue;
use App\Models\TradeLandedCost;
use App\Models\TradeLc;
use App\Models\TradeShipment;
use App\Traits\ControllerHelpers;
use App\Traits\TenantScoped;
use Tests\TestCase;
use Workdo\InventoryManagement\Providers\InventoryManagementServiceProvider;

class InventoryAndImportExportEnterpriseUpgradeTest extends TestCase
{
    public function test_trade_and_inventory_models_use_tenant_scoped_trait(): void
    {
        foreach ([
            TradeLc::class,
            TradeShipment::class,
            TradeLandedCost::class,
            NbeForexQueue::class,
            DjiboutiPortContainer::class,
            EccCustomsDuty::class,
            EcxExportContract::class,
            InventoryAdjustment::class,
            InventoryAuditLog::class,
            InventoryBatch::class,
            InventoryReorderRule::class,
            InventorySerial::class,
        ] as $modelClass) {
            $traits = class_uses_recursive($modelClass);
            $this->assertArrayHasKey(TenantScoped::class, $traits, "{$modelClass} must use TenantScoped");
        }
    }

    public function test_import_export_and_inventory_controllers_harden_rules_and_transactions(): void
    {
        $ieTraits = class_uses_recursive(ImportExportController::class);
        $this->assertArrayHasKey(ControllerHelpers::class, $ieTraits);

        $ieContent = file_get_contents(app_path('Http/Controllers/ImportExportController.php'));
        $invContent = file_get_contents(app_path('Http/Controllers/InventoryController.php'));

        $this->assertDoesNotMatchRegularExpression("/['\"](?:[^'\"]*\\|)?exists:[^'\"]+['\"]/", $ieContent);
        $this->assertStringContainsString('DB::transaction', $ieContent);
        $this->assertDoesNotMatchRegularExpression("/['\"](?:[^'\"]*\\|)?exists:[^'\"]+['\"]/", $invContent);
        $this->assertStringContainsString('DB::transaction', $invContent);
    }

    public function test_inventory_management_and_import_export_manifests_and_wiring_exist(): void
    {
        $inventoryManifest = json_decode(file_get_contents(base_path('packages/workdo/InventoryManagement/module.json')), true);
        $importExportManifest = json_decode(file_get_contents(base_path('packages/workdo/ImportExport/module.json')), true);

        $this->assertEquals('InventoryManagement', $inventoryManifest['name'] ?? null);
        $this->assertEquals('ImportExport', $importExportManifest['name'] ?? null);

        $this->assertTrue(class_exists(InventoryManagementServiceProvider::class));
        $this->assertFileExists(base_path('packages/workdo/InventoryManagement/src/Routes/web.php'));
        $this->assertFileExists(base_path('packages/workdo/InventoryManagement/src/Database/Migrations/2026_11_25_000001_upgrade_inventory_management_enterprise.php'));
        $this->assertFileExists(base_path('packages/workdo/ImportExport/src/Resources/js/menus/company-menu.ts'));
        $this->assertFileExists(base_path('packages/workdo/InventoryManagement/src/Resources/js/menus/company-menu.ts'));
    }

    public function test_calendar_google_calendar_settings_migration_exists(): void
    {
        $migrationPath = base_path('packages/workdo/Calendar/src/Database/Migrations/2026_11_26_000001_create_google_calendar_settings_table.php');
        $this->assertFileExists($migrationPath);
        $content = file_get_contents($migrationPath);
        $this->assertStringContainsString("Schema::create('google_calendar_settings'", $content);
        $this->assertStringContainsString('created_by', $content);
    }
}
