<?php

namespace App\Http\Requests;

use App\Traits\ControllerHelpers;
use Illuminate\Foundation\Http\FormRequest;
use Workdo\ProductService\Models\WarehouseStock;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_warehouse' => ['required', ControllerHelpers::tenantExists('warehouses', 'id')],
            'to_warehouse' => ['required', ControllerHelpers::tenantExists('warehouses', 'id'), 'different:from_warehouse'],
            'product_id' => ['required', ControllerHelpers::tenantExists('product_service_items', 'id')],
            'quantity' => [
                'required',
                'integer',
                'min:1',
                function ($attribute, $value, $fail) {
                    $warehouseStock = WarehouseStock::where('warehouse_id', $this->from_warehouse)
                        ->where('product_id', $this->product_id)
                        ->first();

                    if (!$warehouseStock || $value > $warehouseStock->quantity) {
                        $availableQty = $warehouseStock ? $warehouseStock->quantity : 0;
                        $fail("Quantity cannot exceed available stock ({$availableQty}).");
                    }
                }
            ],
            'date' => 'required|date',
            'driver_name' => 'nullable|string|max:100',
            'driver_phone' => 'nullable|string|max:50',
            'vehicle_plate_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:10000',
        ];
    }
}