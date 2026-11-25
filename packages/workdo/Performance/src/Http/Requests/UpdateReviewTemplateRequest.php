<?php

namespace Workdo\Performance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Workdo\Performance\Traits\PerformanceControllerHelpers;

class UpdateReviewTemplateRequest extends FormRequest
{
    use PerformanceControllerHelpers;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'branch_id' => 'nullable|integer',
            'department_id' => 'nullable|integer',
            'designation_id' => 'nullable|integer',
            'kpi_weight' => 'nullable|numeric|min:0|max:100',
            'goal_weight' => 'nullable|numeric|min:0|max:100',
            'competency_weight' => 'nullable|numeric|min:0|max:100',
            'include_self_assessment' => 'nullable|boolean',
            'status' => 'in:active,inactive',
            'indicator_ids' => 'nullable|array',
            'indicator_ids.*' => [self::tenantExists('performance_indicators')],
            'indicator_weights' => 'nullable|array',
            'indicator_weights.*' => 'nullable|numeric|min:0|max:100',
        ];
    }
}
