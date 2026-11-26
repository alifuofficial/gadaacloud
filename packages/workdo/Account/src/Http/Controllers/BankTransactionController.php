<?php

namespace Workdo\Account\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Workdo\Account\Helpers\AccountUtility;
use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\BankTransaction;
use Workdo\Account\Models\BankTransfer;
use Workdo\Account\Models\CustomerPayment;
use Workdo\Account\Models\Expense;
use Workdo\Account\Models\Revenue;
use Workdo\Account\Models\VendorPayment;
use Workdo\Account\Services\JournalService;
use Workdo\Account\Traits\AccountControllerHelpers;
use Workdo\Account\Traits\AccountDateHelpers;

class BankTransactionController extends Controller
{
    use AccountControllerHelpers, AccountDateHelpers;

    public function index(Request $request)
    {
        if (!Auth::user()->can('manage-bank-transactions')) {
            return back()->with('error', __('Permission denied'));
        }

        [$sortField, $sortDirection] = AccountUtility::sanitizeSort(
            $request->get('sort'),
            ['transaction_date', 'reference_number', 'transaction_type', 'amount', 'running_balance', 'transaction_status', 'reconciliation_status', 'created_at'],
            'created_at',
            $request->get('direction', 'desc')
        );

        $companyId = creatorId();
        $query = BankTransaction::with(['bankAccount'])
            ->where('created_by', $companyId);

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
            $query->where(function ($q) use ($request) {
                $q->where('reference_number', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        $query->orderBy($sortField, $sortDirection);

        $transactions = $query->paginate($this->safePerPage(10, 100))->withQueryString();
        $bankAccounts = BankAccount::where('is_active', true)->where('created_by', $companyId)->get();

        $stats = [
            'total_transactions'  => BankTransaction::where('created_by', $companyId)->count(),
            'reconciled_count'    => BankTransaction::where('created_by', $companyId)->where('reconciliation_status', 'reconciled')->count(),
            'unreconciled_count'  => BankTransaction::where('created_by', $companyId)->where('reconciliation_status', 'unreconciled')->count(),
            'unreconciled_amount' => (float) BankTransaction::where('created_by', $companyId)->where('reconciliation_status', 'unreconciled')->sum('amount'),
        ];

        return Inertia::render('Account/BankTransactions/Index', [
            'transactions' => $transactions,
            'bankAccounts' => $bankAccounts,
            'stats'        => $stats,
            'filters'      => $request->only(['bank_account_id', 'transaction_type', 'reconciliation_status', 'search']),
        ]);
    }

    public function markReconciled($id)
    {
        if (!Auth::user()->can('reconcile-bank-transactions')) {
            return back()->with('error', __('Permission denied'));
        }

        $transaction = BankTransaction::where('id', $id)
            ->where('created_by', creatorId())
            ->first();

        if ($transaction && $transaction->reconciliation_status === 'unreconciled') {
            $transaction->reconciliation_status = 'reconciled';
            $transaction->reconciled_at = now();
            $transaction->reconciled_by = Auth::id();
            $transaction->save();

            return back()->with('success', __('Transaction marked as reconciled'));
        }

        return back()->with('error', __('Transaction not found or already reconciled'));
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
            'bank_account_id'  => ['required', AccountControllerHelpers::tenantExists('bank_accounts', 'id')],
            'statement_format' => 'required|in:cbe,telebirr,generic_csv',
            'file'             => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $companyId = creatorId();
        $rows = $this->parseCsvFile($request->file('file'));
        if (empty($rows)) {
            return back()->with('error', __('Unable to parse CSV file or file is empty.'));
        }

        $format = $request->statement_format;
        $batchReference = strtoupper($format) . '-' . date('YmdHis');

        try {
            $summary = DB::transaction(function () use ($request, $companyId, $rows, $format, $batchReference) {
                $bankAccount = BankAccount::where('id', $request->bank_account_id)
                    ->where('created_by', $companyId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $journalService = new JournalService();
                $importedCount = 0;
                $skippedCount = 0;
                $feesRecorded = 0;
                $runningBalance = (float) ($bankAccount->current_balance ?? 0);

                foreach ($rows as $row) {
                    [$ref, $date, $desc, $type, $amount, $fee] = $this->normalizeStatementRow($row, $format);
                    if ($amount <= 0) {
                        continue;
                    }

                    if ($ref) {
                        $exists = BankTransaction::where('bank_account_id', $bankAccount->id)
                            ->where('created_by', $companyId)
                            ->where('reference_number', $ref)
                            ->exists();

                        if ($exists) {
                            $skippedCount++;
                            continue;
                        }
                    } else {
                        $ref = 'TXN-' . strtoupper(substr(uniqid(), -8));
                    }

                    $delta = $type === 'credit' ? $amount : -$amount;
                    $runningBalance = round($runningBalance + $delta, 2);

                    BankTransaction::create([
                        'bank_account_id'       => $bankAccount->id,
                        'transaction_date'      => $date,
                        'transaction_type'      => $type,
                        'reference_number'      => $ref,
                        'description'           => $desc,
                        'amount'                => $amount,
                        'running_balance'       => $runningBalance,
                        'transaction_status'    => 'cleared',
                        'reconciliation_status' => 'unreconciled',
                        'batch_reference'       => $batchReference,
                        'creator_id'            => Auth::id(),
                        'created_by'            => $companyId,
                    ]);
                    $importedCount++;

                    if ($format === 'telebirr' && $fee > 0) {
                        $runningBalance = round($runningBalance - $fee, 2);
                        $feeRef = "FEE-{$ref}";

                        $feeJournal = $journalService->createBankFeeJournal(
                            (int) $bankAccount->id,
                            $fee,
                            $date,
                            $feeRef,
                            "Telebirr Service Fee for {$ref}",
                            $companyId
                        );

                        BankTransaction::create([
                            'bank_account_id'       => $bankAccount->id,
                            'transaction_date'      => $date,
                            'transaction_type'      => 'debit',
                            'reference_number'      => $feeRef,
                            'description'           => "Telebirr Service Fee for {$ref}",
                            'amount'                => $fee,
                            'running_balance'       => $runningBalance,
                            'transaction_status'    => 'cleared',
                            'reconciliation_status' => 'reconciled',
                            'matched_type'          => 'journal_entry',
                            'matched_id'            => $feeJournal?->id,
                            'reconciled_at'         => now(),
                            'reconciled_by'         => Auth::id(),
                            'batch_reference'       => $batchReference,
                            'creator_id'            => Auth::id(),
                            'created_by'            => $companyId,
                        ]);
                        $feesRecorded++;
                    }
                }

                $bankAccount->current_balance = $runningBalance;
                $bankAccount->save();

                return [$importedCount, $skippedCount, $feesRecorded];
            });

            [$importedCount, $skippedCount, $feesRecorded] = $summary;
            $msg = __(':imported transactions imported successfully.', ['imported' => $importedCount]);
            if ($skippedCount > 0) {
                $msg .= ' ' . __(':skipped duplicates skipped.', ['skipped' => $skippedCount]);
            }
            if ($feesRecorded > 0) {
                $msg .= ' ' . __(':fees Telebirr commission charges recorded & posted to GL.', ['fees' => $feesRecorded]);
            }

            return back()->with('success', $msg);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function normalizeStatementRow(array $row, string $format): array
    {
        $fee = 0.0;

        if ($format === 'telebirr') {
            $ref = $row['transaction_id'] ?? $row['receipt_no'] ?? $row['reference'] ?? null;
            $dateStr = $row['date'] ?? $row['transaction_date'] ?? $row['created_at'] ?? null;
            $amount = (float) abs((float) str_replace(',', '', $row['amount'] ?? '0'));
            $fee = (float) abs((float) str_replace(',', '', $row['fee'] ?? $row['service_fee'] ?? $row['charge'] ?? '0'));
            $payer = $row['msisdn'] ?? $row['phone'] ?? $row['customer'] ?? '';
            $txnType = strtolower($row['type'] ?? 'c2b');
            $type = (str_contains($txnType, 'payout') || str_contains($txnType, 'b2c') || str_contains($txnType, 'debit') || str_contains($txnType, 'transfer')) ? 'debit' : 'credit';
            $desc = 'Telebirr: ' . ($row['type'] ?? 'Payment') . ($payer ? " [{$payer}]" : '');
        } elseif ($format === 'cbe') {
            $ref = $row['reference'] ?? $row['reference_number'] ?? $row['ref_no'] ?? $row['ft_number'] ?? null;
            $dateStr = $row['date'] ?? $row['value_date'] ?? $row['trans_date'] ?? null;
            $desc = $row['description'] ?? $row['details'] ?? $row['remarks'] ?? 'CBE Bank Transaction';
            $debit = (float) abs((float) str_replace(',', '', $row['debit'] ?? '0'));
            $credit = (float) abs((float) str_replace(',', '', $row['credit'] ?? '0'));
            $type = $credit > 0 ? 'credit' : 'debit';
            $amount = $credit > 0 ? $credit : $debit;
        } else {
            $ref = $row['reference'] ?? $row['reference_number'] ?? $row['ref'] ?? null;
            $dateStr = $row['date'] ?? $row['transaction_date'] ?? null;
            $desc = $row['description'] ?? $row['memo'] ?? $row['particulars'] ?? 'Bank Transaction';
            $debit = (float) abs((float) str_replace(',', '', $row['debit'] ?? '0'));
            $credit = (float) abs((float) str_replace(',', '', $row['credit'] ?? '0'));
            if ($credit > 0) {
                $type = 'credit';
                $amount = $credit;
            } elseif ($debit > 0) {
                $type = 'debit';
                $amount = $debit;
            } else {
                $rawAmount = (float) str_replace(',', '', $row['amount'] ?? '0');
                $type = $rawAmount >= 0 ? 'credit' : 'debit';
                $amount = abs($rawAmount);
            }
        }

        $date = $dateStr && strtotime($dateStr) ? date('Y-m-d', strtotime($dateStr)) : date('Y-m-d');
        return [$ref, $date, $desc, $type, round($amount, 2), round($fee, 2)];
    }

    /**
     * Smart Auto-Match unreconciled bank transactions against Payments, Transfers, Revenues, and Expenses.
     */
    public function autoMatch(Request $request)
    {
        if (!Auth::user()->can('reconcile-bank-transactions')) {
            return back()->with('error', __('Permission denied'));
        }

        $companyId = creatorId();
        $bankAccountId = $request->bank_account_id;

        [$matchedCount, $totalMatchedAmount] = DB::transaction(function () use ($companyId, $bankAccountId) {
            $unreconciled = BankTransaction::where('created_by', $companyId)
                ->where('reconciliation_status', 'unreconciled')
                ->when($bankAccountId, fn ($q) => $q->where('bank_account_id', $bankAccountId))
                ->lockForUpdate()
                ->get();

            $usedMatches = BankTransaction::where('created_by', $companyId)
                ->where('reconciliation_status', 'reconciled')
                ->whereNotNull('matched_type')
                ->whereNotNull('matched_id')
                ->get()
                ->groupBy('matched_type')
                ->map(fn ($items) => $items->pluck('matched_id')->all())
                ->toArray();

            $matchedCount = 0;
            $totalMatchedAmount = 0.0;

            foreach ($unreconciled as $txn) {
                $match = $this->findMatchingSourceRecord($txn, $companyId, $usedMatches);
                if (!$match) {
                    continue;
                }

                [$matchedType, $matchedId] = $match;
                $txn->reconciliation_status = 'reconciled';
                $txn->matched_type = $matchedType;
                $txn->matched_id = $matchedId;
                $txn->reconciled_at = now();
                $txn->reconciled_by = Auth::id();
                $txn->save();

                $usedMatches[$matchedType][] = $matchedId;
                $matchedCount++;
                $totalMatchedAmount += (float) $txn->amount;
            }

            return [$matchedCount, $totalMatchedAmount];
        });

        return back()->with('success', __(':count bank transactions successfully auto-matched (Total: ETB :amount).', [
            'count'  => $matchedCount,
            'amount' => number_format($totalMatchedAmount, 2),
        ]));
    }

    private function findMatchingSourceRecord(BankTransaction $txn, int $companyId, array $usedMatches): ?array
    {
        $minDate = Carbon::parse($txn->transaction_date)->subDays(5)->format('Y-m-d');
        $maxDate = Carbon::parse($txn->transaction_date)->addDays(5)->format('Y-m-d');

        if ($txn->transaction_type === 'credit') {
            $cp = CustomerPayment::where('created_by', $companyId)
                ->where('bank_account_id', $txn->bank_account_id)
                ->where('status', 'cleared')
                ->whereNotIn('id', $usedMatches['customer_payment'] ?? [0])
                ->where(function ($q) use ($txn, $minDate, $maxDate) {
                    $q->where('reference_number', $txn->reference_number)
                        ->orWhere('payment_number', $txn->reference_number)
                        ->orWhere(function ($sub) use ($txn, $minDate, $maxDate) {
                            $sub->where('payment_amount', $txn->amount)
                                ->whereBetween('payment_date', [$minDate, $maxDate]);
                        });
                })
                ->first();
            if ($cp) {
                return ['customer_payment', (int) $cp->id];
            }

            $rev = Revenue::where('created_by', $companyId)
                ->where('bank_account_id', $txn->bank_account_id)
                ->where('status', 'posted')
                ->whereNotIn('id', $usedMatches['revenue'] ?? [0])
                ->where(function ($q) use ($txn, $minDate, $maxDate) {
                    $q->where('reference_number', $txn->reference_number)
                        ->orWhere('revenue_number', $txn->reference_number)
                        ->orWhere(function ($sub) use ($txn, $minDate, $maxDate) {
                            $sub->where('amount', $txn->amount)
                                ->whereBetween('revenue_date', [$minDate, $maxDate]);
                        });
                })
                ->first();
            if ($rev) {
                return ['revenue', (int) $rev->id];
            }

            $btIn = BankTransfer::where('created_by', $companyId)
                ->where('to_account_id', $txn->bank_account_id)
                ->where('status', 'completed')
                ->whereNotIn('id', $usedMatches['bank_transfer_in'] ?? [0])
                ->where('transfer_amount', $txn->amount)
                ->whereBetween('transfer_date', [$minDate, $maxDate])
                ->first();
            if ($btIn) {
                return ['bank_transfer_in', (int) $btIn->id];
            }
        } else {
            $vp = VendorPayment::where('created_by', $companyId)
                ->where('bank_account_id', $txn->bank_account_id)
                ->where('status', 'cleared')
                ->whereNotIn('id', $usedMatches['vendor_payment'] ?? [0])
                ->where(function ($q) use ($txn, $minDate, $maxDate) {
                    $q->where('reference_number', $txn->reference_number)
                        ->orWhere('payment_number', $txn->reference_number)
                        ->orWhere(function ($sub) use ($txn, $minDate, $maxDate) {
                            $sub->where('payment_amount', $txn->amount)
                                ->whereBetween('payment_date', [$minDate, $maxDate]);
                        });
                })
                ->first();
            if ($vp) {
                return ['vendor_payment', (int) $vp->id];
            }

            $exp = Expense::where('created_by', $companyId)
                ->where('bank_account_id', $txn->bank_account_id)
                ->where('status', 'posted')
                ->whereNotIn('id', $usedMatches['expense'] ?? [0])
                ->where(function ($q) use ($txn, $minDate, $maxDate) {
                    $q->where('reference_number', $txn->reference_number)
                        ->orWhere('expense_number', $txn->reference_number)
                        ->orWhere(function ($sub) use ($txn, $minDate, $maxDate) {
                            $sub->where('amount', $txn->amount)
                                ->whereBetween('expense_date', [$minDate, $maxDate]);
                        });
                })
                ->first();
            if ($exp) {
                return ['expense', (int) $exp->id];
            }

            $btOut = BankTransfer::where('created_by', $companyId)
                ->where('from_account_id', $txn->bank_account_id)
                ->where('status', 'completed')
                ->whereNotIn('id', $usedMatches['bank_transfer_out'] ?? [0])
                ->where('transfer_amount', $txn->amount)
                ->whereBetween('transfer_date', [$minDate, $maxDate])
                ->first();
            if ($btOut) {
                return ['bank_transfer_out', (int) $btOut->id];
            }
        }

        return null;
    }

    /**
     * Generate Bank Reconciliation Summary Report.
     */
    public function reconciliationReport(Request $request)
    {
        if (!Auth::user()->can('manage-bank-transactions')) {
            return response()->json(['message' => __('Permission denied')], 403);
        }

        $companyId = creatorId();
        $bankAccountId = $request->bank_account_id ?? BankAccount::where('created_by', $companyId)->value('id');

        if (!$bankAccountId) {
            return response()->json(['message' => __('No active bank account found.')], 404);
        }

        $bankAccount = BankAccount::where('id', $bankAccountId)
            ->where('created_by', $companyId)
            ->firstOrFail();

        $bookBalance = (float) $bankAccount->current_balance;

        $reconciledCustomerIds = BankTransaction::where('bank_account_id', $bankAccount->id)
            ->where('created_by', $companyId)
            ->where('reconciliation_status', 'reconciled')
            ->where('matched_type', 'customer_payment')
            ->pluck('matched_id')
            ->filter()
            ->all();

        $reconciledVendorIds = BankTransaction::where('bank_account_id', $bankAccount->id)
            ->where('created_by', $companyId)
            ->where('reconciliation_status', 'reconciled')
            ->where('matched_type', 'vendor_payment')
            ->pluck('matched_id')
            ->filter()
            ->all();

        $uncreditedDeposits = (float) CustomerPayment::where('bank_account_id', $bankAccount->id)
            ->where('created_by', $companyId)
            ->where('status', 'cleared')
            ->when(!empty($reconciledCustomerIds), fn ($q) => $q->whereNotIn('id', $reconciledCustomerIds))
            ->sum('payment_amount');

        $unpresentedPayments = (float) VendorPayment::where('bank_account_id', $bankAccount->id)
            ->where('created_by', $companyId)
            ->where('status', 'cleared')
            ->when(!empty($reconciledVendorIds), fn ($q) => $q->whereNotIn('id', $reconciledVendorIds))
            ->sum('payment_amount');

        $unreconciledBankCredits = (float) BankTransaction::where('bank_account_id', $bankAccount->id)
            ->where('created_by', $companyId)
            ->where('transaction_type', 'credit')
            ->where('reconciliation_status', 'unreconciled')
            ->sum('amount');

        $unreconciledBankDebits = (float) BankTransaction::where('bank_account_id', $bankAccount->id)
            ->where('created_by', $companyId)
            ->where('transaction_type', 'debit')
            ->where('reconciliation_status', 'unreconciled')
            ->sum('amount');

        $adjustedBookBalance = round($bookBalance + $unreconciledBankCredits - $unreconciledBankDebits, 2);
        $reconciledStatementBalance = round($bookBalance + $uncreditedDeposits - $unpresentedPayments, 2);

        return response()->json([
            'bank_account' => [
                'id'       => $bankAccount->id,
                'name'     => $bankAccount->account_name,
                'bank'     => $bankAccount->bank_name,
                'number'   => $bankAccount->account_number,
                'currency' => $bankAccount->currency_code ?: 'ETB',
            ],
            'summary' => [
                'ledger_book_balance'              => round($bookBalance, 2),
                'uncredited_deposits_in_transit'   => round($uncreditedDeposits, 2),
                'unpresented_payments_outstanding' => round($unpresentedPayments, 2),
                'unreconciled_statement_credits'   => round($unreconciledBankCredits, 2),
                'unreconciled_statement_debits'    => round($unreconciledBankDebits, 2),
                'adjusted_book_balance'            => $adjustedBookBalance,
                'reconciled_bank_balance'          => $reconciledStatementBalance,
                'variance'                         => round($adjustedBookBalance - $reconciledStatementBalance, 2),
            ],
        ]);
    }

    private function parseCsvFile($file): array
    {
        $rows = [];
        if (($handle = fopen($file->getRealPath(), 'r')) !== false) {
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            $headers = null;
            while (($data = fgetcsv($handle, 4096, ',')) !== false) {
                if (count($data) === 1 && str_contains($data[0], ';')) {
                    $data = str_getcsv($data[0], ';');
                } elseif (count($data) === 1 && str_contains($data[0], "\t")) {
                    $data = str_getcsv($data[0], "\t");
                }

                if (empty(array_filter($data, fn ($v) => trim((string) $v) !== ''))) {
                    continue;
                }

                if (!$headers) {
                    $headers = array_map(fn ($h) => strtolower(trim(preg_replace('/[^A-Za-z0-9_]/', '', str_replace([' ', '-'], '_', $h)))), $data);
                } elseif (count($data) <= count($headers)) {
                    $row = [];
                    foreach ($headers as $index => $header) {
                        $row[$header] = trim($data[$index] ?? '');
                    }
                    $rows[] = $row;
                }
            }
            fclose($handle);
        }

        return $rows;
    }
}
