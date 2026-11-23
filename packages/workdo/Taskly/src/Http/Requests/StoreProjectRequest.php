<?php

namespace Workdo\Taskly\Http\Requests;

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
            'currency_code' => 'nullable|string|max:10',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
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
