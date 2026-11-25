<?php

namespace Workdo\Performance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'frequency' => 'required|in:monthly,quarterly,semi-annual,annual,ethiopian_fiscal',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'self_review_deadline' => 'nullable|date',
            'manager_review_deadline' => 'nullable|date',
            'calendar_type' => 'nullable|in:gregorian,ethiopian',
            'ethiopian_fiscal_year' => 'nullable|integer|min:2010|max:2050',
            'ethiopian_quarter' => 'nullable|in:annual,q1,q2,q3,q4',
            'branch_id' => 'nullable|integer',
            'department_id' => 'nullable|integer',
            'description' => 'nullable|string',
            'status' => 'in:active,inactive',
        ];
    }
}
