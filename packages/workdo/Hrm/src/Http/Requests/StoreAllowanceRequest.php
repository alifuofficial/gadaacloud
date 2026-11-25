<?php

namespace Workdo\Hrm\Http\Requests;


use Workdo\Hrm\Traits\HrmControllerHelpers;
use Illuminate\Foundation\Http\FormRequest;

class StoreAllowanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', HrmControllerHelpers::tenantExists('employees', 'id')],
            'allowance_type_id' => ['required', 'integer', HrmControllerHelpers::tenantExists('allowance_types', 'id')],
            'type' => 'required|in:fixed,percentage',
            'amount' => 'required|numeric|min:0'
        ];
    }
}