<?php

namespace Workdo\Hrm\Http\Requests;


use Workdo\Hrm\Traits\HrmControllerHelpers;
use Illuminate\Foundation\Http\FormRequest;

class UpdateComplaintRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'employee_id' => ['required', HrmControllerHelpers::tenantExists('users', 'id')],
            'against_employee_id' => ['required', HrmControllerHelpers::tenantExists('users', 'id')],
            'complaint_type_id' => ['required', HrmControllerHelpers::tenantExists('complaint_types', 'id')],
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'complaint_date' => 'required|date',
            'document' => 'nullable|string',
        ];
    }
}