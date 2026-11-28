<?php

namespace Workdo\Performance\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Performance\Events\CreatePerformanceIndicator;
use Workdo\Performance\Events\DestroyPerformanceIndicator;
use Workdo\Performance\Events\UpdatePerformanceIndicator;
use Workdo\Performance\Http\Requests\StorePerformanceIndicatorRequest;
use Workdo\Performance\Http\Requests\UpdatePerformanceIndicatorRequest;
use Workdo\Performance\Models\PerformanceIndicator;
use Workdo\Performance\Models\PerformanceIndicatorCategory;
use Workdo\Performance\Services\PerformanceErpIntegrationService;
use Workdo\Performance\Traits\PerformanceControllerHelpers;

class PerformanceIndicatorController extends Controller
{
    use PerformanceControllerHelpers;

    public function __construct(private PerformanceErpIntegrationService $erpService) {}

    public function index()
    {
        if (!Auth::user()->can('manage-performance-indicators')) {
            return back()->with('error', __('Permission denied'));
        }

        $query = PerformanceIndicator::with(['category', 'department', 'designation'])
            ->where('performance_indicators.created_by', creatorId())
            ->where(function ($q) {
                if (Auth::user()->can('manage-any-performance-indicators')) {
                    // Already scoped to creatorId()
                } elseif (Auth::user()->can('manage-own-performance-indicators')) {
                    $q->where('performance_indicators.creator_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->when(request('name'), fn($q) => $q->where('performance_indicators.name', 'like', '%' . request('name') . '%'))
            ->when(request('category_id'), fn($q) => $q->where('performance_indicators.category_id', request('category_id')))
            ->when(request('department_id'), fn($q) => $q->where('performance_indicators.department_id', request('department_id')))
            ->when(request('measurement_unit'), fn($q) => $q->where('performance_indicators.measurement_unit', 'like', '%' . request('measurement_unit') . '%'))
            ->when(request('status') !== null && request('status') !== '', fn($q) => $q->where('performance_indicators.status', request('status')));

        $indicators = $this->applySafeSort($query, [
            'name' => 'performance_indicators.name',
            'measurement_unit' => 'performance_indicators.measurement_unit',
            'target_value' => 'performance_indicators.target_value',
            'weight' => 'performance_indicators.weight',
            'status' => 'performance_indicators.status',
            'created_at' => 'performance_indicators.created_at',
            'category' => function ($q, $direction) {
                return $q->join('performance_indicator_categories', 'performance_indicators.category_id', '=', 'performance_indicator_categories.id')
                    ->orderBy('performance_indicator_categories.name', $direction)
                    ->select('performance_indicators.*');
            },
        ])
            ->paginate($this->safePerPage())
            ->withQueryString();

        return Inertia::render('Performance/Indicators/Index', [
            'indicators' => $indicators,
            'categories' => $this->getFilteredIndicatorCategories(),
            'hrmContext' => $this->erpService->getHrmContext((int) creatorId()),
        ]);
    }

    public function store(StorePerformanceIndicatorRequest $request)
    {
        if (!Auth::user()->can('create-performance-indicators')) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        $indicator = new PerformanceIndicator();
        $indicator->category_id = $validated['category_id'];
        $indicator->department_id = $validated['department_id'] ?? null;
        $indicator->designation_id = $validated['designation_id'] ?? null;
        $indicator->name = $validated['name'];
        $indicator->description = \App\Support\HtmlSanitizer::sanitize($validated['description'] ?? null);
        $indicator->measurement_unit = $validated['measurement_unit'];
        $indicator->target_value = $validated['target_value'] ?? null;
        $indicator->weight = $validated['weight'] ?? 0;
        $indicator->goal_direction = $validated['goal_direction'] ?? 'higher_is_better';
        $indicator->kpi_source = $validated['kpi_source'] ?? 'manual';
        $indicator->status = $validated['status'] ?? 'active';
        $indicator->creator_id = Auth::id();
        $indicator->created_by = creatorId();
        $indicator->save();

        CreatePerformanceIndicator::dispatch($request, $indicator);

        return back()->with('success', __('The performance indicator has been created successfully.'));
    }

    public function update(UpdatePerformanceIndicatorRequest $request, PerformanceIndicator $indicator)
    {
        if (!Auth::user()->can('edit-performance-indicators')) {
            return back()->with('error', __('Permission denied'));
        }
        if (!$this->canAccessIndicator($indicator)) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        $indicator->category_id = $validated['category_id'];
        $indicator->department_id = $validated['department_id'] ?? null;
        $indicator->designation_id = $validated['designation_id'] ?? null;
        $indicator->name = $validated['name'];
        $indicator->description = \App\Support\HtmlSanitizer::sanitize($validated['description'] ?? null);
        $indicator->measurement_unit = $validated['measurement_unit'];
        $indicator->target_value = $validated['target_value'] ?? null;
        $indicator->weight = $validated['weight'] ?? 0;
        $indicator->goal_direction = $validated['goal_direction'] ?? 'higher_is_better';
        $indicator->kpi_source = $validated['kpi_source'] ?? 'manual';
        $indicator->status = $validated['status'] ?? 'active';
        $indicator->save();

        UpdatePerformanceIndicator::dispatch($request, $indicator);

        return back()->with('success', __('The performance indicator details are updated successfully.'));
    }

    public function destroy(PerformanceIndicator $indicator)
    {
        if (!Auth::user()->can('delete-performance-indicators')) {
            return back()->with('error', __('Permission denied'));
        }
        if (!$this->canAccessIndicator($indicator)) {
            return back()->with('error', __('Permission denied'));
        }

        DestroyPerformanceIndicator::dispatch($indicator);
        $indicator->delete();

        return back()->with('success', __('The performance indicator has been deleted.'));
    }

    private function canAccessIndicator(PerformanceIndicator $indicator): bool
    {
        if ((int) $indicator->created_by !== (int) creatorId()) {
            return false;
        }
        if (Auth::user()->can('manage-any-performance-indicators')) {
            return true;
        }
        if (Auth::user()->can('manage-own-performance-indicators')) {
            return (int) $indicator->creator_id === (int) Auth::id();
        }
        return false;
    }

    private function getFilteredIndicatorCategories()
    {
        return PerformanceIndicatorCategory::where('created_by', creatorId())
            ->where('status', 'active')
            ->when(!Auth::user()->can('manage-any-performance-indicator-categories'), function ($q) {
                if (Auth::user()->can('manage-own-performance-indicator-categories')) {
                    $q->where('creator_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->select('id', 'name')
            ->get();
    }
}
