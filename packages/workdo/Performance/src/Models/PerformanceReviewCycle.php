<?php

namespace Workdo\Performance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Traits\TenantScoped;

class PerformanceReviewCycle extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'performance_review_cycles';

    protected $fillable = [
        'name',
        'frequency',
        'start_date',
        'end_date',
        'self_review_deadline',
        'manager_review_deadline',
        'calendar_type',
        'ethiopian_fiscal_year',
        'ethiopian_quarter',
        'branch_id',
        'department_id',
        'description',
        'status',
        'is_locked',
        'creator_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'self_review_deadline' => 'date',
            'manager_review_deadline' => 'date',
            'ethiopian_fiscal_year' => 'integer',
            'is_locked' => 'boolean',
        ];
    }

    public function employeeReviews()
    {
        return $this->hasMany(PerformanceEmployeeReview::class, 'review_cycle_id');
    }

    public function employeeGoals()
    {
        return $this->hasMany(PerformanceEmployeeGoal::class, 'review_cycle_id');
    }

    public function branch()
    {
        return $this->belongsTo(\Workdo\Hrm\Models\Branch::class, 'branch_id');
    }

    public function department()
    {
        return $this->belongsTo(\Workdo\Hrm\Models\Department::class, 'department_id');
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
