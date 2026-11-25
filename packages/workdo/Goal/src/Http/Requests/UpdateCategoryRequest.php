<?php

namespace Workdo\Goal\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $category = $this->route('category');
        $categoryId = is_object($category) ? $category->id : $category;

        return [
            'category_name' => 'required|string|max:100',
            'category_code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('goal_categories', 'category_code')
                    ->where(fn($q) => $q->where('created_by', creatorId()))
                    ->ignore($categoryId),
            ],
            'description' => 'nullable|string',
            'is_active' => 'required',
        ];
    }
}
