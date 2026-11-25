<?php

namespace Workdo\Performance\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Workdo\Performance\Models\PerformanceEmployeeGoal;
use Workdo\Performance\Models\PerformanceEmployeeReview;

/**
 * Decoupled ERP integration bridge connecting Performance with HRM, Projects, Timesheets, Sales, and EthiopianCalendar.
 * Always degrades gracefully if an optional package is disabled or not subscribed by the tenant.
 */
class PerformanceErpIntegrationService
{
    /**
     * Check whether an optional WorkDo module is installed and active for the current tenant.
     */
    public function isModuleAvailable(string $module, ?int $tenantId = null): bool
    {
        if (function_exists('Module_is_active')) {
            try {
                if (Module_is_active($module, $tenantId ?? creatorId())) {
                    return true;
                }
            } catch (\Throwable $e) {
                // Fall through to class checks
            }
        }

        return match ($module) {
            'Hrm' => class_exists(\Workdo\Hrm\Models\Employee::class),
            'Taskly' => class_exists(\Workdo\Taskly\Models\ProjectTask::class),
            'Timesheet' => class_exists(\Workdo\Timesheet\Models\Timesheet::class),
            'Lead' => class_exists(\Workdo\Lead\Models\Deal::class),
            'EthiopianCalendar' => class_exists(\Workdo\EthiopianCalendar\Services\EthiopianDateService::class),
            default => false,
        };
    }

    /**
     * Fetch HRM organizational metadata (branches, departments, designations, HR action types) safely.
     *
     * @return array<string, mixed>
     */
    public function getHrmContext(int $tenantId): array
    {
        $context = [
            'hrm_active' => $this->isModuleAvailable('Hrm', $tenantId),
            'branches' => [],
            'departments' => [],
            'designations' => [],
            'award_types' => [],
            'warning_types' => [],
            'allowance_types' => [],
        ];

        if (!$context['hrm_active']) {
            return $context;
        }

        try {
            if (class_exists(\Workdo\Hrm\Models\Branch::class)) {
                $context['branches'] = \Workdo\Hrm\Models\Branch::where('created_by', $tenantId)
                    ->select('id', 'branch_name')->orderBy('branch_name')->get();
            }
            if (class_exists(\Workdo\Hrm\Models\Department::class)) {
                $context['departments'] = \Workdo\Hrm\Models\Department::where('created_by', $tenantId)
                    ->select('id', 'department_name', 'branch_id')->orderBy('department_name')->get();
            }
            if (class_exists(\Workdo\Hrm\Models\Designation::class)) {
                $context['designations'] = \Workdo\Hrm\Models\Designation::where('created_by', $tenantId)
                    ->select('id', 'designation_name', 'department_id')->orderBy('designation_name')->get();
            }
            if (class_exists(\Workdo\Hrm\Models\AwardType::class)) {
                $context['award_types'] = \Workdo\Hrm\Models\AwardType::where('created_by', $tenantId)
                    ->select('id', 'name')->orderBy('name')->get();
            }
            if (class_exists(\Workdo\Hrm\Models\WarningType::class)) {
                $context['warning_types'] = \Workdo\Hrm\Models\WarningType::where('created_by', $tenantId)
                    ->select('id', 'warning_type_name')->orderBy('warning_type_name')->get();
            }
            if (class_exists(\Workdo\Hrm\Models\AllowanceType::class)) {
                $context['allowance_types'] = \Workdo\Hrm\Models\AllowanceType::where('created_by', $tenantId)
                    ->select('id', 'name')->orderBy('name')->get();
            }
        } catch (\Throwable $e) {
            // Degrade cleanly if an optional HRM sub-table is absent
        }

        return $context;
    }

