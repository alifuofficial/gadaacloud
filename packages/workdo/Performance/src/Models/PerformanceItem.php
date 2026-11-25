<?php

namespace Workdo\Performance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Traits\TenantScoped;

class PerformanceItem extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'performance_items';

    protected $fillable = [
        'name',
        'description',
        'rating_scale_max',
        'weight',
        'is_active',
        'creator_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'rating_scale_max' => 'integer',
            'weight' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
}
