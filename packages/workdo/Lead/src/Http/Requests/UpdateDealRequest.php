<?php

namespace Workdo\Lead\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Workdo\Lead\Traits\LeadControllerHelpers;

class UpdateDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'price' => 'numeric|min:0',
            'pipeline_id' => ['required', 'integer', LeadControllerHelpers::tenantExists('pipelines', 'id')],
            'stage_id' => ['required', 'integer', LeadControllerHelpers::tenantExists('deal_stages', 'id')],
            'phone' => LeadControllerHelpers::phoneRule(),
            'tin_number' => 'nullable|string|max:50',
            'vat_number' => 'nullable|string|max:50',
            'ethiopian_fiscal_year' => 'nullable|string|max:30',
            'sources' => 'nullable|array',
            'sources.*' => ['integer', LeadControllerHelpers::tenantExists('sources', 'id')],
            'products' => 'nullable|array',
            'notes' => 'nullable|string',
            'clients' => 'nullable|array',
            'clients.*' => ['integer', LeadControllerHelpers::tenantExists('users', 'id')],
        ];
    }
}
