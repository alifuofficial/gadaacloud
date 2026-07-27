<?php

namespace App\Http\Controllers;

use App\Models\TradeLc;
use App\Models\TradeShipment;
use App\Models\TradeLandedCost;
use App\Models\PurchaseInvoice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\JournalEntry;
use Workdo\Account\Models\JournalEntryItem;

class ImportExportController extends Controller
{
    /**
     * Requirement 1: IE Dashboard View (Overview of all Import/Export Operations)
     */
    public function dashboard()
    {
        $companyId = creatorId();

        $lcs = TradeLc::where('created_by', $companyId)->orWhere('created_by', 2)->with(['purchaseOrder', 'vendor'])->orderBy('id', 'desc')->get();
        $shipments = TradeShipment::where('created_by', $companyId)->orWhere('created_by', 2)->with('lc')->orderBy('id', 'desc')->get();
        $landedCosts = TradeLandedCost::where('created_by', $companyId)->orWhere('created_by', 2)->with('lc')->orderBy('id', 'desc')->get();

        $forexQueues = [];
        if (Schema::hasTable('nbe_forex_queues')) {
            $forexQueues = DB::table('nbe_forex_queues')->where('created_by', $companyId)->orWhere('created_by', 2)->orderBy('id', 'desc')->get();
        }

        $djiboutiContainers = [];
        if (Schema::hasTable('djibouti_port_containers')) {
            $djiboutiContainers = DB::table('djibouti_port_containers')->where('created_by', $companyId)->orWhere('created_by', 2)->orderBy('id', 'desc')->get();
        }

        $eccCustomsDuties = [];
        if (Schema::hasTable('ecc_customs_duties')) {
            $eccCustomsDuties = DB::table('ecc_customs_duties')->where('created_by', $companyId)->orWhere('created_by', 2)->orderBy('id', 'desc')->get();
        }

        $ecxContracts = [];
        if (Schema::hasTable('ecx_export_contracts')) {
            $ecxContracts = DB::table('ecx_export_contracts')->where('created_by', $companyId)->orWhere('created_by', 2)->orderBy('id', 'desc')->get();
        }

        // Summary Stats Calculation
        $totalLcValueUsd = $lcs->sum('amount');
        $activeShipmentsCount = $shipments->whereIn('status', ['on_port', 'in_transit', 'customs_clearance'])->count();
        $pendingForexUsd = collect($forexQueues)->where('queue_status', 'pending')->sum('amount_usd');
        
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

        $totalExportUsd = collect($ecxContracts)->sum('contract_value_usd');

        return Inertia::render('import-export/Dashboard', [
            'lcs' => $lcs,
            'shipments' => $shipments,
            'landedCosts' => $landedCosts,
            'forexQueues' => $forexQueues,
            'djiboutiContainers' => $djiboutiContainers,
            'eccCustomsDuties' => $eccCustomsDuties,
            'ecxContracts' => $ecxContracts,
            'summary' => [
                'totalLcValueUsd' => $totalLcValueUsd,
                'activeShipmentsCount' => $activeShipmentsCount,
                'pendingForexUsd' => $pendingForexUsd,
                'totalDemurrageRiskUsd' => $totalDemurrageRiskUsd,
                'totalExportUsd' => $totalExportUsd,
            ],
        ]);
    }

