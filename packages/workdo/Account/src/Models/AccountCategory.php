<?php

namespace Workdo\Account\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\TenantScoped;

class AccountCategory extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'name',
        'code',
        'type',
        'description',
        'is_active',
        'creator_id',
        'created_by'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function accountTypes()
    {
        return $this->hasMany(AccountType::class, 'category_id');
    }
}