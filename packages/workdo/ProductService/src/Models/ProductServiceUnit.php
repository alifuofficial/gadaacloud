<?php

namespace Workdo\ProductService\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductServiceUnit extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'unit_name',
        'creator_id',
        'created_by',
    ];
}
