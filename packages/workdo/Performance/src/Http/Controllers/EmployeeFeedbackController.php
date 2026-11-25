<?php

namespace Workdo\Performance\Http\Controllers;

use App\Models\User;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Performance\Http\Requests\StoreEmployeeFeedbackRequest;
use Workdo\Performance\Http\Requests\UpdateEmployeeFeedbackRequest;
use Workdo\Performance\Models\EmployeeFeedback;
use Workdo\Performance\Models\FeedbackType;
use Workdo\Performance\Models\PerformanceReviewCycle;
use Workdo\Performance\Support\PerformanceHtmlSanitizer;
use Workdo\Performance\Traits\PerformanceControllerHelpers;

class EmployeeFeedbackController extends Controller
{
    use PerformanceControllerHelpers;

    public function index()
    {
        if (!Auth::user()->can('manage-employee-feedback') && !Auth::user()->can('manage-performance')) {
            return back()->with('error', __('Permission denied'));
        }

        $isOwnOnly = !Auth::user()->can('manage-any-employee-feedback') && Auth::user()->can('manage-own-employee-feedback');

        $query = EmployeeFeedback::with(['employee:id,name', 'giver:id,name', 'feedbackType:id,name', 'reviewCycle:id,name'])
            ->where('created_by', creatorId())
            ->when($isOwnOnly, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('given_by', Auth::id())
                        ->orWhere(function ($rec) {
                            $rec->where('employee_id', Auth::id())->whereIn('visibility', ['team', 'public']);
                        })
                        ->orWhere('visibility', 'public');
                });
            })
            ->when(request('employee_id'), fn($q) => $q->where('employee_id', request('employee_id')))
            ->when(request('feedback_type_id'), fn($q) => $q->where('feedback_type_id', request('feedback_type_id')))
            ->when(request('relationship_type'), fn($q) => $q->where('relationship_type', request('relationship_type')));

        $feedback = $this->applySafeSort($query, [
            'date' => 'employee_feedback.date',
            'rating' => 'employee_feedback.rating',
            'visibility' => 'employee_feedback.visibility',
            'created_at' => 'employee_feedback.created_at',
        ])->paginate($this->safePerPage())->withQueryString();

        // Hide giver identity for anonymous 360 entries unless current user is the author
        $feedback->getCollection()->transform(function ($item) {
            if ($item->is_anonymous && (int) $item->given_by !== (int) Auth::id()) {
                $item->setRelation('giver', null);
            }
            return $item;
        });

        return Inertia::render('Performance/Feedback/Index', [
            'feedback' => $feedback,
            'employees' => User::emp()->where('created_by', creatorId())->select('id', 'name')->get(),
            'feedbackTypes' => FeedbackType::where('created_by', creatorId())->where('status', 'active')->select('id', 'name')->get(),
            'reviewCycles' => PerformanceReviewCycle::where('created_by', creatorId())->where('status', 'active')->select('id', 'name')->get(),
        ]);
    }

    public function store(StoreEmployeeFeedbackRequest $request)
    {
        if (!Auth::user()->can('create-employee-feedback') && !Auth::user()->can('manage-employee-feedback')) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        EmployeeFeedback::create([
            'employee_id' => $validated['employee_id'],
            'given_by' => Auth::id(),
            'feedback_type_id' => $validated['feedback_type_id'],
            'review_cycle_id' => $validated['review_cycle_id'] ?? null,
            'relationship_type' => $validated['relationship_type'] ?? 'peer',
            'date' => $validated['date'],
            'rating' => $validated['rating'] ?? null,
            'content' => PerformanceHtmlSanitizer::sanitize($validated['content']) ?? e($validated['content']),
            'visibility' => $validated['visibility'] ?? 'team',
            'is_anonymous' => (bool) ($validated['is_anonymous'] ?? false),
            'creator_id' => Auth::id(),
            'created_by' => creatorId(),
        ]);

        return back()->with('success', __('360 feedback submitted successfully.'));
    }

    public function update(UpdateEmployeeFeedbackRequest $request, EmployeeFeedback $employeeFeedback)
    {
        if ((int) $employeeFeedback->created_by !== (int) creatorId()) {
            return back()->with('error', __('Permission denied'));
        }
        $canEdit = Auth::user()->can('manage-any-employee-feedback')
            || ((int) $employeeFeedback->given_by === (int) Auth::id() && Auth::user()->can('edit-employee-feedback'));
        if (!$canEdit) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        $employeeFeedback->update([
            'employee_id' => $validated['employee_id'],
            'feedback_type_id' => $validated['feedback_type_id'],
            'review_cycle_id' => $validated['review_cycle_id'] ?? null,
            'relationship_type' => $validated['relationship_type'] ?? 'peer',
            'date' => $validated['date'],
            'rating' => $validated['rating'] ?? null,
            'content' => PerformanceHtmlSanitizer::sanitize($validated['content']) ?? e($validated['content']),
            'visibility' => $validated['visibility'] ?? 'team',
            'is_anonymous' => (bool) ($validated['is_anonymous'] ?? false),
        ]);

        return back()->with('success', __('Feedback updated successfully.'));
    }

    public function destroy(EmployeeFeedback $employeeFeedback)
    {
        if ((int) $employeeFeedback->created_by !== (int) creatorId()) {
            return back()->with('error', __('Permission denied'));
        }
        $canDelete = Auth::user()->can('delete-employee-feedback') || (int) $employeeFeedback->given_by === (int) Auth::id();
        if (!$canDelete) {
            return back()->with('error', __('Permission denied'));
        }

        $employeeFeedback->delete();

        return back()->with('success', __('Feedback deleted successfully.'));
    }
}
