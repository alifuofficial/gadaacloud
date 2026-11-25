<?php

namespace Workdo\ProductService\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductServiceItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $nullables = [
            'sale_chartaccount_id',
            'expense_chartaccount_id',
            'inventory_chartaccount_id',
            'purchase_unit_id',
            'min_sale_price',
            'wholesale_price',
            'wholesale_min_qty',
        ];
        $merged = [];
        foreach ($nullables as $field) {
            $val = $this->input($field);
            if ($val === 'none' || $val === '') {
                $merged[$field] = null;
            }
        }
        if (!empty($merged)) {
            $this->merge($merged);
        }
    }

    public function rules(): array
    {
        $tenantId = creatorId();
        $item = $this->route('item');
        $itemId = is_object($item) ? $item->id : $item;

        return [
            'name' => 'required|string|max:255',
            'sku' => [
                'required',
                'string',
                'max:255',
                Rule::unique('product_service_items', 'sku')
                    ->ignore($itemId)
                    ->where(fn ($q) => $q->where('created_by', $tenantId)->whereNull('deleted_at')),
            ],
            'barcode' => 'nullable|string|max:100',
            'hs_code' => 'nullable|string|max:30',
            'tax_ids' => 'required|array|min:1',
            'tax_ids.*' => Rule::exists('product_service_taxes', 'id')->where('created_by', $tenantId),
            'tax_classification' => ['nullable', 'string', Rule::in(['VAT15', 'VAT0', 'EXEMPT', 'EXCISE'])],
            'excise_rate' => 'nullable|numeric|min:0|max:500',
            'is_tax_inclusive' => 'nullable|boolean',
            'category_id' => [
                'required',
                Rule::exists('product_service_categories', 'id')->where('created_by', $tenantId),
            ],
            'description' => 'nullable|string',
            'long_description' => 'nullable|string',
            'sale_price' => 'required|numeric|min:0',
            'min_sale_price' => 'nullable|numeric|min:0',
            'wholesale_price' => 'nullable|numeric|min:0',
            'wholesale_min_qty' => 'nullable|numeric|min:0',
            'purchase_price' => 'required|numeric|min:0',
            'sale_chartaccount_id' => 'nullable|integer|min:1',
            'expense_chartaccount_id' => 'nullable|integer|min:1',
            'inventory_chartaccount_id' => 'nullable|integer|min:1',
            'unit' => 'required|string',
            'purchase_unit_id' => 'nullable|integer|min:1',
            'unit_conversion_factor' => 'nullable|numeric|gt:0|max:1000000',
            'reorder_level' => 'nullable|numeric|min:0',
            'quantity' => 'nullable|numeric|min:0',
            'image' => 'nullable|string',
            'images' => 'nullable|array',
            'warehouse_id' => [
                'nullable',
                Rule::exists('warehouses', 'id')->where('created_by', $tenantId),
            ],
            'type' => 'nullable|string|in:product,service,part|max:255',
            'inventory_type' => 'nullable|string|in:standard,serialized,batch',
            'is_active' => 'nullable|boolean',
        ];
    }
}
