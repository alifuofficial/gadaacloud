<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\TenantScoped;
use Workdo\ProductService\Models\ProductServiceItem;

class InventoryAuditLog extends Model
{
    use TenantScoped;

    protected $table = 'inventory_audit_logs';

    protected $fillable = [
        'created_by',
        'warehouse_id',
        'product_id',
        'action_type',
        'reference_id',
        'qty_delta',
        'final_qty',
        'creator_id'
    ];

    protected $casts = [
        'qty_delta' => 'decimal:4',
        'final_qty' => 'decimal:4',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function creator(): BelongsTo
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
