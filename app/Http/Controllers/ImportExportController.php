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
use Inertia\Inertia;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\JournalEntry;
use Workdo\Account\Models\JournalEntryItem;

class ImportExportController extends Controller
{
    /**
     * Dashboard View
     */
    public function index()
    {
        $companyId = creatorId();

        $lcs = TradeLc::with(['purchaseOrder', 'vendor'])->orderBy('id', 'desc')->get();
        $shipments = TradeShipment::with('lc')->orderBy('id', 'desc')->get();
        $landedCosts = TradeLandedCost::with('lc')->orderBy('id', 'desc')->get();

        // Get purchase invoices to link LCs to
        $purchaseOrders = PurchaseInvoice::orderBy('id', 'desc')->get(['id', 'invoice_number', 'total_amount', 'vendor_id']);
        
        // Get vendor list
        $vendors = User::where('type', 'vendor')
            ->orWhere('type', 'company')
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'email']);

        // Accounts list for allocation displays
        $accounts = [];
        if (class_exists('Workdo\Account\Models\ChartOfAccount')) {
            $accounts = ChartOfAccount::where('created_by', $companyId)
                ->orderBy('account_name', 'asc')
                ->get(['id', 'account_name', 'account_code']);
        }

        return Inertia::render('settings/import_export', [
            'lcs' => $lcs,
            'shipments' => $shipments,
            'landedCosts' => $landedCosts,
            'purchaseOrders' => $purchaseOrders,
            'vendors' => $vendors,
            'accounts' => $accounts
        ]);
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

            // Double Entry Automatic Journal Entry Posting
            if ($totalCost > 0 && class_exists('Workdo\Account\Models\ChartOfAccount')) {
                // Find dynamic accounts (Debit Asset: Inventory | Credit Liability/Cash: Bank/Accruals)
                $debitAccount = ChartOfAccount::where('created_by', $companyId)
                    ->where('account_name', 'like', '%Inventory%')
                    ->first();
                if (!$debitAccount) {
                    $debitAccount = ChartOfAccount::where('created_by', $companyId)
                        ->where('account_name', 'like', '%Stock%')
                        ->first();
                }
                if (!$debitAccount) {
                    $debitAccount = ChartOfAccount::where('created_by', $companyId)->first();
                }

                $creditAccount = ChartOfAccount::where('created_by', $companyId)
                    ->where('account_name', 'like', '%Bank%')
                    ->first();
                if (!$creditAccount) {
                    $creditAccount = ChartOfAccount::where('created_by', $companyId)
                        ->where('account_name', 'like', '%Cash%')
                        ->first();
                }
                if (!$creditAccount) {
                    $creditAccount = ChartOfAccount::where('created_by', $companyId)
                        ->where('id', '!=', $debitAccount->id)
                        ->first() ?? $debitAccount;
                }

                if ($debitAccount && $creditAccount) {
                    $journal = new JournalEntry();
                    $journal->journal_date = now();
                    $journal->reference_type = 'TradeLandedCost';
                    $journal->reference_id = 0; // Temp placeholder
                    $journal->description = __('Landed Cost allocation for LC: ') . $lc->lc_number;
                    $journal->total_debit = $totalCost;
                    $journal->total_credit = $totalCost;
                    $journal->status = 'posted';
                    $journal->creator_id = Auth::id();
                    $journal->created_by = $companyId;
                    $journal->save();

                    // Update reference ID to match journal entry
                    $journal->reference_id = $journal->id;
                    $journal->save();

                    // Create items
                    JournalEntryItem::create([
                        'journal_entry_id' => $journal->id,
                        'account_id' => $debitAccount->id,
                        'description' => __('Inventory capitalization debit'),
                        'debit_amount' => $totalCost,
                        'credit_amount' => 0.00,
                        'creator_id' => Auth::id(),
                        'created_by' => $companyId
                    ]);

                    JournalEntryItem::create([
                        'journal_entry_id' => $journal->id,
                        'account_id' => $creditAccount->id,
                        'description' => __('Clearing cost liability credit'),
                        'debit_amount' => 0.00,
                        'credit_amount' => $totalCost,
                        'creator_id' => Auth::id(),
                        'created_by' => $companyId
                    ]);

                    $landedCost->is_posted_to_accounts = true;
                    $landedCost->journal_entry_id = $journal->id;
                }
            }

            $landedCost->save();
            DB::commit();

            return redirect()->back()->with('success', __('Landed cost recorded and capitalized in DoubleEntry journal entries successfully.'));

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', __('Landed cost booking failed: ') . $e->getMessage());
        }
    }
}
