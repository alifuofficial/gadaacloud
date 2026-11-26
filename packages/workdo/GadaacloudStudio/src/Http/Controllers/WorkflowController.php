<?php

namespace Workdo\GadaacloudStudio\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Workdo\GadaacloudStudio\Models\Workflow;
use Workdo\GadaacloudStudio\Models\WorkflowStep;
use Workdo\GadaacloudStudio\Models\WorkflowRequest;
use Workdo\GadaacloudStudio\Models\WorkflowLog;
use Workdo\GadaacloudStudio\Services\WorkflowService;
use Workdo\GadaacloudStudio\Traits\GadaacloudStudioControllerHelpers;
use Workdo\GadaacloudStudio\Traits\GadaacloudStudioDateHelpers;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use App\Models\User;

class WorkflowController extends Controller
{
    use GadaacloudStudioControllerHelpers, GadaacloudStudioDateHelpers;
    /**
     * Full registry of supported target document models across modules.
     * Dynamically filters based on tenant's active plan subscription and packages.
     */
    private function getTargetModels(): array
    {
        $models = [];

        // ── Core Sales & Purchase Invoicing ───────────────────────────────────
        $this->addIfActive($models, 'App\\Models\\SalesInvoice',                  'Sales Invoice',             'finance');
        $this->addIfActive($models, 'App\\Models\\PurchaseInvoice',               'Purchase Invoice',          'finance');
        $this->addIfActive($models, 'App\\Models\\SalesInvoiceReturn',            'Sales Invoice Return',      'finance');
        $this->addIfActive($models, 'App\\Models\\PurchaseReturn',                'Purchase Return',           'finance');
        $this->addIfActive($models, 'App\\Models\\SalesProposal',                 'Sales Proposal',            'finance');

        // ── Account / Finance Package ─────────────────────────────────────────
        $this->addIfActive($models, 'Workdo\\Account\\Models\\CustomerPayment',   'Customer Payment Receipt',  'finance', 'Account');
        $this->addIfActive($models, 'Workdo\\Account\\Models\\VendorPayment',     'Vendor Payment Voucher',    'finance', 'Account');
        $this->addIfActive($models, 'Workdo\\Account\\Models\\Expense',           'Expense Voucher',           'finance', 'Account');
        $this->addIfActive($models, 'Workdo\\Account\\Models\\Revenue',           'Revenue Entry',             'finance', 'Account');
        $this->addIfActive($models, 'Workdo\\Account\\Models\\BankTransfer',      'Bank Account Transfer',     'finance', 'Account');
        $this->addIfActive($models, 'Workdo\\Account\\Models\\JournalEntry',      'Journal Entry',             'finance', 'Account');
        $this->addIfActive($models, 'Workdo\\Account\\Models\\CreditNote',        'Credit Note',               'finance', 'Account');
        $this->addIfActive($models, 'Workdo\\Account\\Models\\DebitNote',         'Debit Note',                'finance', 'Account');

        // ── Inventory / Warehouse & Logistics ─────────────────────────────────
        $this->addIfActive($models, 'App\\Models\\Warehouse',                     'Warehouse',                 'inventory');
        $this->addIfActive($models, 'App\\Models\\Transfer',                      'Stock Transfer / Waybill',  'inventory');
        $this->addIfActive($models, 'App\\Models\\InventoryAdjustment',           'Stock Adjustment',          'inventory');

        // ── Products & Services ───────────────────────────────────────────────
        $this->addIfActive($models, 'Workdo\\ProductService\\Models\\ProductServiceItem', 'Product / Service Item', 'products', 'ProductService');

        // ── Import & Export / Trade ───────────────────────────────────────────
        $this->addIfActive($models, 'App\\Models\\TradeLc',                       'Letter of Credit (LC)',     'trade', 'ImportExport');
        $this->addIfActive($models, 'App\\Models\\TradeShipment',                 'Trade Shipment Logistics',  'trade', 'ImportExport');
        $this->addIfActive($models, 'App\\Models\\TradeLandedCost',               'Trade Landed Cost Calc',    'trade', 'ImportExport');

        // ── Quotation ─────────────────────────────────────────────────────────
        $this->addIfActive($models, 'Workdo\\Quotation\\Models\\SalesQuotation',  'Sales Quotation',           'sales', 'Quotation');

        // ── HRM ───────────────────────────────────────────────────────────────
        $this->addIfActive($models, 'Workdo\\Hrm\\Models\\LeaveApplication',      'Leave Application',         'hrm', 'Hrm');
        $this->addIfActive($models, 'Workdo\\Hrm\\Models\\Payroll',               'Employee Payroll Batch',    'hrm', 'Hrm');
        $this->addIfActive($models, 'Workdo\\Hrm\\Models\\Employee',              'Employee Confirmation',     'hrm', 'Hrm');
        $this->addIfActive($models, 'Workdo\\Hrm\\Models\\Loan',                  'Employee Loan Request',     'hrm', 'Hrm');
        $this->addIfActive($models, 'Workdo\\Hrm\\Models\\Overtime',              'Overtime Authorization',    'hrm', 'Hrm');
        $this->addIfActive($models, 'Workdo\\Hrm\\Models\\Resignation',           'Employee Resignation',      'hrm', 'Hrm');
        $this->addIfActive($models, 'Workdo\\Hrm\\Models\\Termination',           'Employee Clearance/Severance', 'hrm', 'Hrm');
        $this->addIfActive($models, 'Workdo\\Hrm\\Models\\Promotion',             'Employee Promotion',        'hrm', 'Hrm');

        // ── Recruitment ───────────────────────────────────────────────────────
        $this->addIfActive($models, 'Workdo\\Recruitment\\Models\\Job',           'Job Requisition',           'recruitment', 'Recruitment');
        $this->addIfActive($models, 'Workdo\\Recruitment\\Models\\JobPosting',    'Job Vacancy Posting',       'recruitment', 'Recruitment');
        $this->addIfActive($models, 'Workdo\\Recruitment\\Models\\Offer',         'Job Offer Approval',        'recruitment', 'Recruitment');

        // ── Training ──────────────────────────────────────────────────────────
        $this->addIfActive($models, 'Workdo\\Training\\Models\\Training',         'Training Program',          'training', 'Training');

        // ── Contract ─────────────────────────────────────────────────────────
        $this->addIfActive($models, 'Workdo\\Contract\\Models\\Contract',         'Commercial Contract',       'legal', 'Contract');

        // ── Lead / CRM ────────────────────────────────────────────────────────
        $this->addIfActive($models, 'Workdo\\Lead\\Models\\Lead',                 'Lead / Opportunity Deal',   'crm', 'Lead');

        // ── Goal / Performance ────────────────────────────────────────────────
        $this->addIfActive($models, 'Workdo\\Goal\\Models\\Goal',                 'Goal / Milestone Target',   'performance', 'Goal');
        $this->addIfActive($models, 'Workdo\\Performance\\Models\\PerformanceEmployeeReview', 'Performance Appraisal', 'performance', 'Performance');
        $this->addIfActive($models, 'Workdo\\Performance\\Models\\PerformanceEmployeeGoal',   'Employee KPI Goal',     'performance', 'Performance');

        // ── Taskly / Projects ─────────────────────────────────────────────────
        $this->addIfActive($models, 'Workdo\\Taskly\\Models\\Project',            'Client Project',            'taskly', 'Taskly');
        $this->addIfActive($models, 'Workdo\\Taskly\\Models\\ProjectTask',        'Milestone Project Task',    'taskly', 'Taskly');

        // ── Timesheet ─────────────────────────────────────────────────────────
        $this->addIfActive($models, 'Workdo\\Timesheet\\Models\\Timesheet',       'Staff Timesheet Log',       'timesheet', 'Timesheet');

        // ── Support ───────────────────────────────────────────────────────────
        $this->addIfActive($models, 'App\\Models\\HelpdeskTicket',                'Helpdesk Ticket',           'support');
        $this->addIfActive($models, 'Workdo\\SupportTicket\\Models\\Ticket',      'Support Center Ticket',     'support', 'SupportTicket');

        // ── Budget ────────────────────────────────────────────────────────────
        $this->addIfActive($models, 'Workdo\\BudgetPlanner\\Models\\Budget',       'Budget Plan Allocation',    'finance', 'BudgetPlanner');

        return $models;
    }

