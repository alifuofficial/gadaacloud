<?php

namespace Workdo\Account\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Workdo\Account\Traits\AccountControllerHelpers;

class UpdateRevenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'revenue_date' => 'required|date',
            'category_id' => ['required', AccountControllerHelpers::tenantExists('revenue_categories', 'id')],
            'bank_account_id' => ['required', AccountControllerHelpers::tenantExists('bank_accounts', 'id')],
            'chart_of_account_id' => ['required', AccountControllerHelpers::tenantExists('chart_of_accounts', 'id')],
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'reference_number' => 'nullable|string|max:255',
        ];
    }
}
