<?php

namespace Workdo\Lead\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Workdo\Lead\Traits\LeadControllerHelpers;

class StoreLeadTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lead_id' => ['required', LeadControllerHelpers::tenantExists('leads', 'id')],
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'time' => 'required',
            'priority' => 'required|in:Low,Medium,High',
            'status' => 'required|in:On Going,Complete'
        ];
    }
}