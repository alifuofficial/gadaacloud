<?php

namespace Workdo\Training\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Workdo\Training\Traits\TrainingControllerHelpers;

class UpdateTrainerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'contact' => 'required|string|max:20',
            'email' => ['required', 'email', Rule::unique('trainers', 'email')->ignore($this->trainer->id)->where('created_by', creatorId())],
            'experience' => 'required|string|max:255',
            'branch_id' => ['required', TrainingControllerHelpers::tenantExists('branches', 'id')],
            'department_id' => ['required', TrainingControllerHelpers::tenantExists('departments', 'id')],
            'expertise' => 'nullable|string',
            'qualification' => 'nullable|string',
        ];
    }
}