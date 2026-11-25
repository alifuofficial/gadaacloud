<?php

namespace Workdo\Performance\Http\Controllers;

use App\Models\User;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Performance\Http\Requests\StoreEmployeeCompetencyRequest;
use Workdo\Performance\Http\Requests\UpdateEmployeeCompetencyRequest;
use Workdo\Performance\Models\Competency;
use Workdo\Performance\Models\EmployeeCompetency;
use Workdo\Performance\Models\PerformanceReviewCycle;
use Workdo\Performance\Support\PerformanceHtmlSanitizer;
use Workdo\Performance\Traits\PerformanceControllerHelpers;

class EmployeeCompetencyController extends Controller
{
    use PerformanceControllerHelpers;

    public function index()
    {
        if (!Auth::user()->can('manage-employee-competencies') && !Auth::user()->can('manage-performance')) {
            return back()->with('error', __('Permission denied'));
        }

        $isOwnOnly = !Auth::user()->can('manage-any-employee-competencies') && Auth::user()->can('manage-own-employee-competencies');

        $query = EmployeeCompetency::with(['employee:id,name', 'assessor:id,name', 'competency.category:id,name', 'reviewCycle:id,name'])
            ->where('created_by', creatorId())
            ->when($isOwnOnly, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('employee_id', Auth::id())
                        ->orWhere('assessor_id', Auth::id())
                        ->orWhere('creator_id', Auth::id());
                });
            })
            ->when(request('employee_id'), fn($q) => $q->where('employee_id', request('employee_id')))
            ->when(request('competency_id'), fn($q) => $q->where('competency_id', request('competency_id')));

        $assessments = $this->applySafeSort($query, [
            'assessment_date' => 'employee_competencies.assessment_date',
            'rating' => 'employee_competencies.rating',
            'created_at' => 'employee_competencies.created_at',
        ])->paginate($this->safePerPage())->withQueryString();

        return Inertia::render('Performance/EmployeeCompetencies/Index', [
            'assessments' => $assessments,
            'employees' => User::emp()->where('created_by', creatorId())->select('id', 'name')->get(),
            'competencies' => Competency::where('created_by', creatorId())->where('status', 'active')->select('id', 'name')->get(),
            'reviewCycles' => PerformanceReviewCycle::where('created_by', creatorId())->where('status', 'active')->select('id', 'name')->get(),
        ]);
    }

    public function store(StoreEmployeeCompetencyRequest $request)
    {
        if (!Auth::user()->can('create-employee-competencies') && !Auth::user()->can('manage-employee-competencies')) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        EmployeeCompetency::create([
            'employee_id' => $validated['employee_id'],
            'competency_id' => $validated['competency_id'],
            'review_cycle_id' => $validated['review_cycle_id'] ?? null,
            'assessor_id' => Auth::id(),
            'rating' => $validated['rating'],
            'assessment_date' => $validated['assessment_date'],
            'comments' => PerformanceHtmlSanitizer::sanitize($validated['comments'] ?? null),
            'creator_id' => Auth::id(),
            'created_by' => creatorId(),
        ]);

        return back()->with('success', __('Competency assessment recorded successfully.'));
    }

    public function update(UpdateEmployeeCompetencyRequest $request, EmployeeCompetency $employeeCompetency)
    {
        if ((!Auth::user()->can('edit-employee-competencies') && !Auth::user()->can('manage-employee-competencies'))
            || (int) $employeeCompetency->created_by !== (int) creatorId()
        ) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        $employeeCompetency->update([
            'employee_id' => $validated['employee_id'],
            'competency_id' => $validated['competency_id'],
            'review_cycle_id' => $validated['review_cycle_id'] ?? null,
            'rating' => $validated['rating'],
            'assessment_date' => $validated['assessment_date'],
            'comments' => PerformanceHtmlSanitizer::sanitize($validated['comments'] ?? null),
        ]);

        return back()->with('success', __('Competency assessment updated successfully.'));
    }

    public function destroy(EmployeeCompetency $employeeCompetency)
    {
        if ((!Auth::user()->can('delete-employee-competencies') && !Auth::user()->can('manage-employee-competencies'))
            || (int) $employeeCompetency->created_by !== (int) creatorId()
        ) {
            return back()->with('error', __('Permission denied'));
        }

        $employeeCompetency->delete();

        return back()->with('success', __('Competency assessment deleted successfully.'));
    }
}
