<?php

namespace Workdo\Performance\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Performance\Http\Requests\StoreCompetencyCategoryRequest;
use Workdo\Performance\Http\Requests\UpdateCompetencyCategoryRequest;
use Workdo\Performance\Models\CompetencyCategory;

class CompetencyCategoryController extends Controller
{
    public function index()
    {
        if (!Auth::user()->can('manage-competency-categories') && !Auth::user()->can('manage-performance-system-setup')) {
            return back()->with('error', __('Permission denied'));
        }

        $categories = CompetencyCategory::where('created_by', creatorId())
            ->withCount('competencies')
            ->latest()
            ->get();

        return Inertia::render('Performance/SystemSetup/CompetencyCategories/Index', [
            'categories' => $categories,
        ]);
    }

    public function store(StoreCompetencyCategoryRequest $request)
    {
        if (!Auth::user()->can('create-competency-categories') && !Auth::user()->can('manage-performance-system-setup')) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        CompetencyCategory::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'active',
            'creator_id' => Auth::id(),
            'created_by' => creatorId(),
        ]);

        return back()->with('success', __('Competency category created successfully.'));
    }

    public function update(UpdateCompetencyCategoryRequest $request, CompetencyCategory $competencyCategory)
    {
        if ((!Auth::user()->can('edit-competency-categories') && !Auth::user()->can('manage-performance-system-setup'))
            || (int) $competencyCategory->created_by !== (int) creatorId()
        ) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        $competencyCategory->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);

        return back()->with('success', __('Competency category updated successfully.'));
    }

    public function destroy(CompetencyCategory $competencyCategory)
    {
        if ((!Auth::user()->can('delete-competency-categories') && !Auth::user()->can('manage-performance-system-setup'))
            || (int) $competencyCategory->created_by !== (int) creatorId()
        ) {
            return back()->with('error', __('Permission denied'));
        }

        if ($competencyCategory->competencies()->exists()) {
            return back()->with('error', __('Cannot delete category while competencies belong to it.'));
        }

        $competencyCategory->delete();

        return back()->with('success', __('Competency category deleted successfully.'));
    }
}
