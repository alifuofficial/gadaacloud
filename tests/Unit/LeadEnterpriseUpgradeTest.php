<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Workdo\Lead\Models\Deal;
use Workdo\Lead\Models\DealDiscussion;
use Workdo\Lead\Models\DealStage;
use Workdo\Lead\Models\DealTask;
use Workdo\Lead\Models\Label;
use Workdo\Lead\Models\Lead;
use Workdo\Lead\Models\LeadDiscussion;
use Workdo\Lead\Models\LeadStage;
use Workdo\Lead\Models\LeadTask;
use Workdo\Lead\Models\Pipeline;
use Workdo\Lead\Models\Source;
use Workdo\Lead\Traits\LeadControllerHelpers;
use Workdo\Lead\Traits\LeadDateHelpers;

class LeadEnterpriseUpgradeTest extends TestCase
{
    public function test_all_lead_tenant_models_use_tenant_scoped_trait(): void
    {
        $models = [
            Lead::class,
            Deal::class,
            Pipeline::class,
            LeadStage::class,
            DealStage::class,
            Label::class,
            Source::class,
            LeadTask::class,
            DealTask::class,
            LeadDiscussion::class,
            DealDiscussion::class,
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

    public function test_lead_phone_validation_supports_ethiopian_and_international_formats(): void
    {
        $rule = LeadControllerHelpers::phoneRule();

        $this->assertTrue(Validator::make(['phone' => '+251911234567'], ['phone' => $rule])->passes());
        $this->assertTrue(Validator::make(['phone' => '0911234567'], ['phone' => $rule])->passes());
        $this->assertTrue(Validator::make(['phone' => '0711234567'], ['phone' => $rule])->passes());
        $this->assertTrue(Validator::make(['phone' => '+14155552671'], ['phone' => $rule])->passes());
        $this->assertFalse(Validator::make(['phone' => 'invalid-phone'], ['phone' => $rule])->passes());
    }

    public function test_lead_date_helpers_resolves_ethiopian_fiscal_year(): void
    {
        $helper = new class {
            use LeadDateHelpers;
        };

        $resolved = $helper->resolveLeadDateRange(null, null, '2017');

        $this->assertEquals(2017, $resolved['ethiopian_fiscal_year']);
        $this->assertStringStartsWith('2024-', $resolved['from_date']);
        $this->assertStringStartsWith('2025-', $resolved['to_date']);
    }

    public function test_lead_form_requests_have_zero_unscoped_exists_rules(): void
    {
        $requestFiles = glob(base_path('packages/workdo/Lead/src/Http/Requests/*.php'));
        $this->assertNotEmpty($requestFiles);

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
