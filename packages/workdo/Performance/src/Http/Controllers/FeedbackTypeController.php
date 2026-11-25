<?php

namespace Workdo\Performance\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Performance\Http\Requests\StoreFeedbackTypeRequest;
use Workdo\Performance\Http\Requests\UpdateFeedbackTypeRequest;
use Workdo\Performance\Models\FeedbackType;

class FeedbackTypeController extends Controller
{
    public function index()
    {
        if (!Auth::user()->can('manage-feedback-types') && !Auth::user()->can('manage-performance-system-setup')) {
            return back()->with('error', __('Permission denied'));
        }

        $feedbackTypes = FeedbackType::where('created_by', creatorId())
            ->withCount('feedbacks')
            ->latest()
            ->get();

        return Inertia::render('Performance/SystemSetup/FeedbackTypes/Index', [
            'feedbackTypes' => $feedbackTypes,
        ]);
    }

    public function store(StoreFeedbackTypeRequest $request)
    {
        if (!Auth::user()->can('create-feedback-types') && !Auth::user()->can('manage-performance-system-setup')) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        FeedbackType::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'active',
            'creator_id' => Auth::id(),
            'created_by' => creatorId(),
        ]);

        return back()->with('success', __('Feedback type created successfully.'));
    }

    public function update(UpdateFeedbackTypeRequest $request, FeedbackType $feedbackType)
    {
        if ((!Auth::user()->can('edit-feedback-types') && !Auth::user()->can('manage-performance-system-setup'))
            || (int) $feedbackType->created_by !== (int) creatorId()
        ) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        $feedbackType->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);

        return back()->with('success', __('Feedback type updated successfully.'));
    }

    public function destroy(FeedbackType $feedbackType)
    {
        if ((!Auth::user()->can('delete-feedback-types') && !Auth::user()->can('manage-performance-system-setup'))
            || (int) $feedbackType->created_by !== (int) creatorId()
        ) {
            return back()->with('error', __('Permission denied'));
        }

        if ($feedbackType->feedbacks()->exists()) {
            return back()->with('error', __('Cannot delete feedback type while feedback records exist.'));
        }

        $feedbackType->delete();

        return back()->with('success', __('Feedback type deleted successfully.'));
    }
}
