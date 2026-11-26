<?php

namespace Workdo\Hrm\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Workdo\Hrm\Models\Employee;
use Workdo\Hrm\Models\Resignation;
use Workdo\Hrm\Models\Termination;

/**
 * Coordinates employee offboarding, notice periods, account deactivation,
 * reversals, and cross-module handover impact checks without hard coupling.
 */
class EmployeeLifecycleService
{
    /**
     * Apply separation lifecycle to an employee & their user account.
     *
     * @param  string  $type  'resigned' | 'terminated'
     */
    public function handleSeparation(int $employeeUserId, int $tenantId, string $type, $effectiveDate = null): array
    {
        $reason = in_array($type, ['resigned', 'terminated'], true) ? $type : 'resigned';
        $effectiveCarbon = $effectiveDate ? Carbon::parse($effectiveDate)->startOfDay() : Carbon::today();
        $isImmediate = $effectiveCarbon->lessThanOrEqualTo(Carbon::today());

        $employeeQuery = Employee::withoutGlobalScope('tenant')
            ->where('user_id', $employeeUserId)
            ->where('created_by', $tenantId);

        $hasEmpStatusCols = Schema::hasColumn('employees', 'employment_status');

        if ($isImmediate) {
            if ($hasEmpStatusCols) {
                $employeeQuery->update([
                    'employment_status' => $reason,
                    'separation_date' => $effectiveCarbon->toDateString(),
                ]);
            }

            $user = User::find($employeeUserId);
            if ($user) {
                $user->is_disable = 1;
                $user->is_enable_login = 0;
                if (Schema::hasColumn('users', 'deactivation_reason')) {
                    $user->deactivation_reason = $reason;
                    $user->deactivated_at = now();
                }
                $user->save();

                $this->revokeUserSessionsAndTokens($user);
            }
        } else {
            // Notice period: keep login active until separation_date passes, while marking HRM status
            if ($hasEmpStatusCols) {
                $employeeQuery->update([
                    'employment_status' => 'notice_period',
                    'separation_date' => $effectiveCarbon->toDateString(),
                ]);
            }

            // If previously separated on an earlier date and extended into future notice period, restore login
            $user = User::find($employeeUserId);
            if ($user && Schema::hasColumn('users', 'deactivation_reason')
                && in_array($user->deactivation_reason, ['resigned', 'terminated'], true)) {
                $seatOk = !function_exists('canCreateUser') || (($check = canCreateUser($tenantId))['can_create'] ?? false);
                if ($seatOk) {
                    $user->is_disable = 0;
                    $user->is_enable_login = 1;
                    $user->deactivation_reason = null;
                    $user->deactivated_at = null;
                    $user->save();
                }
            }
        }

        return [
            'mode' => $isImmediate ? 'deactivated' : 'notice_period',
            'reason' => $reason,
            'separation_date' => $effectiveCarbon->toDateString(),
            'impact' => $this->getOffboardingImpactSummary($employeeUserId, $tenantId),
        ];
    }

    /**
     * Reverse a resignation/termination if no other approved separation remains active.
     */
    public function handleReversal(int $employeeUserId, int $tenantId): array
    {
        // Check whether another active accepted resignation or approved termination still applies
        $otherTermination = Termination::withoutGlobalScope('tenant')
            ->where('created_by', $tenantId)
            ->where('employee_id', $employeeUserId)
            ->where('status', 'approved')
            ->orderByDesc('termination_date')
            ->first();

        if ($otherTermination) {
            return $this->handleSeparation(
                $employeeUserId,
                $tenantId,
                'terminated',
                $otherTermination->termination_date ?? $otherTermination->notice_date
            );
        }

        $otherResignation = Resignation::withoutGlobalScope('tenant')
            ->where('created_by', $tenantId)
            ->where('employee_id', $employeeUserId)
            ->whereIn('status', ['accepted', 'approved'])
            ->orderByDesc('last_working_date')
            ->first();

        if ($otherResignation) {
            return $this->handleSeparation(
                $employeeUserId,
                $tenantId,
                'resigned',
                $otherResignation->last_working_date ?? $otherResignation->resignation_date
            );
        }

        if (Schema::hasColumn('employees', 'employment_status')) {
            Employee::withoutGlobalScope('tenant')
                ->where('user_id', $employeeUserId)
                ->where('created_by', $tenantId)
                ->update([
                    'employment_status' => 'active',
                    'separation_date' => null,
                ]);
        }

        $user = User::find($employeeUserId);
        if (!$user) {
            return ['restored' => false, 'reason' => 'user_not_found'];
        }

        $deactivationReason = Schema::hasColumn('users', 'deactivation_reason')
            ? ($user->deactivation_reason ?? null)
            : null;

        // Only auto-restore accounts disabled by separation (or legacy disabled without a non-separation reason)
        if ((int) ($user->is_disable ?? 0) === 1 || !$user->is_enable_login) {
            if ($deactivationReason !== null && !in_array($deactivationReason, ['resigned', 'terminated'], true)) {
                return ['restored' => false, 'reason' => $deactivationReason];
            }

            if (function_exists('canCreateUser')) {
                $seatCheck = canCreateUser($tenantId);
                if (!($seatCheck['can_create'] ?? false)) {
                    if (Schema::hasColumn('users', 'deactivation_reason')) {
                        $user->deactivation_reason = 'plan_limit';
                        $user->save();
                    }

                    return [
                        'restored' => false,
                        'reason' => 'plan_limit',
                        'message' => $seatCheck['message'] ?? __('You have reached the maximum user limit. Please upgrade your plan.'),
                    ];
                }
            }

            $user->is_disable = 0;
            $user->is_enable_login = 1;
            if (Schema::hasColumn('users', 'deactivation_reason')) {
                $user->deactivation_reason = null;
                $user->deactivated_at = null;
            }
            $user->save();

            return ['restored' => true];
        }

        return ['restored' => true];
    }

