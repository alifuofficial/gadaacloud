<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Workdo\Recruitment\Models\Candidate;
use Workdo\Recruitment\Models\CandidateAssessment;
use Workdo\Recruitment\Models\CandidateOnboarding;
use Workdo\Recruitment\Models\CandidateSources;
use Workdo\Recruitment\Models\ChecklistItem;
use Workdo\Recruitment\Models\CustomQuestion;
use Workdo\Recruitment\Models\Interview;
use Workdo\Recruitment\Models\InterviewFeedback;
use Workdo\Recruitment\Models\InterviewRound;
use Workdo\Recruitment\Models\InterviewType;
use Workdo\Recruitment\Models\JobLocation;
use Workdo\Recruitment\Models\JobPosting;
use Workdo\Recruitment\Models\JobType;
use Workdo\Recruitment\Models\Offer;
use Workdo\Recruitment\Models\OfferLetter;
use Workdo\Recruitment\Models\OnboardingChecklist;
use Workdo\Recruitment\Models\RecruitmentSetting;
use Workdo\Recruitment\Traits\RecruitmentControllerHelpers;
use Workdo\Recruitment\Traits\RecruitmentDateHelpers;

class RecruitmentEnterpriseUpgradeTest extends TestCase
{
    public function test_all_recruitment_models_use_tenant_scoped_trait(): void
    {
        $models = [
            JobPosting::class,
            Candidate::class,
            Interview::class,
            InterviewRound::class,
            InterviewType::class,
            InterviewFeedback::class,
            CandidateAssessment::class,
            Offer::class,
            OfferLetter::class,
            CandidateOnboarding::class,
            OnboardingChecklist::class,
            ChecklistItem::class,
            JobType::class,
            JobLocation::class,
            CandidateSources::class,
            CustomQuestion::class,
            RecruitmentSetting::class,
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

    public function test_recruitment_phone_validation_supports_ethiopian_and_international_formats(): void
    {
        $rule = RecruitmentControllerHelpers::phoneRule(true);

        $this->assertTrue(Validator::make(['phone' => '+251911234567'], ['phone' => $rule])->passes());
        $this->assertTrue(Validator::make(['phone' => '0911234567'], ['phone' => $rule])->passes());
        $this->assertTrue(Validator::make(['phone' => '0711234567'], ['phone' => $rule])->passes());
        $this->assertTrue(Validator::make(['phone' => '+14155552671'], ['phone' => $rule])->passes());
        $this->assertFalse(Validator::make(['phone' => 'invalid-phone'], ['phone' => $rule])->passes());
    }

    public function test_recruitment_date_helpers_resolves_ethiopian_fiscal_year(): void
    {
        $helper = new class {
            use RecruitmentDateHelpers;
        };

        $resolved = $helper->resolveRecruitmentDateRange(null, null, '2017');

        $this->assertEquals(2017, $resolved['ethiopian_fiscal_year']);
        $this->assertStringStartsWith('2024-', $resolved['from_date']);
        $this->assertStringStartsWith('2025-', $resolved['to_date']);
    }
}
