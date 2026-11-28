<?php

namespace Workdo\Performance\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Performance\Http\Requests\StoreCompetencyRequest;
use Workdo\Performance\Http\Requests\UpdateCompetencyRequest;
use Workdo\Performance\Models\Competency;
use Workdo\Performance\Models\CompetencyCategory;
use Workdo\Performance\Services\PerformanceErpIntegrationService;
use Workdo\Performance\Traits\PerformanceControllerHelpers;

class CompetencyController extends Controller
{
    use PerformanceControllerHelpers;

    public function __construct(private PerformanceErpIntegrationService $erpService) {}

    public function index()
    {
        if (!Auth::user()->can('manage-competencies') && !Auth::user()->can('manage-performance')) {
            return back()->with('error', __('Permission denied'));
        }

        $query = Competency::with(['category:id,name', 'department:id,department_name', 'designation:id,designation_name'])
            ->where('created_by', creatorId())
            ->when(request('name'), fn($q) => $q->where('name', 'like', '%' . request('name') . '%'))
            ->when(request('category_id'), fn($q) => $q->where('category_id', request('category_id')))
            ->when(request('status'), fn($q) => $q->where('status', request('status')));

        $competencies = $this->applySafeSort($query, [
            'name' => 'competencies.name',
            'weight' => 'competencies.weight',
            'status' => 'competencies.status',
            'created_at' => 'competencies.created_at',
        ])->paginate($this->safePerPage())->withQueryString();

        $categories = CompetencyCategory::where('created_by', creatorId())
            ->where('status', 'active')
            ->select('id', 'name')
            ->get();

        return Inertia::render('Performance/Competencies/Index', [
            'competencies' => $competencies,
            'categories' => $categories,
            'hrmContext' => $this->erpService->getHrmContext((int) creatorId()),
        ]);
    }

    public function store(StoreCompetencyRequest $request)
    {
        if (!Auth::user()->can('create-competencies') && !Auth::user()->can('manage-competencies')) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        Competency::create([
            'category_id' => $validated['category_id'],
            'department_id' => $validated['department_id'] ?? null,
            'designation_id' => $validated['designation_id'] ?? null,
            'name' => $validated['name'],
            'description' => \App\Support\HtmlSanitizer::sanitize($validated['description'] ?? null),
            'weight' => $validated['weight'] ?? 0,
            'status' => $validated['status'] ?? 'active',
            'creator_id' => Auth::id(),
            'created_by' => creatorId(),
        ]);

        return back()->with('success', __('Competency created successfully.'));
    }

    public function update(UpdateCompetencyRequest $request, Competency $competency)
    {
        if ((!Auth::user()->can('edit-competencies') && !Auth::user()->can('manage-competencies'))
            || (int) $competency->created_by !== (int) creatorId()
        ) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        $competency->update([
            'category_id' => $validated['category_id'],
            'department_id' => $validated['department_id'] ?? null,
            'designation_id' => $validated['designation_id'] ?? null,
            'name' => $validated['name'],
            'description' => \App\Support\HtmlSanitizer::sanitize($validated['description'] ?? null),
            'weight' => $validated['weight'] ?? 0,
            'status' => $validated['status'] ?? 'active',
        ]);

        return back()->with('success', __('Competency updated successfully.'));
    }

    public function destroy(Competency $competency)
    {
        if ((!Auth::user()->can('delete-competencies') && !Auth::user()->can('manage-competencies'))
            || (int) $competency->created_by !== (int) creatorId()
        ) {
            return back()->with('error', __('Permission denied'));
        }

        if ($competency->assessments()->exists()) {
            return back()->with('error', __('Cannot delete competency because employee assessments reference it.'));
        }

        $competency->delete();

        return back()->with('success', __('Competency deleted successfully.'));
    }
}
