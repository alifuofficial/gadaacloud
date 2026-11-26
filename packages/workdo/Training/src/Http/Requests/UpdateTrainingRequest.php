<?php

namespace Workdo\Training\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Workdo\Training\Traits\TrainingControllerHelpers;

class UpdateTrainingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'training_type_id' => ['required', TrainingControllerHelpers::tenantExists('training_types', 'id')],
            'trainer_id' => ['required', TrainingControllerHelpers::tenantExists('trainers', 'id')],
            'branch_id' => ['required', TrainingControllerHelpers::tenantExists('branches', 'id')],
            'department_id' => ['required', TrainingControllerHelpers::tenantExists('departments', 'id')],
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'location' => 'nullable|string|max:255',
            'max_participants' => 'nullable|integer|min:1',
            'cost' => 'nullable|numeric|min:0',
            'status' => 'required|in:scheduled,ongoing,completed,cancelled',
        ];
    }
}