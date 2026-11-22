<?php

namespace Workdo\GadaacloudStudio\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Workdo\GadaacloudStudio\Models\Workflow;
use Workdo\GadaacloudStudio\Models\WorkflowRequest;
use Workdo\GadaacloudStudio\Models\WorkflowLog;

class WorkflowService
{
    /**
     * Find an active workflow for a given model class and company.
     */
    public static function getWorkflowForModel(string $modelClass, int $companyId): ?Workflow
    {
        return Workflow::where('target_model', $modelClass)
            ->where('is_active', true)
            ->where('created_by', $companyId)
            ->with(['steps' => fn($q) => $q->orderBy('sequence', 'asc')])
            ->first();
    }

    /**
     * Submit a document model instance for approval.
     * Evaluates initial step conditions and assigns the first eligible stage.
     * Returns the created request or null if no matching active workflow found.
     */
    public static function submit(Model $document, int $companyId, array $options = []): ?WorkflowRequest
    {
        $modelClass = get_class($document);
        $workflow = self::getWorkflowForModel($modelClass, $companyId);

        if (!$workflow) {
            return null;
        }

        // Check if a pending request already exists for this document
        $existing = WorkflowRequest::where('workflow_id', $workflow->id)
            ->where('model_id', $document->id)
            ->whereIn('status', ['pending'])
            ->first();

        if ($existing) {
            return $existing; // Already actively in review
        }

        $allSteps = $workflow->steps;
        if ($allSteps->isEmpty()) {
            return null;
        }

        // Evaluate conditions starting from sequence 1 to locate first eligible stage
        $firstEligibleStep = null;
        $skippedSteps = [];

        foreach ($allSteps as $step) {
            if (!$step->hasCondition()) {
                $firstEligibleStep = $step;
                break;
            }

            if (self::evaluateStepCondition($step, $document)) {
                $firstEligibleStep = $step;
                break;
            }

            if (!$step->skip_if_condition_fails) {
                // Condition not met, but step is NOT marked as skip-if-fails -> requires review
                $firstEligibleStep = $step;
                break;
            }

            // Step condition failed and skip_if_condition_fails is true -> auto-skip
            $skippedSteps[] = $step;
        }

        // If all stages were auto-skipped by conditions, document is immediately auto-approved!
        if (!$firstEligibleStep) {
            $req = WorkflowRequest::create([
                'workflow_id'     => $workflow->id,
                'model_id'        => $document->id,
                'current_step_id' => null,
                'status'          => 'approved',
                'priority'        => $options['priority'] ?? 'medium',
                'deadline_at'     => $options['deadline_at'] ?? null,
                'submitter_note'  => $options['note'] ?? null,
                'created_by'      => $companyId,
            ]);

            foreach ($skippedSteps as $skStep) {
                WorkflowLog::create([
                    'workflow_request_id' => $req->id,
                    'workflow_step_id'    => $skStep->id,
                    'user_id'             => Auth::id() ?? $companyId,
                    'action'              => 'skipped',
                    'comment'             => __('Step auto-skipped: threshold condition [:condition] not triggered.', [
                        'condition' => $skStep->conditionLabel(),
                    ]),
                ]);
            }

            WorkflowLog::create([
                'workflow_request_id' => $req->id,
                'workflow_step_id'    => $allSteps->first()->id,
                'user_id'             => Auth::id() ?? $companyId,
                'action'              => 'approved',
                'comment'             => __('Workflow auto-approved: document values fell below all conditional approval thresholds.'),
            ]);

            self::syncDocumentStatus($req, 'approved', Auth::user());
            return $req;
        }

        $req = WorkflowRequest::create([
            'workflow_id'     => $workflow->id,
            'model_id'        => $document->id,
            'current_step_id' => $firstEligibleStep->id,
            'status'          => 'pending',
            'priority'        => $options['priority'] ?? 'medium',
            'deadline_at'     => $options['deadline_at'] ?? null,
            'submitter_note'  => $options['note'] ?? null,
            'created_by'      => $companyId,
        ]);

        // Log any steps skipped upfront
        foreach ($skippedSteps as $skStep) {
            WorkflowLog::create([
                'workflow_request_id' => $req->id,
                'workflow_step_id'    => $skStep->id,
                'user_id'             => Auth::id() ?? $companyId,
                'action'              => 'skipped',
                'comment'             => __('Step auto-skipped on submission: condition [:condition] not met.', [
                    'condition' => $skStep->conditionLabel(),
                ]),
            ]);
        }

        // Log initial submission
        WorkflowLog::create([
            'workflow_request_id' => $req->id,
            'workflow_step_id'    => $firstEligibleStep->id,
            'user_id'             => Auth::id() ?? $companyId,
            'action'              => 'submitted',
            'comment'             => $options['note'] ?? __('Submitted for approval at stage: :stage', ['stage' => $firstEligibleStep->name]),
        ]);

        return $req;
    }

