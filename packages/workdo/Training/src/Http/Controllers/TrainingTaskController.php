<?php

namespace Workdo\Training\Http\Controllers;

use App\Http\Controllers\Controller;
use Workdo\Training\Models\TrainingTask;
use Workdo\Training\Models\Training;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\User;
use Workdo\Training\Events\CreateTrainingTask;
use Workdo\Training\Events\UpdateTrainingTask;
use Workdo\Training\Events\DestroyTrainingTask;
use Workdo\Training\Http\Requests\StoreTrainingTaskRequest;
use Workdo\Training\Http\Requests\UpdateTrainingTaskRequest;
use Workdo\Training\Traits\TrainingControllerHelpers;
use Workdo\Training\Traits\TrainingDateHelpers;

class TrainingTaskController extends Controller
{
    use TrainingControllerHelpers, TrainingDateHelpers;

    public function index(Training $training)
    {
        if(Auth::user()->can('manage-training-tasks') && $this->assertBelongsToTenant($training)){
            $tasks = TrainingTask::where('training_id', $training->id)
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-training-tasks')) {
                        $q->where('created_by', creatorId());
                    } else if(Auth::user()->can('manage-own-training-tasks')) {
                        // Show tasks user created OR assigned to user's employee
                        $user = Auth::user();
                        $q->where(function($subQ) use ($user) {
                            $subQ->where('creator_id', $user->id)
                                 ->orWhere('assigned_to', $user->id);
                        });
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->with(['assignedUser', 'feedbacks'])
                ->get();

            $users = User::emp()->where('created_by', creatorId())->select('id', 'name')->get();

            return Inertia::render('Training/tasks/index', [
                'training' => $training,
                'tasks' => $tasks,
                'users' => $users,
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function store(StoreTrainingTaskRequest $request, Training $training)
    {
        if(Auth::user()->can('create-training-tasks') && $this->assertBelongsToTenant($training)){
            $validated = $request->validated();

            $task = DB::transaction(function () use ($training, $validated) {
                $task              = new TrainingTask();
                $task->training_id = $training->id;
                $task->title       = $validated['title'];
                $task->description = \App\Support\HtmlSanitizer::sanitize($validated['description'] ?? null);
                $task->due_date    = $validated['due_date'];
                $task->assigned_to = $validated['assigned_to'];
                $task->creator_id  = Auth::id();
                $task->created_by  = creatorId();
                $task->save();
                return $task;
            });

            try {
                CreateTrainingTask::dispatch($request, $task);
            } catch (\Throwable $e) {
                // Non-blocking
            }

            return back()->with('success', __('The task has been created successfully.'));
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function update(UpdateTrainingTaskRequest $request, Training $training, TrainingTask $task)
    {
        if(Auth::user()->can('edit-training-tasks') && $this->assertBelongsToTenant($training) && $this->assertBelongsToTenant($task)){
            $validated = $request->validated();

            DB::transaction(function () use ($task, $validated) {
                $task->title       = $validated['title'];
                $task->description = \App\Support\HtmlSanitizer::sanitize($validated['description'] ?? null);
                $task->due_date    = $validated['due_date'];
                $task->assigned_to = $validated['assigned_to'];
                $task->save();
            });

            try {
                UpdateTrainingTask::dispatch($request, $task);
            } catch (\Throwable $e) {
                // Non-blocking
            }

            return back()->with('success', __('The task details are updated successfully.'));
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function complete(Training $training, TrainingTask $task)
    {
        if(Auth::user()->can('edit-training-tasks') && $this->assertBelongsToTenant($training) && $this->assertBelongsToTenant($task)){
            DB::transaction(function () use ($task) {
                $task->status = 'completed';
                $task->save();
            });

            try {
                UpdateTrainingTask::dispatch(request(), $task);
            } catch (\Throwable $e) {
                // Non-blocking
            }

            return back()->with('success', __('The task has been marked as completed.'));
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function destroy(TrainingTask $task)
    {
        if(Auth::user()->can('delete-training-tasks') && $this->assertBelongsToTenant($task)){
            DB::transaction(function () use ($task) {
                try {
                    DestroyTrainingTask::dispatch($task);
                } catch (\Throwable $e) {
                    // Non-blocking
                }

                $task->delete();
            });
            return back()->with('success', __('The task has been deleted.'));
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }
}