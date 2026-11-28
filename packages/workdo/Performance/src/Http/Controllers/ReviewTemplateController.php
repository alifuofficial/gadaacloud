<?php

namespace Workdo\Performance\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Workdo\Performance\Http\Requests\StoreReviewTemplateRequest;
use Workdo\Performance\Http\Requests\UpdateReviewTemplateRequest;
use Workdo\Performance\Models\PerformanceIndicator;
use Workdo\Performance\Models\ReviewTemplate;
use Workdo\Performance\Services\PerformanceErpIntegrationService;
use Workdo\Performance\Traits\PerformanceControllerHelpers;

class ReviewTemplateController extends Controller
{
    use PerformanceControllerHelpers;

    public function __construct(private PerformanceErpIntegrationService $erpService) {}

    public function index()
    {
        if (!Auth::user()->can('manage-review-templates') && !Auth::user()->can('manage-performance')) {
            return back()->with('error', __('Permission denied'));
        }

        $query = ReviewTemplate::with(['indicators:id,name,category_id', 'branch:id,branch_name', 'department:id,department_name', 'designation:id,designation_name'])
            ->where('created_by', creatorId())
            ->when(request('name'), fn($q) => $q->where('name', 'like', '%' . request('name') . '%'))
            ->when(request('status'), fn($q) => $q->where('status', request('status')));

        $templates = $this->applySafeSort($query, [
            'name' => 'review_templates.name',
            'status' => 'review_templates.status',
            'created_at' => 'review_templates.created_at',
        ])->paginate($this->safePerPage())->withQueryString();

        return Inertia::render('Performance/ReviewTemplates/Index', [
            'templates' => $templates,
            'indicators' => PerformanceIndicator::where('created_by', creatorId())->where('status', 'active')->select('id', 'name', 'category_id')->get(),
            'hrmContext' => $this->erpService->getHrmContext((int) creatorId()),
        ]);
    }

    public function store(StoreReviewTemplateRequest $request)
    {
        if (!Auth::user()->can('create-review-templates') && !Auth::user()->can('manage-review-templates')) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validated();

        DB::transaction(function () use ($validated) {
            $template = ReviewTemplate::create([
                'name' => $validated['name'],
                'description' => \App\Support\HtmlSanitizer::sanitize($validated['description'] ?? null),
                'branch_id' => $validated['branch_id'] ?? null,
                'department_id' => $validated['department_id'] ?? null,
                'designation_id' => $validated['designation_id'] ?? null,
                'kpi_weight' => $validated['kpi_weight'] ?? 60,
                'goal_weight' => $validated['goal_weight'] ?? 20,
                'competency_weight' => $validated['competency_weight'] ?? 20,
                'include_self_assessment' => (bool) ($validated['include_self_assessment'] ?? true),
                'indicator_weights' => $validated['indicator_weights'] ?? [],
                'status' => $validated['status'] ?? 'active',
                'creator_id' => Auth::id(),
                'created_by' => creatorId(),
            ]);

            $this->syncIndicators($template, $validated['indicator_ids'] ?? [], $validated['indicator_weights'] ?? []);
        });

        return back()->with('success', __('Review template created successfully.'));
    }

    public function update(UpdateReviewTemplateRequest $request, ReviewTemplate $reviewTemplate)
    {
        if ((!Auth::user()->can('edit-review-templates') && !Auth::user()->can('manage-review-templates'))
            || (int) $reviewTemplate->created_by !== (int) creatorId()
        ) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validated();

        DB::transaction(function () use ($reviewTemplate, $validated) {
            $reviewTemplate->update([
                'name' => $validated['name'],
                'description' => \App\Support\HtmlSanitizer::sanitize($validated['description'] ?? null),
                'branch_id' => $validated['branch_id'] ?? null,
                'department_id' => $validated['department_id'] ?? null,
                'designation_id' => $validated['designation_id'] ?? null,
                'kpi_weight' => $validated['kpi_weight'] ?? 60,
                'goal_weight' => $validated['goal_weight'] ?? 20,
                'competency_weight' => $validated['competency_weight'] ?? 20,
                'include_self_assessment' => (bool) ($validated['include_self_assessment'] ?? true),
                'indicator_weights' => $validated['indicator_weights'] ?? [],
                'status' => $validated['status'] ?? 'active',
            ]);

            $this->syncIndicators($reviewTemplate, $validated['indicator_ids'] ?? [], $validated['indicator_weights'] ?? []);
        });

        return back()->with('success', __('Review template updated successfully.'));
    }

    public function destroy(ReviewTemplate $reviewTemplate)
    {
        if ((!Auth::user()->can('delete-review-templates') && !Auth::user()->can('manage-review-templates'))
            || (int) $reviewTemplate->created_by !== (int) creatorId()
        ) {
            return back()->with('error', __('Permission denied'));
        }

        if ($reviewTemplate->employeeReviews()->exists()) {
            return back()->with('error', __('Cannot delete template because employee reviews reference it.'));
        }

        $reviewTemplate->delete();

        return back()->with('success', __('Review template deleted successfully.'));
    }

    private function syncIndicators(ReviewTemplate $template, array $indicatorIds, array $weights): void
    {
        $syncPayload = [];
        foreach ($indicatorIds as $id) {
            $syncPayload[(int) $id] = ['weight' => (float) ($weights[$id] ?? 0)];
        }
        $template->indicators()->sync($syncPayload);
    }
}
