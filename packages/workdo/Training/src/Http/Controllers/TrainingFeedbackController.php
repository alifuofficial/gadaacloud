<?php

namespace Workdo\Training\Http\Controllers;

use App\Http\Controllers\Controller;
use Workdo\Training\Models\TrainingFeedback;
use Workdo\Training\Models\TrainingTask;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Workdo\Training\Events\CreateTrainingFeedback;
use Workdo\Training\Http\Requests\StoreTrainingFeedbackRequest;
use Workdo\Training\Traits\TrainingControllerHelpers;
use Workdo\Training\Traits\TrainingDateHelpers;

class TrainingFeedbackController extends Controller
{
    use TrainingControllerHelpers, TrainingDateHelpers;

    public function index(TrainingTask $task)
    {
        if (Auth::user()->can('manage-training-feedbacks') && $this->assertBelongsToTenant($task)) {
            $feedbacks = TrainingFeedback::where('training_task_id', $task->id)
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-training-feedbacks')) {
                        $q->where('created_by', creatorId());
                    } else if(Auth::user()->can('manage-own-training-feedbacks')) {
                        $user = Auth::user();
                        $q->where(function($subQ) use ($user) {
                            $subQ->where('creator_id', $user->id)
                                 ->orWhere('user_id', $user->id);
                        });
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->with(['user'])
                ->get();

            return Inertia::render('Training/feedbacks/index', [
                'task' => $task->load('training'),
                'feedbacks' => $feedbacks,
            ]);
        } else {
            return back()->with('error', __('Permission denied'));
        }
    }

    public function store(StoreTrainingFeedbackRequest $request, TrainingTask $task)
    {
        if(Auth::user()->can('create-training-feedbacks') && $this->assertBelongsToTenant($task)){
            $validated = $request->validated();

            $user = $task->assigned_to;
            if (!$user) {
                return back()->with('error', __('User is not assigned.'));
            }

            $feedback = DB::transaction(function () use ($task, $user, $validated) {
                $feedback                   = new TrainingFeedback();
                $feedback->training_task_id = $task->id;
                $feedback->user_id          = $user;
                $feedback->rating           = $validated['rating'];
                $feedback->comments         = $validated['comments'] ?? null;
                $feedback->creator_id       = Auth::id();
                $feedback->created_by       = creatorId();
                $feedback->save();
                return $feedback;
            });

            try {
                CreateTrainingFeedback::dispatch($request, $feedback);
            } catch (\Throwable $e) {
                // Non-blocking
            }

            return back()->with('success', __('The feedback has been submitted successfully.'));
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }
}