    /**
     * Batch-finalize employees whose notice period has expired prior to today.
     */
    public function processDueSeparations(?Carbon $asOfDate = null): int
    {
        if (!Schema::hasColumn('employees', 'employment_status') || !Schema::hasColumn('employees', 'separation_date')) {
            return 0;
        }

        $cutoff = ($asOfDate ?? Carbon::today())->toDateString();

        $dueEmployees = Employee::withoutGlobalScope('tenant')
            ->where('employment_status', 'notice_period')
            ->whereNotNull('separation_date')
            ->whereDate('separation_date', '<', $cutoff)
            ->get();

        $processed = 0;

        foreach ($dueEmployees as $employee) {
            $reason = 'resigned';

            $hasTermination = Termination::withoutGlobalScope('tenant')
                ->where('created_by', $employee->created_by)
                ->where('employee_id', $employee->user_id)
                ->where('status', 'approved')
                ->whereDate('termination_date', '<', $cutoff)
                ->exists();

            if ($hasTermination) {
                $reason = 'terminated';
            }

            $employee->employment_status = $reason;
            $employee->save();

            $user = User::find($employee->user_id);
            if ($user) {
                $user->is_disable = 1;
                $user->is_enable_login = 0;
                if (Schema::hasColumn('users', 'deactivation_reason')) {
                    $user->deactivation_reason = $reason;
                    $user->deactivated_at = now();
                }
                $user->save();

                $this->revokeUserSessionsAndTokens($user);
            }

            $processed++;
        }

        return $processed;
    }

