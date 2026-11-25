<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\TenantScoped;
use Workdo\ProductService\Models\ProductServiceItem;

class InventoryReorderRule extends Model
{
    use TenantScoped;

    protected $table = 'inventory_reorder_rules';

    protected $fillable = [
        'created_by',
        'creator_id',
        'warehouse_id',
        'product_id',
        'min_stock_level',
        'reorder_qty',
        'preferred_vendor_id'
    ];

    protected $casts = [
        'min_stock_level' => 'decimal:4',
        'reorder_qty'     => 'decimal:4',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductServiceItem::class, 'product_id');
    }

    public function preferredVendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'preferred_vendor_id');
    }
}
