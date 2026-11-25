<?php

namespace Workdo\Performance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Workdo\Performance\Traits\PerformanceControllerHelpers;

class StoreEmployeeCompetencyRequest extends FormRequest
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
            'competency_id' => ['required', self::tenantExists('competencies')],
            'review_cycle_id' => ['nullable', self::tenantExists('performance_review_cycles')],
            'rating' => 'required|numeric|min:0|max:5',
            'assessment_date' => 'required|date',
            'comments' => 'nullable|string|max:5000',
        ];
    }
}
