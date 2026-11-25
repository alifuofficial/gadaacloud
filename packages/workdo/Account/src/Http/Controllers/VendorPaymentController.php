<?php

namespace Workdo\Account\Http\Controllers;

use App\Models\PurchaseInvoice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Workdo\Account\Events\CreateVendorPayment;
use Workdo\Account\Events\DestroyVendorPayment;
use Workdo\Account\Events\UpdateVendorPaymentStatus;
use Workdo\Account\Helpers\AccountUtility;
use Workdo\Account\Http\Requests\StoreVendorPaymentRequest;
use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\DebitNote;
use Workdo\Account\Models\DebitNoteApplication;
use Workdo\Account\Models\VendorPayment;
use Workdo\Account\Models\VendorPaymentAllocation;
use Workdo\Account\Services\BankTransactionsService;
use Workdo\Account\Services\JournalService;

class VendorPaymentController extends Controller
{
    protected $journalService;
    protected $bankTransactionsService;

    public function __construct(JournalService $journalService, BankTransactionsService $bankTransactionsService)
    {
        $this->journalService = $journalService;
        $this->bankTransactionsService = $bankTransactionsService;
    }

    public function index(Request $request)
    {
        if (!Auth::user()->can('manage-vendor-payments')) {
            return back()->with('error', __('Permission denied'));
        }

        [$sortCol, $sortDir] = AccountUtility::sanitizeSort(
            $request->get('sort'),
            ['payment_number', 'payment_date', 'payment_amount', 'wht_amount', 'currency_code', 'status', 'created_at'],
            'created_at',
            $request->get('direction', 'desc')
        );

        $query = VendorPayment::with(['vendor', 'bankAccount', 'allocations.invoice', 'debitNoteApplications.debitNote'])
            ->where(function ($q) {
                if (Auth::user()->can('manage-any-vendor-payments')) {
                    $q->where('created_by', creatorId());
                } elseif (Auth::user()->can('manage-own-vendor-payments')) {
                    $q->where('created_by', creatorId())
                        ->where(function ($sub) {
                            $sub->where('creator_id', Auth::id())->orWhere('vendor_id', Auth::id());
                        });
                } else {
                    $q->whereRaw('1 = 0');
                }
            });

        if ($request->vendor_id) {
            $query->where('vendor_id', $request->vendor_id);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->search) {
            $query->where('payment_number', 'like', '%' . $request->search . '%');
        }
        if ($request->date_from) {
            $query->whereDate('payment_date', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('payment_date', '<=', $request->date_to);
        }
        if ($request->bank_account_id) {
            $query->where('bank_account_id', $request->bank_account_id);
        }

        $query->orderBy($sortCol, $sortDir);

        $payments = $query->paginate($request->get('per_page', 10));
        $vendors = User::where('type', 'vendor')->where('created_by', creatorId())->get();
        $bankAccounts = BankAccount::where('is_active', true)->where('created_by', creatorId())->get();

        return Inertia::render('Account/VendorPayments/Index', [
            'payments'     => $payments,
            'vendors'      => $vendors,
            'bankAccounts' => $bankAccounts,
            'filters'      => $request->only(['vendor_id', 'status', 'search', 'bank_account_id']),
        ]);
    }

    public function store(StoreVendorPaymentRequest $request)
    {
        if (!Auth::user()->can('create-vendor-payments')) {
            return back()->with('error', __('Permission denied'));
        }

        $companyId = creatorId();
        if (!$request->allocations || count($request->allocations) === 0) {
            return back()->with('error', __('At least one invoice allocation is required to create a payment.'));
        }

        $vendorExists = User::where('id', $request->vendor_id)
            ->where('type', 'vendor')
            ->where('created_by', $companyId)
            ->exists();
        $bankAccount = BankAccount::where('id', $request->bank_account_id)
            ->where('created_by', $companyId)
            ->first();

        if (!$vendorExists || !$bankAccount) {
            return back()->with('error', __('Invalid vendor or bank account selected.'));
        }

        $totalInvoiceAllocation = round((float) collect($request->allocations)->sum('amount'), 2);
        $totalDebitNoteAmount = round((float) collect($request->debit_notes ?? [])->sum('amount'), 2);

        if ($totalDebitNoteAmount > $totalInvoiceAllocation) {
            return back()->with('error', __('Debit note amount cannot exceed the total invoice allocation amount.'));
        }

        foreach ($request->allocations as $allocation) {
            $invoice = PurchaseInvoice::where('id', $allocation['invoice_id'])
                ->where('created_by', $companyId)
                ->where('vendor_id', $request->vendor_id)
                ->first();
            if (!$invoice) {
                return back()->with('error', __('Invalid purchase invoice selected for this vendor.'));
            }
            if ((float) $allocation['amount'] > (float) $invoice->balance_amount + 0.01) {
                return back()->with('error', __('Allocation exceeds remaining balance on bill :num.', ['num' => $invoice->invoice_number]));
            }
        }

        foreach (($request->debit_notes ?? []) as $dn) {
            $debitNoteModel = DebitNote::where('id', $dn['debit_note_id'])
                ->where('created_by', $companyId)
                ->where('vendor_id', $request->vendor_id)
                ->first();
            if (!$debitNoteModel || (float) $dn['amount'] > (float) $debitNoteModel->balance_amount + 0.01) {
                return back()->with('error', __('Invalid debit note or amount exceeds available balance.'));
            }
        }

        $currencyCode = strtoupper($request->input('currency_code') ?: ($bankAccount->currency_code ?: 'ETB'));
        $exchangeRate = (float) ($request->input('exchange_rate') ?: 1.0);
        $cashBaseAmount = round((float) $request->payment_amount, 2);
        $foreignAmount = $request->filled('foreign_amount') ? round((float) $request->foreign_amount, 2) : null;
        if ($currencyCode !== 'ETB' && $foreignAmount !== null && $exchangeRate > 0) {
            $cashBaseAmount = round($foreignAmount * $exchangeRate, 2);
        }

        $whtRate = round((float) ($request->input('wht_rate') ?? 0), 2);
        $whtAmount = round((float) ($request->input('wht_amount') ?? 0), 2);
        if ($whtAmount <= 0 && $whtRate > 0) {
            $netTarget = max(0, $totalInvoiceAllocation - $totalDebitNoteAmount);
            $whtAmount = round($netTarget * ($whtRate / 100), 2);
        }

        $apCashPortion = max(0.0, round($totalInvoiceAllocation - $totalDebitNoteAmount, 2));
        $fxGainLoss = $currencyCode !== 'ETB'
            ? round($apCashPortion - ($cashBaseAmount + $whtAmount), 2)
            : 0.0;

        $payment = DB::transaction(function () use (
            $request,
            $companyId,
            $cashBaseAmount,
            $whtRate,
            $whtAmount,
            $apCashPortion,
            $currencyCode,
            $exchangeRate,
            $foreignAmount,
            $fxGainLoss
        ) {
            $payment = new VendorPayment();
            $payment->payment_date = $request->payment_date;
            $payment->vendor_id = $request->vendor_id;
            $payment->bank_account_id = $request->bank_account_id;
            $payment->reference_number = $request->reference_number;
            $payment->payment_amount = $cashBaseAmount;
            $payment->wht_rate = $whtRate;
            $payment->wht_amount = $whtAmount;
            $payment->gross_settled_amount = $apCashPortion > 0 ? $apCashPortion : round($cashBaseAmount + $whtAmount, 2);
            $payment->currency_code = $currencyCode;
            $payment->exchange_rate = $exchangeRate;
            $payment->foreign_amount = $foreignAmount;
            $payment->fx_gain_loss = $fxGainLoss;
            $payment->status = 'pending';
            $payment->notes = $request->notes;
            $payment->creator_id = Auth::id();
            $payment->created_by = $companyId;
            $payment->save();

            foreach ($request->allocations as $allocation) {
                $paymentAllocation = new VendorPaymentAllocation();
                $paymentAllocation->payment_id = $payment->id;
                $paymentAllocation->invoice_id = $allocation['invoice_id'];
                $paymentAllocation->allocated_amount = $allocation['amount'];
                $paymentAllocation->save();
            }

            if ($request->debit_notes) {
                foreach ($request->debit_notes as $debitNote) {
                    DebitNoteApplication::create([
                        'debit_note_id'    => $debitNote['debit_note_id'],
                        'payment_id'       => $payment->id,
                        'applied_amount'   => $debitNote['amount'],
                        'application_date' => $request->payment_date,
                        'creator_id'       => Auth::id(),
                        'created_by'       => $companyId,
                    ]);
                }
            }

            return $payment;
        });

        CreateVendorPayment::dispatch($request, $payment);

        return redirect()->route('account.vendor-payments.index')->with('success', __('The vendor payment has been created successfully.'));
    }

    public function getOutstandingInvoices($vendorId)
    {
        $invoices = PurchaseInvoice::where('vendor_id', $vendorId)
            ->where('balance_amount', '>', 0)
            ->whereIn('status', ['posted', 'partial'])
            ->where('created_by', creatorId())
            ->get();

        $debitNotes = DebitNote::where('vendor_id', $vendorId)
            ->where('balance_amount', '>', 0)
            ->whereIn('status', ['approved', 'partial'])
            ->where('created_by', creatorId())
            ->get(['id', 'debit_note_number', 'balance_amount', 'total_amount', 'status']);

        return response()->json([
            'invoices'   => $invoices,
            'debitNotes' => $debitNotes,
        ]);
    }

    public function updateStatus(Request $request, VendorPayment $vendorPayment)
    {
        if (!Auth::user()->can('cleared-vendor-payments') || $vendorPayment->created_by != creatorId()) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,cleared,cancelled',
        ]);
        $targetStatus = $validated['status'];

