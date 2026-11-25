<?php

namespace Workdo\BudgetPlanner\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Workdo\BudgetPlanner\Traits\BudgetPlannerControllerHelpers;

class UpdateBudgetAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'budget_id' => ['required', 'integer', BudgetPlannerControllerHelpers::tenantExists('budgets', 'id')],
            'account_id' => ['required', 'integer', BudgetPlannerControllerHelpers::tenantExists('chart_of_accounts', 'id')],
            'allocated_amount' => 'required|numeric|min:0',
        ];
    }
}
