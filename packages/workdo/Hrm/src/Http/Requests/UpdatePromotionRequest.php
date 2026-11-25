<?php

namespace Workdo\Hrm\Http\Requests;


use Workdo\Hrm\Traits\HrmControllerHelpers;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', HrmControllerHelpers::tenantExists('users', 'id')],
            'current_branch_id' => ['required', HrmControllerHelpers::tenantExists('branches', 'id')],
            'current_department_id' => ['required', HrmControllerHelpers::tenantExists('departments', 'id')],
            'current_designation_id' => ['required', HrmControllerHelpers::tenantExists('designations', 'id')],
            'effective_date' => 'required|date',
            'reason' => 'nullable|string',
            'document' => 'nullable|string',
        ];
    }
}