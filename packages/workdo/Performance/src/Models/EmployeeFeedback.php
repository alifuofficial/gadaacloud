<?php

namespace Workdo\Performance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Traits\TenantScoped;

class EmployeeFeedback extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'employee_feedback';

    protected $fillable = [
        'employee_id',
        'given_by',
        'feedback_type_id',
        'review_cycle_id',
        'relationship_type',
        'date',
        'rating',
        'content',
        'visibility',
        'is_anonymous',
        'creator_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'rating' => 'integer',
            'is_anonymous' => 'boolean',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function giver()
    {
        return $this->belongsTo(User::class, 'given_by');
    }

    public function feedbackType()
    {
        return $this->belongsTo(FeedbackType::class, 'feedback_type_id');
    }

    public function reviewCycle()
    {
        return $this->belongsTo(PerformanceReviewCycle::class, 'review_cycle_id');
    }
}
