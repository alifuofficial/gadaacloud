<?php

namespace Workdo\Hrm\Models;


use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Branch extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'branch_name',
        'creator_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            
        ];
    }




}