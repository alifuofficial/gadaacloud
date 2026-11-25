<?php

namespace Workdo\Performance\Http\Controllers;

use App\Models\User;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Performance\Models\Competency;
use Workdo\Performance\Models\EmployeeFeedback;
use Workdo\Performance\Models\PerformanceEmployeeGoal;
use Workdo\Performance\Models\PerformanceEmployeeReview;
use Workdo\Performance\Models\PerformanceIndicator;
use Workdo\Performance\Models\PerformanceReviewCycle;
use Workdo\Performance\Models\ReviewTemplate;
use Workdo\Performance\Services\PerformanceErpIntegrationService;
use Workdo\Performance\Services\PerformanceScoringService;

class DashboardController extends Controller
{
    public function __construct(
        private PerformanceScoringService $scoringService,
        private PerformanceErpIntegrationService $erpService
    ) {}

    public function index()
    {
        if (!Auth::user()->can('manage-performance')) {
            return back()->with('error', __('Permission denied'));
        }

        $tenantId = (int) creatorId();
        $isOwnOnly = !Auth::user()->can('manage-any-employee-reviews') && Auth::user()->can('manage-own-employee-reviews');

        $reviewsQuery = PerformanceEmployeeReview::with(['user:id,name', 'reviewer:id,name', 'reviewCycle:id,name'])
            ->where('created_by', $tenantId)
            ->when($isOwnOnly, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('user_id', Auth::id())
                        ->orWhere('reviewer_id', Auth::id())
                        ->orWhere('creator_id', Auth::id());
                });
            });

        $reviews = (clone $reviewsQuery)->latest()->get();

        $goalsQuery = PerformanceEmployeeGoal::with(['employee:id,name', 'goalType:id,name'])
            ->where('created_by', $tenantId)
            ->when(!Auth::user()->can('manage-any-employee-goals') && Auth::user()->can('manage-own-employee-goals'), function ($q) {
                $q->where(function ($sub) {
                    $sub->where('employee_id', Auth::id())->orWhere('creator_id', Auth::id());
                });
            });

        $goals = (clone $goalsQuery)->latest()->get();

        $completedReviews = $reviews->where('status', 'completed');
        $avgOverallRating = $completedReviews->count() > 0
            ? round((float) $completedReviews->avg(fn($r) => $r->average_rating ?? 0), 2)
            : 0.0;

        // Rating band bell-curve distribution
        $bands = [
            'Outstanding' => 0,
            'Exceeds Expectations' => 0,
            'Meets Expectations' => 0,
            'Needs Improvement' => 0,
            'Unsatisfactory' => 0,
        ];
        $nineBoxMatrix = [];
        foreach (['high', 'medium', 'low'] as $pot) {
            foreach (['low', 'medium', 'high'] as $perf) {
                $nineBoxMatrix["{$pot}_{$perf}"] = [
                    'key' => "{$pot}_{$perf}",
                    'potential_tier' => $pot,
                    'performance_tier' => $perf,
                    'count' => 0,
                    'employees' => [],
                ];
            }
        }

        foreach ($completedReviews as $review) {
            $band = $review->rating_band ?: PerformanceEmployeeReview::resolveRatingBand((float) ($review->average_rating ?? 0));
            if ($band && isset($bands[$band])) {
                $bands[$band]++;
            }
            $box = $this->scoringService->classifyNineBox($review);
            $key = "{$box['potential_tier']}_{$box['performance_tier']}";
            if (isset($nineBoxMatrix[$key])) {
                $nineBoxMatrix[$key]['count']++;
                if (count($nineBoxMatrix[$key]['employees']) < 6) {
                    $nineBoxMatrix[$key]['employees'][] = [
                        'id' => $review->id,
                        'name' => $review->user?->name ?? 'Employee',
                        'rating' => $review->average_rating,
                        'box_label' => $box['box_label'],
                    ];
                }
            }
        }

        $overdueGoals = $goals->filter(function ($g) {
            return $g->status === 'overdue'
                || ($g->status !== 'completed' && $g->end_date && $g->end_date->isPast());
        })->take(6)->values();

        $ethYear = null;
        if (class_exists(\Workdo\EthiopianCalendar\Services\EthiopianDateService::class)) {
            try {
                $ethYear = app(\Workdo\EthiopianCalendar\Services\EthiopianDateService::class)->getCurrentFiscalYear();
            } catch (\Throwable $e) {
                $ethYear = null;
            }
        }

        return Inertia::render('Performance/Dashboard/Index', [
            'stats' => [
                'total_employees' => User::emp()->where('created_by', $tenantId)->count(),
                'total_indicators' => PerformanceIndicator::where('created_by', $tenantId)->where('status', 'active')->count(),
                'active_cycles' => PerformanceReviewCycle::where('created_by', $tenantId)->where('status', 'active')->count(),
                'total_reviews' => $reviews->count(),
                'completed_reviews' => $completedReviews->count(),
                'completion_rate' => $reviews->count() > 0 ? round(($completedReviews->count() / $reviews->count()) * 100, 1) : 0,
                'average_rating' => $avgOverallRating,
                'total_goals' => $goals->count(),
                'completed_goals' => $goals->where('status', 'completed')->count(),
                'overdue_goals_count' => $overdueGoals->count(),
                'competencies_count' => Competency::where('created_by', $tenantId)->where('status', 'active')->count(),
                'feedback_count' => EmployeeFeedback::where('created_by', $tenantId)->count(),
                'templates_count' => ReviewTemplate::where('created_by', $tenantId)->where('status', 'active')->count(),
            ],
            'ratingDistribution' => collect($bands)->map(fn($count, $label) => ['band' => $label, 'count' => $count])->values(),
            'workflowBreakdown' => [
                ['stage' => 'Pending / Self Assessment', 'count' => $reviews->whereIn('status', ['pending', 'self_submitted'])->count()],
                ['stage' => 'In Progress', 'count' => $reviews->where('status', 'in_progress')->count()],
                ['stage' => 'Completed', 'count' => $completedReviews->count()],
                ['stage' => 'Locked / Calibrated', 'count' => $reviews->where('is_locked', true)->count()],
            ],
            'nineBoxGrid' => array_values($nineBoxMatrix),
            'overdueGoals' => $overdueGoals,
            'recentReviews' => $reviews->take(6)->values(),
            'ethiopianFiscalYear' => $ethYear,
            'hrmContext' => $this->erpService->getHrmContext($tenantId),
        ]);
    }
}