    /**
     * Add model to list only if the class exists and its parent module is active for the current company.
     */
    private function addIfActive(array &$list, string $class, string $name, string $group, ?string $requiredModule = null): void
    {
        if (!class_exists($class)) {
            return;
        }

        if ($requiredModule && function_exists('module_is_active') && !module_is_active($requiredModule)) {
            return;
        }

        $list[] = [
            'class' => $class,
            'name'  => $name,
            'group' => $group,
            'exists'=> true,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Studio Dashboard
    // ─────────────────────────────────────────────────────────────────────────

    public function dashboard()
    {
        $companyId = creatorId();

        $workflows = Workflow::where('created_by', $companyId)->get();
        $requests  = WorkflowRequest::with(['workflow', 'currentStep', 'logs'])
            ->where('created_by', $companyId)
            ->get();

        $user      = Auth::user();
        $userRoles = $user->roles->pluck('name')->toArray();

        // KPI stats
        $totalPending   = $requests->where('status', 'pending')->count();
        $totalApproved  = $requests->where('status', 'approved')->count();
        $totalRejected  = $requests->where('status', 'rejected')->count();
        $myActionCount  = $requests->filter(fn($r) => $this->canUserActOn($r, $user, $userRoles))->count();
        $overdueCount   = $requests->filter(fn($r) =>
            $r->status === 'pending' && $r->deadline_at && \Carbon\Carbon::parse($r->deadline_at)->isPast()
        )->count();

        // Workflow health table
        $workflowHealth = $workflows->map(function ($wf) use ($requests) {
            $wfReqs    = $requests->where('workflow_id', $wf->id);
            $total     = $wfReqs->count();
            $approved  = $wfReqs->where('status', 'approved')->count();
            $rejected  = $wfReqs->where('status', 'rejected')->count();
            $pending   = $wfReqs->where('status', 'pending')->count();

            $completedLogs = WorkflowLog::whereIn('workflow_request_id', $wfReqs->pluck('id'))
                ->where('action', 'approve')
                ->get();

            return [
                'id'           => $wf->id,
                'name'         => $wf->name,
                'target_model' => $wf->target_model,
                'is_active'    => $wf->is_active,
                'total'        => $total,
                'approved'     => $approved,
                'rejected'     => $rejected,
                'pending'      => $pending,
                'approval_rate'=> $total > 0 ? round(($approved / $total) * 100) : 0,
            ];
        })->values();

        // Recent activity (last 20 logs)
        $recentActivity = WorkflowLog::with(['user', 'request.workflow'])
            ->whereHas('request', fn($q) => $q->where('created_by', $companyId))
            ->orderBy('id', 'desc')
            ->take(20)
            ->get()
            ->map(fn($log) => [
                'user_name'     => $log->user->name ?? __('System'),
                'action'        => $log->action,
                'workflow_name' => $log->request->workflow->name ?? '—',
                'comment'       => $log->comment,
                'date'          => $log->created_at->diffForHumans(),
            ]);

        return Inertia::render('GadaacloudStudio/Dashboard/Index', [
            'stats' => [
                'total_workflows'   => $workflows->count(),
                'active_workflows'  => $workflows->where('is_active', true)->count(),
                'total_pending'     => $totalPending,
                'total_approved'    => $totalApproved,
                'total_rejected'    => $totalRejected,
                'my_action_count'   => $myActionCount,
                'overdue_count'     => $overdueCount,
            ],
            'workflowHealth'  => $workflowHealth,
            'recentActivity'  => $recentActivity,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Workflows CRUD
    // ─────────────────────────────────────────────────────────────────────────

    public function index()
    {
        if (!Auth::user()->can('manage-settings')) {
            abort(403, __('Permission denied'));
        }

        $companyId = creatorId();

        $workflows = Workflow::with('steps.approverUser')
            ->where('created_by', $companyId)
            ->orderBy('id', 'desc')
            ->get();

        // Attach pending request counts per workflow
        $pendingCounts = WorkflowRequest::where('created_by', $companyId)
            ->where('status', 'pending')
            ->groupBy('workflow_id')
            ->selectRaw('workflow_id, count(*) as cnt')
            ->pluck('cnt', 'workflow_id');

        $workflows->each(function ($wf) use ($pendingCounts) {
            $wf->pending_requests_count = $pendingCounts[$wf->id] ?? 0;
        });

        return Inertia::render('GadaacloudStudio/Workflow/Index', [
            'workflows' => $workflows
        ]);
    }

    public function create()
    {
        if (!Auth::user()->can('manage-settings')) {
            abort(403, __('Permission denied'));
        }

        $roles = Role::where('created_by', creatorId())->get(['id', 'name', 'label']);
        $users = User::where('created_by', creatorId())
            ->orWhere('id', creatorId())
            ->get(['id', 'name', 'email']);

        return Inertia::render('GadaacloudStudio/Workflow/CreateEdit', [
            'roles'        => $roles,
            'users'        => $users,
            'targetModels' => $this->getTargetModels(),
            'workflow'     => null
        ]);
    }

    public function store(Request $request)
    {
        if (!Auth::user()->can('manage-settings')) {
            abort(403, __('Permission denied'));
        }

        $request->validate([
            'name'                              => 'required|string|max:255',
            'target_model'                      => 'required|string|max:255',
            'description'                       => 'nullable|string',
            'steps'                             => 'required|array|min:1',
            'steps.*.name'                      => 'required|string|max:255',
            'steps.*.approver_type'             => 'required|in:role,user',
            'steps.*.approver_role'             => 'required_if:steps.*.approver_type,role|nullable|string',
            'steps.*.approver_user_id'          => ['required_if:steps.*.approver_type,user', 'nullable', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('created_by', creatorId())->orWhere('id', creatorId()))],
            'steps.*.condition_field'           => 'nullable|string|max:100',
            'steps.*.condition_operator'        => 'nullable|in:gt,gte,lt,lte,eq,neq',
            'steps.*.condition_value'           => 'nullable|string|max:100',
            'steps.*.skip_if_condition_fails'   => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($request) {
            $workflow = Workflow::create([
                'name'         => $request->name,
                'target_model' => $request->target_model,
                'description'  => $request->description,
                'is_active'    => true,
                'created_by'   => creatorId(),
            ]);

            foreach ($request->steps as $index => $step) {
                WorkflowStep::create([
                    'workflow_id'            => $workflow->id,
                    'name'                   => $step['name'],
                    'approver_type'          => $step['approver_type'],
                    'approver_role'          => $step['approver_type'] === 'role' ? $step['approver_role'] : null,
                    'approver_user_id'       => $step['approver_type'] === 'user' ? $step['approver_user_id'] : null,
                    'sequence'               => $index + 1,
                    'condition_field'        => $step['condition_field'] ?? null,
                    'condition_operator'     => $step['condition_operator'] ?? null,
                    'condition_value'        => $step['condition_value'] ?? null,
                    'skip_if_condition_fails'=> $step['skip_if_condition_fails'] ?? false,
                    'created_by'             => creatorId(),
                ]);
            }
        });

        return redirect()->route('gadaacloud-studio.workflows.index')
            ->with('success', __('Workflow created successfully.'));
    }

    public function edit($id)
    {
        if (!Auth::user()->can('manage-settings')) {
            abort(403, __('Permission denied'));
        }

        $workflow = Workflow::with('steps')->findOrFail($id);
        if ($workflow->created_by != creatorId()) {
            abort(403, __('Permission denied'));
        }

        $roles = Role::where('created_by', creatorId())->get(['id', 'name', 'label']);
        $users = User::where('created_by', creatorId())
            ->orWhere('id', creatorId())
            ->get(['id', 'name', 'email']);

        return Inertia::render('GadaacloudStudio/Workflow/CreateEdit', [
            'roles'        => $roles,
            'users'        => $users,
            'targetModels' => $this->getTargetModels(),
            'workflow'     => $workflow
        ]);
    }

    public function update(Request $request, $id)
    {
        if (!Auth::user()->can('manage-settings')) {
            abort(403, __('Permission denied'));
        }

        $workflow = Workflow::findOrFail($id);
        if ($workflow->created_by != creatorId()) {
            abort(403, __('Permission denied'));
        }

        $request->validate([
            'name'                              => 'required|string|max:255',
            'target_model'                      => 'required|string|max:255',
            'description'                       => 'nullable|string',
            'steps'                             => 'required|array|min:1',
            'steps.*.name'                      => 'required|string|max:255',
            'steps.*.approver_type'             => 'required|in:role,user',
            'steps.*.approver_role'             => 'required_if:steps.*.approver_type,role|nullable|string',
            'steps.*.approver_user_id'          => ['required_if:steps.*.approver_type,user', 'nullable', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('created_by', creatorId())->orWhere('id', creatorId()))],
            'steps.*.condition_field'           => 'nullable|string|max:100',
            'steps.*.condition_operator'        => 'nullable|in:gt,gte,lt,lte,eq,neq',
            'steps.*.condition_value'           => 'nullable|string|max:100',
            'steps.*.skip_if_condition_fails'   => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($request, $workflow) {
            $workflow->update([
                'name'         => $request->name,
                'target_model' => $request->target_model,
                'description'  => $request->description,
            ]);

            $existingStepIds = $workflow->steps->pluck('id')->toArray();
            $retainedStepIds = [];

            foreach ($request->steps as $index => $step) {
                $stepId = !empty($step['id']) ? (int) $step['id'] : null;
                $stepPayload = [
                    'name'                   => $step['name'],
                    'approver_type'          => $step['approver_type'],
                    'approver_role'          => $step['approver_type'] === 'role' ? $step['approver_role'] : null,
                    'approver_user_id'       => $step['approver_type'] === 'user' ? $step['approver_user_id'] : null,
                    'sequence'               => $index + 1,
                    'condition_field'        => $step['condition_field'] ?? null,
                    'condition_operator'     => $step['condition_operator'] ?? null,
                    'condition_value'        => $step['condition_value'] ?? null,
                    'skip_if_condition_fails'=> !empty($step['skip_if_condition_fails']),
                ];

                if ($stepId && in_array($stepId, $existingStepIds)) {
                    $existingStep = WorkflowStep::find($stepId);
                    if ($existingStep) {
                        $existingStep->update($stepPayload);
                        $retainedStepIds[] = $existingStep->id;
                    }
                } else {
                    $stepPayload['workflow_id'] = $workflow->id;
                    $stepPayload['created_by']  = creatorId();
                    $newStep = WorkflowStep::create($stepPayload);
                    $retainedStepIds[] = $newStep->id;
                }
            }

            // Only delete steps that were explicitly removed from the workflow definition
            $stepsToDelete = array_diff($existingStepIds, $retainedStepIds);
            if (!empty($stepsToDelete)) {
                // Re-assign or finalize any pending request currently at a deleted step
                $firstRetainedStep = WorkflowStep::where('workflow_id', $workflow->id)
                    ->whereIn('id', $retainedStepIds)
                    ->orderBy('sequence', 'asc')
                    ->first();

                $pendingAtDeleted = WorkflowRequest::where('workflow_id', $workflow->id)
                    ->whereIn('current_step_id', $stepsToDelete)
                    ->where('status', 'pending')
                    ->get();

                foreach ($pendingAtDeleted as $reqAtDel) {
                    if ($firstRetainedStep) {
                        $reqAtDel->update(['current_step_id' => $firstRetainedStep->id]);
                    } else {
                        $reqAtDel->update(['status' => 'approved', 'current_step_id' => null]);
                    }
                }

                WorkflowStep::whereIn('id', $stepsToDelete)->delete();
            }
        });

        return redirect()->route('gadaacloud-studio.workflows.index')
            ->with('success', __('Workflow updated successfully.'));
    }

    public function destroy($id)
    {
        if (!Auth::user()->can('manage-settings')) {
            abort(403, __('Permission denied'));
        }

        $workflow = Workflow::findOrFail($id);
        if ($workflow->created_by != creatorId()) {
            abort(403, __('Permission denied'));
        }

        $workflow->delete();

        return redirect()->route('gadaacloud-studio.workflows.index')
            ->with('success', __('Workflow deleted successfully.'));
    }

    public function toggleStatus($id)
    {
        if (!Auth::user()->can('manage-settings')) {
            abort(403, __('Permission denied'));
        }

        $workflow = Workflow::findOrFail($id);
        if ($workflow->created_by != creatorId()) {
            abort(403, __('Permission denied'));
        }

        $workflow->is_active = !$workflow->is_active;
        $workflow->save();

        return redirect()->route('gadaacloud-studio.workflows.index')
            ->with('success', __('Workflow status updated successfully.'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Templates
    // ─────────────────────────────────────────────────────────────────────────

    public function templates()
    {
        if (!Auth::user()->can('manage-settings')) {
            abort(403, __('Permission denied'));
        }

        $roles = Role::where('created_by', creatorId())->get(['id', 'name', 'label']);

        return Inertia::render('GadaacloudStudio/Workflow/Templates', [
            'templates'    => WorkflowService::getTemplates(),
            'targetModels' => $this->getTargetModels(),
            'roles'        => $roles,
        ]);
    }

    public function cloneFromTemplate(Request $request)
    {
        if (!Auth::user()->can('manage-settings')) {
            abort(403, __('Permission denied'));
        }

        $request->validate([
            'template_id'  => 'required|string',
            'name'         => 'required|string|max:255',
            'target_model' => 'required|string|max:255',
        ]);

        $templates = WorkflowService::getTemplates();
        $tpl = collect($templates)->firstWhere('id', $request->template_id);

        if (!$tpl) {
            return redirect()->back()->with('error', __('Template not found.'));
        }

        $workflow = DB::transaction(function () use ($request, $tpl) {
            $workflow = Workflow::create([
                'name'         => $request->name,
                'target_model' => $request->target_model,
                'description'  => $tpl['description'] ?? null,
                'is_active'    => true,
                'created_by'   => creatorId(),
            ]);

            foreach ($tpl['steps'] as $index => $step) {
                WorkflowStep::create([
                    'workflow_id'            => $workflow->id,
                    'name'                   => $step['name'],
                    'approver_type'          => $step['approver_type'],
                    'approver_role'          => $step['approver_role'] ?? null,
                    'approver_user_id'       => $step['approver_user_id'] ?? null,
                    'sequence'               => $index + 1,
                    'condition_field'        => $step['condition_field'] ?? null,
                    'condition_operator'     => $step['condition_operator'] ?? null,
                    'condition_value'        => $step['condition_value'] ?? null,
                    'skip_if_condition_fails'=> $step['skip_if_condition_fails'] ?? false,
                    'created_by'             => creatorId(),
                ]);
            }

            return $workflow;
        });

        return redirect()->route('gadaacloud-studio.workflows.edit', $workflow->id)
            ->with('success', __('Workflow cloned from template. Review and customize below.'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function canUserActOn($req, $user, array $userRoles): bool
    {
        if ($req->status !== 'pending' || !$req->currentStep) return false;
        $step = $req->currentStep;
        if ($step->approver_type === 'user' && $step->approver_user_id == $user->id) return true;
        if ($step->approver_type === 'role' && in_array($step->approver_role, $userRoles)) return true;
        return false;
    }
}
