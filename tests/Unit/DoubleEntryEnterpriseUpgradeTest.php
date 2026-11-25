<?php

namespace Tests\Unit;

use Tests\TestCase;
use Workdo\DoubleEntry\Models\BalanceSheet;
use Workdo\DoubleEntry\Models\BalanceSheetItem;
use Workdo\DoubleEntry\Models\BalanceSheetNote;
use Workdo\DoubleEntry\Models\ComparativeBalanceSheet;
use Workdo\DoubleEntry\Services\BalanceSheetService;
use Workdo\DoubleEntry\Traits\DoubleEntryControllerHelpers;
use Workdo\DoubleEntry\Traits\DoubleEntryDateHelpers;
use Workdo\DoubleEntry\Http\Requests\StoreBalanceSheetRequest;
use Workdo\DoubleEntry\Http\Requests\YearEndCloseRequest;

class DoubleEntryEnterpriseUpgradeTest extends TestCase
{
    public function test_all_double_entry_models_use_tenant_scoped_trait(): void
    {
        $models = [
            BalanceSheet::class,
            BalanceSheetItem::class,
            BalanceSheetNote::class,
            ComparativeBalanceSheet::class,
        ];

        foreach ($models as $modelClass) {
            $traits = class_uses_recursive($modelClass);
            $this->assertArrayHasKey(
                \App\Traits\TenantScoped::class,
                $traits,
                "{$modelClass} must use App\\Traits\\TenantScoped trait"
            );
        }
    }

    public function test_double_entry_date_helpers_resolves_gregorian_defaults(): void
    {
        $helper = new class {
            use DoubleEntryDateHelpers;
        };

        $resolved = $helper->resolveDateRange();

        $currentYear = date('Y');
        $this->assertEquals("$currentYear-01-01", $resolved['from_date']);
        $this->assertEquals("$currentYear-12-31", $resolved['to_date']);
        $this->assertNotEmpty($resolved['dual_from']);
        $this->assertNotEmpty($resolved['dual_to']);
    }

    public function test_double_entry_date_helpers_resolves_ethiopian_fiscal_year(): void
    {
        $helper = new class {
            use DoubleEntryDateHelpers;
        };

        $resolved = $helper->resolveDateRange(null, null, '2017');

        $this->assertEquals(2017, $resolved['ethiopian_fiscal_year']);
        $this->assertNotNull($resolved['from_date']);
        $this->assertNotNull($resolved['to_date']);
        // Hamle 1 of 2016 E.C. is in July 2024
        $this->assertStringStartsWith('2024-', $resolved['from_date']);
        // Sene 30 of 2017 E.C. is in July 2025
        $this->assertStringStartsWith('2025-', $resolved['to_date']);
    }

    public function test_double_entry_date_helpers_resolves_ethiopian_quarter(): void
    {
        $helper = new class {
            use DoubleEntryDateHelpers;
        };

        $resolvedQ1 = $helper->resolveDateRange(null, null, '2017', 'q1');

        $this->assertEquals('q1', $resolvedQ1['ethiopian_quarter']);
        $this->assertNotNull($resolvedQ1['from_date']);
        $this->assertNotNull($resolvedQ1['to_date']);
    }

    public function test_balance_sheet_service_get_account_section_numeric(): void
    {
        $service = new BalanceSheetService();

        // Current Assets: 1000-1399
        $sec1 = $service->getAccountSection('1010');
        $this->assertEquals('assets', $sec1['section_type']);
        $this->assertEquals('current_assets', $sec1['sub_section']);

        // Fixed Assets: 1600-1999
        $sec2 = $service->getAccountSection('1650');
        $this->assertEquals('assets', $sec2['section_type']);
        $this->assertEquals('fixed_assets', $sec2['sub_section']);

        // Current Liabilities: 2000-2499
        $sec3 = $service->getAccountSection('2100');
        $this->assertEquals('liabilities', $sec3['section_type']);
        $this->assertEquals('current_liabilities', $sec3['sub_section']);

        // Long Term Liabilities: 2500-2999
        $sec4 = $service->getAccountSection('2600');
        $this->assertEquals('liabilities', $sec4['section_type']);
        $this->assertEquals('long_term_liabilities', $sec4['sub_section']);

        // Equity: 3000-3999
        $sec5 = $service->getAccountSection('3100');
        $this->assertEquals('equity', $sec5['section_type']);
        $this->assertEquals('equity', $sec5['sub_section']);

        // Revenue: 4000-4999 (other / excluded from balance sheet)
        $sec6 = $service->getAccountSection('4100');
        $this->assertEquals('other', $sec6['section_type']);

        // Expense: 5000-5999 (other / excluded from balance sheet)
        $sec7 = $service->getAccountSection('5200');
        $this->assertEquals('other', $sec7['section_type']);
    }

    public function test_balance_sheet_service_get_account_section_with_dynamic_category(): void
    {
        $service = new BalanceSheetService();

        $accountMock = (object) [
            'category_type' => 'assets',
            'type_code' => 'CA',
        ];
        $sec = $service->getAccountSection('9999', $accountMock);
        $this->assertEquals('assets', $sec['section_type']);
        $this->assertEquals('current_assets', $sec['sub_section']);

        $equityMock = (object) [
            'category_type' => 'equity',
            'type_code' => 'EQT',
        ];
        $secEq = $service->getAccountSection('8888', $equityMock);
        $this->assertEquals('equity', $secEq['section_type']);
    }

    public function test_form_requests_have_flexible_financial_year_length(): void
    {
        $storeReq = new StoreBalanceSheetRequest();
        $storeRules = $storeReq->rules();
        $this->assertStringContainsString('max:30', $storeRules['financial_year']);

        $closeReq = new YearEndCloseRequest();
        $closeRules = $closeReq->rules();
        $this->assertStringContainsString('max:30', $closeRules['financial_year']);
    }
}
