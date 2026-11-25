<?php

namespace Workdo\Goal\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Workdo\Goal\Models\Goal;
use Workdo\Goal\Models\GoalContribution;
use Workdo\Goal\Models\GoalMilestone;
use Workdo\Goal\Traits\GoalControllerHelpers;

class DashboardController extends Controller
{
    use GoalControllerHelpers;

    public function index()
    {
        if (!Auth::user()->can('manage-goals')) {
            return back()->with('error', __('Permission denied'));
        }

        $tenantId = creatorId();

        // 1. Key Metrics
        $totalTarget = (float) Goal::where('created_by', $tenantId)->sum('target_amount');
        $totalAchieved = (float) Goal::where('created_by', $tenantId)->sum('current_amount');
        $overallProgress = $totalTarget > 0 ? min(100.0, round(($totalAchieved / $totalTarget) * 100, 1)) : 0.0;

        $activeGoalsCount = Goal::where('created_by', $tenantId)->where('status', 'active')->count();
        $completedGoalsCount = Goal::where('created_by', $tenantId)->where('status', 'completed')->count();
        $pausedGoalsCount = Goal::where('created_by', $tenantId)->where('status', 'paused')->count();

        // 2. Goal Type Breakdown
        $typeBreakdown = Goal::where('created_by', $tenantId)
            ->select('goal_type', DB::raw('count(*) as count'), DB::raw('sum(target_amount) as total_target'), DB::raw('sum(current_amount) as total_current'))
            ->groupBy('goal_type')
            ->get();

        // 3. At-risk goals (behind or critical tracking or overdue)
        $atRiskGoals = Goal::where('created_by', $tenantId)
            ->whereIn('status', ['active', 'draft'])
            ->with(['category', 'tracking' => fn($q) => $q->latest()->limit(1)])
            ->get()
            ->filter(function ($goal) {
                $latestTracking = $goal->tracking->first();
                $isPaceCritical = $latestTracking && in_array($latestTracking->on_track_status, ['behind', 'critical']);
                $isOverdue = Carbon::parse($goal->target_date)->isPast() && $goal->current_amount < $goal->target_amount;
                return $isPaceCritical || $isOverdue;
            })
            ->values()
            ->take(5);

        // 4. Upcoming Milestones
        $upcomingMilestones = GoalMilestone::where('created_by', $tenantId)
            ->where('status', 'pending')
            ->whereDate('target_date', '>=', now())
            ->with('goal:id,goal_name,status')
            ->orderBy('target_date', 'asc')
            ->take(5)
            ->get();

        // 5. Recent Contributions (Last 6 months trend)
        $monthlyTrend = GoalContribution::where('created_by', $tenantId)
            ->whereDate('contribution_date', '>=', now()->subMonths(6)->startOfMonth())
            ->selectRaw('DATE_FORMAT(contribution_date, "%Y-%m") as month, sum(contribution_amount) as total')
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get();

        // 6. Ethiopian Fiscal Year Helper (Hamle 1 - Sene 30)
        $now = Carbon::now();
        $ethiopianYear = ($now->month > 7 || ($now->month == 7 && $now->day >= 8))
            ? $now->year - 7
            : $now->year - 8;
        $ethiopianFiscalYear = "{$ethiopianYear} E.C.";

        return Inertia::render('Goal/Dashboard/Index', [
            'metrics' => [
                'totalTarget'         => $totalTarget,
                'totalAchieved'       => $totalAchieved,
                'overallProgress'     => $overallProgress,
                'activeGoalsCount'    => $activeGoalsCount,
                'completedGoalsCount' => $completedGoalsCount,
                'pausedGoalsCount'    => $pausedGoalsCount,
                'atRiskCount'         => $atRiskGoals->count(),
                'ethiopianFiscalYear' => $ethiopianFiscalYear,
            ],
            'typeBreakdown'      => $typeBreakdown,
            'atRiskGoals'        => $atRiskGoals,
            'upcomingMilestones' => $upcomingMilestones,
            'monthlyTrend'       => $monthlyTrend,
        ]);
    }
}
