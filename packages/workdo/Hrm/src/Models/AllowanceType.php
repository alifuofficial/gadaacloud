<?php

namespace Workdo\Hrm\Models;


use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class AllowanceType extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'name',
        'description',
        'creator_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            
        ];
    }




}