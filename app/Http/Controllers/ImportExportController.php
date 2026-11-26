<?php

namespace App\Http\Controllers;

use App\Models\TradeLc;
use App\Models\TradeShipment;
use App\Models\TradeLandedCost;
use App\Models\NbeForexQueue;
use App\Models\DjiboutiPortContainer;
use App\Models\EccCustomsDuty;
use App\Models\EcxExportContract;
use App\Models\PurchaseInvoice;
use App\Models\Setting;
use App\Models\User;
use App\Services\ContainerTrackingService;
use App\Traits\ControllerHelpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\JournalEntry;
use Workdo\Account\Models\JournalEntryItem;

class ImportExportController extends Controller
{
    use ControllerHelpers;
    /**
     * Requirement 1: IE Dashboard View (Overview of all Import/Export Operations)
     */
    public function dashboard()
    {
        $companyId = creatorId();

        // 1. Letters of Credit, Shipments, Landed Costs (Strictly Tenant-Scoped)
        $lcs = TradeLc::where('created_by', $companyId)
            ->with(['user', 'creator'])
            ->orderBy('id', 'desc')
            ->get();

        $shipments = TradeShipment::where('created_by', $companyId)
            ->with('lc')
            ->orderBy('id', 'desc')
            ->get();

        $landedCosts = TradeLandedCost::where('created_by', $companyId)
            ->with('lc')
            ->orderBy('id', 'desc')
            ->get();

        // 2. Ethiopian Operational Queues & Contracts (Strictly Tenant-Scoped)
        $forexQueues = NbeForexQueue::where('created_by', $companyId)
            ->orderBy('id', 'desc')
            ->get();

        $djiboutiContainers = DjiboutiPortContainer::where('created_by', $companyId)
            ->orderBy('id', 'desc')
            ->get();

        $eccCustomsDuties = EccCustomsDuty::where('created_by', $companyId)
            ->orderBy('id', 'desc')
            ->get();

        $ecxContracts = EcxExportContract::where('created_by', $companyId)
            ->orderBy('id', 'desc')
            ->get();

        // Summary Stats Calculation
        $totalLcValueUsd = $lcs->sum('amount');
        $activeShipmentsCount = $shipments->whereIn('status', ['on_port', 'in_transit', 'customs_clearance'])->count();
        $pendingForexUsd = $forexQueues->where('queue_status', 'pending')->sum('amount_usd');
        
        // Calculate Demurrage Risk
        $totalDemurrageRiskUsd = 0;
        foreach ($djiboutiContainers as $c) {
            $discharge = \Carbon\Carbon::parse($c->discharge_date);
            $daysInPort = max(0, now()->diffInDays($discharge));
            if ($daysInPort > $c->free_storage_days && $c->status === 'in_port') {
                $overdueDays = $daysInPort - $c->free_storage_days;
                $totalDemurrageRiskUsd += ($overdueDays * floatval($c->daily_demurrage_usd));
            }
        }

        $totalExportUsd = $ecxContracts->sum('contract_value_usd');

        // Context data for LC creation
        $vendors = User::where(function ($q) use ($companyId) {
            $q->where('created_by', $companyId)->orWhere('id', $companyId);
        })->get(['id', 'name', 'email']);

        $purchaseOrders = [];
        if (Schema::hasTable('purchase_invoices')) {
            $purchaseOrders = PurchaseInvoice::where('created_by', $companyId)->get(['id', 'invoice_number', 'total_amount']);
        }

        return Inertia::render('import-export/Dashboard', [
            'lcs'                => $lcs,
            'shipments'          => $shipments,
            'landedCosts'        => $landedCosts,
            'forexQueues'        => $forexQueues,
            'djiboutiContainers' => $djiboutiContainers,
            'eccCustomsDuties'   => $eccCustomsDuties,
            'ecxContracts'       => $ecxContracts,
            'vendors'            => $vendors,
            'purchaseOrders'     => $purchaseOrders,
            'summary' => [
                'totalLcValueUsd'       => $totalLcValueUsd,
                'activeShipmentsCount'  => $activeShipmentsCount,
                'pendingForexUsd'       => $pendingForexUsd,
                'totalDemurrageRiskUsd' => $totalDemurrageRiskUsd,
                'totalExportUsd'        => $totalExportUsd,
            ],
        ]);
    }

    /**
     * Standard Settings View (Redirect to modern unified dashboard)
     */
    public function index()
    {
        return $this->dashboard();
    }

