<?php

namespace Workdo\Performance\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Workdo\Performance\Events\CreateEmployeeReview;
use Workdo\Performance\Events\DestroyEmployeeReview;
use Workdo\Performance\Events\UpdateEmployeeReview;
use Workdo\Performance\Http\Requests\StoreEmployeeReviewRequest;
use Workdo\Performance\Http\Requests\UpdateEmployeeReviewRequest;
use Workdo\Performance\Models\Competency;
use Workdo\Performance\Models\EmployeeFeedback;
use Workdo\Performance\Models\PerformanceEmployeeGoal;
use Workdo\Performance\Models\PerformanceEmployeeReview;
use Workdo\Performance\Models\PerformanceIndicator;
use Workdo\Performance\Models\PerformanceReviewCycle;
use Workdo\Performance\Models\ReviewTemplate;
use Workdo\Performance\Services\PerformanceErpIntegrationService;
use Workdo\Performance\Services\PerformanceScoringService;
use Workdo\Performance\Support\PerformanceHtmlSanitizer;
use Workdo\Performance\Traits\PerformanceControllerHelpers;

class EmployeeReviewController extends Controller
{
    use PerformanceControllerHelpers;

    public function __construct(
        private PerformanceScoringService $scoringService,
        private PerformanceErpIntegrationService $erpService
    ) {}