    /**
     * Check if a step condition is satisfied for a given document.
     * Supports both numeric comparisons and string status checks, with smart field aliasing.
     */
    public static function evaluateStepCondition($step, Model $document): bool
    {
        if (!$step->condition_field || !$step->condition_operator || $step->condition_value === null) {
            return true; // No condition defined → always proceed
        }

        $fieldName = $step->condition_field;

        // Resolve value with fallback aliases
        $fieldValue = $document->{$fieldName} ?? null;

        if ($fieldValue === null) {
            if (in_array($fieldName, ['total', 'amount', 'total_amount', 'grand_total'])) {
                $fieldValue = $document->total_amount ?? $document->amount ?? $document->total ?? $document->price ?? null;
            } elseif (in_array($fieldName, ['quantity', 'qty'])) {
                $fieldValue = $document->quantity ?? $document->qty ?? null;
            }
        }

        if ($fieldValue === null) {
            return true; // Field not present on model → skip condition
        }

        // If comparing string status or category
        if (!is_numeric($fieldValue) || !is_numeric($step->condition_value)) {
            $strDoc = strtolower(trim((string) $fieldValue));
            $strCnd = strtolower(trim((string) $step->condition_value));
            return match ($step->condition_operator) {
                'eq'  => $strDoc === $strCnd,
                'neq' => $strDoc !== $strCnd,
                default => true,
            };
        }

        $docVal  = (float) $fieldValue;
        $condVal = (float) $step->condition_value;

        return match ($step->condition_operator) {
            'gt'  => $docVal >  $condVal,
            'gte' => $docVal >= $condVal,
            'lt'  => $docVal <  $condVal,
            'lte' => $docVal <= $condVal,
            'eq'  => $docVal == $condVal,
            'neq' => $docVal != $condVal,
            default => true,
        };
    }

