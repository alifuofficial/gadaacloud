<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
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
use Workdo\Performance\Models\PerformanceReviewCycle;
use Workdo\Performance\Models\ReviewTemplate;
use Workdo\Performance\Services\PerformanceErpIntegrationService;
use Workdo\Performance\Services\PerformanceScoringService;

class PerformanceIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');

        // Minimal core tables required by Performance & HRM FKs
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('type')->default('staff');
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('branch_name');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('department_name');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->string('designation_name');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        // Run all Performance migrations end-to-end
        $this->artisan('migrate', [
            '--path' => 'packages/workdo/Performance/src/Database/Migrations',
            '--database' => 'sqlite',
        ])->assertSuccessful();

        // Run HRM + Taskly + Timesheet minimal operational tables needed for Phase 3 sync & action tests
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('designation_id')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->string('status')->default('present');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('previous_branch_id')->nullable();
            $table->unsignedBigInteger('previous_department_id')->nullable();
            $table->unsignedBigInteger('previous_designation_id')->nullable();
            $table->unsignedBigInteger('current_branch_id')->nullable();
            $table->unsignedBigInteger('current_department_id')->nullable();
            $table->unsignedBigInteger('current_designation_id')->nullable();
            $table->string('effective_date')->nullable();
            $table->string('reason')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('allowance_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('allowances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('allowance_type_id');
            $table->string('type')->default('fixed');
            $table->decimal('amount', 10, 2)->default(0);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function test_phase_1_to_3_migrations_weighted_scoring_erp_sync_and_hr_actions_work_end_to_end(): void
    {
        $company = User::create([
            'name' => 'Gadaa Corp',
            'email' => 'company@gadaa.test',
            'password' => bcrypt('secret'),
            'type' => 'company',
        ]);
        $manager = User::create([
            'name' => 'Abebe Manager',
            'email' => 'abebe@gadaa.test',
            'password' => bcrypt('secret'),
            'type' => 'staff',
            'created_by' => $company->id,
        ]);
        $emp = User::create([
            'name' => 'Bontu Developer',
            'email' => 'bontu@gadaa.test',
            'password' => bcrypt('secret'),
            'type' => 'staff',
            'created_by' => $company->id,
        ]);
        DB::table('employees')->insert([
            'employee_id' => 'EMP-001',
            'user_id' => $emp->id,
            'created_by' => $company->id,
        ]);

        // Phase 2: Setup Categories, Weighted KPIs, Competencies, Feedback, Template, Cycle
        $cat = PerformanceIndicatorCategory::create(['name' => 'Engineering', 'status' => 'active', 'created_by' => $company->id]);
        $ind1 = PerformanceIndicator::create([
            'category_id' => $cat->id,
            'name' => 'Sprint Delivery',
            'measurement_unit' => '%',
            'target_value' => '95',
            'weight' => 70,
            'status' => 'active',
            'created_by' => $company->id,
        ]);
        $ind2 = PerformanceIndicator::create([
            'category_id' => $cat->id,
            'name' => 'Code Review SLA',
            'measurement_unit' => 'hours',
            'target_value' => '24',
            'weight' => 30,
            'status' => 'active',
            'created_by' => $company->id,
        ]);

        $template = ReviewTemplate::create([
            'name' => 'Engineering Scorecard',
            'kpi_weight' => 60,
            'goal_weight' => 20,
            'competency_weight' => 20,
            'include_self_assessment' => true,
            'indicator_weights' => [$ind1->id => 70, $ind2->id => 30],
            'status' => 'active',
            'created_by' => $company->id,
        ]);
        $template->indicators()->sync([$ind1->id => ['weight' => 70], $ind2->id => ['weight' => 30]]);

        $compCat = CompetencyCategory::create(['name' => 'Leadership', 'status' => 'active', 'created_by' => $company->id]);
        $comp = Competency::create(['category_id' => $compCat->id, 'name' => 'System Thinking', 'weight' => 100, 'status' => 'active', 'created_by' => $company->id]);
        EmployeeCompetency::create([
            'employee_id' => $emp->id,
            'competency_id' => $comp->id,
            'rating' => 4.5,
            'assessment_date' => '2026-08-01',
            'created_by' => $company->id,
        ]);

        $fbType = FeedbackType::create(['name' => 'Peer Praise', 'status' => 'active', 'created_by' => $company->id]);
        EmployeeFeedback::create([
            'employee_id' => $emp->id,
            'given_by' => $manager->id,
            'feedback_type_id' => $fbType->id,
            'relationship_type' => 'peer',
            'date' => '2026-08-10',
            'rating' => 5,
            'content' => 'Delivered EIMS invoice signing ahead of deadline.',
            'visibility' => 'team',
            'is_anonymous' => true,
            'created_by' => $company->id,
        ]);

        $cycle = PerformanceReviewCycle::create([
            'name' => 'FY 2019 Q1 Appraisal',
            'frequency' => 'ethiopian_fiscal',
            'start_date' => '2026-07-08',
            'end_date' => '2026-10-10',
            'calendar_type' => 'ethiopian',
            'ethiopian_fiscal_year' => 2019,
            'ethiopian_quarter' => 'q1',
            'status' => 'active',
            'created_by' => $company->id,
        ]);

        $goalType = PerformanceGoalType::create(['name' => 'Attendance & Reliability', 'status' => 'active', 'created_by' => $company->id]);
        $goal = PerformanceEmployeeGoal::create([
            'employee_id' => $emp->id,
            'goal_type_id' => $goalType->id,
            'review_cycle_id' => $cycle->id,
            'title' => 'Maintain 90%+ Attendance',
            'description' => 'Automated HRM attendance tracking',
            'start_date' => '2026-07-08',
            'end_date' => '2026-10-10',
            'target' => '100%',
            'target_numeric' => 100,
            'progress' => 0,
            'status' => 'not_started',
            'kpi_source' => 'hrm_attendance',
            'auto_sync_enabled' => true,
            'created_by' => $company->id,
        ]);

        DB::table('attendances')->insert([
            ['employee_id' => $emp->id, 'date' => '2026-07-10', 'status' => 'present', 'created_by' => $company->id],
            ['employee_id' => $emp->id, 'date' => '2026-07-11', 'status' => 'present', 'created_by' => $company->id],
            ['employee_id' => $emp->id, 'date' => '2026-07-12', 'status' => 'present', 'created_by' => $company->id],
            ['employee_id' => $emp->id, 'date' => '2026-07-13', 'status' => 'absent', 'created_by' => $company->id],
        ]);

        $erpService = new PerformanceErpIntegrationService();
        $syncResult = $erpService->syncGoalFromErpSource($goal);
        $this->assertTrue($syncResult['synced']);
        $this->assertSame(75.0, $syncResult['progress']); // 3 of 4 days present = 75%

        $review = PerformanceEmployeeReview::create([
            'user_id' => $emp->id,
            'reviewer_id' => $manager->id,
            'review_cycle_id' => $cycle->id,
            'review_template_id' => $template->id,
            'review_date' => '2026-10-05',
            'rating' => [$ind1->id => 5, $ind2->id => 4], // Native array (no double encoding!)
            'status' => 'completed',
            'created_by' => $company->id,
        ]);

        // Phase 2: Weighted composite scoring verification
        $scoring = new PerformanceScoringService();
        $scores = $scoring->computeReviewScores($review, [$ind1->id => 5, $ind2->id => 4], [$comp->id => 4.5]);
        // KPI weighted: (5*70 + 4*30)/100 = 4.70; Goal: 75% -> 4.00/5; Comp: 4.50/5
        // Composite (60/20/20): 4.70*0.6 + 4.00*0.2 + 4.50*0.2 = 2.82 + 0.80 + 0.90 = 4.52
        $this->assertSame(4.52, $scores['weighted_rating']);
        $this->assertSame('Outstanding', $scores['rating_band']);

        // Phase 3: Dispatch HRM Promotion & Bonus Allowance
        $promoRes = $erpService->dispatchHrAction($review, 'promotion', ['reason' => 'Outstanding FY19 Q1 rating']);
        $this->assertTrue($promoRes['ok']);
        $this->assertDatabaseHas('promotions', ['employee_id' => $emp->id, 'status' => 'pending', 'created_by' => $company->id]);

        $allowanceTypeId = DB::table('allowance_types')->insertGetId(['name' => 'Merit Performance Bonus', 'created_by' => $company->id]);
        $bonusRes = $erpService->dispatchHrAction($review, 'bonus_allowance', [
            'allowance_type_id' => $allowanceTypeId,
            'bonus_type' => 'fixed',
            'amount' => 15000,
        ]);
        $this->assertTrue($bonusRes['ok']);
        $this->assertDatabaseHas('allowances', [
            'employee_id' => $emp->id,
            'allowance_type_id' => $allowanceTypeId,
            'amount' => 15000,
            'created_by' => $company->id,
        ]);
    }
}
