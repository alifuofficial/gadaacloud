<?php

namespace App\Http\Requests;

use App\Traits\ControllerHelpers;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalesReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'return_date' => 'required|date',
            'customer_id' => ['required', ControllerHelpers::tenantExists('users', 'id')],
            'warehouse_id' => ['nullable', ControllerHelpers::tenantExists('warehouses', 'id')],
            'original_invoice_id' => ['required', ControllerHelpers::tenantExists('sales_invoices', 'id')],
            'reason' => 'required|in:defective,wrong_item,damaged,excess_quantity,other',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => ['required', ControllerHelpers::tenantExists('product_service_items', 'id')],
            'items.*.original_invoice_item_id' => ['required', Rule::exists('sales_invoice_items', 'id')->where('invoice_id', $this->input('original_invoice_id'))],
            'items.*.return_quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.reason' => 'nullable|string'
        ];
    }
}