<?php

namespace Tests\Unit;

use Tests\TestCase;
use Workdo\Taskly\Models\ActivityLog;
use Workdo\Taskly\Models\BugComment;
use Workdo\Taskly\Models\BugStage;
use Workdo\Taskly\Models\Project;
use Workdo\Taskly\Models\ProjectBoq;
use Workdo\Taskly\Models\ProjectBug;
use Workdo\Taskly\Models\ProjectClient;
use Workdo\Taskly\Models\ProjectFile;
use Workdo\Taskly\Models\ProjectLogistics;
use Workdo\Taskly\Models\ProjectMilestone;
use Workdo\Taskly\Models\ProjectSiteDiary;
use Workdo\Taskly\Models\ProjectTask;
use Workdo\Taskly\Models\ProjectUser;
use Workdo\Taskly\Models\TaskComment;
use Workdo\Taskly\Models\TaskStage;
use Workdo\Taskly\Models\TaskSubtask;
use Workdo\Taskly\Models\TaskTimesheet;
use Workdo\Taskly\Traits\TasklyDateHelpers;

class TasklyEnterpriseUpgradeTest extends TestCase
{
    public function test_all_tenant_owned_taskly_models_use_tenant_scoped_trait(): void
    {
        $models = [
            Project::class,
            ProjectTask::class,
            ProjectBug::class,
            TaskStage::class,
            BugStage::class,
            ProjectBoq::class,
            ProjectLogistics::class,
            ProjectSiteDiary::class,
            TaskTimesheet::class,
            ActivityLog::class,
            BugComment::class,
            ProjectClient::class,
            ProjectFile::class,
            ProjectMilestone::class,
            ProjectUser::class,
            TaskComment::class,
            TaskSubtask::class,
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

    public function test_taskly_form_requests_have_zero_unscoped_exists_rules(): void
    {
        $requestFiles = glob(base_path('packages/workdo/Taskly/src/Http/Requests/*.php'));
        foreach ($requestFiles as $file) {
            $content = file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression(
                '/exists:[a-zA-Z0-9_]+,/',
                $content,
                basename($file) . ' still contains an unscoped exists: rule.'
            );
        }
    }

    public function test_taskly_date_helpers_resolves_ethiopian_fiscal_year(): void
    {
        $helper = new class {
            use TasklyDateHelpers;
        };

        $resolved = $helper->resolveTasklyDateRange(null, null, '2017');

        $this->assertEquals(2017, $resolved['ethiopian_fiscal_year']);
        $this->assertStringStartsWith('2024-', $resolved['from_date']);
        $this->assertStringStartsWith('2025-', $resolved['to_date']);
    }
}
