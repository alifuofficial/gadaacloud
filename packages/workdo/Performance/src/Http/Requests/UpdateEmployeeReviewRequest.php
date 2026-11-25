<?php

namespace Workdo\Performance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Workdo\Performance\Traits\PerformanceControllerHelpers;

class UpdateEmployeeReviewRequest extends FormRequest
{
    use PerformanceControllerHelpers;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', self::tenantExists('users')],
            'reviewer_id' => ['required', self::tenantExists('users')],
            'review_cycle_id' => ['required', self::tenantExists('performance_review_cycles')],
            'review_template_id' => ['nullable', self::tenantExists('review_templates')],
            'review_date' => 'required|date',
            'status' => 'required|in:pending,in_progress,self_submitted,calibration,completed,cancelled',
            'workflow_stage' => 'nullable|in:self_assessment,manager_review,hr_calibration,acknowledged,locked',
        ];
    }
}
