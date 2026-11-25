<?php

namespace Workdo\Performance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Workdo\Performance\Traits\PerformanceControllerHelpers;

class StoreEmployeeFeedbackRequest extends FormRequest
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
            'feedback_type_id' => ['required', self::tenantExists('feedback_types')],
            'review_cycle_id' => ['nullable', self::tenantExists('performance_review_cycles')],
            'relationship_type' => 'nullable|in:peer,manager,subordinate,self,client',
            'date' => 'required|date',
            'rating' => 'nullable|integer|min:1|max:5',
            'content' => 'required|string|max:10000',
            'visibility' => 'in:private,team,public',
            'is_anonymous' => 'nullable|boolean',
        ];
    }
}
