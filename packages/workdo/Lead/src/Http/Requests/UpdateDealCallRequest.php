<?php

namespace Workdo\Lead\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Workdo\Lead\Traits\LeadControllerHelpers;

class UpdateDealCallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => 'required|string|max:255',
            'call_type' => 'required|in:Outbound,Inbound',
            'duration' => 'required|string',
            'assignee' => ['required', LeadControllerHelpers::tenantExists('users', 'id')],
            'description' => 'nullable|string',
            'call_result' => 'nullable|string',
        ];
    }
}