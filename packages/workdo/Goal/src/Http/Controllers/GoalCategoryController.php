<?php

namespace Workdo\Goal\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Goal\Events\CreateGoalCategory;
use Workdo\Goal\Events\DestroyGoalCategory;
use Workdo\Goal\Events\UpdateGoalCategory;
use Workdo\Goal\Http\Requests\StoreCategoryRequest;
use Workdo\Goal\Http\Requests\UpdateCategoryRequest;
use Workdo\Goal\Models\GoalCategory;
use Workdo\Goal\Traits\GoalControllerHelpers;

class GoalCategoryController extends Controller
{
    use GoalControllerHelpers;

    public function index()
    {
        if (!Auth::user()->can('manage-categories')) {
            return back()->with('error', __('Permission denied'));
        }

        $sortMap = [
            'category_name' => 'category_name',
            'category_code' => 'category_code',
            'is_active'     => 'is_active',
            'created_at'    => 'created_at',
        ];

        $categories = GoalCategory::query()
            ->where(function ($q) {
                if (Auth::user()->can('manage-any-categories')) {
                    $q->where('created_by', creatorId());
                } elseif (Auth::user()->can('manage-own-categories')) {
                    $q->where('creator_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->when(request('category_name'), function ($q) {
                $term = request('category_name');
                $q->where(function ($query) use ($term) {
                    $query->where('category_name', 'like', "%{$term}%")
                        ->orWhere('category_code', 'like', "%{$term}%");
                });
            })
            ->when(request('is_active') !== null && request('is_active') !== '', function ($q) {
                $q->where('is_active', request('is_active') === '1' ? 1 : 0);
            });

        $categories = $this->applySafeSort($categories, $sortMap, 'category_name')
            ->paginate($this->safePerPage(10))
            ->withQueryString();

        return Inertia::render('Goal/Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function store(StoreCategoryRequest $request)
    {
        if (!Auth::user()->can('create-categories')) {
            return redirect()->route('goal.categories.index')->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        $validated['is_active'] = $request->boolean('is_active', true);

        $category = new GoalCategory();
        $category->category_name = $validated['category_name'];
        $category->category_code = $validated['category_code'];
        $category->description = $validated['description'] ?? null;
        $category->is_active = $validated['is_active'];
        $category->creator_id = Auth::id();
        $category->created_by = creatorId();
        $category->save();

        CreateGoalCategory::dispatch($request, $category);

        return redirect()->route('goal.categories.index')->with('success', __('The category has been created successfully.'));
    }

    public function update(UpdateCategoryRequest $request, GoalCategory $category)
    {
        if (!Auth::user()->can('edit-categories') || !$this->assertBelongsToTenant($category)) {
            return redirect()->route('goal.categories.index')->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        $validated['is_active'] = $request->boolean('is_active', true);

        $category->category_name = $validated['category_name'];
        $category->category_code = $validated['category_code'];
        $category->description = $validated['description'] ?? null;
        $category->is_active = $validated['is_active'];
        $category->save();

        UpdateGoalCategory::dispatch($request, $category);

        return back()->with('success', __('The category details are updated successfully.'));
    }

    public function destroy(GoalCategory $category)
    {
        if (!Auth::user()->can('delete-categories') || !$this->assertBelongsToTenant($category)) {
            return redirect()->route('goal.categories.index')->with('error', __('Permission denied'));
        }

        if ($category->goals()->exists()) {
            return back()->with('error', __('Cannot delete category because it has active linked financial goals.'));
        }

        DestroyGoalCategory::dispatch($category);

        $category->delete();

        return redirect()->back()->with('success', __('The category has been deleted.'));
    }
}
