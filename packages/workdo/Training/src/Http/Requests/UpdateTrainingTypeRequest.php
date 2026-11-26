<?php

namespace Workdo\Training\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Workdo\Training\Traits\TrainingControllerHelpers;

class UpdateTrainingTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'branch_id' => ['required', TrainingControllerHelpers::tenantExists('branches', 'id')],
            'department_id' => ['required', TrainingControllerHelpers::tenantExists('departments', 'id')],            
        ];
    }
}