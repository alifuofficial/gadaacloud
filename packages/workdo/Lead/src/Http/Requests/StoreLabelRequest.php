<?php

namespace Workdo\Lead\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Workdo\Lead\Traits\LeadControllerHelpers;

class StoreLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|max:100',
            'color' => 'required',
            'pipeline_id' => ['nullable', LeadControllerHelpers::tenantExists('pipelines', 'id')]
        ];
    }
}