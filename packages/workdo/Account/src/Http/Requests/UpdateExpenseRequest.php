<?php

namespace Workdo\Account\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Workdo\Account\Traits\AccountControllerHelpers;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expense_date' => 'required|date',
            'category_id' => ['required', AccountControllerHelpers::tenantExists('expense_categories', 'id')],
            'bank_account_id' => ['required', AccountControllerHelpers::tenantExists('bank_accounts', 'id')],
            'chart_of_account_id' => ['required', AccountControllerHelpers::tenantExists('chart_of_accounts', 'id')],
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'reference_number' => 'nullable|string|max:255',
        ];
    }
}
