<?php

namespace Workdo\Performance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Traits\TenantScoped;

class PerformanceIndicatorCategory extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'name',
        'description',
        'status',
        'creator_id',
        'created_by',
    ];

    public function indicators()
    {
        return $this->hasMany(PerformanceIndicator::class, 'category_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}