    /**
     * Synchronize the approval outcome with the underlying source document across all ERP modules.
     */
    public static function syncDocumentStatus(WorkflowRequest $req, string $action, $user = null): void
    {
        $doc = $req->document;
        if (!$doc) return;

        $baseClass = class_basename(get_class($doc));

        try {
            if ($action === 'approved') {
                if ($baseClass === 'LeaveApplication') {
                    $doc->update([
                        'status'      => 'approved',
                        'approved_by' => $user?->id,
                        'approved_at' => now(),
                    ]);
                } elseif (in_array($baseClass, ['SalesInvoice', 'PurchaseInvoice'])) {
                    if (in_array($doc->status, ['draft', 'pending', 'under_review'])) {
                        $doc->update(['status' => 'posted']);
                    }
                } elseif ($baseClass === 'Transfer') {
                    if ($doc->status === 'pending') {
                        $doc->update(['status' => 'approved']);
                    }
                } elseif ($baseClass === 'Expense') {
                    $doc->update(['status' => 'approved']);
                } elseif ($baseClass === 'SalesQuotation') {
                    $doc->update(['status' => 'approved']);
                } elseif ($baseClass === 'Contract') {
                    $doc->update(['status' => 'approved']);
                } elseif (in_array($baseClass, ['Loan', 'Overtime', 'Resignation', 'Termination', 'Promotion'])) {
                    $doc->update(['status' => 'approved']);
                } elseif (in_array($baseClass, ['Offer', 'JobPosting'])) {
                    $doc->update(['status' => 'approved']);
                } else {
                    if (Schema::hasColumn($doc->getTable(), 'approval_status')) {
                        $doc->update(['approval_status' => 'approved']);
                    } elseif (Schema::hasColumn($doc->getTable(), 'status') && in_array($doc->status, ['pending', 'draft', 'in_review'])) {
                        $doc->update(['status' => 'approved']);
                    }
                }
            } elseif ($action === 'rejected') {
                if ($baseClass === 'LeaveApplication') {
                    $doc->update(['status' => 'rejected']);
                } elseif (in_array($baseClass, ['SalesInvoice', 'PurchaseInvoice'])) {
                    $doc->update(['status' => 'rejected']);
                } elseif ($baseClass === 'Transfer') {
                    $doc->update(['status' => 'cancelled']);
                } elseif ($baseClass === 'Expense') {
                    $doc->update(['status' => 'rejected']);
                } elseif ($baseClass === 'SalesQuotation') {
                    $doc->update(['status' => 'declined']);
                } elseif ($baseClass === 'Contract') {
                    $doc->update(['status' => 'rejected']);
                } elseif (in_array($baseClass, ['Loan', 'Overtime', 'Resignation', 'Termination', 'Promotion'])) {
                    $doc->update(['status' => 'rejected']);
                } elseif (in_array($baseClass, ['Offer', 'JobPosting'])) {
                    $doc->update(['status' => 'rejected']);
                } else {
                    if (Schema::hasColumn($doc->getTable(), 'approval_status')) {
                        $doc->update(['approval_status' => 'rejected']);
                    } elseif (Schema::hasColumn($doc->getTable(), 'status')) {
                        $doc->update(['status' => 'rejected']);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Workflow status sync failed for {$baseClass} #{$doc->id}: " . $e->getMessage());
        }
    }

    /**
     * Get built-in workflow templates for cross-module business operations.
     */
    public static function getTemplates(): array
    {
        return [
            [
                'id'          => 'invoice_approval',
                'name'        => 'Invoice Approval Flow',
                'description' => '2-step approval for sales invoices: Operational Review followed by Finance Sign-off.',
                'icon'        => 'receipt',
                'target_model'=> 'App\\Models\\SalesInvoice',
                'steps' => [
                    ['name' => 'Operations Review',   'approver_type' => 'role', 'approver_role' => 'manager'],
                    ['name' => 'Finance Director',     'approver_type' => 'role', 'approver_role' => 'finance'],
                ],
            ],
            [
                'id'          => 'purchase_order',
                'name'        => 'Purchase Order Multi-Tier Flow',
                'description' => '3-step conditional approval for purchase invoices: Dept Head → CFO (>= 5,000 ETB) → CEO (>= 50,000 ETB).',
                'icon'        => 'shopping-cart',
                'target_model'=> 'App\\Models\\PurchaseInvoice',
                'steps' => [
                    ['name' => 'Department Head',  'approver_type' => 'role', 'approver_role' => 'manager'],
                    ['name' => 'CFO Review',       'approver_type' => 'role', 'approver_role' => 'finance', 'condition_field' => 'total_amount', 'condition_operator' => 'gte', 'condition_value' => '5000', 'skip_if_condition_fails' => true],
                    ['name' => 'CEO Final Sign-off','approver_type' => 'role', 'approver_role' => 'admin',   'condition_field' => 'total_amount', 'condition_operator' => 'gte', 'condition_value' => '50000', 'skip_if_condition_fails' => true],
                ],
            ],
            [
                'id'          => 'leave_approval',
                'name'        => 'HR Leave Approval Flow',
                'description' => 'Standard leave application workflow: Line Manager validation.',
                'icon'        => 'calendar',
                'target_model'=> 'Workdo\\Hrm\\Models\\LeaveApplication',
                'steps' => [
                    ['name' => 'Line Manager Approval', 'approver_type' => 'role', 'approver_role' => 'manager'],
                ],
            ],
            [
                'id'          => 'waybill_approval',
                'name'        => 'Inter-Branch Transfer Waybill (የጭነት ማስተላለፊያ ደረሰኝ)',
                'description' => '2-step logistics security flow: Dispatch Warehouse Manager clearance before vehicle transit.',
                'icon'        => 'package',
                'target_model'=> 'App\\Models\\Transfer',
                'steps' => [
                    ['name' => 'Warehouse Supervisor', 'approver_type' => 'role', 'approver_role' => 'manager'],
                    ['name' => 'Operations Director',  'approver_type' => 'role', 'approver_role' => 'admin', 'condition_field' => 'quantity', 'condition_operator' => 'gte', 'condition_value' => '100', 'skip_if_condition_fails' => true],
                ],
            ],
            [
                'id'          => 'expense_claim',
                'name'        => 'Expense Claim Clearance',
                'description' => '2-step expense verification: Department Manager check and Finance disbursement clearance.',
                'icon'        => 'credit-card',
                'target_model'=> 'Workdo\\Account\\Models\\Expense',
                'steps' => [
                    ['name' => 'Line Manager',      'approver_type' => 'role', 'approver_role' => 'manager'],
                    ['name' => 'Finance Clearance', 'approver_type' => 'role', 'approver_role' => 'finance', 'condition_field' => 'amount', 'condition_operator' => 'gte', 'condition_value' => '1000', 'skip_if_condition_fails' => true],
                ],
            ],
            [
                'id'          => 'quotation_approval',
                'name'        => 'Sales Quotation Flow',
                'description' => '2-step quotation sign-off before client delivery.',
                'icon'        => 'file-check',
                'target_model'=> 'Workdo\\Quotation\\Models\\SalesQuotation',
                'steps' => [
                    ['name' => 'Sales Manager Review', 'approver_type' => 'role', 'approver_role' => 'manager'],
                    ['name' => 'Commercial Director',  'approver_type' => 'role', 'approver_role' => 'admin', 'condition_field' => 'total_amount', 'condition_operator' => 'gte', 'condition_value' => '20000', 'skip_if_condition_fails' => true],
                ],
            ],
            [
                'id'          => 'loan_approval',
                'name'        => 'Staff Salary Advance / Loan Approval',
                'description' => '2-step HR loan workflow: HR Manager verification then Managing Director approval.',
                'icon'        => 'users',
                'target_model'=> 'Workdo\\Hrm\\Models\\Loan',
                'steps' => [
                    ['name' => 'HR Manager Clearance',   'approver_type' => 'role', 'approver_role' => 'manager'],
                    ['name' => 'Managing Director Approval', 'approver_type' => 'role', 'approver_role' => 'admin', 'condition_field' => 'amount', 'condition_operator' => 'gte', 'condition_value' => '5000', 'skip_if_condition_fails' => true],
                ],
            ],
            [
                'id'          => 'contract_signing',
                'name'        => 'Contract Execution Flow',
                'description' => '3-step corporate agreement flow: Legal Team → Managing Director → CEO.',
                'icon'        => 'file-text',
                'target_model'=> 'Workdo\\Contract\\Models\\Contract',
                'steps' => [
                    ['name' => 'Legal Team Review',  'approver_type' => 'role', 'approver_role' => 'manager'],
                    ['name' => 'Managing Director',  'approver_type' => 'role', 'approver_role' => 'admin'],
                    ['name' => 'Executive Sign-off', 'approver_type' => 'role', 'approver_role' => 'admin'],
                ],
            ],
            [
                'id'          => 'stock_adjustment',
                'name'        => 'Inventory Adjustment Approval',
                'description' => '1-step approval for inventory corrections and damaged goods write-offs.',
                'icon'        => 'package',
                'target_model'=> 'App\\Models\\InventoryAdjustment',
                'steps' => [
                    ['name' => 'Warehouse Manager', 'approver_type' => 'role', 'approver_role' => 'manager'],
                ],
            ],
        ];
    }
}
