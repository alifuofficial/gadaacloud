<?php

namespace Workdo\Hrm\Http\Requests;


use Workdo\Hrm\Traits\HrmControllerHelpers;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAcknowledgmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', HrmControllerHelpers::tenantExists('users', 'id')],
            'document_id' => ['required', HrmControllerHelpers::tenantExists('hrm_documents', 'id')],
            'acknowledgment_note' => 'nullable|string'
        ];
    }
}