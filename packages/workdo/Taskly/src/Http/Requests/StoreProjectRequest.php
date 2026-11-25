<?php

namespace Workdo\Taskly\Http\Requests;


use Workdo\Taskly\Traits\TasklyControllerHelpers;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'project_type' => 'nullable|string|in:general,construction,manufacturing,import_export,consulting',
            'project_scope' => 'nullable|string|in:client,internal',
            'internal_category' => 'nullable|string|in:capex,opex,rd,maintenance,strategic',
            'project_sponsor_id' => ['nullable', TasklyControllerHelpers::tenantExists('users', 'id')],
            'business_case' => 'nullable|string',
            'target_roi_savings' => 'nullable|string|max:255',
            'currency_code' => 'nullable|string|max:10',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => [TasklyControllerHelpers::tenantExists('users', 'id')],
            'description' => 'nullable|string',
            'budget' => 'required|numeric|min:0',
            'estimated_hrs' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'branch_id' => 'nullable|integer',
            'department_id' => 'nullable|integer',
            'cost_center_id' => 'nullable|integer',
        ];
    }
}
