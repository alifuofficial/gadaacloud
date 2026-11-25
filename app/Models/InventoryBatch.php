<?php

namespace App\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workdo\ProductService\Models\ProductServiceItem;

class InventoryBatch extends Model
{
    use TenantScoped;

    protected $table = 'inventory_batches';

    protected $fillable = [
        'created_by',
        'creator_id',
        'product_id',
        'warehouse_id',
        'batch_number',
        'lot_grade',
        'origin',
        'manufacture_date',
        'expiry_date',
        'initial_quantity',
        'remaining_quantity',
        'unit_cost',
        'purchase_invoice_id',
        'status',
    ];

    protected $casts = [
        'initial_quantity'   => 'decimal:4',
        'remaining_quantity' => 'decimal:4',
        'unit_cost'          => 'decimal:4',
        'manufacture_date'   => 'date',
        'expiry_date'        => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductServiceItem::class, 'product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
