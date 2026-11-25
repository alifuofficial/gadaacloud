<?php

namespace Workdo\Pos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Workdo\Pos\Traits\PosControllerHelpers;

class StorePosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', PosControllerHelpers::tenantExists('users', 'id')],
            'warehouse_id' => ['required', 'integer', PosControllerHelpers::tenantExists('warehouses', 'id')],
            'pos_date' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.id' => ['required', 'integer', PosControllerHelpers::tenantExists('product_service_items', 'id')],
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.tax_amount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
        ];
    }
}
