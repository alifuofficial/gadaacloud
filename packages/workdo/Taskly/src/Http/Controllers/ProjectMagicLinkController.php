<?php

namespace Workdo\Taskly\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Workdo\Taskly\Models\Project;
use Workdo\Taskly\Models\ProjectMilestone;

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
                $q->select('id', 'project_id', 'title', 'priority', 'status', 'start_date', 'due_date', 'progress', 'stage_id')
                  ->with('stage:id,name');
            },
            'files:id,project_id,file_name,file_path',
        ])
        ->where('share_token', $token)
        ->where('is_client_visible', true)
        ->firstOrFail();

        $evm = $project->getEvmMetrics();

        return Inertia::render('Taskly/PublicTrack/View', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'project_type' => $project->project_type,
                'currency_code' => $project->currency_code,
                'start_date' => $project->start_date?->format('Y-m-d'),
                'end_date' => $project->end_date?->format('Y-m-d'),
                'status' => $project->status,
                'share_token' => $project->share_token,
            ],
            'milestones' => $project->milestones,
            'tasks' => $project->tasks,
            'files' => $project->files,
            'metrics' => [
                'progress_percent' => $evm['progress_percent'],
                'total_tasks' => $project->tasks->count(),
                'completed_tasks' => $project->tasks->where('progress', 100)->count(),
            ],
        ]);
    }

    /**
     * Public client milestone approval with confirmation note.
     */
    public function clientApproveMilestone(Request $request, $token, $milestoneId)
    {
        $project = Project::where('share_token', $token)
            ->where('is_client_visible', true)
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
