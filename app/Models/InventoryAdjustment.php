<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\TenantScoped;
use Workdo\ProductService\Models\ProductServiceItem;

class InventoryAdjustment extends Model
{
    use TenantScoped;

    protected $table = 'inventory_adjustments';

    protected $fillable = [
        'created_by',
        'warehouse_id',
        'product_id',
        'adjustment_type',
        'quantity',
        'reason',
        'creator_id'
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductServiceItem::class, 'product_id');
    }
}
