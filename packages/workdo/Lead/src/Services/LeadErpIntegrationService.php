<?php

namespace Workdo\Lead\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Workdo\Lead\Models\Deal;

class LeadErpIntegrationService
{
    /**
     * Sync a converted Lead/Deal client User into Account\Models\Customer (if Account module active).
     */
    public function syncClientToAccountCustomer(User $client, array $metadata = [])
    {
        if (!function_exists('Module_is_active') || !Module_is_active('Account') || !class_exists(\Workdo\Account\Models\Customer::class)) {
            return null;
        }

        try {
            $companyId = function_exists('creatorId') ? creatorId() : ($client->created_by ?? 1);
            $customerClass = \Workdo\Account\Models\Customer::class;

            $existing = $customerClass::where('created_by', $companyId)
                ->where(function ($q) use ($client) {
                    $q->where('user_id', $client->id)
                      ->orWhere('contact_person_email', $client->email);
                })
                ->first();

            if ($existing) {
                $updates = [];
                if (empty($existing->user_id)) {
                    $updates['user_id'] = $client->id;
                }
                if (empty($existing->tax_number) && (!empty($metadata['tin_number']) || !empty($metadata['vat_number']))) {
                    $updates['tax_number'] = $metadata['tin_number'] ?? $metadata['vat_number'];
                }
                if (!empty($updates)) {
                    $existing->update($updates);
                }
                return $existing;
            }

            return $customerClass::create([
                'user_id' => $client->id,
                'company_name' => $metadata['company_name'] ?? $client->name,
                'contact_person_name' => $client->name,
                'contact_person_email' => $client->email,
                'contact_person_mobile' => $metadata['phone'] ?? $client->mobile_no ?? null,
                'tax_number' => $metadata['tin_number'] ?? $metadata['vat_number'] ?? null,
                'notes' => $metadata['notes'] ?? 'Auto-provisioned from CRM Lead conversion.',
                'creator_id' => Auth::id() ?? $companyId,
                'created_by' => $companyId,
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Convert a Deal into a SalesQuotation (if Quotation & Account modules are active).
     */
    public function convertDealToQuotation(Deal $deal)
    {
        if (
            !function_exists('Module_is_active') ||
            !Module_is_active('Quotation') ||
            !class_exists(\Workdo\Quotation\Models\SalesQuotation::class)
        ) {
            throw new \Exception(__('Quotation module is not active.'));
        }

        if ($deal->quotation_id) {
            return \Workdo\Quotation\Models\SalesQuotation::where('id', $deal->quotation_id)
                ->where('created_by', creatorId())
                ->first();
        }

        return DB::transaction(function () use ($deal) {
            $companyId = function_exists('creatorId') ? creatorId() : $deal->created_by;
            $deal->load('clientDeals.client');

            $clientUser = $deal->clientDeals->first()?->client;
            $customerId = null;

            if ($clientUser) {
                $customer = $this->syncClientToAccountCustomer($clientUser, [
                    'phone' => $deal->phone,
                    'tin_number' => $deal->tin_number,
                    'vat_number' => $deal->vat_number,
                ]);
                $customerId = $customer?->id;
            }

            if (!$customerId) {
                throw new \Exception(__('A valid Customer record is required to generate a Quotation. Please assign a client to this deal first.'));
            }

            $quotation = \Workdo\Quotation\Models\SalesQuotation::create([
                'quotation_date' => now()->toDateString(),
                'due_date' => now()->addDays(15)->toDateString(),
                'customer_id' => $customerId,
                'subtotal' => (float) ($deal->price ?? 0),
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => (float) ($deal->price ?? 0),
                'status' => 'draft',
                'notes' => trim(($deal->notes ?? '') . "\nGenerated from CRM Deal: {$deal->name}"),
                'creator_id' => Auth::id() ?? $companyId,
                'created_by' => $companyId,
            ]);

            $deal->update(['quotation_id' => $quotation->id]);

            return $quotation;
        });
    }

    /**
     * Convert a Deal into a Contract (if Contract module is active).
     */
    public function convertDealToContract(Deal $deal)
    {
        if (
            !function_exists('Module_is_active') ||
            !Module_is_active('Contract') ||
            !class_exists(\Workdo\Contract\Models\Contract::class)
        ) {
            throw new \Exception(__('Contract module is not active.'));
        }

        if ($deal->contract_id) {
            return \Workdo\Contract\Models\Contract::where('id', $deal->contract_id)
                ->where('created_by', creatorId())
                ->first();
        }

        return DB::transaction(function () use ($deal) {
            $companyId = function_exists('creatorId') ? creatorId() : $deal->created_by;
            $deal->load('clientDeals.client');
            $clientUser = $deal->clientDeals->first()?->client;

            if (!$clientUser) {
                throw new \Exception(__('Please assign a client to this deal before creating a Contract.'));
            }

            $contract = \Workdo\Contract\Models\Contract::create([
                'subject' => $deal->name,
                'user_id' => $clientUser->id,
                'value' => (float) ($deal->price ?? 0),
                'start_date' => now()->toDateString(),
                'end_date' => now()->addYear()->toDateString(),
                'description' => $deal->notes ?? "Contract generated from CRM Deal #{$deal->id}",
                'status' => 'pending',
                'source_type' => 'contract',
                'creator_id' => Auth::id() ?? $companyId,
                'created_by' => $companyId,
            ]);

            $deal->update(['contract_id' => $contract->id]);

            return $contract;
        });
    }

    /**
     * Convert a Deal into a Taskly Client Project (if Taskly module is active).
     */
    public function convertDealToProject(Deal $deal)
    {
        if (
            !function_exists('Module_is_active') ||
            !Module_is_active('Taskly') ||
            !class_exists(\Workdo\Taskly\Models\Project::class)
        ) {
            throw new \Exception(__('Taskly (Projects) module is not active.'));
        }

        if ($deal->project_id) {
            return \Workdo\Taskly\Models\Project::where('id', $deal->project_id)
                ->where('created_by', creatorId())
                ->first();
        }

        return DB::transaction(function () use ($deal) {
            $companyId = function_exists('creatorId') ? creatorId() : $deal->created_by;

            $project = \Workdo\Taskly\Models\Project::create([
                'name' => $deal->name,
                'project_scope' => 'client',
                'description' => $deal->notes ?? "Project initialized from CRM Deal #{$deal->id}",
                'budget' => (float) ($deal->price ?? 0),
                'start_date' => now()->toDateString(),
                'end_date' => now()->addMonths(3)->toDateString(),
                'status' => 'Ongoing',
                'is_client_visible' => true,
                'creator_id' => Auth::id() ?? $companyId,
                'created_by' => $companyId,
            ]);

            $deal->update(['project_id' => $project->id]);

            return $project;
        });
    }
}
