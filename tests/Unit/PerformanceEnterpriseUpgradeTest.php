<?php

namespace Tests\Unit;

use App\Traits\TenantScoped;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use Tests\TestCase;
use Workdo\Performance\Http\Requests\StoreCompetencyRequest;
use Workdo\Performance\Http\Requests\StoreEmployeeCompetencyRequest;
use Workdo\Performance\Http\Requests\StoreEmployeeFeedbackRequest;
use Workdo\Performance\Http\Requests\StoreEmployeeGoalRequest;
use Workdo\Performance\Http\Requests\StoreEmployeeReviewRequest;
use Workdo\Performance\Http\Requests\StorePerformanceIndicatorRequest;
use Workdo\Performance\Http\Requests\StoreReviewTemplateRequest;
use Workdo\Performance\Models\Competency;
use Workdo\Performance\Models\CompetencyCategory;
use Workdo\Performance\Models\EmployeeCompetency;
use Workdo\Performance\Models\EmployeeFeedback;
use Workdo\Performance\Models\FeedbackType;
use Workdo\Performance\Models\PerformanceEmployeeGoal;
use Workdo\Performance\Models\PerformanceEmployeeReview;
use Workdo\Performance\Models\PerformanceGoalType;
use Workdo\Performance\Models\PerformanceIndicator;
use Workdo\Performance\Models\PerformanceIndicatorCategory;
use Workdo\Performance\Models\PerformanceItem;
use Workdo\Performance\Models\PerformanceReviewCycle;
use Workdo\Performance\Models\PerformanceUtility;
use Workdo\Performance\Models\ReviewTemplate;
use Workdo\Performance\Services\PerformanceErpIntegrationService;
use Workdo\Performance\Support\PerformanceHtmlSanitizer;
use Workdo\Performance\Traits\PerformanceControllerHelpers;

class PerformanceEnterpriseUpgradeTest extends TestCase
{
    public function test_all_performance_domain_models_use_tenant_scoped_trait(): void
    {
        $models = [
            PerformanceIndicatorCategory::class,
            PerformanceIndicator::class,
            PerformanceGoalType::class,
            PerformanceEmployeeGoal::class,
            PerformanceReviewCycle::class,
            PerformanceEmployeeReview::class,
            CompetencyCategory::class,
            Competency::class,
            EmployeeCompetency::class,
            FeedbackType::class,
            EmployeeFeedback::class,
            PerformanceItem::class,
            ReviewTemplate::class,
        ];

        foreach ($models as $modelClass) {
            $traits = class_uses_recursive($modelClass);
            $this->assertArrayHasKey(
                TenantScoped::class,
                $traits,
                "{$modelClass} must use App\\Traits\\TenantScoped for strict multi-tenant isolation."
            );
        }
    }

    public function test_all_performance_routes_enforce_plan_module_check_middleware(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn($route) => str_starts_with((string) $route->getName(), 'performance.'));

