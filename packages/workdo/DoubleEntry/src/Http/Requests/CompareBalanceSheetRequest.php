<?php

namespace Workdo\DoubleEntry\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Workdo\DoubleEntry\Traits\DoubleEntryControllerHelpers;

class CompareBalanceSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_period_id' => [
                'required',
                'integer',
                DoubleEntryControllerHelpers::tenantExists('balance_sheets', 'id'),
            ],
            'previous_period_id' => [
                'required',
                'integer',
                'different:current_period_id',
                DoubleEntryControllerHelpers::tenantExists('balance_sheets', 'id'),
            ],
        ];
    }
}
