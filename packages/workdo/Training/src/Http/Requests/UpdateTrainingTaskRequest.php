<?php

namespace Workdo\Training\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Workdo\Training\Traits\TrainingControllerHelpers;

class UpdateTrainingTaskRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'required|date',
            'assigned_to' => ['required', TrainingControllerHelpers::tenantExists('users', 'id')],
        ];
    }
}