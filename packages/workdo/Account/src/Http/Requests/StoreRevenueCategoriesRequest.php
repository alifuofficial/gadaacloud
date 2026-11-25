<?php

namespace Workdo\Account\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Workdo\Account\Traits\AccountControllerHelpers;

class StoreRevenueCategoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_name' => 'required',
            'category_code' => 'required|unique:revenue_categories,category_code',
            'gl_account_id' => ['nullable', AccountControllerHelpers::tenantExists('chart_of_accounts', 'id')],
            'description' => 'nullable',
            'is_active' => 'boolean'
        ];
    }
}