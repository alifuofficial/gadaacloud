<?php

namespace Workdo\Lead\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Workdo\Lead\Traits\LeadControllerHelpers;

class StoreDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|max:100',
            'price' => 'required|numeric|min:0',
            'phone' => LeadControllerHelpers::phoneRule(),
            'tin_number' => 'nullable|string|max:50',
            'vat_number' => 'nullable|string|max:50',
            'ethiopian_fiscal_year' => 'nullable|string|max:30',
            'clients' => 'required|array|min:1',
            'clients.*' => ['integer', LeadControllerHelpers::tenantExists('users', 'id')],
        ];
    }
}
