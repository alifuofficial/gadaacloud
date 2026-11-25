<?php

namespace Workdo\Hrm\Http\Requests;


use Workdo\Hrm\Traits\HrmControllerHelpers;
use Illuminate\Foundation\Http\FormRequest;

class UpdateHrmDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'document_category_id' => ['required', HrmControllerHelpers::tenantExists('document_categories', 'id')],
            'description' => 'nullable|string',
            'document' => 'required|string',
        ];
    }
}