<?php

namespace Workdo\Performance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Workdo\Performance\Traits\PerformanceControllerHelpers;

class StorePerformanceIndicatorRequest extends FormRequest
{
    use PerformanceControllerHelpers;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', self::tenantExists('performance_indicator_categories')],
            'department_id' => 'nullable|integer',
            'designation_id' => 'nullable|integer',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'measurement_unit' => 'required|string|max:100',
            'target_value' => 'nullable|string|max:50',
            'weight' => 'nullable|numeric|min:0|max:100',
            'goal_direction' => 'nullable|in:higher_is_better,lower_is_better',
            'kpi_source' => 'nullable|in:manual,hrm_attendance,taskly_on_time,timesheet_hours,crm_deals',
            'status' => 'in:active,inactive',
        ];
    }
}
