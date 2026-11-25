<?php

namespace Workdo\Hrm\Http\Requests;


use Workdo\Hrm\Traits\HrmControllerHelpers;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDesignationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'designation_name' => 'required|max:100',
            'branch_id' => ['required', HrmControllerHelpers::tenantExists('branches', 'id')],
            'department_id' => ['required', HrmControllerHelpers::tenantExists('departments', 'id')]
        ];
    }
}