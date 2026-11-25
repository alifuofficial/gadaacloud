<?php

namespace Workdo\Hrm\Http\Requests;


use Workdo\Hrm\Traits\HrmControllerHelpers;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWarningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', HrmControllerHelpers::tenantExists('users', 'id')],
            'warning_by' => ['required', HrmControllerHelpers::tenantExists('users', 'id')],
            'warning_type_id' => ['required', HrmControllerHelpers::tenantExists('warning_types', 'id')],
            'subject' => 'required|max:255',
            'severity' => 'required',
            'warning_date' => 'required|date',
            'description' => 'nullable',
            'document' => 'nullable|string',

        ];
    }
}