    /**
     * Store NBE Forex Allocation Queue Entry
     */
    public function storeForexQueue(Request $request)
    {
        $request->validate([
            'lc_number'        => 'required|string|max:100',
            'bank_name'        => 'required|string|max:255',
            'nbe_queue_number' => 'nullable|string|max:100',
            'amount_usd'       => 'required|numeric|min:1',
            'application_date' => 'required|date',
        ]);

        $companyId = creatorId();

        DB::transaction(function () use ($request, $companyId) {
            NbeForexQueue::create([
                'created_by'       => $companyId,
                'lc_number'        => $request->lc_number,
                'bank_name'        => $request->bank_name,
                'nbe_queue_number' => $request->nbe_queue_number ?? ('NBE-FX-' . rand(1000, 9999)),
                'is_franco_valuta' => $request->boolean('is_franco_valuta'),
                'amount_usd'       => $request->amount_usd,
                'queue_status'     => $request->input('queue_status', 'pending'),
                'application_date' => $request->application_date,
                'notes'            => $request->notes,
            ]);
        });

        return redirect()->back()->with('success', __('NBE Forex Allocation Queue entry recorded successfully.'));
    }

    /**
     * Store Djibouti Container Demurrage Record
     */
    public function storeDjiboutiContainer(Request $request)
    {
        $request->validate([
            'container_number'    => 'required|string|max:100',
            'vessel_name'         => 'required|string|max:255',
            'bill_of_lading'      => 'required|string|max:100',
            'discharge_date'      => 'required|date',
            'free_storage_days'   => 'required|integer|min:1',
            'daily_demurrage_usd' => 'required|numeric|min:0',
        ]);

        $companyId = creatorId();

        DB::transaction(function () use ($request, $companyId) {
            DjiboutiPortContainer::create([
                'created_by'          => $companyId,
                'container_number'    => $request->container_number,
                'vessel_name'         => $request->vessel_name,
                'bill_of_lading'      => $request->bill_of_lading,
                'discharge_date'      => $request->discharge_date,
                'free_storage_days'   => $request->free_storage_days,
                'daily_demurrage_usd' => $request->daily_demurrage_usd,
                'current_location'    => $request->input('current_location', 'DCT Djibouti'),
                'status'              => $request->input('status', 'in_port'),
                'notes'               => $request->notes,
            ]);
        });

        return redirect()->back()->with('success', __('Djibouti Port container demurrage tracking record saved successfully.'));
    }