    /**
     * Summarize open cross-module responsibilities for safe offboarding handover.
     */
    public function getOffboardingImpactSummary(int $employeeUserId, int $tenantId): array
    {
        $summary = [
            'open_tasks' => 0,
            'open_bugs' => 0,
            'projects' => 0,
            'leads' => 0,
            'deals' => 0,
            'tickets' => 0,
            'pending_approvals' => 0,
            'direct_reports' => 0,
            'total_open_items' => 0,
        ];

        // Taskly (Projects, Tasks, Bugs)
        if (class_exists(\Workdo\Taskly\Models\ProjectUser::class) && Schema::hasTable('project_users')) {
            $summary['projects'] = \Workdo\Taskly\Models\ProjectUser::where('user_id', $employeeUserId)->count();
        }

        if (class_exists(\Workdo\Taskly\Models\ProjectTask::class)
            && class_exists(\Workdo\Taskly\Models\TaskStage::class)
            && Schema::hasTable('project_tasks')
            && Schema::hasTable('task_stages')) {
            $completedStageIds = \Workdo\Taskly\Models\TaskStage::withoutGlobalScope('tenant')
                ->where('created_by', $tenantId)
                ->where('complete', true)
                ->pluck('id')
                ->all();

            $tasks = \Workdo\Taskly\Models\ProjectTask::withoutGlobalScope('tenant')
                ->where('created_by', $tenantId)
                ->when(!empty($completedStageIds), fn ($q) => $q->whereNotIn('stage_id', $completedStageIds))
                ->get(['id', 'assigned_to']);

            $summary['open_tasks'] = $tasks->filter(function ($task) use ($employeeUserId) {
                return $this->userAssignedToField($task->assigned_to, $employeeUserId);
            })->count();
        }

        if (class_exists(\Workdo\Taskly\Models\ProjectBug::class)
            && class_exists(\Workdo\Taskly\Models\BugStage::class)
            && Schema::hasTable('project_bugs')
            && Schema::hasTable('bug_stages')) {
            $completedBugStageIds = \Workdo\Taskly\Models\BugStage::withoutGlobalScope('tenant')
                ->where('created_by', $tenantId)
                ->where('complete', true)
                ->pluck('id')
                ->all();

            $bugs = \Workdo\Taskly\Models\ProjectBug::withoutGlobalScope('tenant')
                ->where('created_by', $tenantId)
                ->when(!empty($completedBugStageIds), fn ($q) => $q->whereNotIn('stage_id', $completedBugStageIds))
                ->get(['id', 'assigned_to']);

            $summary['open_bugs'] = $bugs->filter(function ($bug) use ($employeeUserId) {
                return $this->userAssignedToField($bug->assigned_to, $employeeUserId);
            })->count();
        }

        // CRM (Leads & Deals)
        if (class_exists(\Workdo\Lead\Models\Lead::class) && Schema::hasTable('leads')) {
            $summary['leads'] = \Workdo\Lead\Models\Lead::withoutGlobalScope('tenant')
                ->where('created_by', $tenantId)
                ->where('user_id', $employeeUserId)
                ->where('is_converted', 0)
                ->count();
        }

        if (class_exists(\Workdo\Lead\Models\UserDeal::class)
            && Schema::hasTable('user_deals')
            && Schema::hasTable('deals')) {
            $summary['deals'] = \Workdo\Lead\Models\UserDeal::withoutGlobalScope('tenant')
                ->where('created_by', $tenantId)
                ->where('user_id', $employeeUserId)
                ->whereHas('deal', fn ($q) => $q->where('status', 'Active'))
                ->count();
        }

        // SupportTicket
        if (class_exists(\Workdo\SupportTicket\Models\Ticket::class)
            && Schema::hasTable((new \Workdo\SupportTicket\Models\Ticket())->getTable())) {
            $summary['tickets'] = \Workdo\SupportTicket\Models\Ticket::withoutGlobalScope('tenant')
                ->where('created_by', $tenantId)
                ->where('account_type', 'staff')
                ->where('user_id', $employeeUserId)
                ->whereNotIn('status', ['Closed', 'Resolved'])
                ->count();
        }

        // GadaacloudStudio pending approvals
        if (class_exists(\Workdo\GadaacloudStudio\Models\WorkflowRequest::class)
            && class_exists(\Workdo\GadaacloudStudio\Models\WorkflowStep::class)
            && Schema::hasTable((new \Workdo\GadaacloudStudio\Models\WorkflowRequest())->getTable())
            && Schema::hasTable((new \Workdo\GadaacloudStudio\Models\WorkflowStep())->getTable())) {
            $summary['pending_approvals'] = \Workdo\GadaacloudStudio\Models\WorkflowRequest::withoutGlobalScope('tenant')
                ->where('created_by', $tenantId)
                ->where('status', 'pending')
                ->where(function ($q) use ($employeeUserId) {
                    $q->where('delegated_to_user_id', $employeeUserId)
                        ->orWhereHas('currentStep', fn ($sq) => $sq->where('approver_id', $employeeUserId));
                })
                ->count();
        }

        // Direct reports inside HRM org chart
        if (Schema::hasColumn('employees', 'manager_id')) {
            $empRecord = Employee::withoutGlobalScope('tenant')
                ->where('created_by', $tenantId)
                ->where('user_id', $employeeUserId)
                ->first(['id']);

            if ($empRecord) {
                $summary['direct_reports'] = Employee::withoutGlobalScope('tenant')
                    ->where('created_by', $tenantId)
                    ->where('manager_id', $empRecord->id)
                    ->active()
                    ->count();
            }
        }

        $summary['total_open_items'] = $summary['open_tasks']
            + $summary['open_bugs']
            + $summary['leads']
            + $summary['deals']
            + $summary['tickets']
            + $summary['pending_approvals']
            + $summary['direct_reports'];

        return $summary;
    }

    private function userAssignedToField($assignedTo, int $userId): bool
    {
        if (empty($assignedTo)) {
            return false;
        }

        if (is_array($assignedTo)) {
            return in_array($userId, array_map('intval', $assignedTo), true);
        }

        if (is_string($assignedTo)) {
            $decoded = json_decode($assignedTo, true);
            $ids = is_array($decoded) ? $decoded : explode(',', $assignedTo);
            return in_array($userId, array_map('intval', array_filter($ids, fn ($v) => $v !== '')), true);
        }

        return (int) $assignedTo === $userId;
    }

    private function revokeUserSessionsAndTokens(User $user): void
    {
        if (method_exists($user, 'tokens') && Schema::hasTable('personal_access_tokens')) {
            $user->tokens()->delete();
        }

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
    }
}
