<?php

namespace Workdo\Account\Http\Controllers;

use Workdo\Account\Models\BankTransaction;
use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\CustomerPayment;
use Workdo\Account\Models\VendorPayment;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Inertia\Inertia;

class BankTransactionController extends Controller
{
    public function index(Request $request)
    {
        if(Auth::user()->can('manage-bank-transactions')){
            $query = BankTransaction::with(['bankAccount'])
                ->where('created_by', creatorId());
            // Apply filters
            if ($request->bank_account_id) {
                $query->where('bank_account_id', $request->bank_account_id);
            }
            if ($request->transaction_type) {
                $query->where('transaction_type', $request->transaction_type);
            }
            if ($request->reconciliation_status) {
                $query->where('reconciliation_status', $request->reconciliation_status);
            }
            if ($request->search) {
                $query->where(function($q) use ($request) {
                    $q->where('reference_number', 'like', '%' . $request->search . '%')
                      ->orWhere('description', 'like', '%' . $request->search . '%');
                });
            }

            $sortField = $request->get('sort', 'created_at');
            $sortDirection = $request->get('direction', 'desc');
            if ($sortField) {
                $query->orderBy($sortField, $sortDirection);
            }

            $transactions = $query->paginate($request->get('per_page', 10))->withQueryString();
            $bankAccounts = BankAccount::where('is_active', true)->where('created_by', creatorId())->get();

            // Reconciliation statistics
            $stats = [
                'total_transactions' => BankTransaction::where('created_by', creatorId())->count(),
                'reconciled_count' => BankTransaction::where('created_by', creatorId())->where('reconciliation_status', 'reconciled')->count(),
                'unreconciled_count' => BankTransaction::where('created_by', creatorId())->where('reconciliation_status', 'unreconciled')->count(),
                'unreconciled_amount' => BankTransaction::where('created_by', creatorId())->where('reconciliation_status', 'unreconciled')->sum('amount'),
            ];

            return Inertia::render('Account/BankTransactions/Index', [
                'transactions' => $transactions,
                'bankAccounts' => $bankAccounts,
                'stats' => $stats,
                'filters' => $request->only(['bank_account_id', 'transaction_type', 'reconciliation_status', 'search'])
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function markReconciled($id)
    {
        if(Auth::user()->can('reconcile-bank-transactions')){
            $transaction = BankTransaction::where('id', $id)
                ->where('created_by', creatorId())
                ->first();

            if($transaction && $transaction->reconciliation_status === 'unreconciled') {
                $transaction->reconciliation_status = 'reconciled';
                $transaction->save();

                return back()->with('success', __('Transaction marked as reconciled'));
            }

            return back()->with('error', __('Transaction not found or already reconciled'));
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    /**
     * Import bank statement (Commercial Bank of Ethiopia, Telebirr, or Generic CSV).
     */
    public function importStatement(Request $request)
    {
        if (!Auth::user()->can('manage-bank-transactions')) {
            return back()->with('error', __('Permission denied'));
        }

        $request->validate([
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'statement_format' => 'required|in:cbe,telebirr,generic_csv',
            'file' => 'required|file|max:10240'
        ]);

        $bankAccount = BankAccount::where('id', $request->bank_account_id)
            ->where('created_by', creatorId())
            ->firstOrFail();

        $rows = $this->parseCsvFile($request->file('file'));

        if (empty($rows)) {
            return back()->with('error', __('Unable to parse CSV file or file is empty.'));
        }

        $format = $request->statement_format;
        $importedCount = 0;
        $skippedCount = 0;
        $feesRecorded = 0;

        foreach ($rows as $row) {
            $date = null;
            $ref = null;
            $desc = '';
            $type = 'credit';
            $amount = 0;
            $fee = 0;

            if ($format === 'telebirr') {
                // Telebirr Merchant Statement Format
                $ref = $row['transaction_id'] ?? $row['receipt_no'] ?? $row['reference'] ?? null;
                $dateStr = $row['date'] ?? $row['transaction_date'] ?? $row['created_at'] ?? null;
                $amount = (float) abs((float) str_replace(',', '', $row['amount'] ?? 0));
                $fee = (float) abs((float) str_replace(',', '', $row['fee'] ?? $row['service_fee'] ?? $row['charge'] ?? 0));
                $payer = $row['msisdn'] ?? $row['phone'] ?? $row['customer'] ?? '';
                $txnType = strtolower($row['type'] ?? 'c2b');

                $type = (str_contains($txnType, 'payout') || str_contains($txnType, 'b2c') || str_contains($txnType, 'debit') || str_contains($txnType, 'transfer')) ? 'debit' : 'credit';
                $desc = "Telebirr: " . ($row['type'] ?? 'Payment') . ($payer ? " [{$payer}]" : "");
                $date = $dateStr ? date('Y-m-d', strtotime($dateStr)) : date('Y-m-d');
            } elseif ($format === 'cbe') {
                // Commercial Bank of Ethiopia (CBE) Statement Format
                $ref = $row['reference'] ?? $row['reference_number'] ?? $row['ref_no'] ?? $row['ft_number'] ?? null;
                $dateStr = $row['date'] ?? $row['value_date'] ?? $row['trans_date'] ?? null;
                $desc = $row['description'] ?? $row['details'] ?? $row['remarks'] ?? 'CBE Bank Transaction';
                $debit = (float) abs((float) str_replace(',', '', $row['debit'] ?? 0));
                $credit = (float) abs((float) str_replace(',', '', $row['credit'] ?? 0));

                if ($credit > 0) {
                    $type = 'credit';
                    $amount = $credit;
                } else {
                    $type = 'debit';
                    $amount = $debit;
                }
                $date = $dateStr ? date('Y-m-d', strtotime($dateStr)) : date('Y-m-d');
            } else {
                // Generic Bank CSV Format
                $ref = $row['reference'] ?? $row['reference_number'] ?? $row['ref'] ?? null;
                $dateStr = $row['date'] ?? $row['transaction_date'] ?? null;
                $desc = $row['description'] ?? $row['memo'] ?? $row['particulars'] ?? 'Bank Transaction';
                $debit = (float) abs((float) str_replace(',', '', $row['debit'] ?? 0));
                $credit = (float) abs((float) str_replace(',', '', $row['credit'] ?? 0));

                if ($credit > 0) {
                    $type = 'credit';
                    $amount = $credit;
                } elseif ($debit > 0) {
                    $type = 'debit';
                    $amount = $debit;
                } else {
                    $rawAmount = (float) str_replace(',', '', $row['amount'] ?? 0);
                    $type = $rawAmount >= 0 ? 'credit' : 'debit';
                    $amount = abs($rawAmount);
                }
                $date = $dateStr ? date('Y-m-d', strtotime($dateStr)) : date('Y-m-d');
            }

            if ($amount <= 0) {
                continue;
            }

            // Deduplication by reference number within this account
            if ($ref) {
                $exists = BankTransaction::where('bank_account_id', $bankAccount->id)
                    ->where('created_by', creatorId())
                    ->where('reference_number', $ref)
                    ->exists();

                if ($exists) {
                    $skippedCount++;
                    continue;
                }
            } else {
                $ref = 'TXN-' . strtoupper(substr(uniqid(), -8));
            }

            BankTransaction::create([
                'bank_account_id' => $bankAccount->id,
                'transaction_date' => $date,
                'transaction_type' => $type,
                'reference_number' => $ref,
                'description' => $desc,
                'amount' => $amount,
                'running_balance' => 0,
                'transaction_status' => 'completed',
                'reconciliation_status' => 'unreconciled',
                'creator_id' => Auth::id(),
                'created_by' => creatorId(),
            ]);
            $importedCount++;

            // If Telebirr merchant fee exists, automatically record it as a separate reconciled fee debit
            if ($format === 'telebirr' && $fee > 0) {
                BankTransaction::create([
                    'bank_account_id' => $bankAccount->id,
                    'transaction_date' => $date,
                    'transaction_type' => 'debit',
                    'reference_number' => "FEE-{$ref}",
                    'description' => "Telebirr Service Fee for {$ref}",
                    'amount' => $fee,
                    'running_balance' => 0,
                    'transaction_status' => 'completed',
                    'reconciliation_status' => 'reconciled',
                    'creator_id' => Auth::id(),
                    'created_by' => creatorId(),
                ]);
                $feesRecorded++;
            }
        }

        $msg = __(':imported transactions imported successfully.', ['imported' => $importedCount]);
        if ($skippedCount > 0) {
            $msg .= ' ' . __(':skipped duplicates skipped.', ['skipped' => $skippedCount]);
        }
        if ($feesRecorded > 0) {
            $msg .= ' ' . __(':fees Telebirr commission charges recorded.', ['fees' => $feesRecorded]);
        }

        return back()->with('success', $msg);
    }

    /**
     * Smart Auto-Match unreconciled transactions with Customer/Vendor Payments.
     */
    public function autoMatch(Request $request)
    {
        if (!Auth::user()->can('reconcile-bank-transactions')) {
            return back()->with('error', __('Permission denied'));
        }

        $bankAccountId = $request->bank_account_id;
        $unreconciled = BankTransaction::where('created_by', creatorId())
            ->where('reconciliation_status', 'unreconciled')
            ->when($bankAccountId, fn($q) => $q->where('bank_account_id', $bankAccountId))
            ->get();

        $matchedCount = 0;
        $totalMatchedAmount = 0;

        foreach ($unreconciled as $txn) {
            if ($txn->transaction_type === 'credit') {
                // Match deposits against CustomerPayment
                $payment = CustomerPayment::where('created_by', creatorId())
                    ->where('bank_account_id', $txn->bank_account_id)
                    ->where(function($q) use ($txn) {
                        $q->where('reference_number', $txn->reference_number)
                          ->orWhere(function($sub) use ($txn) {
                              $sub->where('payment_amount', $txn->amount)
                                  ->whereBetween('payment_date', [
                                      Carbon::parse($txn->transaction_date)->subDays(5)->format('Y-m-d'),
                                      Carbon::parse($txn->transaction_date)->addDays(5)->format('Y-m-d')
                                  ]);
                          });
                    })
                    ->first();

                if ($payment) {
                    $txn->reconciliation_status = 'reconciled';
                    $txn->save();
                    $matchedCount++;
                    $totalMatchedAmount += (float) $txn->amount;
                }
            } elseif ($txn->transaction_type === 'debit') {
                // Match withdrawals against VendorPayment
                $payment = VendorPayment::where('created_by', creatorId())
                    ->where('bank_account_id', $txn->bank_account_id)
                    ->where(function($q) use ($txn) {
                        $q->where('reference_number', $txn->reference_number)
                          ->orWhere(function($sub) use ($txn) {
                              $sub->where('payment_amount', $txn->amount)
                                  ->whereBetween('payment_date', [
                                      Carbon::parse($txn->transaction_date)->subDays(5)->format('Y-m-d'),
                                      Carbon::parse($txn->transaction_date)->addDays(5)->format('Y-m-d')
                                  ]);
                          });
                    })
                    ->first();

                if ($payment) {
                    $txn->reconciliation_status = 'reconciled';
                    $txn->save();
                    $matchedCount++;
                    $totalMatchedAmount += (float) $txn->amount;
                }
            }
        }

        return back()->with('success', __(':count bank transactions successfully auto-matched (Total: ETB :amount).', [
            'count' => $matchedCount,
            'amount' => number_format($totalMatchedAmount, 2)
        ]));
    }

    /**
     * Generate Bank Reconciliation Summary Report.
     */
    public function reconciliationReport(Request $request)
    {
        if (!Auth::user()->can('manage-bank-transactions')) {
            return back()->with('error', __('Permission denied'));
        }

        $bankAccountId = $request->bank_account_id ?? BankAccount::where('created_by', creatorId())->value('id');

        $bankAccount = BankAccount::where('id', $bankAccountId)
            ->where('created_by', creatorId())
            ->firstOrFail();

        $bookBalance = (float) $bankAccount->current_balance;

        // Deposits in Transit (Customer payments recorded in ERP but not in bank statement)
        $uncreditedDeposits = CustomerPayment::where('bank_account_id', $bankAccount->id)
            ->where('created_by', creatorId())
            ->where('status', '!=', 'reconciled')
            ->sum('payment_amount');

        // Outstanding Checks / Payments (Vendor payments recorded in ERP not yet cleared in bank)
        $unpresentedPayments = VendorPayment::where('bank_account_id', $bankAccount->id)
            ->where('created_by', creatorId())
            ->where('status', '!=', 'reconciled')
            ->sum('payment_amount');

        // Unreconciled Bank Credits and Debits
        $unreconciledBankCredits = BankTransaction::where('bank_account_id', $bankAccount->id)
            ->where('created_by', creatorId())
            ->where('transaction_type', 'credit')
            ->where('reconciliation_status', 'unreconciled')
            ->sum('amount');

        $unreconciledBankDebits = BankTransaction::where('bank_account_id', $bankAccount->id)
            ->where('created_by', creatorId())
            ->where('transaction_type', 'debit')
            ->where('reconciliation_status', 'unreconciled')
            ->sum('amount');

        $adjustedBookBalance = $bookBalance + $unreconciledBankCredits - $unreconciledBankDebits;
        $reconciledStatementBalance = $bookBalance + $uncreditedDeposits - $unpresentedPayments;

        return response()->json([
            'bank_account' => [
                'id' => $bankAccount->id,
                'name' => $bankAccount->account_name,
                'bank' => $bankAccount->bank_name,
                'number' => $bankAccount->account_number,
                'currency' => 'ETB',
            ],
            'summary' => [
                'ledger_book_balance' => $bookBalance,
                'uncredited_deposits_in_transit' => (float) $uncreditedDeposits,
                'unpresented_payments_outstanding' => (float) $unpresentedPayments,
                'unreconciled_statement_credits' => (float) $unreconciledBankCredits,
                'unreconciled_statement_debits' => (float) $unreconciledBankDebits,
                'adjusted_book_balance' => $adjustedBookBalance,
                'reconciled_bank_balance' => $reconciledStatementBalance,
                'variance' => round($adjustedBookBalance - $reconciledStatementBalance, 2)
            ]
        ]);
    }

    /**
     * Parse CSV file into an array of associative rows.
     */
    private function parseCsvFile($file)
    {
        $rows = [];
        if (($handle = fopen($file->getRealPath(), 'r')) !== false) {
            // Read first line and skip UTF-8 BOM if present
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            $headers = null;
            while (($data = fgetcsv($handle, 4096, ',')) !== false) {
                // If delimiter might be semicolon or tab
                if (count($data) === 1 && str_contains($data[0], ';')) {
                    $data = str_getcsv($data[0], ';');
                } elseif (count($data) === 1 && str_contains($data[0], "\t")) {
                    $data = str_getcsv($data[0], "\t");
                }

                // Filter out empty rows
                if (empty(array_filter($data, fn($v) => trim($v) !== ''))) {
                    continue;
                }

                if (!$headers) {
                    $headers = array_map(fn($h) => strtolower(trim(preg_replace('/[^A-Za-z0-9_]/', '', str_replace([' ', '-'], '_', $h)))), $data);
                } else {
                    if (count($data) <= count($headers)) {
                        $row = [];
                        foreach ($headers as $index => $header) {
                            $row[$header] = trim($data[$index] ?? '');
                        }
                        $rows[] = $row;
                    }
                }
            }
            fclose($handle);
        }
        return $rows;
    }
}
