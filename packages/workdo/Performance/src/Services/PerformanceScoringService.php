<?php

namespace Workdo\Performance\Services;

use Workdo\Performance\Models\EmployeeCompetency;
use Workdo\Performance\Models\PerformanceEmployeeGoal;
use Workdo\Performance\Models\PerformanceEmployeeReview;
use Workdo\Performance\Models\PerformanceIndicator;
use Workdo\Performance\Models\ReviewTemplate;
use Workdo\Performance\Traits\PerformanceControllerHelpers;

/**
 * Computes weighted KPI, goal, competency, composite appraisal scores, and 9-box placement.
 */
class PerformanceScoringService
{
    /**
     * Compute weighted KPI, Goal, Competency, and Composite scores for an appraisal.
     *
     * @param  array<int|string, float|int>  $indicatorRatings
     * @param  array<int|string, float|int>  $competencyRatings
     * @return array{kpi_score: ?float, goal_score: ?float, competency_score: ?float, weighted_rating: ?float, overall_score: ?float, rating_band: ?string}
     */
    public function computeReviewScores(
        PerformanceEmployeeReview $review,
        array $indicatorRatings,
        array $competencyRatings = []
    ): array {
        $template = $review->reviewTemplate;
        $tenantId = $review->created_by ?? creatorId();

        // 1. KPI score (1..5 weighted average across rated indicators)
        $kpiRating = $this->computeWeightedIndicatorRating($indicatorRatings, $template, (int) $tenantId);

        // 2. Goal achievement score (0..100 -> converted to 1..5 scale)
        $goalScorePct = $this->computeEmployeeGoalScore($review, (int) $tenantId);
        $goalRating = $goalScorePct !== null ? round(1 + ($goalScorePct / 100) * 4, 2) : null;

        // 3. Competency score (1..5 average)
        $competencyRating = $this->computeCompetencyScore($review, $competencyRatings, (int) $tenantId);

        // Blend components based on template weights (default 60/20/20 across available signals)
        $weights = [
            'kpi' => $template ? (float) $template->kpi_weight : 60.0,
            'goal' => $template ? (float) $template->goal_weight : 20.0,
            'competency' => $template ? (float) $template->competency_weight : 20.0,
        ];

        $components = [];
        if ($kpiRating !== null && $weights['kpi'] > 0) {
            $components[] = ['rating' => $kpiRating, 'weight' => $weights['kpi']];
        }
        if ($goalRating !== null && $weights['goal'] > 0) {
            $components[] = ['rating' => $goalRating, 'weight' => $weights['goal']];
        }
        if ($competencyRating !== null && $weights['competency'] > 0) {
            $components[] = ['rating' => $competencyRating, 'weight' => $weights['competency']];
        }

        $weightedRating = null;
        if (!empty($components)) {
            $totalWeight = array_sum(array_column($components, 'weight'));
            if ($totalWeight > 0) {
                $sum = 0.0;
                foreach ($components as $comp) {
                    $sum += $comp['rating'] * $comp['weight'];
                }
                $weightedRating = round($sum / $totalWeight, 2);
            }
        } elseif ($kpiRating !== null) {
            $weightedRating = $kpiRating;
        }

        $overallScore = $weightedRating !== null ? round(($weightedRating / 5) * 100, 2) : null;

        return [
            'kpi_score' => $kpiRating !== null ? round(($kpiRating / 5) * 100, 2) : null,
            'goal_score' => $goalScorePct,
            'competency_score' => $competencyRating !== null ? round(($competencyRating / 5) * 100, 2) : null,
            'weighted_rating' => $weightedRating,
            'overall_score' => $overallScore,
            'rating_band' => PerformanceEmployeeReview::resolveRatingBand($weightedRating),
        ];
    }

