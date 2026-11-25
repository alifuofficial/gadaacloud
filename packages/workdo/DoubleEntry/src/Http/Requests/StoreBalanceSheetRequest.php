<?php

namespace Workdo\DoubleEntry\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBalanceSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'balance_sheet_date' => 'required|date',
            'financial_year' => 'required|string|max:30',
            'ethiopian_fiscal_year' => 'nullable|string|max:30',
            'ethiopian_quarter' => 'nullable|string|in:q1,q2,q3,q4,Q1,Q2,Q3,Q4',
        ];
    }
}
