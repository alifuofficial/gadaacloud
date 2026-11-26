<?php

namespace Tests\Unit;

use Tests\TestCase;
use Workdo\InventoryManagement\Providers\InventoryManagementServiceProvider;

class InventoryAndImportExportEnterpriseUpgradeTest extends TestCase
{
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
