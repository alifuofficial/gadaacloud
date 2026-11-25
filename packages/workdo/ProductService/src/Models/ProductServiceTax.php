<?php

namespace Workdo\ProductService\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductServiceTax extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'tax_name',
        'rate',
        'creator_id',
        'created_by',
    ];
}
