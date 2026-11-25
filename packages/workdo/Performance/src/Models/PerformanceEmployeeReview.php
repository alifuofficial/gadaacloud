<?php

namespace Workdo\Performance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Traits\TenantScoped;
use Workdo\Performance\Traits\PerformanceControllerHelpers;

class PerformanceEmployeeReview extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'performance_employee_reviews';

    protected $fillable = [
        'user_id',
        'reviewer_id',
        'review_cycle_id',
        'review_template_id',
        'review_date',
        'completion_date',
        'rating',
        'self_rating',
        'competency_ratings',
        'goal_score',
        'kpi_score',
        'competency_score',
        'overall_score',
        'weighted_rating',
        'rating_band',
        'pros',
        'cons',
        'self_pros',
        'self_cons',
        'self_submitted_at',
        'status',
        'workflow_stage',
        'is_locked',
        'calibrated_by',
        'calibrated_at',
        'calibration_notes',
        'acknowledged_at',
        'employee_acknowledgment_notes',
        'pip_recommended',
        'promotion_recommended',
        'bonus_recommendation_amount',
        'bonus_recommendation_type',
        'linked_action_type',
        'linked_action_id',
        'creator_id',
        'created_by',
    ];

    protected $appends = ['average_rating'];

    protected function casts(): array
    {
        return [
            'review_date' => 'date',
            'completion_date' => 'date',
            'self_submitted_at' => 'datetime',
            'calibrated_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'rating' => 'array',
            'self_rating' => 'array',
            'competency_ratings' => 'array',
            'goal_score' => 'decimal:2',
            'kpi_score' => 'decimal:2',
            'competency_score' => 'decimal:2',
            'overall_score' => 'decimal:2',
            'weighted_rating' => 'decimal:2',
            'bonus_recommendation_amount' => 'decimal:2',
            'is_locked' => 'boolean',
            'pip_recommended' => 'boolean',
            'promotion_recommended' => 'boolean',
        ];
    }

    /**
     * Safely resolve decoded rating map even for legacy double-encoded JSON rows.
     *
     * @return array<int|string, float|int>
     */
    public function getDecodedRatingsAttribute(): array
    {
        return PerformanceControllerHelpers::decodeRatingMap($this->rating);
    }

    /**
     * Map numeric weighted rating (1..5) to standard HR rating band label.
     */
    public static function resolveRatingBand(?float $ratingOutOfFive): ?string
    {
        if ($ratingOutOfFive === null || $ratingOutOfFive <= 0) {
            return null;
        }

        return match (true) {
            $ratingOutOfFive >= 4.5 => 'Outstanding',
            $ratingOutOfFive >= 3.75 => 'Exceeds Expectations',
            $ratingOutOfFive >= 2.75 => 'Meets Expectations',
            $ratingOutOfFive >= 1.75 => 'Needs Improvement',
            default => 'Unsatisfactory',
        };
    }

    public function getAverageRatingAttribute()
    {
        if ($this->weighted_rating !== null && (float) $this->weighted_rating > 0) {
            return round((float) $this->weighted_rating, 1);
        }

        $ratings = PerformanceControllerHelpers::decodeRatingMap($this->rating);
        if (empty($ratings)) {
            return null;
        }

        $ratedValues = array_filter($ratings, fn($r) => is_numeric($r) && (float) $r > 0);
        return !empty($ratedValues)
            ? round(array_sum($ratedValues) / count($ratedValues), 1)
            : null;
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function calibratedBy()
    {
        return $this->belongsTo(User::class, 'calibrated_by');
    }

    public function reviewCycle()
    {
        return $this->belongsTo(PerformanceReviewCycle::class, 'review_cycle_id');
    }

    public function reviewTemplate()
    {
        return $this->belongsTo(ReviewTemplate::class, 'review_template_id');
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
