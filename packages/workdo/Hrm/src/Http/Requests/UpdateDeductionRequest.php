<?php

namespace Workdo\Hrm\Http\Requests;


use Workdo\Hrm\Traits\HrmControllerHelpers;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDeductionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'deduction_type_id' => ['required', HrmControllerHelpers::tenantExists('deduction_types', 'id')],
            'type' => 'required|in:fixed,percentage',
            'amount' => 'required|numeric|min:0',
        ];
    }
}