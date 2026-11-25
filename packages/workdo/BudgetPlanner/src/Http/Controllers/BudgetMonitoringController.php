<?php

namespace Workdo\BudgetPlanner\Http\Controllers;


use Workdo\BudgetPlanner\Traits\BudgetPlannerControllerHelpers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Workdo\BudgetPlanner\Models\BudgetMonitoring;
use Workdo\BudgetPlanner\Models\Budget;
use Illuminate\Support\Facades\Auth;

class BudgetMonitoringController extends Controller
{
    use BudgetPlannerControllerHelpers;
    public function index()
    {
        if(Auth::user()->can('manage-budget-monitoring')){
            $budgetMonitorings = BudgetMonitoring::query()
                ->with(['budget'])
                ->where(function($q) {
                    if(Auth::user()->can('manage-own-budgets')) {
                        $q->whereHas('budget', fn($query) => $query->where('creator_id', Auth::id()));
                    }
                })

                ->when(request('search'), function($q) {
                    $search = request('search');
                    $q->whereHas('budget', fn($query) => $query->where('budget_name', 'like', "%{$search}%"));
                })
                ->when(request('budget_id'), fn($q) => $q->whereHas('budget', fn($query) => $query->where('id', request('budget_id'))))
                ->when(request('date_from'), fn($q) => $q->whereDate('monitoring_date', '>=', request('date_from')))
                ->when(request('date_to'), fn($q) => $q->whereDate('monitoring_date', '<=', request('date_to')))
                ->when(true, fn($q) => $this->applySafeSort($q, ['monitoring_date' => 'monitoring_date', 'total_allocated' => 'total_allocated', 'total_spent' => 'total_spent', 'variance_amount' => 'variance_amount', 'created_at' => 'created_at'], 'monitoring_date'))
                ->paginate($this->safePerPage(10, 100))
                ->withQueryString();

            $budgets = Budget::where('created_by', creatorId())
                ->select('id', 'budget_name')
                ->get();

            return Inertia::render('BudgetPlanner/BudgetMonitorings/Index', [
                'budgetMonitorings' => $budgetMonitorings,
                'budgets' => $budgets
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }
}
