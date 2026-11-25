<?php

namespace Workdo\Hrm\Http\Requests;


use Workdo\Hrm\Traits\HrmControllerHelpers;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAllowanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'allowance_type_id' => ['required', 'integer', HrmControllerHelpers::tenantExists('allowance_types', 'id')],
            'type' => 'required|in:fixed,percentage',
            'amount' => 'required|numeric|min:0'
        ];
    }
}