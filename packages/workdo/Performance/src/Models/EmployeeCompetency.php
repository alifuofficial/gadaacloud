<?php

namespace Workdo\Performance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Traits\TenantScoped;

class EmployeeCompetency extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'employee_competencies';

    protected $fillable = [
        'employee_id',
        'competency_id',
        'review_cycle_id',
        'assessor_id',
        'rating',
        'assessment_date',
        'comments',
        'creator_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:2',
            'assessment_date' => 'date',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function assessor()
    {
        return $this->belongsTo(User::class, 'assessor_id');
    }

    public function competency()
    {
        return $this->belongsTo(Competency::class, 'competency_id');
    }

    public function reviewCycle()
    {
        return $this->belongsTo(PerformanceReviewCycle::class, 'review_cycle_id');
    }
}
