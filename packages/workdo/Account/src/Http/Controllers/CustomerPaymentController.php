<?php

namespace Workdo\Account\Http\Controllers;

use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Workdo\Account\Events\CreateCustomerPayment;
use Workdo\Account\Events\DestroyCustomerPayment;
use Workdo\Account\Events\UpdateCustomerPaymentStatus;
use Workdo\Account\Helpers\AccountUtility;
use Workdo\Account\Http\Requests\StoreCustomerPaymentRequest;
use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\CreditNote;
use Workdo\Account\Models\CreditNoteApplication;
use Workdo\Account\Models\CustomerPayment;
use Workdo\Account\Models\CustomerPaymentAllocation;
use Workdo\Account\Services\BankTransactionsService;
use Workdo\Account\Services\JournalService;

class CustomerPaymentController extends Controller
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
        if (!Auth::user()->can('manage-customer-payments')) {
            return back()->with('error', __('Permission denied'));
        }

        [$sortCol, $sortDir] = AccountUtility::sanitizeSort(
            $request->get('sort'),
            ['payment_number', 'payment_date', 'payment_amount', 'wht_amount', 'currency_code', 'status', 'created_at'],
            'created_at',
            $request->get('direction', 'desc')
        );

        $query = CustomerPayment::with(['customer', 'bankAccount', 'allocations.invoice', 'creditNoteApplications.creditNote'])
            ->where(function ($q) {
                if (Auth::user()->can('manage-any-customer-payments')) {
                    $q->where('created_by', creatorId());
                } elseif (Auth::user()->can('manage-own-customer-payments')) {
                    $q->where('created_by', creatorId())
                        ->where(function ($sub) {
                            $sub->where('creator_id', Auth::id())->orWhere('customer_id', Auth::id());
                        });
                } else {
                    $q->whereRaw('1 = 0');
                }
            });

        if ($request->customer_id) {
            $query->where('customer_id', $request->customer_id);
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
        $customers = User::where('type', 'client')->where('created_by', creatorId())->get();
        $bankAccounts = BankAccount::where('is_active', true)->where('created_by', creatorId())->get();

        return Inertia::render('Account/CustomerPayments/Index', [
            'payments'     => $payments,
            'customers'    => $customers,
            'bankAccounts' => $bankAccounts,
            'filters'      => $request->only(['customer_id', 'status', 'search', 'bank_account_id']),
        ]);
    }

    public function store(StoreCustomerPaymentRequest $request)
    {
        if (!Auth::user()->can('create-customer-payments')) {
            return back()->with('error', __('Permission denied'));
        }

        $companyId = creatorId();
        if (!$request->allocations || count($request->allocations) === 0) {
            return back()->with('error', __('At least one invoice allocation is required to create a payment.'));
        }

        $customerExists = User::where('id', $request->customer_id)
            ->where('type', 'client')
            ->where('created_by', $companyId)
            ->exists();
        $bankAccount = BankAccount::where('id', $request->bank_account_id)
            ->where('created_by', $companyId)
            ->first();

        if (!$customerExists || !$bankAccount) {
            return back()->with('error', __('Invalid customer or bank account selected.'));
        }

        $totalInvoiceAllocation = round((float) collect($request->allocations)->sum('amount'), 2);
        $totalCreditNoteAmount = round((float) collect($request->credit_notes ?? [])->sum('amount'), 2);

        if ($totalCreditNoteAmount > $totalInvoiceAllocation) {
            return back()->with('error', __('Credit note amount cannot exceed the total invoice allocation amount.'));
        }

        foreach ($request->allocations as $allocation) {
            $invoice = SalesInvoice::where('id', $allocation['invoice_id'])
                ->where('created_by', $companyId)
                ->where('customer_id', $request->customer_id)
                ->first();
            if (!$invoice) {
                return back()->with('error', __('Invalid sales invoice selected for this customer.'));
            }
            if ((float) $allocation['amount'] > (float) $invoice->balance_amount + 0.01) {
                return back()->with('error', __('Allocation exceeds remaining balance on invoice :num.', ['num' => $invoice->invoice_number]));
            }
        }

        foreach (($request->credit_notes ?? []) as $cn) {
            $creditNoteModel = CreditNote::where('id', $cn['credit_note_id'])
                ->where('created_by', $companyId)
                ->where('customer_id', $request->customer_id)
                ->first();
            if (!$creditNoteModel || (float) $cn['amount'] > (float) $creditNoteModel->balance_amount + 0.01) {
                return back()->with('error', __('Invalid credit note or amount exceeds available balance.'));
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
            $netTarget = max(0, $totalInvoiceAllocation - $totalCreditNoteAmount);
            $whtAmount = round($netTarget * ($whtRate / 100), 2);
        }

        $arCashPortion = max(0.0, round($totalInvoiceAllocation - $totalCreditNoteAmount, 2));
        $fxGainLoss = $currencyCode !== 'ETB'
            ? round(($cashBaseAmount + $whtAmount) - $arCashPortion, 2)
            : 0.0;

        $payment = DB::transaction(function () use (
            $request,
            $companyId,
            $cashBaseAmount,
            $whtRate,
            $whtAmount,
            $arCashPortion,
            $currencyCode,
            $exchangeRate,
            $foreignAmount,
            $fxGainLoss
        ) {
            $payment = new CustomerPayment();
            $payment->payment_date = $request->payment_date;
            $payment->customer_id = $request->customer_id;
            $payment->bank_account_id = $request->bank_account_id;
            $payment->reference_number = $request->reference_number;
            $payment->payment_amount = $cashBaseAmount;
            $payment->wht_rate = $whtRate;
            $payment->wht_amount = $whtAmount;
            $payment->gross_settled_amount = $arCashPortion > 0 ? $arCashPortion : round($cashBaseAmount + $whtAmount, 2);
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
                $paymentAllocation = new CustomerPaymentAllocation();
                $paymentAllocation->payment_id = $payment->id;
                $paymentAllocation->invoice_id = $allocation['invoice_id'];
                $paymentAllocation->allocated_amount = $allocation['amount'];
                $paymentAllocation->creator_id = Auth::id();
                $paymentAllocation->created_by = $companyId;
                $paymentAllocation->save();
            }

            if ($request->credit_notes) {
                foreach ($request->credit_notes as $creditNote) {
                    CreditNoteApplication::create([
                        'credit_note_id'   => $creditNote['credit_note_id'],
                        'payment_id'       => $payment->id,
                        'applied_amount'   => $creditNote['amount'],
                        'application_date' => $request->payment_date,
                        'creator_id'       => Auth::id(),
                        'created_by'       => $companyId,
                    ]);
                }
            }

            return $payment;
        });

        CreateCustomerPayment::dispatch($request, $payment);

        return redirect()->route('account.customer-payments.index')->with('success', __('The customer payment has been created successfully.'));
    }

    public function getOutstandingInvoices($customerId)
    {
        $invoices = SalesInvoice::where('customer_id', $customerId)
            ->where('balance_amount', '>', 0)
            ->whereIn('status', ['posted', 'partial'])
            ->where('created_by', creatorId())
            ->get();

        $creditNotes = CreditNote::where('customer_id', $customerId)
            ->where('balance_amount', '>', 0)
            ->whereIn('status', ['approved', 'partial'])
            ->where('created_by', creatorId())
            ->get(['id', 'credit_note_number', 'balance_amount', 'total_amount', 'status']);

        return response()->json([
            'invoices'    => $invoices,
            'creditNotes' => $creditNotes,
        ]);
    }

    public function updateStatus(Request $request, CustomerPayment $customerPayment)
    {
        if (!Auth::user()->can('cleared-customer-payments') || $customerPayment->created_by != creatorId()) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,cleared,cancelled',
        ]);
        $targetStatus = $validated['status'];

        try {
            DB::transaction(function () use ($customerPayment, $targetStatus) {
                $companyId = creatorId();
                $lockedPayment = CustomerPayment::where('id', $customerPayment->id)
                    ->where('created_by', $companyId)
                    ->with(['allocations', 'creditNoteApplications'])
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
                            CustomerPayment::class,
                            'wht_certificate_number',
                            'WHT-CP-' . date('Ym') . '-',
                            $companyId
                        );
                    }

                    if ((float) $lockedPayment->payment_amount > 0 || (float) $lockedPayment->wht_amount > 0) {
                        $this->journalService->createCustomerPaymentJournal($lockedPayment);
                    }
                    if ((float) $lockedPayment->payment_amount > 0) {
                        $this->bankTransactionsService->createCustomerPayment($lockedPayment);
                    }

                    foreach ($lockedPayment->allocations as $allocation) {
                        $invoice = SalesInvoice::where('id', $allocation->invoice_id)
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

                    foreach ($lockedPayment->creditNoteApplications as $cnApp) {
                        $creditNoteModel = CreditNote::where('id', $cnApp->credit_note_id)
                            ->where('created_by', $companyId)
                            ->lockForUpdate()
                            ->first();
                        if (!$creditNoteModel) {
                            continue;
                        }
                        $creditNoteModel->applied_amount = round((float) $creditNoteModel->applied_amount + (float) $cnApp->applied_amount, 2);
                        $creditNoteModel->balance_amount = max(0.0, round((float) $creditNoteModel->total_amount - (float) $creditNoteModel->applied_amount, 2));
                        $creditNoteModel->status = $creditNoteModel->balance_amount <= 0.01 ? 'applied' : 'partial';
                        $creditNoteModel->save();
                    }

                    $lockedPayment->status = 'cleared';
                    $lockedPayment->save();
                } elseif ($targetStatus === 'cancelled') {
                    if ($lockedPayment->status === 'cleared') {
                        $this->journalService->reverseJournalEntryByReference('customer_payment', (int) $lockedPayment->id, $companyId, 'Customer payment cancelled');
                        if ((float) $lockedPayment->payment_amount > 0) {
                            $this->bankTransactionsService->updateBankBalance((int) $lockedPayment->bank_account_id, -(float) $lockedPayment->payment_amount, $companyId);
                        }

                        foreach ($lockedPayment->allocations as $allocation) {
                            $invoice = SalesInvoice::where('id', $allocation->invoice_id)
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

                        foreach ($lockedPayment->creditNoteApplications as $cnApp) {
                            $creditNoteModel = CreditNote::where('id', $cnApp->credit_note_id)
                                ->where('created_by', $companyId)
                                ->lockForUpdate()
                                ->first();
                            if (!$creditNoteModel) {
                                continue;
                            }
                            $creditNoteModel->applied_amount = max(0.0, round((float) $creditNoteModel->applied_amount - (float) $cnApp->applied_amount, 2));
                            $creditNoteModel->balance_amount = round((float) $creditNoteModel->total_amount - (float) $creditNoteModel->applied_amount, 2);
                            $creditNoteModel->status = $creditNoteModel->applied_amount > 0 ? 'partial' : 'approved';
                            $creditNoteModel->save();
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

            $customerPayment->refresh();
            UpdateCustomerPaymentStatus::dispatch($request, $customerPayment);

            return back()->with('success', __('The payment status are updated successfully.'));
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(CustomerPayment $customerPayment)
    {
        if (Auth::user()->can('delete-customer-payments') && $customerPayment->created_by == creatorId() && $customerPayment->status === 'pending') {
            DestroyCustomerPayment::dispatch($customerPayment);
            $customerPayment->delete();

            return back()->with('success', __('The customer payment has been deleted.'));
        }

        return back()->with('error', __('Permission denied'));
    }
}
