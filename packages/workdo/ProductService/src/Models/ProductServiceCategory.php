<?php

namespace Workdo\ProductService\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductServiceCategory extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'name',
        'color',
        'creator_id',
        'created_by',
    ];
}
