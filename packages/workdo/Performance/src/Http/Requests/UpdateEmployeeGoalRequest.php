<?php

namespace Workdo\Performance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Workdo\Performance\Traits\PerformanceControllerHelpers;

class UpdateEmployeeGoalRequest extends FormRequest
{
    use PerformanceControllerHelpers;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', self::tenantExists('users')],
            'department_id' => ['nullable', 'integer'],
            'goal_type_id' => ['required', self::tenantExists('performance_goal_types')],
            'review_cycle_id' => ['nullable', self::tenantExists('performance_review_cycles')],
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'target' => 'required|string|max:50',
            'target_numeric' => 'nullable|numeric|min:0',
            'current_value' => 'nullable|numeric|min:0',
            'unit' => 'nullable|string|max:30',
            'progress' => 'required|numeric|min:0|max:100',
            'weight' => 'nullable|numeric|min:0|max:100',
            'status' => 'required|in:not_started,in_progress,completed,overdue',
            'kpi_source' => 'nullable|in:manual,hrm_attendance,taskly_on_time,timesheet_hours,crm_deals',
            'auto_sync_enabled' => 'nullable|boolean',
        ];
    }
}
