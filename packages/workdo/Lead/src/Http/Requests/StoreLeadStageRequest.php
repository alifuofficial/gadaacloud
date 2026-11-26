<?php

namespace Workdo\Lead\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Workdo\Lead\Traits\LeadControllerHelpers;

class StoreLeadStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|max:100',
            'pipeline_id' => ['nullable', 'integer', LeadControllerHelpers::tenantExists('pipelines', 'id')]
        ];
    }
}