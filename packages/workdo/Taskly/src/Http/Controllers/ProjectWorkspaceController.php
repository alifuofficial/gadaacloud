<?php

namespace Workdo\Taskly\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Workdo\Taskly\Models\Project;
use Workdo\Taskly\Models\ProjectBoq;
use Workdo\Taskly\Models\ProjectSiteDiary;
use Workdo\Taskly\Models\ProjectLogistics;
use Workdo\Taskly\Models\ProjectMilestone;

class ProjectWorkspaceController extends Controller
{
    // ==========================================
    // 1. Bill of Quantities (BoQ) for Construction
    // ==========================================

    public function storeBoq(Request $request, $projectId)
    {
        $project = Project::where('created_by', creatorId())->findOrFail($projectId);

        $validated = $request->validate([
            'item_no' => 'required|string|max:50',
            'description' => 'required|string',
            'unit' => 'required|string|max:20',
            'quantity' => 'required|numeric|min:0.01',
            'rate' => 'required|numeric|min:0',
        ]);

        $amount = (float) ($validated['quantity'] * $validated['rate']);

        $boq = new ProjectBoq();
        $boq->project_id = $project->id;
        $boq->item_no = $validated['item_no'];
        $boq->description = $validated['description'];
        $boq->unit = $validated['unit'];
        $boq->quantity = $validated['quantity'];
        $boq->rate = $validated['rate'];
        $boq->amount = $amount;
        $boq->actual_quantity = 0;
        $boq->actual_amount = 0;
        $boq->status = 'pending';
        $boq->created_by = creatorId();
        $boq->save();

        return back()->with('success', __('BoQ line item created successfully.'));
    }

    public function updateBoq(Request $request, $boqId)
    {
        $boq = ProjectBoq::where('created_by', creatorId())->findOrFail($boqId);

        $validated = $request->validate([
            'actual_quantity' => 'required|numeric|min:0',
            'status' => 'required|in:pending,in_progress,completed',
        ]);

        $boq->actual_quantity = $validated['actual_quantity'];
        $boq->actual_amount = (float) ($validated['actual_quantity'] * $boq->rate);
        $boq->status = $validated['status'];
        $boq->save();

        return back()->with('success', __('BoQ line item updated successfully.'));
    }

    public function destroyBoq($boqId)
    {
        $boq = ProjectBoq::where('created_by', creatorId())->findOrFail($boqId);
        $boq->delete();

        return back()->with('success', __('BoQ item deleted successfully.'));
    }

    // ==========================================
    // 2. Daily Site Diary for Construction
    // ==========================================

    public function storeSiteDiary(Request $request, $projectId)
    {
        $project = Project::where('created_by', creatorId())->findOrFail($projectId);

        $validated = $request->validate([
            'date' => 'required|date',
            'weather' => 'required|string|max:100',
            'workforce_count' => 'required|integer|min:0',
            'machinery_hours' => 'nullable|numeric|min:0',
            'work_completed' => 'required|string',
            'challenges' => 'nullable|string',
        ]);

        $diary = new ProjectSiteDiary();
        $diary->project_id = $project->id;
        $diary->date = $validated['date'];
        $diary->weather = $validated['weather'];
        $diary->workforce_count = $validated['workforce_count'];
        $diary->machinery_hours = $validated['machinery_hours'] ?? 0;
        $diary->work_completed = $validated['work_completed'];
        $diary->challenges = $validated['challenges'] ?? null;
        $diary->logged_by = Auth::id();
        $diary->created_by = creatorId();
        $diary->save();

        return back()->with('success', __('Site diary logged successfully.'));
    }

    public function destroySiteDiary($diaryId)
    {
        $diary = ProjectSiteDiary::where('created_by', creatorId())->findOrFail($diaryId);
        $diary->delete();

        return back()->with('success', __('Site diary deleted successfully.'));
    }

    // ==========================================
    // 3. Import / Export Logistics Tracking
    // ==========================================

    public function updateLogistics(Request $request, $projectId)
    {
        $project = Project::where('created_by', creatorId())->findOrFail($projectId);

        $validated = $request->validate([
            'lc_number' => 'nullable|string|max:100',
            'nbe_permit_no' => 'nullable|string|max:100',
            'bl_number' => 'nullable|string|max:100',
            'container_count' => 'required|integer|min:1',
            'container_numbers' => 'nullable|string',
            'port_of_loading' => 'nullable|string|max:100',
            'discharge_port' => 'required|string|max:100',
            'dry_port' => 'required|string|max:100',
            'current_stage' => 'required|in:forex_approved,lc_opened,ocean_transit,djibouti_discharge,modjo_customs,delivered',
            'port_free_days' => 'required|integer|min:0',
            'discharge_date' => 'nullable|date',
            'demurrage_daily_cost' => 'nullable|numeric|min:0',
        ]);

        $logistics = ProjectLogistics::firstOrNew(['project_id' => $project->id]);
        $logistics->fill($validated);
        $logistics->created_by = creatorId();

        // Calculate demurrage start date if discharge date is set
        if (!empty($validated['discharge_date'])) {
            $discharge = \Carbon\Carbon::parse($validated['discharge_date']);
            $logistics->demurrage_start_date = $discharge->copy()->addDays((int) $validated['port_free_days']);
        }

        $logistics->save();

        return back()->with('success', __('Logistics shipment tracking updated.'));
    }

    // ==========================================
    // 4. Milestone Stage-Gate Approvals
    // ==========================================

    public function approveMilestone(Request $request, $milestoneId)
    {
        $milestone = ProjectMilestone::whereHas('project', function ($q) {
            $q->where('created_by', creatorId());
        })->findOrFail($milestoneId);

        $milestone->approval_status = 'approved';
        $milestone->approved_by = Auth::id();
        $milestone->approved_at = now();
        $milestone->status = 'Complete';
        $milestone->progress = 100;
        $milestone->save();

        return back()->with('success', __('Milestone approved and marked as complete.'));
    }

    public function rejectMilestone(Request $request, $milestoneId)
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $milestone = ProjectMilestone::whereHas('project', function ($q) {
            $q->where('created_by', creatorId());
        })->findOrFail($milestoneId);

        $milestone->approval_status = 'rejected';
        $milestone->rejection_reason = $validated['rejection_reason'];
        $milestone->status = 'Incomplete';
        $milestone->save();

        return back()->with('success', __('Milestone rejected with feedback provided.'));
    }
}
