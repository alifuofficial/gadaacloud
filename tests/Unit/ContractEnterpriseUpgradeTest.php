<?php

namespace Tests\Unit;

use Tests\TestCase;
use Workdo\Contract\Models\Contract;
use Workdo\Contract\Models\ContractType;
use Workdo\Contract\Models\ContractAttachment;
use Workdo\Contract\Models\ContractComment;
use Workdo\Contract\Models\ContractNote;
use Workdo\Contract\Models\ContractRenewal;
use Workdo\Contract\Models\ContractSignature;
use Workdo\Contract\Traits\ContractControllerHelpers;
use Workdo\Contract\Traits\ContractDateHelpers;

class ContractEnterpriseUpgradeTest extends TestCase
{
    public function test_all_contract_models_use_tenant_scoped_trait(): void
    {
        $models = [
            Contract::class,
            ContractType::class,
            ContractAttachment::class,
            ContractComment::class,
            ContractNote::class,
            ContractRenewal::class,
            ContractSignature::class,
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

    public function test_contract_form_requests_have_zero_unscoped_exists_rules(): void
    {
        $requestFiles = glob(base_path('packages/workdo/Contract/src/Http/Requests/*.php'));
        foreach ($requestFiles as $file) {
            $content = file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression(
                '/exists:[a-zA-Z0-9_]+,/',
                $content,
                basename($file) . ' still contains an unscoped exists: rule.'
            );
        }
    }

    public function test_contract_date_helpers_resolves_ethiopian_fiscal_year(): void
    {
        $helper = new class {
            use ContractDateHelpers;
        };

        $resolved = $helper->resolveContractDateRange(null, null, '2017');

        $this->assertEquals(2017, $resolved['ethiopian_fiscal_year']);
        $this->assertStringStartsWith('2024-', $resolved['from_date']);
        $this->assertStringStartsWith('2025-', $resolved['to_date']);
    }

    public function test_contract_controller_helpers_bounds_per_page(): void
    {
        $helper = new class {
            use ContractControllerHelpers;
        };

        request()->merge(['per_page' => 9999]);
        $this->assertEquals(100, $helper->safePerPage(10, 100));

        request()->merge(['per_page' => -5]);
        $this->assertEquals(10, $helper->safePerPage(10, 100));
    }
}