        try {
            DB::transaction(function () use ($vendorPayment, $targetStatus) {
                $companyId = creatorId();
                $lockedPayment = VendorPayment::where('id', $vendorPayment->id)
                    ->where('created_by', $companyId)
                    ->with(['allocations', 'debitNoteApplications'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedPayment->status === $targetStatus) {
                    throw new \Exception(__('Payment is already in :status status.', ['status' => $targetStatus]));
                }

                if ($targetStatus === 'cleared') {
                    if ($lockedPayment->status !== 'pending') {
                        throw new \Exception(__('Only pending payments can be cleared.'));
                    }

                    if ((float) $lockedPayment->wht_amount > 0 && empty($lockedPayment->wht_certificate_number)) {
                        $lockedPayment->wht_certificate_number = AccountUtility::generateSequentialCode(
                            VendorPayment::class,
                            'wht_certificate_number',
                            'WHT-VP-' . date('Ym') . '-',
                            $companyId
                        );
                    }

                    if ((float) $lockedPayment->payment_amount > 0 || (float) $lockedPayment->wht_amount > 0) {
                        $this->journalService->createVendorPaymentJournal($lockedPayment);
                    }
                    if ((float) $lockedPayment->payment_amount > 0) {
                        $this->bankTransactionsService->createVendorPayment($lockedPayment);
                    }

                    foreach ($lockedPayment->allocations as $allocation) {
                        $invoice = PurchaseInvoice::where('id', $allocation->invoice_id)
                            ->where('created_by', $companyId)
                            ->lockForUpdate()
                            ->first();
                        if (!$invoice) {
                            continue;
                        }
                        $invoice->paid_amount = round((float) $invoice->paid_amount + (float) $allocation->allocated_amount, 2);
                        $invoice->balance_amount = max(0.0, round((float) $invoice->total_amount - (float) $invoice->paid_amount, 2));
                        $invoice->status = $invoice->balance_amount <= 0.01 ? 'paid' : ($invoice->paid_amount > 0 ? 'partial' : $invoice->status);
                        $invoice->save();
                    }

                    foreach ($lockedPayment->debitNoteApplications as $dnApp) {
                        $debitNoteModel = DebitNote::where('id', $dnApp->debit_note_id)
                            ->where('created_by', $companyId)
                            ->lockForUpdate()
                            ->first();
                        if (!$debitNoteModel) {
                            continue;
                        }
                        $debitNoteModel->applied_amount = round((float) $debitNoteModel->applied_amount + (float) $dnApp->applied_amount, 2);
                        $debitNoteModel->balance_amount = max(0.0, round((float) $debitNoteModel->total_amount - (float) $debitNoteModel->applied_amount, 2));
                        $debitNoteModel->status = $debitNoteModel->balance_amount <= 0.01 ? 'applied' : 'partial';
                        $debitNoteModel->save();
                    }

                    $lockedPayment->status = 'cleared';
                    $lockedPayment->save();
                } elseif ($targetStatus === 'cancelled') {
                    if ($lockedPayment->status === 'cleared') {
                        $this->journalService->reverseJournalEntryByReference('vendor_payment', (int) $lockedPayment->id, $companyId, 'Vendor payment cancelled');
                        if ((float) $lockedPayment->payment_amount > 0) {
                            $this->bankTransactionsService->updateBankBalance((int) $lockedPayment->bank_account_id, (float) $lockedPayment->payment_amount, $companyId);
                        }

                        foreach ($lockedPayment->allocations as $allocation) {
                            $invoice = PurchaseInvoice::where('id', $allocation->invoice_id)
                                ->where('created_by', $companyId)
                                ->lockForUpdate()
                                ->first();
                            if (!$invoice) {
                                continue;
                            }
                            $invoice->paid_amount = max(0.0, round((float) $invoice->paid_amount - (float) $allocation->allocated_amount, 2));
                            $invoice->balance_amount = round((float) $invoice->total_amount - (float) $invoice->paid_amount, 2);
                            $invoice->status = $invoice->paid_amount > 0 ? 'partial' : 'posted';
                            $invoice->save();
                        }

                        foreach ($lockedPayment->debitNoteApplications as $dnApp) {
                            $debitNoteModel = DebitNote::where('id', $dnApp->debit_note_id)
                                ->where('created_by', $companyId)
                                ->lockForUpdate()
                                ->first();
                            if (!$debitNoteModel) {
                                continue;
                            }
                            $debitNoteModel->applied_amount = max(0.0, round((float) $debitNoteModel->applied_amount - (float) $dnApp->applied_amount, 2));
                            $debitNoteModel->balance_amount = round((float) $debitNoteModel->total_amount - (float) $debitNoteModel->applied_amount, 2);
                            $debitNoteModel->status = $debitNoteModel->applied_amount > 0 ? 'partial' : 'approved';
                            $debitNoteModel->save();
                        }
                    }

                    $lockedPayment->status = 'cancelled';
                    $lockedPayment->save();
                } else {
                    if ($lockedPayment->status === 'cleared') {
                        throw new \Exception(__('Cleared payments cannot revert to pending; cancel to reverse entries.'));
                    }
                    $lockedPayment->status = $targetStatus;
                    $lockedPayment->save();
                }
            });

            $vendorPayment->refresh();
            UpdateVendorPaymentStatus::dispatch($request, $vendorPayment);

            return back()->with('success', __('The payment status are updated successfully.'));
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(VendorPayment $vendorPayment)
    {
        if (Auth::user()->can('delete-vendor-payments') && $vendorPayment->created_by == creatorId() && $vendorPayment->status === 'pending') {
            DestroyVendorPayment::dispatch($vendorPayment);
            $vendorPayment->delete();

            return back()->with('success', __('The vendor payment has been deleted.'));
        }

        return back()->with('error', __('Permission denied'));
    }
}
