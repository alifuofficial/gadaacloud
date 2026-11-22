<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantScoped;

class Transfer extends Model
{
    use TenantScoped;
    protected $fillable = [
        'waybill_number',
        'from_warehouse',
        'to_warehouse', 
        'product_id',
        'quantity',
        'received_quantity',
        'damaged_quantity',
        'discrepancy_reason',
        'status',
        'driver_name',
        'driver_phone',
        'vehicle_plate_number',
        'date',
        'dispatched_at',
        'received_at',
        'notes',
        'creator_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'received_quantity' => 'decimal:2',
            'damaged_quantity' => 'decimal:2',
            'date' => 'date',
            'dispatched_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    /**
     * Generate standard Ethiopian inter-branch Waybill Number.
     */
    public static function generateWaybillNumber(): string
    {
        $prefix = 'WB-' . date('Ym') . '-';
        $random = strtoupper(substr(uniqid(), -5));
        return $prefix . $random;
    }

    public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse');
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse');
    }

    public function product()
    {
        return $this->belongsTo(\Workdo\ProductService\Models\ProductServiceItem::class, 'product_id');
    }
}