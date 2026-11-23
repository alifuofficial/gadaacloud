<?php

namespace Workdo\Taskly\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
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
            'description' => 'nullable|string',
            'budget' => 'required|numeric|min:0',
            'estimated_hrs' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'nullable|in:Ongoing,Onhold,Finished',
            'branch_id' => 'nullable|integer',
            'department_id' => 'nullable|integer',
            'cost_center_id' => 'nullable|integer',
            'is_client_visible' => 'nullable|boolean',
        ];
    }
}
