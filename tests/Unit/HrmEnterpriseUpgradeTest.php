<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Workdo\Hrm\Traits\HrmControllerHelpers;
use Workdo\Hrm\Traits\HrmDateHelpers;

class HrmEnterpriseUpgradeTest extends TestCase
{
    public function test_all_hrm_eloquent_models_use_tenant_scoped_trait(): void
    {
        $modelFiles = glob(base_path('packages/workdo/Hrm/src/Models/*.php'));
        $checked = 0;

        foreach ($modelFiles as $file) {
            $className = 'Workdo\\Hrm\\Models\\' . basename($file, '.php');
            if (basename($file, '.php') === 'HrmModel') {
                continue; // Static permission seeder helper, not a DB table
            }

            $traits = class_uses_recursive($className);
            $this->assertArrayHasKey(
                \App\Traits\TenantScoped::class,
                $traits,
                "{$className} must use App\\Traits\\TenantScoped trait"
            );
            $checked++;
        }

        $this->assertGreaterThanOrEqual(42, $checked);
    }

    public function test_hrm_phone_validation_supports_ethiopian_and_international_formats(): void
    {
        $rule = HrmControllerHelpers::phoneRule(true);

        $this->assertTrue(Validator::make(['phone' => '+251911234567'], ['phone' => $rule])->passes());
        $this->assertTrue(Validator::make(['phone' => '0911234567'], ['phone' => $rule])->passes());
        $this->assertTrue(Validator::make(['phone' => '0711234567'], ['phone' => $rule])->passes());
        $this->assertTrue(Validator::make(['phone' => '+14155552671'], ['phone' => $rule])->passes());
        $this->assertFalse(Validator::make(['phone' => 'invalid-phone'], ['phone' => $rule])->passes());
    }

    public function test_hrm_date_helpers_resolves_ethiopian_fiscal_year(): void
    {
        $helper = new class {
            use HrmDateHelpers;
        };

        $resolved = $helper->resolveHrmDateRange(null, null, '2017');

        $this->assertEquals(2017, $resolved['ethiopian_fiscal_year']);
        $this->assertStringStartsWith('2024-', $resolved['from_date']);
        $this->assertStringStartsWith('2025-', $resolved['to_date']);
    }

    public function test_hrm_form_requests_have_zero_unscoped_exists_rules(): void
    {
        $requestFiles = glob(base_path('packages/workdo/Hrm/src/Http/Requests/*.php'));
        foreach ($requestFiles as $file) {
            $content = file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression(
                '/exists:[a-zA-Z0-9_]+,/',
                $content,
                basename($file) . ' still contains an unscoped exists: rule.'
            );
        }
    }
}
