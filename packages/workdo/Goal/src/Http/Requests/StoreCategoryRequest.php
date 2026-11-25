<?php

namespace Workdo\Goal\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_name' => 'required|string|max:100',
            'category_code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('goal_categories', 'category_code')->where(function ($q) {
                    return $q->where('created_by', creatorId());
                }),
            ],
            'description' => 'nullable|string',
            'is_active' => 'required',
        ];
    }
}
