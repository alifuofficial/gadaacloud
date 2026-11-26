<?php

namespace Tests\Unit;

use App\Traits\TenantScoped;
use Tests\TestCase;
use Workdo\ERPMigrate\Http\Controllers\MigrationController;
use Workdo\ERPMigrate\Models\MigrationBatch;
use Workdo\ERPMigrate\Models\MigrationConnection;
use Workdo\ERPMigrate\Models\MigrationFieldMapping;
use Workdo\ERPMigrate\Models\MigrationLog;
use Workdo\ERPMigrate\Traits\ERPMigrateControllerHelpers;
use Workdo\ERPMigrate\Traits\ERPMigrateDateHelpers;

class ERPMigrateEnterpriseUpgradeTest extends TestCase
{
    public function test_erp_migrate_models_use_tenant_scoped_trait(): void
    {
        foreach ([
            MigrationConnection::class,
            MigrationBatch::class,
            MigrationFieldMapping::class,
            MigrationLog::class,
        ] as $modelClass) {
            $traits = class_uses_recursive($modelClass);
            $this->assertArrayHasKey(TenantScoped::class, $traits, "{$modelClass} must use TenantScoped");
        }
    }

    public function test_migration_controller_wires_enterprise_helpers_and_ssrf_guard(): void
    {
        $traits = class_uses_recursive(MigrationController::class);
        $this->assertArrayHasKey(ERPMigrateControllerHelpers::class, $traits);
        $this->assertArrayHasKey(ERPMigrateDateHelpers::class, $traits);

        $this->assertFalse(ERPMigrateControllerHelpers::isValidConnectorHost('http://127.0.0.1:8069'));
        $this->assertFalse(ERPMigrateControllerHelpers::isValidConnectorHost('http://localhost/api'));
        $this->assertFalse(ERPMigrateControllerHelpers::isValidConnectorHost('http://169.254.169.254/latest/meta-data/'));
        $this->assertFalse(ERPMigrateControllerHelpers::isValidConnectorHost('http://10.0.0.5:8069'));
        $this->assertFalse(ERPMigrateControllerHelpers::isValidConnectorHost('http://192.168.1.10'));
        $this->assertFalse(ERPMigrateControllerHelpers::isValidConnectorHost('ftp://erp.example.com'));

        $this->assertTrue(ERPMigrateControllerHelpers::isValidConnectorHost('https://erp.company.et'));
        $this->assertTrue(ERPMigrateControllerHelpers::isValidConnectorHost('https://odoo.gadaacloud.com:8069'));
    }

    public function test_erp_migrate_helpers_bound_pagination_and_resolve_kenat(): void
    {
        $helper = new class {
            use ERPMigrateControllerHelpers, ERPMigrateDateHelpers;
        };

        request()->merge(['per_page' => 9999]);
        $this->assertEquals(100, $helper->safePerPage(10, 100));

        request()->merge(['per_page' => 0]);
        $this->assertEquals(10, $helper->safePerPage(10, 100));

        $query = MigrationBatch::query();
        $helper->applySafeSort($query, ['batch_code', 'status', 'created_at'], 'invalid; DROP TABLE erp_migration_batches;', 'DESC');
        $orders = $query->getQuery()->orders;
        $this->assertNotEmpty($orders);
        $this->assertEquals('id', $orders[0]['column']);

        $fy = $helper->resolveERPMigrateDateRange('2017');
        $this->assertStringStartsWith('2024-', $fy['start_date']);
        $this->assertStringStartsWith('2025-', $fy['end_date']);

        $dual = $helper->formatDualERPMigrateDate('2025-01-07');
        $this->assertStringContainsString('2025-01-07 / ', $dual);
    }
}
