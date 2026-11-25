<?php

namespace Workdo\Performance\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Workdo\Performance\Events\CreateReviewCycle;
use Workdo\Performance\Events\DestroyReviewCycle;
use Workdo\Performance\Events\UpdateReviewCycle;
use Workdo\Performance\Http\Requests\StoreReviewCycleRequest;
use Workdo\Performance\Http\Requests\UpdateReviewCycleRequest;
use Workdo\Performance\Models\PerformanceEmployeeReview;
use Workdo\Performance\Models\PerformanceReviewCycle;
use Workdo\Performance\Models\ReviewTemplate;
use Workdo\Performance\Services\PerformanceErpIntegrationService;
use Workdo\Performance\Traits\PerformanceControllerHelpers;

class ReviewCycleController extends Controller
{
    use PerformanceControllerHelpers;

    public function __construct(private PerformanceErpIntegrationService $erpService) {}

    public function index()
    {
        if (!Auth::user()->can('manage-review-cycles')) {
            return back()->with('error', __('Permission denied'));
        }

        $query = PerformanceReviewCycle::with(['createdBy', 'branch', 'department'])
            ->withCount('employeeReviews')
            ->where('performance_review_cycles.created_by', creatorId())
            ->where(function ($q) {
                if (Auth::user()->can('manage-any-review-cycles')) {
                    // Already scoped to creatorId()
                } elseif (Auth::user()->can('manage-own-review-cycles')) {
                    $q->where('performance_review_cycles.creator_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->when(request('name'), fn($q) => $q->where('performance_review_cycles.name', 'like', '%' . request('name') . '%'))
            ->when(request('frequency'), fn($q) => $q->where('performance_review_cycles.frequency', request('frequency')))
            ->when(request('status') !== null && request('status') !== '', fn($q) => $q->where('performance_review_cycles.status', request('status')));

        $reviewCycles = $this->applySafeSort($query, [
            'name' => 'performance_review_cycles.name',
            'frequency' => 'performance_review_cycles.frequency',
            'start_date' => 'performance_review_cycles.start_date',
            'end_date' => 'performance_review_cycles.end_date',
            'status' => 'performance_review_cycles.status',
            'created_at' => 'performance_review_cycles.created_at',
        ])
            ->paginate($this->safePerPage())
            ->withQueryString();

        $reviewTemplates = ReviewTemplate::where('created_by', creatorId())
            ->where('status', 'active')
            ->select('id', 'name')
            ->get();

        $reviewers = User::emp()->where('created_by', creatorId())->select('id', 'name')->get();

        return Inertia::render('Performance/ReviewCycles/Index', [
            'reviewCycles' => $reviewCycles,
            'hrmContext' => $this->erpService->getHrmContext((int) creatorId()),
            'reviewTemplates' => $reviewTemplates,
            'reviewers' => $reviewers,
            'ethiopianCalendarActive' => $this->erpService->isModuleAvailable('EthiopianCalendar', (int) creatorId()),
        ]);
    }

    public function store(StoreReviewCycleRequest $request)
    {
        if (!Auth::user()->can('create-review-cycles')) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        [$startDate, $endDate] = $this->resolveDatesWithEthiopianSupport($validated);

        $reviewCycle = new PerformanceReviewCycle();
        $reviewCycle->name = $validated['name'];
        $reviewCycle->frequency = $validated['frequency'];
        $reviewCycle->start_date = $startDate;
        $reviewCycle->end_date = $endDate;
        $reviewCycle->self_review_deadline = $validated['self_review_deadline'] ?? null;
        $reviewCycle->manager_review_deadline = $validated['manager_review_deadline'] ?? null;
        $reviewCycle->calendar_type = $validated['calendar_type'] ?? 'gregorian';
        $reviewCycle->ethiopian_fiscal_year = $validated['ethiopian_fiscal_year'] ?? null;
        $reviewCycle->ethiopian_quarter = $validated['ethiopian_quarter'] ?? null;
        $reviewCycle->branch_id = $validated['branch_id'] ?? null;
        $reviewCycle->department_id = $validated['department_id'] ?? null;
        $reviewCycle->description = $validated['description'] ?? null;
        $reviewCycle->status = $validated['status'] ?? 'active';
        $reviewCycle->creator_id = Auth::id();
        $reviewCycle->created_by = creatorId();
        $reviewCycle->save();

        CreateReviewCycle::dispatch($request, $reviewCycle);

        return back()->with('success', __('The review cycle has been created successfully.'));
    }

    public function update(UpdateReviewCycleRequest $request, PerformanceReviewCycle $reviewCycle)
    {
        if (!Auth::user()->can('edit-review-cycles')) {
            return back()->with('error', __('Permission denied'));
        }
        if (!$this->canAccessReviewCycle($reviewCycle)) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        [$startDate, $endDate] = $this->resolveDatesWithEthiopianSupport($validated, $reviewCycle);

        $reviewCycle->name = $validated['name'];
        $reviewCycle->frequency = $validated['frequency'];
        $reviewCycle->start_date = $startDate;
        $reviewCycle->end_date = $endDate;
        $reviewCycle->self_review_deadline = $validated['self_review_deadline'] ?? $reviewCycle->self_review_deadline;
        $reviewCycle->manager_review_deadline = $validated['manager_review_deadline'] ?? $reviewCycle->manager_review_deadline;
        $reviewCycle->calendar_type = $validated['calendar_type'] ?? $reviewCycle->calendar_type;
        $reviewCycle->ethiopian_fiscal_year = $validated['ethiopian_fiscal_year'] ?? null;
        $reviewCycle->ethiopian_quarter = $validated['ethiopian_quarter'] ?? null;
        $reviewCycle->branch_id = $validated['branch_id'] ?? null;
        $reviewCycle->department_id = $validated['department_id'] ?? null;
        $reviewCycle->description = $validated['description'] ?? null;
        $reviewCycle->status = $validated['status'] ?? 'active';
        $reviewCycle->save();

        UpdateReviewCycle::dispatch($request, $reviewCycle);

        return back()->with('success', __('The review cycle details are updated successfully.'));
    }

    public function show(PerformanceReviewCycle $reviewCycle)
    {
        if (!Auth::user()->can('view-review-cycles')) {
            return back()->with('error', __('Permission denied'));
        }
        if (!$this->canAccessReviewCycle($reviewCycle)) {
            return back()->with('error', __('Permission denied'));
        }

        $data = [
            'reviewCycle' => $reviewCycle->load([
                'creator', 'createdBy', 'branch', 'department',
                'employeeReviews.user', 'employeeReviews.reviewer',
            ]),
        ];

        if (request()->wantsJson() || request()->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json(['props' => $data]);
        }

        return Inertia::render('Performance/ReviewCycles/Show', $data);
    }

    public function destroy(PerformanceReviewCycle $reviewCycle)
    {
        if (!Auth::user()->can('delete-review-cycles')) {
            return back()->with('error', __('Permission denied'));
        }
        if (!$this->canAccessReviewCycle($reviewCycle)) {
            return back()->with('error', __('Permission denied'));
        }
        if ($reviewCycle->employeeReviews()->exists()) {
            return back()->with('error', __('Cannot delete review cycle because employee appraisals already reference it.'));
        }

        DestroyReviewCycle::dispatch($reviewCycle);
        $reviewCycle->delete();

        return back()->with('success', __('The review cycle has been deleted.'));
    }

    /**
     * Resolve Ethiopian Fiscal Year / Quarter Gregorian dates for quick cycle creation.
     */
    public function ethiopianPreset(Request $request)
    {
        $validated = $request->validate([
            'ethiopian_fiscal_year' => 'required|integer|min:2010|max:2050',
            'period' => 'required|in:annual,q1,q2,q3,q4',
        ]);

        $resolved = $this->erpService->resolveEthiopianFiscalPeriod(
            (int) $validated['ethiopian_fiscal_year'],
            $validated['period']
        );

        if (!$resolved) {
            return response()->json(['error' => __('Ethiopian Calendar service is unavailable.')], 422);
        }

        return response()->json($resolved);
    }

    /**
     * Bulk-assign all active employees (optionally filtered by branch/department) to a review cycle.
     */
    public function bulkAssign(Request $request, PerformanceReviewCycle $reviewCycle)
    {
        if (!Auth::user()->can('create-employee-reviews') || !$this->canAccessReviewCycle($reviewCycle)) {
            return back()->with('error', __('Permission denied'));
        }
        if ($reviewCycle->is_locked) {
            return back()->with('error', __('Cannot assign appraisals to a locked review cycle.'));
        }

        $validated = $request->validate([
            'reviewer_id' => ['required', self::tenantExists('users')],
            'review_template_id' => ['nullable', self::tenantExists('review_templates')],
            'review_date' => 'required|date',
            'branch_id' => 'nullable|integer',
            'department_id' => 'nullable|integer',
        ]);

        $tenantId = (int) creatorId();
        $userQuery = User::emp()->where('created_by', $tenantId);

        if ((!empty($validated['branch_id']) || !empty($validated['department_id'])) && class_exists(\Workdo\Hrm\Models\Employee::class)) {
            $empQuery = \Workdo\Hrm\Models\Employee::where('created_by', $tenantId);
            if (!empty($validated['branch_id'])) {
                $empQuery->where('branch_id', (int) $validated['branch_id']);
            }
            if (!empty($validated['department_id'])) {
                $empQuery->where('department_id', (int) $validated['department_id']);
            }
            $userIds = $empQuery->pluck('user_id')->all();
            $userQuery->whereIn('id', $userIds);
        }

        $employeeIds = $userQuery->pluck('id')->all();
        $existingUserIds = PerformanceEmployeeReview::where('created_by', $tenantId)
            ->where('review_cycle_id', $reviewCycle->id)
            ->pluck('user_id')
            ->all();

        $template = !empty($validated['review_template_id'])
            ? ReviewTemplate::find($validated['review_template_id'])
            : null;
        $stage = $template && $template->include_self_assessment ? 'self_assessment' : 'manager_review';

        $createdCount = 0;
        DB::transaction(function () use ($employeeIds, $existingUserIds, $validated, $reviewCycle, $stage, $tenantId, &$createdCount) {
            foreach ($employeeIds as $empId) {
                if (in_array($empId, $existingUserIds, true) || (int) $empId === (int) $validated['reviewer_id']) {
                    continue;
                }
                PerformanceEmployeeReview::create([
                    'user_id' => $empId,
                    'reviewer_id' => $validated['reviewer_id'],
                    'review_cycle_id' => $reviewCycle->id,
                    'review_template_id' => $validated['review_template_id'] ?? null,
                    'review_date' => $validated['review_date'],
                    'status' => 'pending',
                    'workflow_stage' => $stage,
                    'creator_id' => Auth::id(),
                    'created_by' => $tenantId,
                ]);
                $createdCount++;
            }
        });

        return back()->with('success', __(':count employee review(s) assigned to this cycle.', ['count' => $createdCount]));
    }

    public function toggleLock(PerformanceReviewCycle $reviewCycle)
    {
        if (!Auth::user()->can('edit-review-cycles') || !$this->canAccessReviewCycle($reviewCycle)) {
            return back()->with('error', __('Permission denied'));
        }

        $reviewCycle->is_locked = !$reviewCycle->is_locked;
        $reviewCycle->save();

        return back()->with('success', $reviewCycle->is_locked ? __('Review cycle locked.') : __('Review cycle unlocked.'));
    }

    private function resolveDatesWithEthiopianSupport(array $validated, ?PerformanceReviewCycle $existing = null): array
    {
        $startDate = $validated['start_date'] ?? $existing?->start_date;
        $endDate = $validated['end_date'] ?? $existing?->end_date;

        if (($validated['calendar_type'] ?? '') === 'ethiopian'
            && !empty($validated['ethiopian_fiscal_year'])
            && (empty($startDate) || empty($endDate))
        ) {
            $resolved = $this->erpService->resolveEthiopianFiscalPeriod(
                (int) $validated['ethiopian_fiscal_year'],
                $validated['ethiopian_quarter'] ?? 'annual'
            );
            if ($resolved) {
                $startDate = $startDate ?: $resolved['start_date'];
                $endDate = $endDate ?: $resolved['end_date'];
            }
        }

        return [$startDate, $endDate];
    }

    private function canAccessReviewCycle(PerformanceReviewCycle $reviewCycle): bool
    {
        if ((int) $reviewCycle->created_by !== (int) creatorId()) {
            return false;
        }
        if (Auth::user()->can('manage-any-review-cycles')) {
            return true;
        }
        if (Auth::user()->can('manage-own-review-cycles')) {
            return (int) $reviewCycle->creator_id === (int) Auth::id();
        }
        return false;
    }
}
