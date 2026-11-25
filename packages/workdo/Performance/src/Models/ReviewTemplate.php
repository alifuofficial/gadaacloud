<?php

namespace Workdo\Performance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Traits\TenantScoped;

class ReviewTemplate extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'review_templates';

    protected $fillable = [
        'name',
        'description',
        'branch_id',
        'department_id',
        'designation_id',
        'kpi_weight',
        'goal_weight',
        'competency_weight',
        'include_self_assessment',
        'indicator_weights',
        'status',
        'creator_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'kpi_weight' => 'decimal:2',
            'goal_weight' => 'decimal:2',
            'competency_weight' => 'decimal:2',
            'include_self_assessment' => 'boolean',
            'indicator_weights' => 'array',
        ];
    }

    public function indicators()
    {
        return $this->belongsToMany(
            PerformanceIndicator::class,
            'review_template_indicators',
            'review_template_id',
            'performance_indicator_id'
        )->withPivot('weight')->withTimestamps();
    }

    public function branch()
    {
        return $this->belongsTo(\Workdo\Hrm\Models\Branch::class, 'branch_id');
    }

    public function department()
    {
        return $this->belongsTo(\Workdo\Hrm\Models\Department::class, 'department_id');
    }

    public function designation()
    {
        return $this->belongsTo(\Workdo\Hrm\Models\Designation::class, 'designation_id');
    }

    public function employeeReviews()
    {
        return $this->hasMany(PerformanceEmployeeReview::class, 'review_template_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
}