    /**
     * Calculate & Record ECC Customs Duty Calculation
     */
    public function calculateEccDuty(Request $request)
    {
        $request->validate([
            'hs_code'             => 'required|string|max:50',
            'description'         => 'required|string|max:255',
            'cif_value_etb'       => 'required|numeric|min:1',
            'duty_rate_percent'   => 'required|numeric|min:0|max:100',
            'excise_rate_percent' => 'nullable|numeric|min:0|max:100',
            'vat_percent'         => 'nullable|numeric|min:0|max:100',
            'surtax_percent'      => 'nullable|numeric|min:0|max:100',
            'withholding_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        $cif = floatval($request->cif_value_etb);
        $dutyRate = floatval($request->duty_rate_percent) / 100;
        $exciseRate = floatval($request->input('excise_rate_percent', 0)) / 100;
        $vatRate = floatval($request->input('vat_percent', 15)) / 100;
        $surtaxRate = floatval($request->input('surtax_percent', 10)) / 100;
        $withholdingRate = floatval($request->input('withholding_percent', 3)) / 100;

        $dutyAmt = $cif * $dutyRate;
        $exciseAmt = ($cif + $dutyAmt) * $exciseRate;
        $vatAmt = ($cif + $dutyAmt + $exciseAmt) * $vatRate;
        $surtaxAmt = ($cif + $dutyAmt) * $surtaxRate;
        $withholdingAmt = $cif * $withholdingRate;

        $totalDutyPayable = $dutyAmt + $exciseAmt + $vatAmt + $surtaxAmt + $withholdingAmt;

        $companyId = creatorId();

        DB::transaction(function () use ($request, $companyId, $cif, $totalDutyPayable) {
            EccCustomsDuty::create([
                'created_by'             => $companyId,
                'hs_code'                => $request->hs_code,
                'description'            => $request->description,
                'cif_value_etb'          => $cif,
                'duty_rate_percent'      => $request->duty_rate_percent,
                'excise_rate_percent'    => $request->input('excise_rate_percent', 0),
                'vat_percent'            => $request->input('vat_percent', 15),
                'surtax_percent'         => $request->input('surtax_percent', 10),
                'withholding_percent'    => $request->input('withholding_percent', 3),
                'total_duty_payable_etb' => $totalDutyPayable,
            ]);
        });

        return redirect()->back()->with('success', __('Ethiopian Customs Commission duty calculated and stored successfully.'));
    }

    /**
     * Store ECX & Coffee Export Contract
     */
    public function storeEcxContract(Request $request)
    {
        $request->validate([
            'contract_number'      => 'required|string|max:100',
            'commodity_type'       => 'required|string|max:100',
            'ecx_grade'            => 'required|string|max:50',
            'quantity_metric_tons' => 'required|numeric|min:0.1',
            'contract_value_usd'   => 'required|numeric|min:1',
            'destination_country'  => 'required|string|max:100',
        ]);

        $companyId = creatorId();

        DB::transaction(function () use ($request, $companyId) {
            EcxExportContract::create([
                'created_by'           => $companyId,
                'contract_number'      => $request->contract_number,
                'commodity_type'       => $request->commodity_type,
                'ecx_grade'            => $request->ecx_grade,
                'quantity_metric_tons' => $request->quantity_metric_tons,
                'contract_value_usd'   => $request->contract_value_usd,
                'destination_country'  => $request->destination_country,
                'lc_status'            => $request->input('lc_status', 'lc_opened'),
                'mep_rate_usd'         => $request->input('mep_rate_usd'),
                'notes'                => $request->notes,
            ]);
        });

        return redirect()->back()->with('success', __('ECX Export contract recorded successfully.'));
    }

    /**
     * Create / Store LC
     */
    public function storeLc(Request $request)
    {
        $companyId = creatorId();
        $request->validate([
            'lc_number'            => 'required|string|max:100|unique:trade_lcs,lc_number',
            'purchase_order_id'    => 'nullable',
            'vendor_id'            => ['required', Rule::exists('users', 'id')->where(fn($q) => $q->where('created_by', $companyId)->orWhere('id', $companyId))],
            'issuing_bank'         => 'required|string|max:255',
            'advising_bank'        => 'nullable|string|max:255',
            'amount'               => 'required|numeric|min:0',
            'currency'             => 'required|string|max:10',
            'exchange_rate'        => 'required|numeric|min:0.000001',
            'tolerance_percent'    => 'required|numeric|min:0|max:100',
            'payment_terms'        => 'nullable|string|max:255',
            'latest_shipment_date' => 'nullable|date',
            'expiry_date'          => 'required|date|after_or_equal:today',
        ]);

        DB::transaction(function () use ($request, $companyId) {
            $lc = new TradeLc();
            $lc->created_by = $companyId;
            $lc->lc_number = $request->lc_number;
            $lc->purchase_order_id = $request->purchase_order_id;
            $lc->vendor_id = $request->vendor_id;
            $lc->issuing_bank = $request->issuing_bank;
            $lc->advising_bank = $request->advising_bank;
            $lc->amount = $request->amount;
            $lc->currency = $request->currency;
            $lc->exchange_rate = $request->exchange_rate;
            $lc->tolerance_percent = $request->tolerance_percent;
            $lc->payment_terms = $request->payment_terms;
            $lc->latest_shipment_date = $request->latest_shipment_date;
            $lc->expiry_date = $request->expiry_date;
            $lc->status = 'open';
            $lc->creator_id = Auth::id();
            $lc->save();
        });

        return redirect()->back()->with('success', __('Letter of Credit (LC) opened successfully.'));
    }

    /**
     * Create / Store Shipment
     */
    public function storeShipment(Request $request)
    {
        $request->validate([
            'lc_id'             => ['required', self::tenantExists('trade_lcs', 'id')],
            'shipping_line'     => 'required|string|max:255',
            'vessel_name'       => 'required|string|max:255',
            'voyage_number'     => 'nullable|string|max:100',
            'container_numbers' => 'nullable|string',
            'bill_of_lading'    => 'required|string|max:100|unique:trade_shipments,bill_of_lading',
            'etd'               => 'nullable|date',
            'eta'               => 'nullable|date',
        ]);

        $containers = [];
        if ($request->filled('container_numbers')) {
            $containers = array_map('trim', explode(',', $request->container_numbers));
        }

        DB::transaction(function () use ($request, $containers) {
            $shipment = new TradeShipment();
            $shipment->created_by = creatorId();
            $shipment->creator_id = Auth::id();
            $shipment->lc_id = $request->lc_id;
            $shipment->shipping_line = $request->shipping_line;
            $shipment->vessel_name = $request->vessel_name;
            $shipment->voyage_number = $request->voyage_number;
            $shipment->container_numbers = $containers;
            $shipment->bill_of_lading = $request->bill_of_lading;
            $shipment->etd = $request->etd;
            $shipment->eta = $request->eta;
            $shipment->status = 'on_port';
            $shipment->save();
        });

        return redirect()->back()->with('success', __('Shipment logistics recorded successfully.'));
    }

    /**
     * Update Shipment Status
     */
    public function updateShipmentStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:on_port,in_transit,customs_clearance,delivered',
            'atd'    => 'nullable|date',
            'ata'    => 'nullable|date',
        ]);

