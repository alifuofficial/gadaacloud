<?php

namespace Workdo\Performance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Traits\TenantScoped;

class PerformanceIndicator extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'category_id',
        'department_id',
        'designation_id',
        'name',
        'description',
        'measurement_unit',
        'target_value',
        'weight',
        'goal_direction',
        'kpi_source',
        'status',
        'creator_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
        ];
    }

    public function category()
    {
        return $this->belongsTo(PerformanceIndicatorCategory::class, 'category_id');
    }

    public function department()
    {
        return $this->belongsTo(\Workdo\Hrm\Models\Department::class, 'department_id');
    }

    public function designation()
    {
        return $this->belongsTo(\Workdo\Hrm\Models\Designation::class, 'designation_id');
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
