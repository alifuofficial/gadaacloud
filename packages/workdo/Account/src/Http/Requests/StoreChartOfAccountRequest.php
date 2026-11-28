<?php

namespace Workdo\Account\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Workdo\Account\Traits\AccountControllerHelpers;

class StoreChartOfAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_code' => 'required|string|max:255|unique:chart_of_accounts,account_code,NULL,id,created_by,' . creatorId(),
            'account_name' => 'required|string|max:255',
            'level' => 'nullable|integer|min:1',
            'normal_balance' => 'required|string',
            'opening_balance' => 'nullable|numeric|min:0',
            'current_balance' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'description' => 'nullable|string|max:20000',
            'account_type_id' => ['nullable', AccountControllerHelpers::tenantExists('account_types', 'id')],
            'parent_account_id' => ['nullable', AccountControllerHelpers::tenantExists('chart_of_accounts', 'id')]
        ];
    }
}