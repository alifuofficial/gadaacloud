<?php

namespace Workdo\Goal\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'goal_name' => 'required|string|max:255',
            'goal_description' => 'nullable|string',
            'category_id' => [
                'required',
                Rule::exists('goal_categories', 'id')->where(fn($q) => $q->where('created_by', creatorId())),
            ],
            'goal_type' => 'required|in:savings,debt_reduction,expense_reduction,revenue_target',
            'target_amount' => 'required|numeric|min:0.01',
            'start_date' => 'required|date',
            'target_date' => 'required|date|after_or_equal:start_date',
            'ethiopian_fiscal_year' => 'nullable|string|max:20',
            'ethiopian_quarter' => 'nullable|string|max:10',
            'priority' => 'required|in:low,medium,high,critical',
            'status' => 'required|in:draft,active,paused,completed,cancelled',
            'account_id' => [
                'nullable',
                Rule::exists('chart_of_accounts', 'id')->where(fn($q) => $q->where('created_by', creatorId())),
            ],
            'bank_account_id' => [
                'nullable',
                Rule::exists('bank_accounts', 'id')->where(fn($q) => $q->where('created_by', creatorId())),
            ],
        ];
    }
}