    public function index()
    {
        if (!Auth::user()->can('manage-employee-reviews')) {
            return back()->with('error', __('Permission denied'));
        }

        $query = PerformanceEmployeeReview::with(['user', 'reviewer', 'reviewCycle', 'reviewTemplate'])
            ->where('performance_employee_reviews.created_by', creatorId())
            ->where(function ($q) {
                if (Auth::user()->can('manage-any-employee-reviews')) {
                    // Already scoped to creatorId()
                } elseif (Auth::user()->can('manage-own-employee-reviews')) {
                    $q->where(function ($sub) {
                        $sub->where('performance_employee_reviews.creator_id', Auth::id())
                            ->orWhere('performance_employee_reviews.reviewer_id', Auth::id())
                            ->orWhere('performance_employee_reviews.user_id', Auth::id());
                    });
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->when(request('employee_name'), function ($q) {
                $q->whereHas('user', function ($query) {
                    $query->where('name', 'like', '%' . request('employee_name') . '%');
                });
            })
            ->when(request('reviewer_name'), function ($q) {
                $q->whereHas('reviewer', function ($query) {
                    $query->where('name', 'like', '%' . request('reviewer_name') . '%');
                });
            })
            ->when(request('review_cycle_id'), fn($q) => $q->where('performance_employee_reviews.review_cycle_id', request('review_cycle_id')))
            ->when(request('status'), fn($q) => $q->where('performance_employee_reviews.status', request('status')))
            ->when(request('workflow_stage'), fn($q) => $q->where('performance_employee_reviews.workflow_stage', request('workflow_stage')));

        $reviews = $this->applySafeSort($query, [
            'user_id' => 'performance_employee_reviews.user_id',
            'reviewer_id' => 'performance_employee_reviews.reviewer_id',
            'review_cycle_id' => 'performance_employee_reviews.review_cycle_id',
            'review_date' => 'performance_employee_reviews.review_date',
            'overall_score' => 'performance_employee_reviews.overall_score',
            'status' => 'performance_employee_reviews.status',
            'created_at' => 'performance_employee_reviews.created_at',
        ])
            ->paginate($this->safePerPage())
            ->withQueryString();

        $users = $this->getFilteredUsers();
        $reviewCycles = $this->getFilteredReviewCycles();
        $reviewTemplates = ReviewTemplate::where('created_by', creatorId())
            ->where('status', 'active')
            ->select('id', 'name', 'department_id', 'designation_id', 'include_self_assessment')
            ->get();

        return Inertia::render('Performance/EmployeeReviews/Index', [
            'employeeReviews' => $reviews,
            'employees' => $users,
            'reviewers' => $users,
            'reviewCycles' => $reviewCycles,
            'reviewTemplates' => $reviewTemplates,
            'hrmContext' => $this->erpService->getHrmContext((int) creatorId()),
        ]);
    }

    public function store(StoreEmployeeReviewRequest $request)
    {
        if (!Auth::user()->can('create-employee-reviews')) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        $template = !empty($validated['review_template_id'])
            ? ReviewTemplate::find($validated['review_template_id'])
            : null;

        $employeeReview = new PerformanceEmployeeReview();
        $employeeReview->user_id = $validated['user_id'];
        $employeeReview->reviewer_id = $validated['reviewer_id'];
        $employeeReview->review_cycle_id = $validated['review_cycle_id'];
        $employeeReview->review_template_id = $validated['review_template_id'] ?? null;
        $employeeReview->review_date = $validated['review_date'];
        $employeeReview->status = $validated['status'];
        $employeeReview->workflow_stage = $validated['workflow_stage']
            ?? ($template && $template->include_self_assessment ? 'self_assessment' : 'manager_review');
        $employeeReview->creator_id = Auth::id();
        $employeeReview->created_by = creatorId();
        $employeeReview->save();

        CreateEmployeeReview::dispatch($request, $employeeReview);

        return back()->with('success', __('The employee review has been created successfully.'));
    }

    public function update(UpdateEmployeeReviewRequest $request, PerformanceEmployeeReview $employeeReview)
    {
        if (!Auth::user()->can('edit-employee-reviews')) {
            return back()->with('error', __('Permission denied'));
        }
        if (!$this->canAccessReview($employeeReview)) {
            return back()->with('error', __('Permission denied'));
        }
        if ($employeeReview->is_locked) {
            return back()->with('error', __('This appraisal is locked after calibration and cannot be edited.'));
        }

        $validated = $request->validated();
        $employeeReview->user_id = $validated['user_id'];
        $employeeReview->reviewer_id = $validated['reviewer_id'];
        $employeeReview->review_cycle_id = $validated['review_cycle_id'];
        $employeeReview->review_template_id = $validated['review_template_id'] ?? $employeeReview->review_template_id;
        $employeeReview->review_date = $validated['review_date'];
        $employeeReview->status = $validated['status'];
        if (!empty($validated['workflow_stage'])) {
            $employeeReview->workflow_stage = $validated['workflow_stage'];
        }
        $employeeReview->save();

        UpdateEmployeeReview::dispatch($request, $employeeReview);

        return back()->with('success', __('The employee review details are updated successfully.'));
    }

    public function show(PerformanceEmployeeReview $employeeReview)
    {
        if (!Auth::user()->can('view-employee-reviews')) {
            return back()->with('error', __('Permission denied'));
        }
        if (!$this->canAccessReview($employeeReview)) {
            return back()->with('error', __('Permission denied'));
        }

        $ratings = self::decodeRatingMap($employeeReview->rating);
        $selfRatings = self::decodeRatingMap($employeeReview->self_rating);
        $competencyRatings = self::decodeRatingMap($employeeReview->competency_ratings);

        $indicators = $this->resolveIndicatorsForReview($employeeReview);

        $performanceIndicators = $indicators
            ->groupBy(fn($ind) => $ind->category?->name ?? __('Uncategorized'))
            ->map(function ($group) use ($ratings, $selfRatings) {
                return $group->map(function ($indicator) use ($ratings, $selfRatings) {
                    $indicator->user_rating = (float) ($ratings[$indicator->id] ?? 0);
                    $indicator->employee_self_rating = (float) ($selfRatings[$indicator->id] ?? 0);
                    return $indicator;
                })->values();
            });

        $ratedValues = array_filter($ratings, fn($r) => is_numeric($r) && (float) $r > 0);
        $averageRating = $employeeReview->average_rating;
        if ($averageRating === null && !empty($ratedValues)) {
            $averageRating = round(array_sum($ratedValues) / count($ratedValues), 1);
        }

        $competencies = Competency::with('category')
            ->where('created_by', creatorId())
            ->where('status', 'active')
            ->get()
            ->map(function ($comp) use ($competencyRatings) {
                $comp->assessed_rating = (float) ($competencyRatings[$comp->id] ?? 0);
                return $comp;
            });

        $employeeGoals = PerformanceEmployeeGoal::with('goalType')
            ->where('created_by', creatorId())
            ->where('employee_id', $employeeReview->user_id)
            ->when($employeeReview->review_cycle_id, fn($q) => $q->where(function ($sub) use ($employeeReview) {
                $sub->where('review_cycle_id', $employeeReview->review_cycle_id)->orWhereNull('review_cycle_id');
            }))
            ->get();

        $peerFeedback = EmployeeFeedback::with(['feedbackType', 'giver:id,name'])
            ->where('created_by', creatorId())
            ->where('employee_id', $employeeReview->user_id)
            ->when($employeeReview->review_cycle_id, fn($q) => $q->where(function ($sub) use ($employeeReview) {
                $sub->where('review_cycle_id', $employeeReview->review_cycle_id)->orWhereNull('review_cycle_id');
            }))
            ->latest('date')
            ->limit(10)
            ->get()
            ->map(function ($fb) {
                if ($fb->is_anonymous) {
                    $fb->setRelation('giver', null);
                }
                return $fb;
            });

        $data = [
            'employeeReview' => $employeeReview->load(['user', 'reviewer', 'calibratedBy', 'reviewCycle', 'reviewTemplate']),
            'performanceIndicators' => $performanceIndicators,
            'competencies' => $competencies,
            'employeeGoals' => $employeeGoals,
            'peerFeedback' => $peerFeedback,
            'averageRating' => $averageRating,
            'nineBox' => $this->scoringService->classifyNineBox($employeeReview),
            'hrmContext' => $this->erpService->getHrmContext((int) creatorId()),
        ];

        if (request()->wantsJson() || request()->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json(['props' => $data]);
        }

        return Inertia::render('Performance/EmployeeReviews/Show', $data);
    }

    public function destroy(PerformanceEmployeeReview $employeeReview)
    {
        if (!Auth::user()->can('delete-employee-reviews')) {
            return back()->with('error', __('Permission denied'));
        }
        if (!$this->canAccessReview($employeeReview)) {
            return back()->with('error', __('Permission denied'));
        }
        if ($employeeReview->is_locked) {
            return back()->with('error', __('Locked appraisals cannot be deleted.'));
        }

        DestroyEmployeeReview::dispatch($employeeReview);
        $employeeReview->delete();

        return back()->with('success', __('The employee review has been deleted.'));
    }

    public function conduct(PerformanceEmployeeReview $employeeReview)
    {
        if (!Auth::user()->can('conduct-employee-reviews')) {
            return redirect()->route('performance.employee-reviews.index')->with('error', __('Permission denied'));
        }
        if (!$this->canAccessReview($employeeReview)) {
            return redirect()->route('performance.employee-reviews.index')->with('error', __('Permission denied'));
        }

        $performanceIndicators = $this->resolveIndicatorsForReview($employeeReview)
            ->groupBy(fn($ind) => $ind->category?->name ?? __('Uncategorized'));

        $competencies = Competency::with('category')
            ->where('created_by', creatorId())
            ->where('status', 'active')
            ->get();

        $employeeGoals = PerformanceEmployeeGoal::with('goalType')
            ->where('created_by', creatorId())
            ->where('employee_id', $employeeReview->user_id)
            ->get();

        return Inertia::render('Performance/EmployeeReviews/Conduct', [
            'employeeReview' => $employeeReview->load(['user', 'reviewer', 'reviewCycle', 'reviewTemplate']),
            'performanceIndicators' => $performanceIndicators,
            'competencies' => $competencies,
            'employeeGoals' => $employeeGoals,
            'existingRatings' => self::decodeRatingMap($employeeReview->rating),
            'existingSelfRatings' => self::decodeRatingMap($employeeReview->self_rating),
            'existingCompetencyRatings' => self::decodeRatingMap($employeeReview->competency_ratings),
            'isReviewedEmployee' => (int) $employeeReview->user_id === (int) Auth::id(),
        ]);
    }

    public function conductStore(Request $request, PerformanceEmployeeReview $employeeReview)
    {
        if (!Auth::user()->can('conduct-employee-reviews')) {
            return back()->with('error', __('Permission denied'));
        }
        if (!$this->canAccessReview($employeeReview)) {
            return back()->with('error', __('Permission denied'));
        }
        if ($employeeReview->is_locked) {
            return back()->with('error', __('This appraisal is locked after calibration and cannot be modified.'));
        }

        $tenantId = (int) creatorId();
        $validIndicatorIds = PerformanceIndicator::where('created_by', $tenantId)->pluck('id')->all();
        $validCompetencyIds = Competency::where('created_by', $tenantId)->pluck('id')->all();

        $validated = $request->validate([
            'ratings' => 'required|array|min:1',
            'ratings.*' => 'integer|min:1|max:5',
            'competency_ratings' => 'nullable|array',
            'competency_ratings.*' => 'numeric|min:1|max:5',
            'pros' => 'nullable|string|max:65535',
            'cons' => 'nullable|string|max:65535',
            'submission_mode' => 'nullable|in:draft,submit',
            'pip_recommended' => 'nullable|boolean',
            'promotion_recommended' => 'nullable|boolean',
            'bonus_recommendation_amount' => 'nullable|numeric|min:0',
            'bonus_recommendation_type' => 'nullable|in:fixed,percentage',
        ]);

        // Filter rating keys strictly to indicators owned by this tenant
        $filteredRatings = [];
        foreach ($validated['ratings'] as $id => $val) {
            if (in_array((int) $id, $validIndicatorIds, true)) {
                $filteredRatings[(int) $id] = (int) $val;
            }
        }
        if (empty($filteredRatings)) {
            return back()->withErrors(['ratings' => __('Please rate at least one valid indicator for this company.')]);
        }

        $filteredCompetencies = [];
        foreach ($validated['competency_ratings'] ?? [] as $compId => $val) {
            if (in_array((int) $compId, $validCompetencyIds, true)) {
                $filteredCompetencies[(int) $compId] = (float) $val;
            }
        }

        $scores = $this->scoringService->computeReviewScores($employeeReview, $filteredRatings, $filteredCompetencies);
        $isDraft = ($validated['submission_mode'] ?? 'submit') === 'draft';

        // Store native PHP array directly (Eloquent 'array' cast handles JSON encoding without double-encoding)
        $employeeReview->rating = $filteredRatings;
        if (!empty($filteredCompetencies)) {
            $employeeReview->competency_ratings = $filteredCompetencies;
        }
        $employeeReview->pros = PerformanceHtmlSanitizer::sanitize($validated['pros'] ?? null);
        $employeeReview->cons = PerformanceHtmlSanitizer::sanitize($validated['cons'] ?? null);
        $employeeReview->kpi_score = $scores['kpi_score'];
        $employeeReview->goal_score = $scores['goal_score'];
        $employeeReview->competency_score = $scores['competency_score'];
        $employeeReview->weighted_rating = $scores['weighted_rating'];
        $employeeReview->overall_score = $scores['overall_score'];
        $employeeReview->rating_band = $scores['rating_band'];
        $employeeReview->pip_recommended = (bool) ($validated['pip_recommended'] ?? false);
        $employeeReview->promotion_recommended = (bool) ($validated['promotion_recommended'] ?? false);
        $employeeReview->bonus_recommendation_amount = $validated['bonus_recommendation_amount'] ?? null;
        $employeeReview->bonus_recommendation_type = $validated['bonus_recommendation_type'] ?? null;

        if ($isDraft) {
            $employeeReview->status = 'in_progress';
            $employeeReview->workflow_stage = 'manager_review';
        } else {
            $employeeReview->status = 'completed';
            $employeeReview->workflow_stage = 'hr_calibration';
            $employeeReview->completion_date = now();
        }
        $employeeReview->save();

        UpdateEmployeeReview::dispatch($request, $employeeReview);

        return redirect()->route('performance.employee-reviews.index')->with(
            'success',
            $isDraft ? __('Performance review draft saved.') : __('Performance review has been completed successfully.')
        );
    }

    /**
     * Save employee self-assessment stage.
     */
    public function selfAssessStore(Request $request, PerformanceEmployeeReview $employeeReview)
    {
        if (!$this->canAccessReview($employeeReview)) {
            return back()->with('error', __('Permission denied'));
        }
        if ((int) $employeeReview->user_id !== (int) Auth::id() && !Auth::user()->can('manage-any-employee-reviews')) {
            return back()->with('error', __('Only the reviewed employee can submit a self-assessment.'));
        }
        if ($employeeReview->is_locked) {
            return back()->with('error', __('This appraisal is locked and cannot be modified.'));
        }

        $tenantId = (int) creatorId();
        $validIndicatorIds = PerformanceIndicator::where('created_by', $tenantId)->pluck('id')->all();

        $validated = $request->validate([
            'self_ratings' => 'required|array|min:1',
            'self_ratings.*' => 'integer|min:1|max:5',
            'self_pros' => 'nullable|string|max:65535',
            'self_cons' => 'nullable|string|max:65535',
        ]);

        $filteredSelf = [];
        foreach ($validated['self_ratings'] as $id => $val) {
            if (in_array((int) $id, $validIndicatorIds, true)) {
                $filteredSelf[(int) $id] = (int) $val;
            }
        }

        $employeeReview->self_rating = $filteredSelf;
        $employeeReview->self_pros = PerformanceHtmlSanitizer::sanitize($validated['self_pros'] ?? null);
        $employeeReview->self_cons = PerformanceHtmlSanitizer::sanitize($validated['self_cons'] ?? null);
        $employeeReview->self_submitted_at = now();
        $employeeReview->status = 'self_submitted';
        $employeeReview->workflow_stage = 'manager_review';
        $employeeReview->save();

        UpdateEmployeeReview::dispatch($request, $employeeReview);

        return back()->with('success', __('Self-assessment submitted for manager evaluation.'));
    }

    /**
     * HR calibration & lock/unlock stage.
     */
    public function calibrate(Request $request, PerformanceEmployeeReview $employeeReview)
    {
        if (!Auth::user()->can('calibrate-employee-reviews') && !Auth::user()->can('edit-employee-reviews')) {
            return back()->with('error', __('Permission denied'));
        }
        if ((int) $employeeReview->created_by !== (int) creatorId()) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validate([
            'weighted_rating' => 'nullable|numeric|min:1|max:5',
            'calibration_notes' => 'nullable|string|max:5000',
            'is_locked' => 'nullable|boolean',
        ]);

        if (isset($validated['weighted_rating'])) {
            $employeeReview->weighted_rating = round((float) $validated['weighted_rating'], 2);
            $employeeReview->overall_score = round(((float) $validated['weighted_rating'] / 5) * 100, 2);
            $employeeReview->rating_band = PerformanceEmployeeReview::resolveRatingBand((float) $validated['weighted_rating']);
        }
        $employeeReview->calibration_notes = PerformanceHtmlSanitizer::sanitize($validated['calibration_notes'] ?? null);
        $employeeReview->calibrated_by = Auth::id();
        $employeeReview->calibrated_at = now();

        if (!empty($validated['is_locked'])) {
            $employeeReview->is_locked = true;
            $employeeReview->workflow_stage = 'locked';
            $employeeReview->status = 'completed';
            $employeeReview->completion_date ??= now();
        } else {
            $employeeReview->is_locked = false;
            $employeeReview->workflow_stage = 'acknowledged';
        }
        $employeeReview->save();

        UpdateEmployeeReview::dispatch($request, $employeeReview);

        return back()->with('success', __('Appraisal calibration saved.'));
    }

    /**
     * Employee acknowledgment sign-off.
     */
    public function acknowledge(Request $request, PerformanceEmployeeReview $employeeReview)
    {
        if ((int) $employeeReview->user_id !== (int) Auth::id() && !$this->canAccessReview($employeeReview)) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validate([
            'employee_acknowledgment_notes' => 'nullable|string|max:5000',
        ]);

        $employeeReview->acknowledged_at = now();
        $employeeReview->employee_acknowledgment_notes = PerformanceHtmlSanitizer::sanitize(
            $validated['employee_acknowledgment_notes'] ?? null
        );
        if (!$employeeReview->is_locked) {
            $employeeReview->workflow_stage = 'acknowledged';
        }
        $employeeReview->save();

        return back()->with('success', __('Appraisal acknowledgment recorded.'));
    }

    /**
     * Trigger downstream HRM actions (Promotion nomination, Award, PIP Warning, Bonus Allowance).
     */
    public function hrAction(Request $request, PerformanceEmployeeReview $employeeReview)
    {
        if (!Auth::user()->can('edit-employee-reviews') && !Auth::user()->can('calibrate-employee-reviews')) {
            return back()->with('error', __('Permission denied'));
        }
        if ((int) $employeeReview->created_by !== (int) creatorId()) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validate([
            'action' => 'required|in:promotion,award,warning,bonus_allowance',
            'effective_date' => 'nullable|date',
            'reason' => 'nullable|string|max:2000',
            'branch_id' => 'nullable|integer',
            'department_id' => 'nullable|integer',
            'designation_id' => 'nullable|integer',
            'award_type_id' => 'required_if:action,award|nullable|integer',
            'warning_type_id' => 'required_if:action,warning|nullable|integer',
            'severity' => 'nullable|in:low,medium,high,critical',
            'subject' => 'nullable|string|max:255',
            'allowance_type_id' => 'required_if:action,bonus_allowance|nullable|integer',
            'bonus_type' => 'nullable|in:fixed,percentage',
            'amount' => 'required_if:action,bonus_allowance|nullable|numeric|min:0',
        ]);

        $result = $this->erpService->dispatchHrAction($employeeReview, $validated['action'], $validated);

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    private function resolveIndicatorsForReview(PerformanceEmployeeReview $employeeReview)
    {
        if ($employeeReview->review_template_id && $employeeReview->reviewTemplate) {
            $templateIndicators = $employeeReview->reviewTemplate
                ->indicators()
                ->with('category')
                ->where('performance_indicators.status', 'active')
                ->get();
            if ($templateIndicators->isNotEmpty()) {
                return $templateIndicators;
            }
        }

        return PerformanceIndicator::with('category')
            ->where('created_by', creatorId())
            ->where('status', 'active')
            ->whereHas('category', fn($q) => $q->where('status', 'active'))
            ->get();
    }

    private function canAccessReview(PerformanceEmployeeReview $review): bool
    {
        if ((int) $review->created_by !== (int) creatorId()) {
            return false;
        }

        if (Auth::user()->can('manage-any-employee-reviews')) {
            return true;
        }

        if (Auth::user()->can('manage-own-employee-reviews')) {
            return (int) $review->creator_id === (int) Auth::id()
                || (int) $review->reviewer_id === (int) Auth::id()
                || (int) $review->user_id === (int) Auth::id();
        }

        return false;
    }

    private function getFilteredUsers()
    {
        return User::emp()->where('created_by', creatorId())
            ->when(!Auth::user()->can('manage-any-users'), function ($q) {
                if (Auth::user()->can('manage-own-users')) {
                    $q->where('creator_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->select('id', 'name')
            ->get();
    }

    private function getFilteredReviewCycles()
    {
        return PerformanceReviewCycle::where('created_by', creatorId())
            ->where('status', 'active')
            ->when(!Auth::user()->can('manage-any-review-cycles'), function ($q) {
                if (Auth::user()->can('manage-own-review-cycles')) {
                    $q->where('creator_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            })
            ->select('id', 'name')
            ->get();
    }
}
