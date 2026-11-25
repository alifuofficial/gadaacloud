<?php

namespace Workdo\Lead\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Workdo\Lead\Traits\LeadControllerHelpers;

class UpdateLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|max:100',
            'company_name' => 'nullable|string|max:150',
            'email' => 'required|email',
            'subject' => 'required|max:200',
            'user_id' => ['required', 'integer', LeadControllerHelpers::tenantExists('users', 'id')],
            'pipeline_id' => ['nullable', 'integer', LeadControllerHelpers::tenantExists('pipelines', 'id')],
            'stage_id' => ['nullable', 'integer', LeadControllerHelpers::tenantExists('lead_stages', 'id')],
            'sources' => 'nullable|max:100',
            'products' => 'nullable|max:100',
            'notes' => 'nullable',
            'phone' => LeadControllerHelpers::phoneRule(),
            'tin_number' => 'nullable|string|max:50',
            'vat_number' => 'nullable|string|max:50',
            'date' => 'nullable|date',
            'ethiopian_fiscal_year' => 'nullable|string|max:30',
        ];
    }
}
