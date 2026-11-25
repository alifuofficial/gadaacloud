<?php

namespace Workdo\Performance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Workdo\Performance\Traits\PerformanceControllerHelpers;

class StoreCompetencyRequest extends FormRequest
{
    use PerformanceControllerHelpers;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', self::tenantExists('competency_categories')],
            'department_id' => 'nullable|integer',
            'designation_id' => 'nullable|integer',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'weight' => 'nullable|numeric|min:0|max:100',
            'status' => 'in:active,inactive',
        ];
    }
}
