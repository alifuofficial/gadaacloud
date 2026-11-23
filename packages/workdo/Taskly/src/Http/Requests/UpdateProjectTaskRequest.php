<?php

namespace Workdo\Taskly\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'priority' => 'nullable|in:High,Medium,Low',
            'assigned_to' => 'nullable|array',
            'assigned_to.*' => 'exists:users,id',
            'duration' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'estimated_hours' => 'nullable|numeric|min:0',
            'progress' => 'nullable|integer|min:0|max:100',
            'predecessor_id' => 'nullable|exists:project_tasks,id',
            'dependency_type' => 'nullable|in:finish_to_start,start_to_start,finish_to_finish',
            'is_critical' => 'nullable|boolean',
            'description' => 'nullable|string',
            'stage_id' => 'nullable|integer|exists:task_stages,id',
        ];
    }
}