        $this->assertGreaterThanOrEqual(40, $routes->count());

        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();
            $this->assertContains(
                'PlanModuleCheck:Performance',
                $middleware,
                "Route [{$route->getName()}] must be protected by PlanModuleCheck:Performance."
            );
        }
    }

    public function test_html_sanitizer_strips_xss_payloads_while_preserving_safe_tags(): void
    {
        $dirty = '<p>Strong <strong>delivery</strong>!</p><script>alert("xss")</script><img src=x onerror="alert(1)"><a href="javascript:alert(2)" onclick="steal()">link</a><a href="https://example.com">docs</a>';
        $clean = PerformanceHtmlSanitizer::sanitize($dirty);

        $this->assertNotNull($clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringContainsString('<p>', $clean);
        $this->assertStringContainsString('<strong>delivery</strong>', $clean);
        $this->assertStringContainsString('https://example.com', $clean);
    }

    public function test_goal_progress_percentage_handles_non_numeric_targets_without_php8_typeerror(): void
    {
        $goal = new PerformanceEmployeeGoal([
            'target' => 'ETB 500,000 revenue',
            'progress' => 65,
        ]);
        $this->assertSame(65.0, $goal->progress_percentage);

        $textOnlyGoal = new PerformanceEmployeeGoal([
            'target' => 'Complete ISO Audit',
            'progress' => 40,
        ]);
        $this->assertSame(40.0, $textOnlyGoal->progress_percentage);

        $quantGoal = new PerformanceEmployeeGoal([
            'target' => '200 hours',
            'target_numeric' => 200,
            'current_value' => 150,
            'progress' => 10,
        ]);
        $this->assertSame(75.0, $quantGoal->progress_percentage);
    }

    public function test_rating_decoder_and_rating_band_resolution_handle_legacy_double_encoded_json(): void
    {
        $single = json_encode(['1' => 4, '2' => 5]);
        $double = json_encode($single);

        $this->assertSame(['1' => 4, '2' => 5], PerformanceControllerHelpers::decodeRatingMap($single));
        $this->assertSame(['1' => 4, '2' => 5], PerformanceControllerHelpers::decodeRatingMap($double));
        $this->assertSame(['1' => 5], PerformanceControllerHelpers::decodeRatingMap(['1' => 5]));

        $this->assertSame('Outstanding', PerformanceEmployeeReview::resolveRatingBand(4.8));
        $this->assertSame('Exceeds Expectations', PerformanceEmployeeReview::resolveRatingBand(4.0));
        $this->assertSame('Meets Expectations', PerformanceEmployeeReview::resolveRatingBand(3.2));
        $this->assertSame('Needs Improvement', PerformanceEmployeeReview::resolveRatingBand(2.0));
        $this->assertSame('Unsatisfactory', PerformanceEmployeeReview::resolveRatingBand(1.2));
    }

    public function test_foreign_key_form_requests_use_tenant_scoped_exists_validation_not_global_strings(): void
    {
        $sourceChecks = [
            StoreEmployeeGoalRequest::class,
            StoreEmployeeReviewRequest::class,
            StorePerformanceIndicatorRequest::class,
            StoreCompetencyRequest::class,
            StoreEmployeeCompetencyRequest::class,
            StoreEmployeeFeedbackRequest::class,
            StoreReviewTemplateRequest::class,
        ];

        foreach ($sourceChecks as $requestClass) {
            $reflection = new ReflectionClass($requestClass);
            $source = file_get_contents($reflection->getFileName());
            $this->assertIsString($source);
            $this->assertStringContainsString(
                'tenantExists(',
                $source,
                "{$requestClass} must scope foreign-key exists rules via tenantExists()."
            );
            $this->assertStringNotContainsString(
                '|exists:',
                $source,
                "{$requestClass} must not contain unscoped string |exists: validation rules."
            );
        }
    }

    public function test_ethiopian_fiscal_period_resolution_returns_hamle_to_sene_bounds(): void
    {
        $service = new PerformanceErpIntegrationService();
        $fy = $service->resolveEthiopianFiscalPeriod(2019, 'annual');

        $this->assertIsArray($fy);
        $this->assertSame('2026-07-08', $fy['start_date']);
        $this->assertSame('2027-07-07', $fy['end_date']);

        $q1 = $service->resolveEthiopianFiscalPeriod(2019, 'q1');
        $this->assertIsArray($q1);
        $this->assertSame('2026-07-08', $q1['start_date']);
    }

    public function test_staff_utility_does_not_grant_global_destructive_company_permissions(): void
    {
        $reflection = new ReflectionClass(PerformanceUtility::class);
        $source = file_get_contents($reflection->getFileName());

        $this->assertIsString($source);
        $this->assertStringNotContainsString('delete-performance-indicators', $source);
        $this->assertStringNotContainsString('delete-employee-reviews', $source);
        $this->assertStringNotContainsString('manage-any-goal-types', $source);
        $this->assertStringNotContainsString('manage-any-performance-indicator-categories', $source);
    }
}