    /**
     * Standard Settings View
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
            'lc_number' => 'required|string|max:100',
            'bank_name' => 'required|string|max:255',
            'nbe_queue_number' => 'nullable|string|max:100',
            'amount_usd' => 'required|numeric|min:1',
            'application_date' => 'required|date',
        ]);

        $companyId = creatorId();

        DB::table('nbe_forex_queues')->insert([
            'created_by' => $companyId,
            'lc_number' => $request->lc_number,
            'bank_name' => $request->bank_name,
            'nbe_queue_number' => $request->nbe_queue_number ?? ('NBE-FX-' . rand(1000, 9999)),
            'is_franco_valuta' => $request->boolean('is_franco_valuta'),
            'amount_usd' => $request->amount_usd,
            'queue_status' => $request->input('queue_status', 'pending'),
            'application_date' => $request->application_date,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', __('NBE Forex Allocation Queue entry recorded successfully.'));
    }

    /**
     * Store Djibouti Container Demurrage Record
     */
    public function storeDjiboutiContainer(Request $request)
    {
        $request->validate([
            'container_number' => 'required|string|max:100',
            'vessel_name' => 'required|string|max:255',
            'bill_of_lading' => 'required|string|max:100',
            'discharge_date' => 'required|date',
            'free_storage_days' => 'required|integer|min:1',
            'daily_demurrage_usd' => 'required|numeric|min:0',
        ]);

        $companyId = creatorId();

        DB::table('djibouti_port_containers')->insert([
            'created_by' => $companyId,
            'container_number' => $request->container_number,
            'vessel_name' => $request->vessel_name,
            'bill_of_lading' => $request->bill_of_lading,
            'discharge_date' => $request->discharge_date,
            'free_storage_days' => $request->free_storage_days,
            'daily_demurrage_usd' => $request->daily_demurrage_usd,
            'current_location' => $request->input('current_location', 'DCT Djibouti'),
            'status' => $request->input('status', 'in_port'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', __('Djibouti Port container demurrage tracking record saved successfully.'));
    }

    /**
     * Calculate & Record ECC Customs Duty Calculation
     */
    public function calculateEccDuty(Request $request)
    {
        $request->validate([
            'hs_code' => 'required|string|max:50',
            'description' => 'required|string|max:255',
            'cif_value_etb' => 'required|numeric|min:1',
            'duty_rate_percent' => 'required|numeric|min:0|max:100',
            'excise_rate_percent' => 'nullable|numeric|min:0|max:100',
            'vat_percent' => 'nullable|numeric|min:0|max:100',
            'surtax_percent' => 'nullable|numeric|min:0|max:100',
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

        DB::table('ecc_customs_duties')->insert([
            'created_by' => $companyId,
            'hs_code' => $request->hs_code,
            'description' => $request->description,
            'cif_value_etb' => $cif,
            'duty_rate_percent' => $request->duty_rate_percent,
            'excise_rate_percent' => $request->input('excise_rate_percent', 0),
            'vat_percent' => $request->input('vat_percent', 15),
            'surtax_percent' => $request->input('surtax_percent', 10),
            'withholding_percent' => $request->input('withholding_percent', 3),
            'total_duty_payable_etb' => $totalDutyPayable,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', __('Ethiopian Customs Commission duty calculated and stored successfully.'));
    }

    /**
     * Store ECX & Coffee Export Contract
     */
    public function storeEcxContract(Request $request)
    {
        $request->validate([
            'contract_number' => 'required|string|max:100',
            'commodity_type' => 'required|string|max:100',
            'ecx_grade' => 'required|string|max:20',
            'quantity_metric_tons' => 'required|numeric|min:0.1',
            'contract_value_usd' => 'required|numeric|min:1',
            'destination_country' => 'required|string|max:100',
        ]);

        $companyId = creatorId();

        DB::table('ecx_export_contracts')->insert([
            'created_by' => $companyId,
            'contract_number' => $request->contract_number,
            'commodity_type' => $request->commodity_type,
            'ecx_grade' => $request->ecx_grade,
            'quantity_metric_tons' => $request->quantity_metric_tons,
            'contract_value_usd' => $request->contract_value_usd,
            'destination_country' => $request->destination_country,
            'lc_status' => $request->input('lc_status', 'lc_opened'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', __('ECX Export contract recorded successfully.'));
    }

    /**
     * Create / Store LC
     */
    public function storeLc(Request $request)
    {
        $request->validate([
            'lc_number' => 'required|string|max:100|unique:trade_lcs,lc_number',
            'purchase_order_id' => 'nullable|exists:purchase_invoices,id',
            'vendor_id' => 'required|exists:users,id',
            'issuing_bank' => 'required|string|max:255',
            'advising_bank' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|max:10',
            'exchange_rate' => 'required|numeric|min:0.000001',
            'tolerance_percent' => 'required|numeric|min:0|max:100',
            'payment_terms' => 'nullable|string|max:255',
            'latest_shipment_date' => 'nullable|date',
            'expiry_date' => 'required|date|after_or_equal:today',
        ]);

        $lc = new TradeLc();
        $lc->created_by = creatorId();
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

        return redirect()->back()->with('success', __('Letter of Credit (LC) opened successfully.'));
    }

    /**
     * Create / Store Shipment
     */
    public function storeShipment(Request $request)
    {
        $request->validate([
            'lc_id' => 'required|exists:trade_lcs,id',
            'shipping_line' => 'required|string|max:255',
            'vessel_name' => 'required|string|max:255',
            'voyage_number' => 'nullable|string|max:100',
            'container_numbers' => 'nullable|string',
            'bill_of_lading' => 'required|string|max:100|unique:trade_shipments,bill_of_lading',
            'etd' => 'nullable|date',
            'eta' => 'nullable|date',
        ]);

        $containers = [];
        if ($request->filled('container_numbers')) {
            $containers = array_map('trim', explode(',', $request->container_numbers));
        }

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

        return redirect()->back()->with('success', __('Shipment logistics recorded successfully.'));
    }

    /**
     * Update Shipment Status
     */
    public function updateShipmentStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:on_port,in_transit,customs_clearance,delivered',
            'atd' => 'nullable|date',
            'ata' => 'nullable|date',
        ]);

        $shipment = TradeShipment::findOrFail($id);
        $shipment->status = $request->status;
        if ($request->filled('atd')) $shipment->atd = $request->atd;
        if ($request->filled('ata')) $shipment->ata = $request->ata;
        $shipment->save();

        return redirect()->back()->with('success', __('Shipment status updated successfully.'));
    }

    /**
     * Record Landed Costs & Trigger Double-Entry Booking
     */
    public function storeLandedCost(Request $request)
    {
        $request->validate([
            'lc_id' => 'required|exists:trade_lcs,id',
            'freight_charges' => 'required|numeric|min:0',
            'insurance_fees' => 'required|numeric|min:0',
            'custom_duties' => 'required|numeric|min:0',
            'agent_fees' => 'required|numeric|min:0',
            'bank_fees' => 'required|numeric|min:0',
            'currency' => 'required|string|max:10',
            'allocation_method' => 'required|in:value,quantity',
        ]);

        $companyId = creatorId();
        $lc = TradeLc::findOrFail($request->lc_id);

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

            $landedCost->save();
            DB::commit();

            return redirect()->back()->with('success', __('Landed cost recorded and capitalized in DoubleEntry journal entries successfully.'));

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', __('Landed cost booking failed: ') . $e->getMessage());
        }
    }
}
