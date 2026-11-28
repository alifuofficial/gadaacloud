<?php

namespace Workdo\Performance\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Performance\Events\CreateGoalType;
use Workdo\Performance\Events\DestroyGoalType;
use Workdo\Performance\Events\UpdateGoalType;
use Workdo\Performance\Http\Requests\StoreGoalTypeRequest;
use Workdo\Performance\Http\Requests\UpdateGoalTypeRequest;
use Workdo\Performance\Models\PerformanceGoalType;

class GoalTypeController extends Controller
{
    public function index()
    {
        if (!Auth::user()->can('manage-goal-types')) {
            return back()->with('error', __('Permission denied'));
        }

        $goalTypes = PerformanceGoalType::select('id', 'name', 'description', 'status', 'created_at')
            ->where('created_by', creatorId())
            ->where(function ($q) {
                if (Auth::user()->can('manage-any-goal-types')) {
                    // Already scoped to creatorId()
                } elseif (Auth::user()->can('manage-own-goal-types')) {
                    $q->where('creator_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->latest()
            ->get();

        return Inertia::render('Performance/SystemSetup/GoalTypes/Index', [
            'goalTypes' => $goalTypes,
        ]);
    }

    public function store(StoreGoalTypeRequest $request)
    {
        if (!Auth::user()->can('create-goal-types')) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        $goalType = new PerformanceGoalType();
        $goalType->name = $validated['name'];
        $goalType->description = \App\Support\HtmlSanitizer::sanitize($validated['description'] ?? null);
        $goalType->status = $validated['status'] ?? 'active';
        $goalType->creator_id = Auth::id();
        $goalType->created_by = creatorId();
        $goalType->save();

        CreateGoalType::dispatch($request, $goalType);

        return back()->with('success', __('The goal type has been created successfully.'));
    }

    public function update(UpdateGoalTypeRequest $request, PerformanceGoalType $goalType)
    {
        if (!Auth::user()->can('edit-goal-types') || !$this->canAccessGoalType($goalType)) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        $goalType->name = $validated['name'];
        $goalType->description = \App\Support\HtmlSanitizer::sanitize($validated['description'] ?? null);
        $goalType->status = $validated['status'] ?? 'active';
        $goalType->save();

        UpdateGoalType::dispatch($request, $goalType);

        return back()->with('success', __('The goal type details are updated successfully.'));
    }

    public function destroy(PerformanceGoalType $goalType)
    {
        if (!Auth::user()->can('delete-goal-types') || !$this->canAccessGoalType($goalType)) {
            return back()->with('error', __('Permission denied'));
        }

        if ($goalType->employeeGoals()->exists()) {
            return back()->with('error', __('Cannot delete goal type because active employee goals reference it.'));
        }

        DestroyGoalType::dispatch($goalType);
        $goalType->delete();

        return back()->with('success', __('The goal type has been deleted.'));
    }

    private function canAccessGoalType(PerformanceGoalType $goalType): bool
    {
        if ((int) $goalType->created_by !== (int) creatorId()) {
            return false;
        }
        if (Auth::user()->can('manage-any-goal-types')) {
            return true;
        }
        if (Auth::user()->can('manage-own-goal-types')) {
            return (int) $goalType->creator_id === (int) Auth::id();
        }
        return false;
    }
}