        $shipment = TradeShipment::where('created_by', creatorId())->findOrFail($id);
        DB::transaction(function () use ($shipment, $request) {
            $shipment->status = $request->status;
            if ($request->filled('atd')) $shipment->atd = $request->atd;
            if ($request->filled('ata')) $shipment->ata = $request->ata;
            $shipment->save();
        });

        return redirect()->back()->with('success', __('Shipment status updated successfully.'));
    }

    /**
     * Record Landed Costs & Trigger Double-Entry Booking
     */
    public function storeLandedCost(Request $request)
    {
        $request->validate([
            'lc_id'             => ['required', self::tenantExists('trade_lcs', 'id')],
            'freight_charges'   => 'required|numeric|min:0',
            'insurance_fees'    => 'required|numeric|min:0',
            'custom_duties'     => 'required|numeric|min:0',
            'agent_fees'        => 'required|numeric|min:0',
            'bank_fees'         => 'required|numeric|min:0',
            'currency'          => 'required|string|max:10',
            'allocation_method' => 'required|in:value,quantity',
            'warehouse_id'      => ['nullable', self::tenantExists('warehouses', 'id')],
            'product_id'        => ['nullable', self::tenantExists('product_service_items', 'id')],
        ]);

        $companyId = creatorId();
        $lc = TradeLc::where('created_by', $companyId)->findOrFail($request->lc_id);

        DB::beginTransaction();
        try {
            $landedCost = new TradeLandedCost();
            $landedCost->created_by = $companyId;
            $landedCost->creator_id = Auth::id();
            $landedCost->lc_id = $request->lc_id;
            $landedCost->freight_charges = $request->freight_charges;
            $landedCost->insurance_fees = $request->insurance_fees;
            $landedCost->custom_duties = $request->custom_duties;
            $landedCost->agent_fees = $request->agent_fees;
            $landedCost->bank_fees = $request->bank_fees;
            $landedCost->currency = $request->currency;
            $landedCost->allocation_method = $request->allocation_method;
            
            $totalCost = floatval($request->freight_charges) +
                         floatval($request->insurance_fees) +
                         floatval($request->custom_duties) +
                         floatval($request->agent_fees) +
                         floatval($request->bank_fees);

            // DoubleEntry General Ledger integration if Accounting module active
            if (function_exists('module_is_active') && module_is_active('Account', $companyId) && class_exists(JournalEntry::class)) {
                try {
                    $journal = new JournalEntry();
                    $journal->journal_id = rand(100000, 999999);
                    $journal->date = now()->toDateString();
                    $journal->reference = 'LC-LANDED-' . $lc->lc_number;
                    $journal->description = 'Landed Cost capitalization for LC #' . $lc->lc_number;
                    $journal->created_by = $companyId;
                    $journal->save();

                    $landedCost->is_posted_to_accounts = true;
                    $landedCost->journal_entry_id = $journal->id;
                } catch (\Throwable $jErr) {
                    \Log::warning("Landed Cost GL posting notice: " . $jErr->getMessage());
                }
            }

            $landedCost->save();

            // Capitalize landed cost into WarehouseStock valuation if target warehouse & product were specified
            if ($request->filled('warehouse_id') && $request->filled('product_id') && class_exists(\App\Services\InventoryService::class)) {
                \App\Services\InventoryService::capitalizeLandedCost(
                    companyId: (int) $companyId,
                    warehouseId: (int) $request->warehouse_id,
                    productId: (int) $request->product_id,
                    addedTotalCost: $totalCost,
                    landedCostId: (int) $landedCost->id
                );
            }

            DB::commit();

            return redirect()->back()->with('success', __('Landed cost recorded and capitalized in DoubleEntry journal entries successfully.'));

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', __('Landed cost booking failed: ') . $e->getMessage());
        }
    }

    /**
     * Superadmin Global ImportExport & Container Tracking Setup View
     */
    public function setup()
    {
        $user = Auth::user();
        if ($user->type !== 'superadmin' && !(method_exists($user, 'hasRole') && $user->hasRole('superadmin'))) {
            abort(403, __('Only Superadmin can configure global container tracking API settings.'));
        }

        $service = app(ContainerTrackingService::class);
        $settings = $service->getSettings();

        return Inertia::render('settings/import-export-setup', [
            'trackingSettings' => $settings,
        ]);
    }

    /**
     * Save Superadmin Global ImportExport & Container Tracking Setup
     */
    public function saveSetup(Request $request)
    {
        $user = Auth::user();
        if ($user->type !== 'superadmin' && !(method_exists($user, 'hasRole') && $user->hasRole('superadmin'))) {
            abort(403, __('Only Superadmin can configure global container tracking API settings.'));
        }

        $request->validate([
            'provider'         => 'required|string|in:trackingmore,shipsgo,terminal49,sandbox',
            'apiKey'           => 'nullable|string',
            'sandboxMode'      => 'required|boolean',
            'webhookSecret'    => 'nullable|string',
            'freeDays'         => 'required|integer|min:1|max:60',
            'dailyPenalty20ft' => 'required|numeric|min:0',
            'dailyPenalty40ft' => 'required|numeric|min:0',
        ]);

        $superadminUser = User::where('type', 'superadmin')->first();
        $superadminId = $superadminUser ? $superadminUser->id : $user->id;

        $fields = [
            'importexport_container_api_provider'        => $request->provider,
            'importexport_container_api_key'             => $request->apiKey ?? '',
            'importexport_container_sandbox_mode'        => $request->sandboxMode ? '1' : '0',
            'importexport_container_webhook_secret'     => $request->webhookSecret ?? '',
            'importexport_djibouti_free_days_threshold'  => (string)$request->freeDays,
            'importexport_demurrage_daily_penalty_20ft'  => (string)$request->dailyPenalty20ft,
            'importexport_demurrage_daily_penalty_40ft'  => (string)$request->dailyPenalty40ft,
        ];

        DB::transaction(function () use ($fields, $superadminId) {
            foreach ($fields as $key => $val) {
                Setting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $val, 'is_public' => 0, 'created_by' => $superadminId]
                );
            }
        });

        return redirect()->back()->with('success', __('Global Container Tracking API and Djibouti Port Demurrage policies updated successfully.'));
    }

    /**
     * Test Container Tracking Connection & Lookup (Superadmin or API check)
     */
    public function testTrackingApi(Request $request)
    {
        $request->validate([
            'container_number' => 'required|string',
            'shipping_line'    => 'nullable|string',
        ]);

        $service = app(ContainerTrackingService::class);
        $result = $service->track($request->container_number, $request->shipping_line);

        return response()->json($result);
    }

    /**
     * Live Container Tracking Endpoint for Tenants in Dashboard
     */
    public function trackContainer(Request $request, $containerNumber)
    {
        $service = app(ContainerTrackingService::class);
        $shippingLine = $request->query('shipping_line');

        // Check if container exists in company's registered Djibouti port containers
        $companyId = creatorId();
        $djiboutiRecord = DjiboutiPortContainer::where('created_by', $companyId)
            ->where('container_number', strtoupper(trim($containerNumber)))
            ->first();

        if ($djiboutiRecord && empty($shippingLine)) {
            $shippingLine = $djiboutiRecord->shipping_line;
        }

        $trackingData = $service->track($containerNumber, $shippingLine);

        // Augment with tenant-specific records if found
        if ($djiboutiRecord) {
            $trackingData['tenant_record'] = [
                'id' => $djiboutiRecord->id,
                'bl_number' => $djiboutiRecord->bl_number,
                'dry_port_destination' => $djiboutiRecord->dry_port_destination,
                'evacuation_status' => $djiboutiRecord->evacuation_status,
                'arrival_date_djibouti' => $djiboutiRecord->arrival_date_djibouti,
            ];
        }

        return response()->json($trackingData);
    }
}
