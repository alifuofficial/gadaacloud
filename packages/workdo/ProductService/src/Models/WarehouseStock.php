<?php

namespace Workdo\ProductService\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WarehouseStock extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'quantity',
        'unit_cost',
        'total_value',
        'creator_id',
        'created_by',
    ];

    protected $casts = [
        'quantity'    => 'decimal:4',
        'unit_cost'   => 'decimal:4',
        'total_value' => 'decimal:4',
    ];

    public function product()
    {
        return $this->belongsTo(ProductServiceItem::class, 'product_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(\App\Models\Warehouse::class, 'warehouse_id');
    }

    /**
     * Strictly scope warehouse stock queries to a single tenant company.
     */
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->whereHas('warehouse', function (Builder $q) use ($companyId) {
            $q->where('created_by', $companyId);
        });
    }
}
