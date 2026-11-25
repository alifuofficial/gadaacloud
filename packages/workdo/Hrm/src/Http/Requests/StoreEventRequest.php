<?php

namespace Workdo\Hrm\Http\Requests;


use Workdo\Hrm\Traits\HrmControllerHelpers;
use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'event_type_id' => ['required', HrmControllerHelpers::tenantExists('event_types', 'id')],
            'departments' => 'required|array',
            'departments.*' => [HrmControllerHelpers::tenantExists('departments', 'id')],
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'location' => 'required|string|max:255',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'description' => 'nullable|string',
        ];
    }
}
