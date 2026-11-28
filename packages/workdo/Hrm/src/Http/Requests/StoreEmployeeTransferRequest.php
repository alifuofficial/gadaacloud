<?php

namespace Workdo\Hrm\Http\Requests;


use Workdo\Hrm\Traits\HrmControllerHelpers;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', HrmControllerHelpers::tenantExists('users', 'id')],
            'to_branch_id' => ['required', HrmControllerHelpers::tenantExists('branches', 'id')],
            'to_department_id' => ['required', HrmControllerHelpers::tenantExists('departments', 'id')],
            'to_designation_id' => ['required', HrmControllerHelpers::tenantExists('designations', 'id')],
            'effective_date' => 'required|date|after_or_equal:today',
            'reason' => 'required|string|max:20000',
            'document' => 'nullable|string'
        ];
    }
}