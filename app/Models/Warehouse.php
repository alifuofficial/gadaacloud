<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantScoped;

class Warehouse extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'name',
        'address',
        'city',
        'zip_code',
        'phone',
        'email',
        'is_active',
        'creator_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
