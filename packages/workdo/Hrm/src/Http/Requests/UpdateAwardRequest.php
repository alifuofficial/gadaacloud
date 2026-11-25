<?php

namespace Workdo\Hrm\Http\Requests;


use Workdo\Hrm\Traits\HrmControllerHelpers;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAwardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', HrmControllerHelpers::tenantExists('users', 'id')],
            'award_type_id' => ['required', HrmControllerHelpers::tenantExists('award_types', 'id')],
            'award_date' => 'required|string|max:255',
            'description' => 'nullable',
            'certificate' => 'nullable'
        ];
    }
}