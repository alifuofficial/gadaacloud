<?php

namespace Workdo\BudgetPlanner\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Workdo\BudgetPlanner\Traits\BudgetPlannerControllerHelpers;

class UpdateBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'budget_name' => 'required|string|max:255',
            'period_id' => ['required', 'integer', BudgetPlannerControllerHelpers::tenantExists('budget_periods', 'id')],
            'budget_type' => 'required|in:operational,capital,cash_flow',
        ];
    }
}
