<?php

namespace Workdo\GadaacloudStudio\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
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
     * Creates a WorkflowRequest at the first step.
     * Returns the created request or null if no matching workflow found.
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
            return $existing; // Already in review
        }

        $firstStep = $workflow->steps->first();
        if (!$firstStep) {
            return null;
        }

        $req = WorkflowRequest::create([
            'workflow_id'     => $workflow->id,
            'model_id'        => $document->id,
            'current_step_id' => $firstStep->id,
            'status'          => 'pending',
            'priority'        => $options['priority'] ?? 'medium',
            'deadline_at'     => $options['deadline_at'] ?? null,
            'submitter_note'  => $options['note'] ?? null,
            'created_by'      => $companyId,
        ]);

        // Log the submission
        WorkflowLog::create([
            'workflow_request_id' => $req->id,
            'workflow_step_id'    => $firstStep->id,
            'user_id'             => Auth::id() ?? $companyId,
            'action'              => 'submitted',
            'comment'             => $options['note'] ?? __('Submitted for approval'),
        ]);

        return $req;
    }

    /**
     * Check if a step condition is satisfied for a given document.
     * Returns true if step should proceed (condition passes or no condition set).
     */
    public static function evaluateStepCondition($step, Model $document): bool
    {
        if (!$step->condition_field || !$step->condition_operator || $step->condition_value === null) {
            return true; // No condition defined → always proceed
        }

        $fieldValue = $document->{$step->condition_field} ?? null;
        if ($fieldValue === null) {
            return true; // Field not present → skip condition
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
     * Get built-in workflow templates.
     */
    public static function getTemplates(): array
    {
        return [
            [
                'id'          => 'invoice_approval',
                'name'        => 'Invoice Approval Flow',
                'description' => '2-step approval for sales or purchase invoices: Manager review followed by Finance Director sign-off.',
                'icon'        => 'receipt',
                'target_model'=> 'App\\Models\\SalesInvoice',
                'steps' => [
                    ['name' => 'Manager Review',        'approver_type' => 'role', 'approver_role' => 'manager'],
                    ['name' => 'Finance Director',      'approver_type' => 'role', 'approver_role' => 'finance'],
                ],
            ],
            [
                'id'          => 'purchase_order',
                'name'        => 'Purchase Order Approval',
                'description' => '3-step approval for purchase orders: Department Head → CFO → CEO.',
                'icon'        => 'shopping-cart',
                'target_model'=> 'App\\Models\\PurchaseInvoice',
                'steps' => [
                    ['name' => 'Department Head',  'approver_type' => 'role', 'approver_role' => 'manager'],
                    ['name' => 'CFO Review',        'approver_type' => 'role', 'approver_role' => 'finance', 'condition_field' => 'total', 'condition_operator' => 'gte', 'condition_value' => '5000', 'skip_if_condition_fails' => true],
                    ['name' => 'CEO Final Sign-off','approver_type' => 'role', 'approver_role' => 'admin',   'condition_field' => 'total', 'condition_operator' => 'gte', 'condition_value' => '50000', 'skip_if_condition_fails' => true],
                ],
            ],
            [
                'id'          => 'leave_approval',
                'name'        => 'HR Leave Approval',
                'description' => 'Simple 1-step leave application approval by the Line Manager.',
                'icon'        => 'calendar',
                'target_model'=> 'Workdo\\Hrm\\Models\\LeaveApplication',
                'steps' => [
                    ['name' => 'Line Manager Approval', 'approver_type' => 'role', 'approver_role' => 'manager'],
                ],
            ],
            [
                'id'          => 'contract_signing',
                'name'        => 'Contract Signing Flow',
                'description' => '3-step contract approval: Legal Team → Managing Director → CEO.',
                'icon'        => 'file-text',
                'target_model'=> 'Workdo\\Contract\\Models\\Contract',
                'steps' => [
                    ['name' => 'Legal Review',      'approver_type' => 'role', 'approver_role' => 'manager'],
                    ['name' => 'MD Approval',       'approver_type' => 'role', 'approver_role' => 'admin'],
                    ['name' => 'CEO Final Sign-off','approver_type' => 'role', 'approver_role' => 'admin'],
                ],
            ],
            [
                'id'          => 'expense_claim',
                'name'        => 'Expense Claim Approval',
                'description' => '2-step expense approval: Line Manager verification then Finance team clearance.',
                'icon'        => 'credit-card',
                'target_model'=> 'App\\Models\\Expense',
                'steps' => [
                    ['name' => 'Line Manager',   'approver_type' => 'role', 'approver_role' => 'manager'],
                    ['name' => 'Finance Clearance','approver_type' => 'role', 'approver_role' => 'finance', 'condition_field' => 'amount', 'condition_operator' => 'gte', 'condition_value' => '500', 'skip_if_condition_fails' => true],
                ],
            ],
            [
                'id'          => 'stock_adjustment',
                'name'        => 'Inventory Adjustment Approval',
                'description' => '1-step approval for inventory stock corrections and write-offs.',
                'icon'        => 'package',
                'target_model'=> 'App\\Models\\InventoryAdjustment',
                'steps' => [
                    ['name' => 'Warehouse Manager', 'approver_type' => 'role', 'approver_role' => 'manager'],
                ],
            ],
            [
                'id'          => 'quotation_approval',
                'name'        => 'Quotation Approval',
                'description' => '2-step approval before sending a sales quotation to the customer.',
                'icon'        => 'file-check',
                'target_model'=> 'Workdo\\Quotation\\Models\\Quotation',
                'steps' => [
                    ['name' => 'Sales Manager Review', 'approver_type' => 'role', 'approver_role' => 'manager'],
                    ['name' => 'Director Clearance',   'approver_type' => 'role', 'approver_role' => 'admin', 'condition_field' => 'total', 'condition_operator' => 'gte', 'condition_value' => '10000', 'skip_if_condition_fails' => true],
                ],
            ],
            [
                'id'          => 'recruitment_approval',
                'name'        => 'Job Vacancy Approval',
                'description' => '2-step approval to publish a new job vacancy: HR Manager → Department Head.',
                'icon'        => 'users',
                'target_model'=> 'Workdo\\Recruitment\\Models\\JobApplication',
                'steps' => [
                    ['name' => 'HR Manager Review',  'approver_type' => 'role', 'approver_role' => 'manager'],
                    ['name' => 'Department Head',    'approver_type' => 'role', 'approver_role' => 'admin'],
                ],
            ],
        ];
    }
}
