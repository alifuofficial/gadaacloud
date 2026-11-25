<?php

namespace Workdo\ProductService\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductServiceItem extends Model
{
    use HasFactory, SoftDeletes, TenantScoped;

    protected $fillable = [
        'name',
        'sku',
        'barcode',
        'hs_code',
        'tax_ids',
        'tax_classification',
        'excise_rate',
        'is_tax_inclusive',
        'category_id',
        'description',
        'long_description',
        'sale_price',
        'min_sale_price',
        'wholesale_price',
        'wholesale_min_qty',
        'purchase_price',
        'sale_chartaccount_id',
        'expense_chartaccount_id',
        'inventory_chartaccount_id',
        'unit',
        'purchase_unit_id',
        'unit_conversion_factor',
        'reorder_level',
        'image',
        'images',
        'type',
        'inventory_type',
        'is_active',
        'creator_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_tax_inclusive' => 'boolean',
            'tax_ids' => 'array',
            'images' => 'array',
            'sale_price' => 'decimal:2',
            'min_sale_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'wholesale_min_qty' => 'decimal:2',
            'purchase_price' => 'decimal:2',
            'excise_rate' => 'decimal:2',
            'unit_conversion_factor' => 'decimal:4',
            'reorder_level' => 'decimal:4',
        ];
    }

    /**
     * Safely handle both modern JSON-array casts and legacy double-encoded strings.
     */
    protected function images(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (is_array($value)) {
                    return $value;
                }
                if (is_string($value) && $value !== '') {
                    $decoded = json_decode($value, true);
                    if (is_string($decoded)) {
                        $decoded = json_decode($decoded, true);
                    }
                    return is_array($decoded) ? $decoded : [];
                }
                return [];
            },
            set: function ($value) {
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    return json_encode(is_array($decoded) ? $decoded : []);
                }
                return json_encode(is_array($value) ? array_values($value) : []);
            }
        );
    }

    public function category()
    {
        return $this->belongsTo(ProductServiceCategory::class, 'category_id');
    }

    public function unitRelation()
    {
        return $this->belongsTo(ProductServiceUnit::class, 'unit', 'id');
    }

    public function purchaseUnit()
    {
        return $this->belongsTo(ProductServiceUnit::class, 'purchase_unit_id', 'id');
    }

    public function getTaxesAttribute()
    {
        $taxIds = $this->tax_ids;

        if (is_string($taxIds)) {
            $taxIds = json_decode($taxIds, true);
        }

        if (empty($taxIds) || !is_array($taxIds)) {
            return collect([]);
        }

        return ProductServiceTax::whereIn('id', $taxIds)->get();
    }

    public function warehouseStocks()
    {
        return $this->hasMany(WarehouseStock::class, 'product_id');
    }
}
