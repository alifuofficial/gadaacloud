<?php

namespace Workdo\Taskly\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Workdo\Taskly\Models\Project;
use Workdo\Taskly\Models\ProjectMilestone;
use Carbon\Carbon;

class ProjectMagicLinkController extends Controller
{
    /**
     * Public, secure client tracking portal view.
     */
    public function track($token)
    {
        $project = Project::with([
            'milestones:id,project_id,title,cost,start_date,end_date,summary,status,progress,approval_status',
            'tasks' => function ($q) {
                $q->select('id', 'project_id', 'title', 'priority', 'start_date', 'due_date', 'progress', 'stage_id')
                  ->with('stage:id,name');
            },
            'files:id,project_id,file_name,file_path',
        ])
        ->where('share_token', $token)
        ->first();

        if (!$project) {
            abort(404, __('Project tracking portal not found or token has expired.'));
        }

        $evm = $project->getEvmMetrics();

        $startDate = $project->start_date ? Carbon::parse($project->start_date)->format('Y-m-d') : null;
        $endDate = $project->end_date ? Carbon::parse($project->end_date)->format('Y-m-d') : null;

        return Inertia::render('Taskly/PublicTrack/View', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'project_type' => $project->project_type ?? 'general',
                'currency_code' => $project->currency_code ?? 'ETB',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $project->status ?? 'Ongoing',
                'share_token' => $project->share_token,
            ],
            'milestones' => $project->milestones ?? [],
            'tasks' => $project->tasks ?? [],
            'files' => $project->files ?? [],
            'metrics' => [
                'progress_percent' => $evm['progress_percent'] ?? 0,
                'total_tasks' => $project->tasks ? $project->tasks->count() : 0,
                'completed_tasks' => $project->tasks ? $project->tasks->where('progress', 100)->count() : 0,
            ],
        ]);
    }

    /**
     * Public client milestone approval with confirmation note.
     */
    public function clientApproveMilestone(Request $request, $token, $milestoneId)
    {
        $project = Project::where('share_token', $token)
            ->firstOrFail();

        $milestone = ProjectMilestone::where('project_id', $project->id)
            ->findOrFail($milestoneId);

        $milestone->approval_status = 'approved';
        $milestone->status = 'Complete';
        $milestone->progress = 100;
        $milestone->approved_at = now();
        $milestone->rejection_reason = $request->input('client_note', 'Client Approved via Portal');
        $milestone->save();

        return back()->with('success', __('Milestone approved successfully. Thank you!'));
    }
}