    /**
     * Resolve Ethiopian fiscal year / quarterly Gregorian date bounds safely via EthiopianDateService.
     *
     * @return array{start_date: string, end_date: string, label: string}|null
     */
    public function resolveEthiopianFiscalPeriod(int $ethFiscalYear, string $period = 'annual'): ?array
    {
        if (!class_exists(\Workdo\EthiopianCalendar\Services\EthiopianDateService::class)) {
            return null;
        }

        try {
            $service = app(\Workdo\EthiopianCalendar\Services\EthiopianDateService::class);
            $range = $service->getFiscalYearRange($ethFiscalYear);

            if ($period === 'annual' || $period === 'fy') {
                return [
                    'start_date' => $range['start_date'],
                    'end_date' => $range['end_date'],
                    'label' => $range['label'],
                ];
            }

            foreach ($range['quarters'] ?? [] as $quarter) {
                if (strtolower($quarter['key']) === strtolower($period)) {
                    return [
                        'start_date' => $quarter['start_date'],
                        'end_date' => $quarter['end_date'],
                        'label' => $quarter['label'],
                    ];
                }
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    /**
     * Sync an employee goal's actual KPI value & progress % from its linked ERP operational module.
     *
     * @return array{synced: bool, message: string, current_value: ?float, progress: float}
     */
    public function syncGoalFromErpSource(PerformanceEmployeeGoal $goal): array
    {
        $source = $goal->kpi_source ?? 'manual';
        $tenantId = (int) ($goal->created_by ?? creatorId());
        $start = $goal->start_date ? Carbon::parse($goal->start_date)->startOfDay() : Carbon::now()->startOfQuarter();
        $end = $goal->end_date ? Carbon::parse($goal->end_date)->endOfDay() : Carbon::now()->endOfDay();

        if ($source === 'manual') {
            return [
                'synced' => false,
                'message' => __('Goal is configured for manual progress tracking.'),
                'current_value' => $goal->current_value !== null ? (float) $goal->current_value : null,
                'progress' => (float) $goal->progress_percentage,
            ];
        }

        $actual = null;

        try {
            if ($source === 'hrm_attendance' && $this->isModuleAvailable('Hrm', $tenantId)) {
                $records = \Workdo\Hrm\Models\Attendance::where('created_by', $tenantId)
                    ->where('employee_id', $goal->employee_id)
                    ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                    ->get();
                if ($records->isNotEmpty()) {
                    $present = $records->filter(fn($r) => in_array(strtolower((string) $r->status), ['present', 'half day', 'late', 'approved'], true))->count();
                    $actual = round(($present / $records->count()) * 100, 2);
                } else {
                    $actual = 0.0;
                }
            } elseif ($source === 'taskly_on_time' && $this->isModuleAvailable('Taskly', $tenantId)) {
                $tasks = \Workdo\Taskly\Models\ProjectTask::where('created_by', $tenantId)
                    ->where(function ($q) use ($goal) {
                        $q->where('assigned_to', $goal->employee_id)
                            ->orWhere('assigned_to', 'like', '%"' . $goal->employee_id . '"%')
                            ->orWhere('assigned_to', 'like', '%,' . $goal->employee_id . '%');
                    })
                    ->whereBetween('start_date', [$start->toDateString(), $end->toDateString()])
                    ->get();
                if ($tasks->isNotEmpty()) {
                    $completed = $tasks->filter(fn($t) => (int) ($t->progress ?? 0) >= 100)->count();
                    $actual = round(($completed / $tasks->count()) * 100, 2);
                } else {
                    $actual = 0.0;
                }
            } elseif ($source === 'timesheet_hours' && $this->isModuleAvailable('Timesheet', $tenantId)) {
                $entries = \Workdo\Timesheet\Models\Timesheet::where('created_by', $tenantId)
                    ->where('user_id', $goal->employee_id)
                    ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                    ->get();
                $actual = round($entries->sum(fn($e) => (float) ($e->hours ?? 0) + ((float) ($e->minutes ?? 0) / 60)), 2);
            } elseif ($source === 'crm_deals' && $this->isModuleAvailable('Lead', $tenantId)) {
                $deals = \Workdo\Lead\Models\Deal::where('created_by', $tenantId)
                    ->where('creator_id', $goal->employee_id)
                    ->whereBetween('created_at', [$start, $end])
                    ->whereIn('status', ['Won', 'won', 'Closed Won', 'closed_won', '1'])
                    ->get();
                $actual = round((float) $deals->sum('price'), 2);
            } else {
                return [
                    'synced' => false,
                    'message' => __('Required ERP source module is inactive or unsupported.'),
                    'current_value' => $goal->current_value !== null ? (float) $goal->current_value : null,
                    'progress' => (float) $goal->progress_percentage,
                ];
            }
        } catch (\Throwable $e) {
            return [
                'synced' => false,
                'message' => __('Unable to sync ERP metric safely: ') . $e->getMessage(),
                'current_value' => $goal->current_value !== null ? (float) $goal->current_value : null,
                'progress' => (float) $goal->progress_percentage,
            ];
        }

        $targetNumeric = $goal->target_numeric !== null
            ? (float) $goal->target_numeric
            : PerformanceEmployeeGoal::parseNumericTarget($goal->target);

        $progressPct = ($targetNumeric !== null && $targetNumeric > 0)
            ? round(min(100, max(0, ($actual / $targetNumeric) * 100)), 2)
            : round(min(100, max(0, $actual)), 2);

        $goal->current_value = $actual;
        $goal->progress = $progressPct;
        $goal->last_synced_at = now();
        if ($progressPct >= 100) {
            $goal->status = 'completed';
        } elseif ($progressPct > 0 && $goal->status === 'not_started') {
            $goal->status = 'in_progress';
        }
        $goal->save();

        return [
            'synced' => true,
            'message' => __('Goal KPI synced successfully from ERP operations.'),
            'current_value' => $actual,
            'progress' => $progressPct,
        ];
    }

    /**
     * Dispatch an HR lifecycle action (Promotion nomination, Award, Warning/PIP, Bonus Allowance) from an appraisal.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, message: string, action_id: ?int}
     */
    public function dispatchHrAction(PerformanceEmployeeReview $review, string $action, array $payload): array
    {
        $tenantId = (int) ($review->created_by ?? creatorId());
        if (!$this->isModuleAvailable('Hrm', $tenantId)) {
            return ['ok' => false, 'message' => __('HRM module is not active for this company.'), 'action_id' => null];
        }

        $actorId = Auth::id() ?? $review->reviewer_id;
        $effectiveDate = $payload['effective_date'] ?? now()->toDateString();

        try {
            if ($action === 'promotion') {
                $emp = \Workdo\Hrm\Models\Employee::where('created_by', $tenantId)
                    ->where('user_id', $review->user_id)
                    ->first();

                $promotion = new \Workdo\Hrm\Models\Promotion();
                $promotion->employee_id = $review->user_id;
                $promotion->previous_branch_id = $emp?->branch_id;
                $promotion->previous_department_id = $emp?->department_id;
                $promotion->previous_designation_id = $emp?->designation_id;
                $promotion->current_branch_id = $payload['branch_id'] ?? $emp?->branch_id;
                $promotion->current_department_id = $payload['department_id'] ?? $emp?->department_id;
                $promotion->current_designation_id = $payload['designation_id'] ?? $emp?->designation_id;
                $promotion->effective_date = $effectiveDate;
                $promotion->reason = $payload['reason'] ?? __('Recommended following Performance Appraisal #') . $review->id;
                $promotion->status = 'pending';
                $promotion->creator_id = $actorId;
                $promotion->created_by = $tenantId;
                $promotion->save();

                $review->promotion_recommended = true;
                $review->linked_action_type = 'promotion';
                $review->linked_action_id = $promotion->id;
                $review->save();

                return ['ok' => true, 'message' => __('Pending promotion nomination created in HRM.'), 'action_id' => $promotion->id];
            }

            if ($action === 'award') {
                $award = new \Workdo\Hrm\Models\Award();
                $award->employee_id = $review->user_id;
                $award->award_type_id = (int) $payload['award_type_id'];
                $award->award_date = $effectiveDate;
                $award->description = $payload['reason'] ?? __('Awarded for strong performance in review #') . $review->id;
                $award->creator_id = $actorId;
                $award->created_by = $tenantId;
                $award->save();

                $review->linked_action_type = 'award';
                $review->linked_action_id = $award->id;
                $review->save();

                return ['ok' => true, 'message' => __('Employee award recorded in HRM.'), 'action_id' => $award->id];
            }

            if ($action === 'warning') {
                $warning = new \Workdo\Hrm\Models\Warning();
                $warning->employee_id = $review->user_id;
                $warning->warning_by = $actorId;
                $warning->warning_type_id = (int) $payload['warning_type_id'];
                $warning->subject = $payload['subject'] ?? __('Performance Improvement Plan (PIP)');
                $warning->severity = $payload['severity'] ?? 'medium';
                $warning->warning_date = $effectiveDate;
                $warning->description = $payload['reason'] ?? __('Initiated following Performance Review #') . $review->id;
                $warning->status = 'pending';
                $warning->creator_id = $actorId;
                $warning->created_by = $tenantId;
                $warning->save();

                $review->pip_recommended = true;
                $review->linked_action_type = 'warning';
                $review->linked_action_id = $warning->id;
                $review->save();

                return ['ok' => true, 'message' => __('PIP / Warning initiated in HRM.'), 'action_id' => $warning->id];
            }

            if ($action === 'bonus_allowance') {
                $allowance = \Workdo\Hrm\Models\Allowance::updateOrCreate(
                    [
                        'employee_id' => $review->user_id,
                        'allowance_type_id' => (int) $payload['allowance_type_id'],
                        'created_by' => $tenantId,
                    ],
                    [
                        'type' => $payload['bonus_type'] ?? 'fixed',
                        'amount' => (float) $payload['amount'],
                        'creator_id' => $actorId,
                    ]
                );

                $review->bonus_recommendation_type = $payload['bonus_type'] ?? 'fixed';
                $review->bonus_recommendation_amount = (float) $payload['amount'];
                $review->linked_action_type = 'allowance';
                $review->linked_action_id = $allowance->id;
                $review->save();

                return ['ok' => true, 'message' => __('Merit bonus allowance saved to HRM Payroll.'), 'action_id' => $allowance->id];
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => __('Failed to dispatch HRM action: ') . $e->getMessage(), 'action_id' => null];
        }

        return ['ok' => false, 'message' => __('Unsupported HR action requested.'), 'action_id' => null];
    }
}
