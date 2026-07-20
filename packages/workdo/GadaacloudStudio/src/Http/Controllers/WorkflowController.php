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
use Spatie\Permission\Models\Role;
use App\Models\User;

class WorkflowController extends Controller
{
    /**
     * Full registry of supported target document models across all modules.
     */
    private function getTargetModels(): array
    {
        $models = [];

        // ── Account / Finance ─────────────────────────────────────────────────
        $this->addIfClassExists($models, 'App\\Models\\SalesInvoice',        'Sales Invoice',        'finance');
        $this->addIfClassExists($models, 'App\\Models\\PurchaseInvoice',     'Purchase Invoice',     'finance');
        $this->addIfClassExists($models, 'App\\Models\\SalesInvoiceReturn',  'Sales Invoice Return', 'finance');
        $this->addIfClassExists($models, 'App\\Models\\PurchaseReturn',      'Purchase Return',      'finance');
        $this->addIfClassExists($models, 'App\\Models\\Payment',             'Payment Receipt',      'finance');
        $this->addIfClassExists($models, 'App\\Models\\Expense',             'Expense',              'finance');

        // ── Inventory / Warehouse ─────────────────────────────────────────────
        $this->addIfClassExists($models, 'App\\Models\\Warehouse',           'Warehouse',            'inventory');
        $this->addIfClassExists($models, 'App\\Models\\Transfer',            'Stock Transfer',       'inventory');
        $this->addIfClassExists($models, 'App\\Models\\InventoryAdjustment', 'Stock Adjustment',     'inventory');

        // ── Products & Services ───────────────────────────────────────────────
        $this->addIfClassExists($models, 'Workdo\\ProductService\\Models\\ProductServiceItem', 'Product / Service Item', 'products');

        // ── Import & Export / Trade ───────────────────────────────────────────
        $this->addIfClassExists($models, 'Workdo\\ImportExport\\Models\\LetterOfCredit',   'Letter of Credit (LC)',  'trade');
        $this->addIfClassExists($models, 'Workdo\\ImportExport\\Models\\ShippingDocument', 'Shipping Document',      'trade');
        $this->addIfClassExists($models, 'Workdo\\ImportExport\\Models\\TradeOperation',   'Trade Operation',        'trade');

        // ── Quotation ─────────────────────────────────────────────────────────
        $this->addIfClassExists($models, 'Workdo\\Quotation\\Models\\Quotation', 'Quotation', 'sales');

        // ── HRM ───────────────────────────────────────────────────────────────
        $this->addIfClassExists($models, 'Workdo\\Hrm\\Models\\LeaveApplication',  'Leave Application',   'hrm');
        $this->addIfClassExists($models, 'Workdo\\Hrm\\Models\\ExpenseClaim',       'Expense Claim (HR)',  'hrm');
        $this->addIfClassExists($models, 'Workdo\\Hrm\\Models\\Payslip',            'Employee Payslip',    'hrm');
        $this->addIfClassExists($models, 'Workdo\\Hrm\\Models\\Employee',           'Employee Onboarding', 'hrm');

        // ── Recruitment ───────────────────────────────────────────────────────
        $this->addIfClassExists($models, 'Workdo\\Recruitment\\Models\\JobApplication', 'Job Application', 'recruitment');
        $this->addIfClassExists($models, 'Workdo\\Recruitment\\Models\\JobVacancy',     'Job Vacancy',     'recruitment');

        // ── Training ──────────────────────────────────────────────────────────
        $this->addIfClassExists($models, 'Workdo\\Training\\Models\\Training',   'Training Program',  'training');
        $this->addIfClassExists($models, 'Workdo\\Training\\Models\\Enrollment', 'Training Enrollment','training');

        // ── Contract ─────────────────────────────────────────────────────────
        $this->addIfClassExists($models, 'Workdo\\Contract\\Models\\Contract', 'Contract', 'legal');

        // ── Lead / CRM ────────────────────────────────────────────────────────
        $this->addIfClassExists($models, 'Workdo\\Lead\\Models\\Lead', 'Lead / Opportunity', 'crm');

        // ── Goal / KPI ────────────────────────────────────────────────────────
        $this->addIfClassExists($models, 'Workdo\\Goal\\Models\\Goal',       'Goal / KPI Target', 'performance');
        $this->addIfClassExists($models, 'Workdo\\Performance\\Models\\Review', 'Performance Review','performance');

        // ── Taskly ────────────────────────────────────────────────────────────
        $this->addIfClassExists($models, 'Workdo\\Taskly\\Models\\Task',    'Task',    'taskly');
        $this->addIfClassExists($models, 'Workdo\\Taskly\\Models\\Project', 'Project', 'taskly');

        // ── Timesheet ─────────────────────────────────────────────────────────
        $this->addIfClassExists($models, 'Workdo\\Timesheet\\Models\\Timesheet', 'Timesheet Entry', 'timesheet');

        // ── Support ───────────────────────────────────────────────────────────
        $this->addIfClassExists($models, 'App\\Models\\HelpdeskTicket', 'Helpdesk Ticket', 'support');

        // ── Budget ────────────────────────────────────────────────────────────
        $this->addIfClassExists($models, 'Workdo\\BudgetPlanner\\Models\\Budget', 'Budget Plan', 'finance');

        return $models;
    }

    /**
     * Add model to list only if the class actually exists (module may not be active).
     */
    private function addIfClassExists(array &$list, string $class, string $name, string $group): void
    {
        $list[] = [
            'class' => $class,
            'name'  => $name,
            'group' => $group,
            'exists'=> class_exists($class),
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
            'steps.*.approver_user_id'          => 'required_if:steps.*.approver_type,user|nullable|integer',
            'steps.*.condition_field'           => 'nullable|string|max:100',
            'steps.*.condition_operator'        => 'nullable|in:gt,gte,lt,lte,eq,neq',
            'steps.*.condition_value'           => 'nullable|string|max:100',
            'steps.*.skip_if_condition_fails'   => 'nullable|boolean',
        ]);

        $workflow = Workflow::create([
            'name'         => $request->name,
            'target_model' => $request->target_model,
            'description'  => $request->description,
            'is_active'    => true,
            'created_by'   => creatorId()
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
            ]);
        }

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
            'steps.*.approver_user_id'          => 'required_if:steps.*.approver_type,user|nullable|integer',
            'steps.*.condition_field'           => 'nullable|string|max:100',
            'steps.*.condition_operator'        => 'nullable|in:gt,gte,lt,lte,eq,neq',
            'steps.*.condition_value'           => 'nullable|string|max:100',
            'steps.*.skip_if_condition_fails'   => 'nullable|boolean',
        ]);

        $workflow->update([
            'name'         => $request->name,
            'target_model' => $request->target_model,
            'description'  => $request->description,
        ]);

        $workflow->steps()->delete();

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
            ]);
        }

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
            ]);
        }

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
