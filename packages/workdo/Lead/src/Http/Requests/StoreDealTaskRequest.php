<?php

namespace Workdo\Lead\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Workdo\Lead\Traits\LeadControllerHelpers;

class StoreDealTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'deal_id' => ['required', LeadControllerHelpers::tenantExists('deals', 'id')],
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'time' => 'required',
            'priority' => 'required|in:Low,Medium,High',
            'status' => 'required|in:On Going,Complete'
        ];
    }
}