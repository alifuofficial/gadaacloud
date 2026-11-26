<?php

namespace Workdo\Taskly\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Workdo\Taskly\Models\Project;
use Workdo\Taskly\Models\ProjectTask;
use Workdo\Taskly\Models\TaskTimesheet;
use Workdo\Taskly\Traits\TasklyControllerHelpers;

class TaskTimesheetController extends Controller
{
    use TasklyControllerHelpers;

    public function store(Request $request, $projectId)
    {
        $project = Project::where('created_by', creatorId())->findOrFail($projectId);

        $validated = $request->validate([
            'task_id' => ['nullable', self::tenantExists('project_tasks', 'id')],
            'date' => 'required|date',
            'hours' => 'required|numeric|min:0.1|max:24',
            'is_billable' => 'boolean',
            'hourly_rate' => 'nullable|numeric|min:0',
            'note' => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($project, $validated) {
            $timesheet = new TaskTimesheet();
            $timesheet->project_id = $project->id;
            $timesheet->task_id = $validated['task_id'] ?? null;
            $timesheet->user_id = Auth::id();
            $timesheet->date = $validated['date'];
            $timesheet->hours = $validated['hours'];
            $timesheet->is_billable = $validated['is_billable'] ?? true;
            $timesheet->hourly_rate = $validated['hourly_rate'] ?? 0;
            $timesheet->note = $validated['note'] ?? null;
            $timesheet->status = Auth::user()->can('manage-project-timesheet') ? 'approved' : 'submitted';
            if ($timesheet->status === 'approved') {
                $timesheet->approved_by = Auth::id();
            }
            $timesheet->created_by = creatorId();
            $timesheet->save();
        });

        return back()->with('success', __('Timesheet logged successfully.'));
    }

    public function approve($id)
    {
        if (!Auth::user()->can('manage-project-timesheet')) {
            return back()->with('error', __('Permission denied.'));
        }

        $timesheet = TaskTimesheet::where('created_by', creatorId())->findOrFail($id);
        DB::transaction(function () use ($timesheet) {
            $timesheet->status = 'approved';
            $timesheet->approved_by = Auth::id();
            $timesheet->save();
        });

        return back()->with('success', __('Timesheet approved successfully.'));
    }

    public function destroy($id)
    {
        $timesheet = TaskTimesheet::where('created_by', creatorId())->findOrFail($id);

        if ($timesheet->user_id !== Auth::id() && !Auth::user()->can('manage-project-timesheet')) {
            return back()->with('error', __('Permission denied.'));
        }

        DB::transaction(function () use ($timesheet) {
            $timesheet->delete();
        });

        return back()->with('success', __('Timesheet deleted successfully.'));
    }
}
