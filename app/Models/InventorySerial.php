<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\TenantScoped;
use Workdo\ProductService\Models\ProductServiceItem;

class InventorySerial extends Model
{
    use TenantScoped;

    protected $table = 'inventory_serials';

    protected $fillable = [
        'created_by',
        'product_id',
        'warehouse_id',
        'serial_number',
        'status',
        'purchase_invoice_id',
        'sales_invoice_id'
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
