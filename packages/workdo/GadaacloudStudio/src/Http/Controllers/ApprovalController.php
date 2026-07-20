<?php

namespace Workdo\GadaacloudStudio\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Workdo\GadaacloudStudio\Models\WorkflowRequest;
use Workdo\GadaacloudStudio\Models\WorkflowStep;
use Workdo\GadaacloudStudio\Models\WorkflowLog;
use Workdo\GadaacloudStudio\Models\WorkflowDelegation;
use App\Models\User;

class ApprovalController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // Index — Approval Center
    // ─────────────────────────────────────────────────────────────────────────

    public function index()
    {
        $user      = Auth::user();
        $userRoles = $user->roles->pluck('name')->toArray();

        $requests = WorkflowRequest::with([
            'workflow.steps',
            'currentStep',
            'logs.user',
            'logs.step',
            'delegatedToUser',
        ])
            ->where('created_by', creatorId())
            ->orderByRaw("FIELD(priority, 'urgent','high','medium','low')")
            ->orderBy('deadline_at', 'asc')
            ->orderBy('updated_at', 'desc')
            ->get();

        $processedRequests = $requests->map(function ($req) use ($user, $userRoles) {
            return $this->formatRequest($req, $user, $userRoles);
        });

        // Stats
        $myAction      = $processedRequests->filter(fn($r) => $r['can_action'] && $r['status'] === 'pending')->count();
        $totalPending  = $processedRequests->filter(fn($r) => $r['status'] === 'pending')->count();
        $totalApproved = $processedRequests->filter(fn($r) => $r['status'] === 'approved')->count();
        $totalRejected = $processedRequests->filter(fn($r) => $r['status'] === 'rejected')->count();
        $overdueCount  = $processedRequests->filter(fn($r) => $r['is_overdue'])->count();

        return Inertia::render('GadaacloudStudio/Approval/Index', [
            'requests' => $processedRequests->values(),
            'stats' => [
                'my_action'      => $myAction,
                'total_pending'  => $totalPending,
                'total_approved' => $totalApproved,
                'total_rejected' => $totalRejected,
                'overdue_count'  => $overdueCount,
            ],
            'users' => User::where('created_by', creatorId())
                ->orWhere('id', creatorId())
                ->get(['id', 'name', 'email']),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Show — Request Detail Page
    // ─────────────────────────────────────────────────────────────────────────

    public function show($id)
    {
        $user      = Auth::user();
        $userRoles = $user->roles->pluck('name')->toArray();

        $req = WorkflowRequest::with([
            'workflow.steps.approverUser',
            'currentStep',
            'logs.user',
            'logs.step',
            'delegations.delegatedBy',
            'delegations.delegatedTo',
            'delegatedToUser',
        ])->where('created_by', creatorId())->findOrFail($id);

        $formatted = $this->formatRequest($req, $user, $userRoles, detailed: true);

        return Inertia::render('GadaacloudStudio/Approval/Show', [
            'request' => $formatted,
            'users'   => User::where('created_by', creatorId())
                ->orWhere('id', creatorId())
                ->get(['id', 'name', 'email']),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Approve / Reject
    // ─────────────────────────────────────────────────────────────────────────

    public function approve(Request $request, $id)
    {
        $req  = WorkflowRequest::where('created_by', creatorId())->findOrFail($id);
        $user = Auth::user();

        if ($req->status !== 'pending' || !$req->currentStep) {
            return redirect()->back()->with('error', __('Request is not in pending state.'));
        }

        $step         = $req->currentStep;
        $userRoles    = $user->roles->pluck('name')->toArray();
        $isAuthorized = $this->isAuthorizedForStep($step, $user, $userRoles, $req);

        if (!$isAuthorized) {
            return redirect()->back()->with('error', __('You are not authorized to approve this step.'));
        }

        WorkflowLog::create([
            'workflow_request_id' => $req->id,
            'workflow_step_id'    => $step->id,
            'user_id'             => $user->id,
            'action'              => 'approve',
            'comment'             => $request->comment,
        ]);

        // Deactivate any delegations for this step
        WorkflowDelegation::where('workflow_request_id', $req->id)
            ->where('workflow_step_id', $step->id)
            ->update(['is_active' => false]);

        // Advance to next step, evaluating conditions
        $nextStep = $this->getNextEligibleStep($req, $step);

        if ($nextStep) {
            $req->current_step_id      = $nextStep->id;
            $req->delegated_to_user_id = null;
        } else {
            $req->status               = 'approved';
            $req->current_step_id      = null;
            $req->delegated_to_user_id = null;
        }
        $req->save();

        return redirect()->back()->with('success', __('Step approved successfully.'));
    }

    public function reject(Request $request, $id)
    {
        $req  = WorkflowRequest::where('created_by', creatorId())->findOrFail($id);
        $user = Auth::user();

        if ($req->status !== 'pending' || !$req->currentStep) {
            return redirect()->back()->with('error', __('Request is not in pending state.'));
        }

        $step         = $req->currentStep;
        $userRoles    = $user->roles->pluck('name')->toArray();
        $isAuthorized = $this->isAuthorizedForStep($step, $user, $userRoles, $req);

        if (!$isAuthorized) {
            return redirect()->back()->with('error', __('You are not authorized to reject this step.'));
        }

        WorkflowLog::create([
            'workflow_request_id' => $req->id,
            'workflow_step_id'    => $step->id,
            'user_id'             => $user->id,
            'action'              => 'reject',
            'comment'             => $request->comment,
        ]);

        $req->status               = 'rejected';
        $req->current_step_id      = null;
        $req->delegated_to_user_id = null;
        $req->save();

        return redirect()->back()->with('success', __('Request rejected.'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Delegate
    // ─────────────────────────────────────────────────────────────────────────

    public function delegate(Request $request, $id)
    {
        $request->validate([
            'delegate_to_user_id' => 'required|exists:users,id',
            'reason'              => 'nullable|string|max:500',
            'valid_until'         => 'nullable|date|after:today',
        ]);

        $req  = WorkflowRequest::where('created_by', creatorId())->findOrFail($id);
        $user = Auth::user();

        if ($req->status !== 'pending' || !$req->currentStep) {
            return redirect()->back()->with('error', __('Cannot delegate a non-pending request.'));
        }

        $step      = $req->currentStep;
        $userRoles = $user->roles->pluck('name')->toArray();

        if (!$this->isAuthorizedForStep($step, $user, $userRoles, $req)) {
            return redirect()->back()->with('error', __('You are not authorized to delegate this step.'));
        }

        // Deactivate previous delegations for this step
        WorkflowDelegation::where('workflow_request_id', $req->id)
            ->where('workflow_step_id', $step->id)
            ->update(['is_active' => false]);

        WorkflowDelegation::create([
            'workflow_request_id' => $req->id,
            'workflow_step_id'    => $step->id,
            'delegated_by_user_id'=> $user->id,
            'delegated_to_user_id'=> $request->delegate_to_user_id,
            'reason'              => $request->reason,
            'valid_from'          => now(),
            'valid_until'         => $request->valid_until,
            'is_active'           => true,
        ]);

        $req->delegated_to_user_id = $request->delegate_to_user_id;
        $req->save();

        WorkflowLog::create([
            'workflow_request_id' => $req->id,
            'workflow_step_id'    => $step->id,
            'user_id'             => $user->id,
            'action'              => 'delegated',
            'comment'             => __('Delegated to :name. Reason: :reason', [
                'name'   => User::find($request->delegate_to_user_id)?->name ?? 'Unknown',
                'reason' => $request->reason ?? '—',
            ]),
        ]);

        return redirect()->back()->with('success', __('Step delegated successfully.'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Format a WorkflowRequest into the array shape expected by the React frontend.
     */
    private function formatRequest(WorkflowRequest $req, $user, array $userRoles, bool $detailed = false): array
    {
        $doc        = $req->document;
        $docDetails = $this->getDocumentDetails($doc, $req->workflow?->target_model);
        $canAction  = $this->isAuthorizedForStep($req->currentStep, $user, $userRoles, $req);

        $deadlineAt  = $req->deadline_at ? $req->deadline_at->toDateString() : null;
        $isOverdue   = $req->status === 'pending' && $req->deadline_at && $req->deadline_at->isPast();
        $daysLeft    = $req->deadline_at ? (int) now()->diffInDays($req->deadline_at, false) : null;

        $base = [
            'id'                  => $req->id,
            'workflow_name'       => $req->workflow?->name ?? '—',
            'target_model_name'   => class_basename($req->workflow?->target_model ?? ''),
            'document_title'      => $docDetails['title'],
            'document_details'    => $docDetails['details'],
            'document_fields'     => $docDetails['fields'] ?? [],
            'status'              => $req->status,
            'priority'            => $req->priority ?? 'medium',
            'deadline_at'         => $deadlineAt,
            'days_until_deadline' => $daysLeft,
            'is_overdue'          => $isOverdue,
            'current_step_name'   => $req->currentStep?->name ?? '—',
            'submitter_note'      => $req->submitter_note,
            'can_action'          => $canAction,
            'delegated_to'        => $req->delegatedToUser?->name,
            'logs' => $req->logs->map(fn($log) => [
                'user_name'  => $log->user?->name ?? __('System'),
                'step_name'  => $log->step?->name ?? '—',
                'action'     => $log->action,
                'comment'    => $log->comment,
                'date'       => $log->created_at->format('Y-m-d H:i'),
            ]),
            'steps' => $req->workflow?->steps->map(fn($s) => [
                'id'                    => $s->id,
                'name'                  => $s->name,
                'sequence'              => $s->sequence,
                'approver_type'         => $s->approver_type,
                'approver_role'         => $s->approver_role,
                'approver_user_name'    => $s->approverUser?->name,
                'condition_label'       => $s->conditionLabel(),
                'skip_if_condition_fails'=> $s->skip_if_condition_fails,
            ]) ?? [],
        ];

        if ($detailed) {
            $base['delegations'] = $req->delegations?->map(fn($d) => [
                'delegated_by'  => $d->delegatedBy?->name,
                'delegated_to'  => $d->delegatedTo?->name,
                'reason'        => $d->reason,
                'valid_until'   => $d->valid_until?->toDateString(),
                'is_active'     => $d->isCurrentlyActive(),
            ]) ?? [];
        }

        return $base;
    }

    /**
     * Check if a user is authorized to act on a step (including delegations).
     */
    private function isAuthorizedForStep($step, $user, array $userRoles, WorkflowRequest $req): bool
    {
        if (!$step || $req->status !== 'pending') return false;

        // Direct approver assignment
        if ($step->approver_type === 'user' && $step->approver_user_id == $user->id) return true;
        if ($step->approver_type === 'role' && in_array($step->approver_role, $userRoles)) return true;

        // Check active delegation
        if ($req->delegated_to_user_id == $user->id) {
            $delegation = WorkflowDelegation::where('workflow_request_id', $req->id)
                ->where('workflow_step_id', $step->id)
                ->where('delegated_to_user_id', $user->id)
                ->where('is_active', true)
                ->first();
            if ($delegation && $delegation->isCurrentlyActive()) return true;
        }

        return false;
    }

    /**
     * Find the next eligible step after the current one, skipping steps whose conditions fail.
     */
    private function getNextEligibleStep(WorkflowRequest $req, WorkflowStep $currentStep): ?WorkflowStep
    {
        $document = $req->document;

        $nextSteps = WorkflowStep::where('workflow_id', $req->workflow_id)
            ->where('sequence', '>', $currentStep->sequence)
            ->orderBy('sequence', 'asc')
            ->get();

        foreach ($nextSteps as $step) {
            if (!$step->hasCondition()) {
                return $step; // No condition → use this step
            }

            $conditionPasses = \Workdo\GadaacloudStudio\Services\WorkflowService::evaluateStepCondition($step, $document);

            if ($conditionPasses) {
                return $step; // Condition passes → use this step
            }

            if (!$step->skip_if_condition_fails) {
                return $step; // Condition fails but NOT skip_if → still use this step
            }

            // Condition fails AND skip_if_condition_fails=true → auto-skip, log it
            WorkflowLog::create([
                'workflow_request_id' => $req->id,
                'workflow_step_id'    => $step->id,
                'user_id'             => Auth::id(),
                'action'              => 'skipped',
                'comment'             => __('Step auto-skipped: condition [:condition] not met.', [
                    'condition' => $step->conditionLabel(),
                ]),
            ]);
            // Continue looping to find the next eligible step
        }

        return null; // No more steps — workflow is fully approved
    }

    /**
     * Build document title and details for any supported model class.
     */
    private function getDocumentDetails($document, ?string $modelClass = null): array
    {
        if (!$document) {
            return ['title' => __('Unknown Document'), 'details' => '—', 'fields' => []];
        }

        $baseClass = class_basename(get_class($document));
        $title     = $baseClass;
        $details   = [];
        $fields    = [];

        // ── Finance / Invoices ────────────────────────────────────────────────
        if (in_array($baseClass, ['SalesInvoice', 'PurchaseInvoice', 'SalesInvoiceReturn', 'PurchaseReturn'])) {
            $num = $document->invoice_id ?? $document->order_id ?? $document->id;
            $title = __($baseClass) . ' #' . $num;
            if (isset($document->total))    $fields[] = ['label' => __('Total'),    'value' => '$' . number_format((float)$document->total, 2)];
            if (isset($document->status))   $fields[] = ['label' => __('Status'),   'value' => ucfirst($document->status)];
            if (isset($document->due_date)) $fields[] = ['label' => __('Due Date'), 'value' => $document->due_date];
        }

        // ── Expense ───────────────────────────────────────────────────────────
        elseif ($baseClass === 'Expense') {
            $title = __('Expense') . ' #' . $document->id;
            if (isset($document->amount))   $fields[] = ['label' => __('Amount'),   'value' => '$' . number_format((float)$document->amount, 2)];
            if (isset($document->category)) $fields[] = ['label' => __('Category'), 'value' => $document->category];
            if (isset($document->status))   $fields[] = ['label' => __('Status'),   'value' => ucfirst($document->status)];
        }

        // ── Payment ───────────────────────────────────────────────────────────
        elseif ($baseClass === 'Payment') {
            $title = __('Payment') . ' #' . ($document->payment_id ?? $document->id);
            if (isset($document->amount)) $fields[] = ['label' => __('Amount'), 'value' => '$' . number_format((float)$document->amount, 2)];
            if (isset($document->date))   $fields[] = ['label' => __('Date'),   'value' => $document->date];
        }

        // ── Leave Application ─────────────────────────────────────────────────
        elseif ($baseClass === 'LeaveApplication') {
            $leaveType = $document->leaveType?->name ?? __('Leave');
            $title     = __('Leave Request') . ': ' . $leaveType;
            $fields[]  = ['label' => __('From'),     'value' => $document->start_date ?? '—'];
            $fields[]  = ['label' => __('To'),       'value' => $document->end_date ?? '—'];
            $fields[]  = ['label' => __('Duration'),  'value' => ($document->total_days ?? '?') . ' ' . __('day(s)')];
            if (isset($document->status)) $fields[] = ['label' => __('Status'), 'value' => ucfirst($document->status)];
        }

        // ── Employee / Payslip ────────────────────────────────────────────────
        elseif (in_array($baseClass, ['Employee', 'Payslip'])) {
            $name  = $document->name ?? ($document->employee?->name ?? '#' . $document->id);
            $title = __($baseClass) . ': ' . $name;
            if (isset($document->net_salary))   $fields[] = ['label' => __('Net Salary'), 'value' => '$' . number_format((float)$document->net_salary, 2)];
            if (isset($document->department))   $fields[] = ['label' => __('Department'), 'value' => $document->department];
        }

        // ── Contract ─────────────────────────────────────────────────────────
        elseif ($baseClass === 'Contract') {
            $title = __('Contract') . ': ' . ($document->title ?? $document->name ?? '#' . $document->id);
            if (isset($document->value))      $fields[] = ['label' => __('Value'),    'value' => '$' . number_format((float)$document->value, 2)];
            if (isset($document->start_date)) $fields[] = ['label' => __('Start'),    'value' => $document->start_date];
            if (isset($document->end_date))   $fields[] = ['label' => __('End'),      'value' => $document->end_date];
            if (isset($document->status))     $fields[] = ['label' => __('Status'),   'value' => ucfirst($document->status)];
        }

        // ── Quotation ─────────────────────────────────────────────────────────
        elseif ($baseClass === 'Quotation') {
            $title = __('Quotation') . ' #' . ($document->quotation_id ?? $document->id);
            if (isset($document->total))  $fields[] = ['label' => __('Total'),  'value' => '$' . number_format((float)$document->total, 2)];
            if (isset($document->status)) $fields[] = ['label' => __('Status'), 'value' => ucfirst($document->status)];
        }

        // ── Lead ─────────────────────────────────────────────────────────────
        elseif ($baseClass === 'Lead') {
            $title = __('Lead') . ': ' . ($document->name ?? '#' . $document->id);
            if (isset($document->amount)) $fields[] = ['label' => __('Value'),  'value' => '$' . number_format((float)$document->amount, 2)];
            if (isset($document->status)) $fields[] = ['label' => __('Status'), 'value' => ucfirst($document->status)];
        }

        // ── Task / Project ────────────────────────────────────────────────────
        elseif (in_array($baseClass, ['Task', 'Project'])) {
            $title = __($baseClass) . ': ' . ($document->name ?? $document->title ?? '#' . $document->id);
            if (isset($document->due_date))  $fields[] = ['label' => __('Due Date'), 'value' => $document->due_date];
            if (isset($document->priority))  $fields[] = ['label' => __('Priority'), 'value' => ucfirst($document->priority)];
            if (isset($document->status))    $fields[] = ['label' => __('Status'),   'value' => ucfirst($document->status)];
        }

        // ── Stock Adjustment ──────────────────────────────────────────────────
        elseif ($baseClass === 'InventoryAdjustment') {
            $title = __('Stock Adjustment') . ' #' . $document->id;
            if (isset($document->quantity))        $fields[] = ['label' => __('Quantity'),  'value' => $document->quantity];
            if (isset($document->adjustment_type)) $fields[] = ['label' => __('Type'),      'value' => ucfirst($document->adjustment_type)];
            if (isset($document->reason))          $fields[] = ['label' => __('Reason'),    'value' => $document->reason];
        }

        // ── Job Application ───────────────────────────────────────────────────
        elseif (in_array($baseClass, ['JobApplication', 'JobVacancy'])) {
            $title = __($baseClass) . ': ' . ($document->name ?? $document->position ?? '#' . $document->id);
            if (isset($document->status)) $fields[] = ['label' => __('Status'), 'value' => ucfirst($document->status)];
        }

        // ── Goal / KPI ────────────────────────────────────────────────────────
        elseif (in_array($baseClass, ['Goal', 'Review'])) {
            $title = __($baseClass) . ': ' . ($document->name ?? $document->title ?? '#' . $document->id);
            if (isset($document->target))    $fields[] = ['label' => __('Target'),    'value' => $document->target];
            if (isset($document->progress))  $fields[] = ['label' => __('Progress'),  'value' => $document->progress . '%'];
        }

        // ── Generic fallback ──────────────────────────────────────────────────
        else {
            if (isset($document->name))       $title .= ': ' . $document->name;
            elseif (isset($document->title))  $title .= ': ' . $document->title;
            else                              $title .= ' #' . $document->id;

            if (isset($document->amount))     $fields[] = ['label' => __('Amount'),  'value' => '$' . number_format((float)$document->amount, 2)];
            if (isset($document->total))      $fields[] = ['label' => __('Total'),   'value' => '$' . number_format((float)$document->total, 2)];
            if (isset($document->status))     $fields[] = ['label' => __('Status'),  'value' => ucfirst($document->status)];
            if (isset($document->created_at)) $fields[] = ['label' => __('Date'),    'value' => $document->created_at->format('Y-m-d')];
        }

        $details = collect($fields)->map(fn($f) => $f['label'] . ': ' . $f['value'])->implode(' | ');

        return compact('title', 'details', 'fields');
    }
}