    private function computeWeightedIndicatorRating(
        array $ratings,
        ?ReviewTemplate $template,
        int $tenantId
    ): ?float {
        $validRatings = [];
        foreach ($ratings as $id => $value) {
            if (is_numeric($value) && (float) $value > 0) {
                $validRatings[(int) $id] = (float) $value;
            }
        }

        if (empty($validRatings)) {
            return null;
        }

        $indicators = PerformanceIndicator::where('created_by', $tenantId)
            ->whereIn('id', array_keys($validRatings))
            ->get()
            ->keyBy('id');

        $templateWeights = [];
        if ($template) {
            $pivotWeights = $template->indicators()->pluck('review_template_indicators.weight', 'performance_indicators.id')->toArray();
            $jsonWeights = is_array($template->indicator_weights) ? $template->indicator_weights : [];
            $templateWeights = $pivotWeights + $jsonWeights;
        }

        $weightedSum = 0.0;
        $weightTotal = 0.0;
        foreach ($validRatings as $indicatorId => $score) {
            $weight = (float) ($templateWeights[$indicatorId] ?? ($indicators->get($indicatorId)->weight ?? 0));
            $effectiveWeight = $weight > 0 ? $weight : 1.0;
            $weightedSum += $score * $effectiveWeight;
            $weightTotal += $effectiveWeight;
        }

        return $weightTotal > 0 ? round($weightedSum / $weightTotal, 2) : null;
    }

    private function computeEmployeeGoalScore(PerformanceEmployeeReview $review, int $tenantId): ?float
    {
        $goalsQuery = PerformanceEmployeeGoal::where('created_by', $tenantId)
            ->where('employee_id', $review->user_id);

        if ($review->review_cycle_id) {
            $cycleGoals = (clone $goalsQuery)->where('review_cycle_id', $review->review_cycle_id)->get();
            $goals = $cycleGoals->isNotEmpty() ? $cycleGoals : $goalsQuery->get();
        } else {
            $goals = $goalsQuery->get();
        }

        if ($goals->isEmpty()) {
            return null;
        }

        $weightedSum = 0.0;
        $weightTotal = 0.0;
        foreach ($goals as $goal) {
            $w = (float) $goal->weight > 0 ? (float) $goal->weight : 1.0;
            $weightedSum += (float) $goal->progress_percentage * $w;
            $weightTotal += $w;
        }

        return $weightTotal > 0 ? round($weightedSum / $weightTotal, 2) : null;
    }

    private function computeCompetencyScore(
        PerformanceEmployeeReview $review,
        array $competencyRatings,
        int $tenantId
    ): ?float {
        $valid = array_filter($competencyRatings, fn($v) => is_numeric($v) && (float) $v > 0);
        if (!empty($valid)) {
            return round(array_sum(array_map('floatval', $valid)) / count($valid), 2);
        }

        $query = EmployeeCompetency::where('created_by', $tenantId)
            ->where('employee_id', $review->user_id);
        if ($review->review_cycle_id) {
            $cycleRecords = (clone $query)->where('review_cycle_id', $review->review_cycle_id)->get();
            $records = $cycleRecords->isNotEmpty() ? $cycleRecords : $query->get();
        } else {
            $records = $query->get();
        }

        if ($records->isEmpty()) {
            return null;
        }

        // Normalize 0..10 stored ratings to 1..5 if needed
        $normalized = $records->map(function ($r) {
            $val = (float) $r->rating;
            return $val > 5 ? round($val / 2, 2) : $val;
        })->filter(fn($v) => $v > 0);

        return $normalized->isNotEmpty() ? round($normalized->avg(), 2) : null;
    }

    /**
     * Classify an appraisal into the 9-box talent grid (low/medium/high for performance & potential).
     *
     * @return array{performance_tier: string, potential_tier: string, box_label: string}
     */
    public function classifyNineBox(PerformanceEmployeeReview $review): array
    {
        $perf = (float) ($review->kpi_score ?? (($review->average_rating ?? 0) / 5 * 100));
        $pot = (float) ($review->competency_score ?? $perf);

        $perfTier = $perf >= 80 ? 'high' : ($perf >= 55 ? 'medium' : 'low');
        $potTier = $pot >= 80 ? 'high' : ($pot >= 55 ? 'medium' : 'low');

        $labels = [
            'high_high' => 'Top Talent / Star',
            'high_medium' => 'High Performer',
            'high_low' => 'Consistent Specialist',
            'medium_high' => 'Rising Potential',
            'medium_medium' => 'Core Contributor',
            'medium_low' => 'Solid Performer',
            'low_high' => 'Rough Diamond (Coaching)',
            'low_medium' => 'Inconsistent / At Risk',
            'low_low' => 'Underperformer / PIP',
        ];

        return [
            'performance_tier' => $perfTier,
            'potential_tier' => $potTier,
            'box_label' => $labels["{$perfTier}_{$potTier}"] ?? 'Core Contributor',
        ];
    }
}
