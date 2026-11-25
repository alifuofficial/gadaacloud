<?php

namespace Workdo\Account\Services;

use Workdo\Account\Events\UpdateBudgetSpending;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Workdo\Account\Helpers\AccountUtility;
use Workdo\Account\Models\AccountingPeriod;
use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\JournalEntry;
use Workdo\Account\Models\JournalEntryItem;
use Workdo\Retainer\Models\RetainerPaymentAllocation;

/**
 * JournalService - Atomic double-entry General Ledger engine for all GadaaCloud transactions.
 */
class JournalService
{
    private function resolveCompanyId($model = null, ?int $explicitCompanyId = null): int
    {
        if ($explicitCompanyId) {
            return $explicitCompanyId;
        }
        if ($model && !empty($model->created_by)) {
            return (int) $model->created_by;
        }
        return Auth::check() ? (int) creatorId() : 1;
    }

    private function resolveActorId(?int $companyId = null, $model = null): int
    {
        if (Auth::check()) {
            return (int) Auth::id();
        }
        if ($model && !empty($model->creator_id)) {
            return (int) $model->creator_id;
        }
        return $companyId ?? 1;
    }

    private function getAccountByCode(string $code, int $companyId): ChartOfAccount
    {
        $account = ChartOfAccount::withoutGlobalScope('tenant')
            ->where('account_code', $code)
            ->where('created_by', $companyId)
            ->first();

        if (!$account) {
            $account = AccountUtility::ensureAccountCode($code, $companyId);
        }

        if (!$account) {
            throw new \Exception("Account with code {$code} not found");
        }

        return $account;
    }

    private function validateAccounts(array $accountCodes, ?int $userID = null): void
    {
        $companyId = $userID ?? (Auth::check() ? (int) creatorId() : 1);
        foreach ($accountCodes as $code) {
            $this->getAccountByCode((string) $code, $companyId);
        }
    }

    private function validateBalance($totalDebit, $totalCredit): void
    {
        if (abs((float) $totalDebit - (float) $totalCredit) > 0.01) {
            throw new \Exception("Journal entry not balanced: Debit {$totalDebit} != Credit {$totalCredit}");
        }
    }

    private function assertPeriodOpen($date, int $companyId): void
    {
        $dateStr = $date instanceof \DateTimeInterface
            ? $date->format('Y-m-d')
            : Carbon::parse($date ?? now())->toDateString();

        $locked = AccountingPeriod::coveringDate($dateStr, $companyId)
            ->whereIn('status', ['soft_locked', 'closed'])
            ->first();

        if ($locked) {
            throw new \Exception(__('Accounting period ":period" (:start to :end) is :status. Posting for :date is blocked.', [
                'period' => $locked->name,
                'start'  => $locked->start_date->toDateString(),
                'end'    => $locked->end_date->toDateString(),
                'status' => $locked->status,
                'date'   => $dateStr,
            ]));
        }
    }

    public function hasPostedJournal($referenceTypes, int $referenceId, int $companyId): bool
    {
        return JournalEntry::withoutGlobalScope('tenant')
            ->where('created_by', $companyId)
            ->whereIn('reference_type', (array) $referenceTypes)
            ->where('reference_id', $referenceId)
            ->where('status', 'posted')
            ->exists();
    }

    private function dispatchBudgetUpdate(JournalEntry $journalEntry): void
    {
        try {
            UpdateBudgetSpending::dispatch($journalEntry);
        } catch (\Throwable $e) {
            Log::warning('UpdateBudgetSpending listener failed: ' . $e->getMessage(), [
                'journal_entry_id' => $journalEntry->id,
            ]);
        }
    }

    private function findBankGLAccount(?int $bankAccountId, int $companyId): array
    {
        if (!$bankAccountId) {
            throw new \Exception('Bank account is required for posting.');
        }

        $bankAccount = BankAccount::withoutGlobalScope('tenant')
            ->with('glAccount')
            ->where('created_by', $companyId)
            ->find($bankAccountId);

        if (!$bankAccount || !$bankAccount->glAccount) {
            throw new \Exception('Bank account must have a valid GL account assigned');
        }

        return [$bankAccount, $bankAccount->glAccount];
    }

    private function resolveValidCustomAccountId(?int $customAccountId, int $companyId): ?int
    {
        if (!$customAccountId) {
            return null;
        }

        return ChartOfAccount::withoutGlobalScope('tenant')
            ->where('created_by', $companyId)
            ->where('id', $customAccountId)
            ->value('id');
    }

    /**
     * Resolve inventory valuation cost from WarehouseStock (WAC) with graceful fallback to Product purchase_price.
     */
    private function resolveWarehouseUnitCost(int $productId, ?int $warehouseId, int $companyId, float $fallback = 0.0): float
    {
        if (class_exists(\Workdo\ProductService\Models\WarehouseStock::class)) {
            $stockClass = \Workdo\ProductService\Models\WarehouseStock::class;

            if ($warehouseId) {
                $warehouseCost = (float) ($stockClass::forCompany($companyId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('product_id', $productId)
                    ->value('unit_cost') ?? 0);

                if ($warehouseCost > 0) {
                    return $warehouseCost;
                }
            }

            $stocks = $stockClass::forCompany($companyId)
                ->where('product_id', $productId)
                ->where('quantity', '>', 0)
                ->get(['quantity', 'unit_cost']);

            $totalQty = 0.0;
            $totalVal = 0.0;
            foreach ($stocks as $stock) {
                $qty = (float) $stock->quantity;
                $cost = (float) ($stock->unit_cost ?? 0);
                if ($qty > 0 && $cost > 0) {
                    $totalQty += $qty;
                    $totalVal += ($qty * $cost);
                }
            }

            if ($totalQty > 0 && $totalVal > 0) {
                return round($totalVal / $totalQty, 4);
            }
        }

        return $fallback > 0 ? $fallback : 0.0;
    }

    private function createHeader(
        int $companyId,
        int $actorId,
        $date,
        string $referenceType,
        ?int $referenceId,
        string $description,
        float $totalDebit,
        float $totalCredit,
        string $currencyCode = 'ETB',
        float $exchangeRate = 1.0
    ): JournalEntry {
        $this->assertPeriodOpen($date, $companyId);
        $this->validateBalance($totalDebit, $totalCredit);

        return JournalEntry::create([
            'journal_date'   => $date ?? now(),
            'entry_type'     => 'automatic',
            'reference_type' => $referenceType,
            'reference_id'   => $referenceId,
            'description'    => $description,
            'total_debit'    => round($totalDebit, 2),
            'total_credit'   => round($totalCredit, 2),
            'currency_code'  => $currencyCode ?: 'ETB',
            'exchange_rate'  => $exchangeRate > 0 ? $exchangeRate : 1.0,
            'status'         => 'posted',
            'creator_id'     => $actorId,
            'created_by'     => $companyId,
        ]);
    }

    private function addLine(
        JournalEntry $journalEntry,
        int $accountId,
        string $description,
        float $debit,
        float $credit,
        int $actorId,
        int $companyId
    ): JournalEntryItem {
        return JournalEntryItem::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id'       => $accountId,
            'description'      => $description,
            'debit_amount'     => round(max(0, $debit), 2),
            'credit_amount'    => round(max(0, $credit), 2),
            'creator_id'       => $actorId,
            'created_by'       => $companyId,
        ]);
    }

    private function distributeBuckets(
        JournalEntry $journalEntry,
        array $buckets,
        float $targetTotal,
        int $fallbackAccountId,
        string $lineDescription,
        bool $isDebit,
        int $actorId,
        int $companyId
    ): void {
        $allocatedSum = array_sum($buckets);
        if (count($buckets) <= 1 || $allocatedSum <= 0) {
            $accountId = count($buckets) === 1 ? (int) array_key_first($buckets) : $fallbackAccountId;
            $this->addLine(
                $journalEntry,
                $accountId,
                $lineDescription,
                $isDebit ? $targetTotal : 0,
                $isDebit ? 0 : $targetTotal,
                $actorId,
                $companyId
            );
            return;
        }

        $variance = round($targetTotal - $allocatedSum, 2);
        if (abs($variance) > 0.001) {
            $firstKey = array_key_first($buckets);
            $buckets[$firstKey] = round($buckets[$firstKey] + $variance, 2);
        }

        foreach ($buckets as $accId => $amount) {
            if (abs($amount) < 0.01) {
                continue;
            }
            $this->addLine(
                $journalEntry,
                (int) $accId,
                $lineDescription,
                $isDebit ? $amount : 0,
                $isDebit ? 0 : $amount,
                $actorId,
                $companyId
            );
        }
    }

    private function updateAccountBalances(JournalEntry $journalEntry): void
    {
        $journalEntry->loadMissing('items');

        foreach ($journalEntry->items as $item) {
            $account = ChartOfAccount::withoutGlobalScope('tenant')
                ->where('id', $item->account_id)
                ->lockForUpdate()
                ->first();

            if (!$account) {
                continue;
            }

            $debitAmount = (float) $item->debit_amount;
            $creditAmount = (float) $item->credit_amount;

            if ($account->normal_balance === 'debit') {
                $account->current_balance = round((float) $account->current_balance + ($debitAmount - $creditAmount), 2);
            } else {
                $account->current_balance = round((float) $account->current_balance + ($creditAmount - $debitAmount), 2);
            }

            $account->save();
        }
    }

    private function reverseAccountBalances(JournalEntry $journalEntry): void
    {
        $journalEntry->loadMissing('items');

        foreach ($journalEntry->items as $item) {
            $account = ChartOfAccount::withoutGlobalScope('tenant')
                ->where('id', $item->account_id)
                ->lockForUpdate()
                ->first();

            if (!$account) {
                continue;
            }

            $debitAmount = (float) $item->debit_amount;
            $creditAmount = (float) $item->credit_amount;

            if ($account->normal_balance === 'debit') {
                $account->current_balance = round((float) $account->current_balance - ($debitAmount - $creditAmount), 2);
            } else {
                $account->current_balance = round((float) $account->current_balance - ($creditAmount - $debitAmount), 2);
            }

            $account->save();
        }
    }

    /**
     * Reverse a posted journal entry with an immutable offsetting reversal entry.
     */
    public function reverseJournalEntry(JournalEntry $originalEntry, string $reason = 'Reversed'): ?JournalEntry
    {
        if ($originalEntry->status !== 'posted') {
            return null;
        }

        $companyId = (int) $originalEntry->created_by;
        $actorId = $this->resolveActorId($companyId, $originalEntry);

        return DB::transaction(function () use ($originalEntry, $reason, $companyId, $actorId) {
            $lockedOriginal = JournalEntry::withoutGlobalScope('tenant')
                ->with('items')
                ->where('id', $originalEntry->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedOriginal || $lockedOriginal->status !== 'posted') {
                return null;
            }

            $reversalDate = now()->toDateString();
            $this->assertPeriodOpen($reversalDate, $companyId);

            $reversal = JournalEntry::create([
                'journal_date'   => $reversalDate,
                'entry_type'     => 'automatic',
                'reference_type' => $lockedOriginal->reference_type . '_reversal',
                'reference_id'   => $lockedOriginal->reference_id,
                'description'    => 'Reversal of ' . $lockedOriginal->journal_number . ': ' . $reason,
                'total_debit'    => $lockedOriginal->total_credit,
                'total_credit'   => $lockedOriginal->total_debit,
                'currency_code'  => $lockedOriginal->currency_code ?? 'ETB',
                'exchange_rate'  => $lockedOriginal->exchange_rate ?? 1.0,
                'status'         => 'posted',
                'reversal_reason'=> $reason,
                'creator_id'     => $actorId,
                'created_by'     => $companyId,
            ]);

            foreach ($lockedOriginal->items as $item) {
                $this->addLine(
                    $reversal,
                    (int) $item->account_id,
                    'Reversal: ' . ($item->description ?? $lockedOriginal->description),
                    (float) $item->credit_amount,
                    (float) $item->debit_amount,
                    $actorId,
                    $companyId
                );
            }

            $this->updateAccountBalances($reversal);

            $lockedOriginal->update([
                'status'               => 'reversed',
                'reversed_by_entry_id' => $reversal->id,
                'reversal_reason'      => $reason,
            ]);

            return $reversal;
        });
    }

    public function reverseJournalEntryByReference($referenceTypes, int $referenceId, int $companyId, string $reason = 'Reversed'): int
    {
        $entries = JournalEntry::withoutGlobalScope('tenant')
            ->where('created_by', $companyId)
            ->whereIn('reference_type', (array) $referenceTypes)
            ->where('reference_id', $referenceId)
            ->where('status', 'posted')
            ->get();

        $count = 0;
        foreach ($entries as $entry) {
            if ($this->reverseJournalEntry($entry, $reason)) {
                $count++;
            }
        }

        return $count;
    }

    public function createSalesInvoiceJournal($salesInvoice)
    {
        $companyId = $this->resolveCompanyId($salesInvoice);
        $actorId = $this->resolveActorId($companyId, $salesInvoice);

        if ($this->hasPostedJournal('sales_invoice', (int) $salesInvoice->id, $companyId)) {
            return null;
        }

        return DB::transaction(function () use ($salesInvoice, $companyId, $actorId) {
            $requiredAccounts = ['1100', '4100'];
            if ((float) $salesInvoice->tax_amount > 0) {
                $requiredAccounts[] = '2210';
            }
            $this->validateAccounts($requiredAccounts, $companyId);

            $netRevenue = round((float) $salesInvoice->subtotal - (float) $salesInvoice->discount_amount, 2);
            $taxAmount = round((float) ($salesInvoice->tax_amount ?? 0), 2);
            $totalAmount = round((float) $salesInvoice->total_amount, 2);

            $arAccount = $this->getAccountByCode('1100', $companyId);
            $salesAccount = $this->getAccountByCode('4100', $companyId);
            $taxAccount = $taxAmount > 0 ? $this->getAccountByCode('2210', $companyId) : null;

            $journalEntry = $this->createHeader(
                $companyId,
                $actorId,
                $salesInvoice->invoice_date ?? now(),
                'sales_invoice',
                (int) $salesInvoice->id,
                'Sales Invoice #' . $salesInvoice->invoice_number,
                $totalAmount,
                $netRevenue + $taxAmount
            );

            $customerName = $salesInvoice->customer?->name ?? 'Customer';
            $this->addLine($journalEntry, $arAccount->id, 'Sales to ' . $customerName, $totalAmount, 0, $actorId, $companyId);

            $salesInvoice->loadMissing('items.product');
            $revenueBuckets = [];
            foreach ($salesInvoice->items as $invItem) {
                $customAccId = $this->resolveValidCustomAccountId($invItem->product->sale_chartaccount_id ?? null, $companyId);
                $targetAccountId = $customAccId ?: $salesAccount->id;
                $lineNet = round(max(0, ((float) $invItem->quantity * (float) $invItem->unit_price) - (float) ($invItem->discount_amount ?? 0)), 2);
                $revenueBuckets[$targetAccountId] = ($revenueBuckets[$targetAccountId] ?? 0.0) + $lineNet;
            }

            $this->distributeBuckets($journalEntry, $revenueBuckets, $netRevenue, $salesAccount->id, 'Product sales', false, $actorId, $companyId);

            if ($taxAmount > 0 && $taxAccount) {
                $this->addLine($journalEntry, $taxAccount->id, 'Sales tax collected', 0, $taxAmount, $actorId, $companyId);
            }

            $this->updateAccountBalances($journalEntry);
            return $journalEntry;
        });
    }

    public function createSalesRetainerToInvoiceJournal($retainer)
    {
        $companyId = $this->resolveCompanyId($retainer);
        $actorId = $this->resolveActorId($companyId, $retainer);

        $allocations = RetainerPaymentAllocation::whereHas('payment', function ($q) {
                $q->where('status', 'cleared');
            })
            ->where('retainer_id', $retainer->id)
            ->get();

        $totalAmount = round((float) $allocations->sum('allocated_amount'), 2);
        if ($totalAmount <= 0 || $this->hasPostedJournal('retainer_to_invoice', (int) $retainer->id, $companyId)) {
            return null;
        }

        return DB::transaction(function () use ($retainer, $totalAmount, $companyId, $actorId) {
            $customerDepositsAccount = $this->getAccountByCode('2350', $companyId);
            $arAccount = $this->getAccountByCode('1100', $companyId);

            $journalEntry = $this->createHeader(
                $companyId,
                $actorId,
                now(),
                'retainer_to_invoice',
                (int) $retainer->id,
                'Retainer converted to invoice',
                $totalAmount,
                $totalAmount
            );

            $this->addLine($journalEntry, $customerDepositsAccount->id, 'Retainer converted to invoice', $totalAmount, 0, $actorId, $companyId);
            $this->addLine($journalEntry, $arAccount->id, 'Retainer converted to invoice', 0, $totalAmount, $actorId, $companyId);

            $this->updateAccountBalances($journalEntry);
            return $journalEntry;
        });
    }

    public function createServiceInvoiceJournal($salesInvoice)
    {
        $companyId = $this->resolveCompanyId($salesInvoice);
        $actorId = $this->resolveActorId($companyId, $salesInvoice);

        if ($this->hasPostedJournal('service_invoice', (int) $salesInvoice->id, $companyId)) {
            return null;
        }

        return DB::transaction(function () use ($salesInvoice, $companyId, $actorId) {
            $taxAmount = round((float) ($salesInvoice->tax_amount ?? 0), 2);
            $netRevenue = round((float) $salesInvoice->subtotal - (float) $salesInvoice->discount_amount, 2);
            $totalAmount = round((float) $salesInvoice->total_amount, 2);

            $arAccount = $this->getAccountByCode('1100', $companyId);
            $serviceRevenueAccount = $this->getAccountByCode('4200', $companyId);
            $taxAccount = $taxAmount > 0 ? $this->getAccountByCode('2210', $companyId) : null;

            $journalEntry = $this->createHeader(
                $companyId,
                $actorId,
                $salesInvoice->invoice_date ?? now(),
                'service_invoice',
                (int) $salesInvoice->id,
                'Service Invoice #' . $salesInvoice->invoice_number,
                $totalAmount,
                $netRevenue + $taxAmount
            );

            $customerName = $salesInvoice->customer?->name ?? 'Customer';
            $this->addLine($journalEntry, $arAccount->id, 'Service to ' . $customerName, $totalAmount, 0, $actorId, $companyId);

            $salesInvoice->loadMissing('items.product');
            $revenueBuckets = [];
            foreach ($salesInvoice->items as $invItem) {
                $customAccId = $this->resolveValidCustomAccountId($invItem->product->sale_chartaccount_id ?? null, $companyId);
                $targetAccountId = $customAccId ?: $serviceRevenueAccount->id;
                $lineNet = round(max(0, ((float) $invItem->quantity * (float) $invItem->unit_price) - (float) ($invItem->discount_amount ?? 0)), 2);
                $revenueBuckets[$targetAccountId] = ($revenueBuckets[$targetAccountId] ?? 0.0) + $lineNet;
            }

            $this->distributeBuckets($journalEntry, $revenueBuckets, $netRevenue, $serviceRevenueAccount->id, 'Service revenue', false, $actorId, $companyId);

            if ($taxAmount > 0 && $taxAccount) {
                $this->addLine($journalEntry, $taxAccount->id, 'Sales tax collected', 0, $taxAmount, $actorId, $companyId);
            }

            $this->updateAccountBalances($journalEntry);
            return $journalEntry;
        });
    }

    /**
     * Purchase Invoice GL Entry: splits per-item into Inventory Asset (1200/custom) or Expense/COGS (5100/custom) for services.
     */
    public function createPurchaseInventoryJournal($purchaseInvoice)
    {
        $companyId = $this->resolveCompanyId($purchaseInvoice);
        $actorId = $this->resolveActorId($companyId, $purchaseInvoice);

        if ($this->hasPostedJournal('purchase_invoice', (int) $purchaseInvoice->id, $companyId)) {
            return null;
        }

        return DB::transaction(function () use ($purchaseInvoice, $companyId, $actorId) {
            $taxAmount = round((float) ($purchaseInvoice->tax_amount ?? 0), 2);
            $netSubtotal = round((float) $purchaseInvoice->subtotal - (float) $purchaseInvoice->discount_amount, 2);
            $totalAmount = round((float) $purchaseInvoice->total_amount, 2);

            $apAccount = $this->getAccountByCode('2000', $companyId);
            $inventoryAccount = $this->getAccountByCode('1200', $companyId);
            $expenseFallbackAccount = $this->getAccountByCode('5100', $companyId);
            $taxAccount = $taxAmount > 0 ? $this->getAccountByCode('1500', $companyId) : null;

            $journalEntry = $this->createHeader(
                $companyId,
                $actorId,
                $purchaseInvoice->invoice_date ?? now(),
                'purchase_invoice',
                (int) $purchaseInvoice->id,
                'Purchase Invoice #' . $purchaseInvoice->invoice_number,
                $netSubtotal + $taxAmount,
                $totalAmount
            );

            $purchaseInvoice->loadMissing('items.product');
            $debitBuckets = [];
            foreach ($purchaseInvoice->items as $item) {
                $product = $item->product;
                $isService = ($product?->type ?? 'product') === 'service';
                if ($isService) {
                    $customId = $this->resolveValidCustomAccountId($product->expense_chartaccount_id ?? null, $companyId);
                    $targetId = $customId ?: $expenseFallbackAccount->id;
                } else {
                    $customId = $this->resolveValidCustomAccountId($product->inventory_chartaccount_id ?? null, $companyId);
                    $targetId = $customId ?: $inventoryAccount->id;
                }
                $lineNet = round(max(0, ((float) $item->quantity * (float) $item->unit_price) - (float) ($item->discount_amount ?? 0)), 2);
                $debitBuckets[$targetId] = ($debitBuckets[$targetId] ?? 0.0) + $lineNet;
            }

            $vendorName = $purchaseInvoice->vendor?->name ?? 'Vendor';
            $this->distributeBuckets($journalEntry, $debitBuckets, $netSubtotal, $inventoryAccount->id, 'Purchase from ' . $vendorName, true, $actorId, $companyId);

            if ($taxAmount > 0 && $taxAccount) {
                $this->addLine($journalEntry, $taxAccount->id, 'Purchase tax paid', $taxAmount, 0, $actorId, $companyId);
            }

            $this->addLine($journalEntry, $apAccount->id, 'Purchase from vendor', 0, $totalAmount, $actorId, $companyId);

            $this->updateAccountBalances($journalEntry);
            return $journalEntry;
        });
    }

    /**
     * Customer Payment Receipt with Ethiopian 2% WHT Receivable (1510) & Realized FX Gain/Loss (4310 / 5520).
     */
    public function createCustomerPaymentJournal($customerPayment)
    {
        $companyId = $this->resolveCompanyId($customerPayment);
        $actorId = $this->resolveActorId($companyId, $customerPayment);

        if ($this->hasPostedJournal('customer_payment', (int) $customerPayment->id, $companyId)) {
            return null;
        }

        return DB::transaction(function () use ($customerPayment, $companyId, $actorId) {
            [$bankAccount, $bankGLAccount] = $this->findBankGLAccount((int) $customerPayment->bank_account_id, $companyId);
            $arAccount = $this->getAccountByCode('1100', $companyId);

            $cashAmount = round((float) $customerPayment->payment_amount, 2);
            $whtAmount = round((float) ($customerPayment->wht_amount ?? 0), 2);
            $fxGainLoss = round((float) ($customerPayment->fx_gain_loss ?? 0), 2);
            $arSettled = round((float) ($customerPayment->gross_settled_amount ?? ($cashAmount + $whtAmount - $fxGainLoss)), 2);

            $whtReceivableAccount = $whtAmount > 0 ? $this->getAccountByCode('1510', $companyId) : null;
            $fxGainAccount = $fxGainLoss > 0.005 ? $this->getAccountByCode('4310', $companyId) : null;
            $fxLossAccount = $fxGainLoss < -0.005 ? $this->getAccountByCode('5520', $companyId) : null;

            $totalDebit = round($cashAmount + $whtAmount + ($fxGainLoss < -0.005 ? abs($fxGainLoss) : 0.0), 2);
            $totalCredit = round($arSettled + ($fxGainLoss > 0.005 ? $fxGainLoss : 0.0), 2);

            $journalEntry = $this->createHeader(
                $companyId,
                $actorId,
                $customerPayment->payment_date ?? now(),
                'customer_payment',
                (int) $customerPayment->id,
                'Customer Payment #' . $customerPayment->payment_number,
                $totalDebit,
                $totalCredit,
                $customerPayment->currency_code ?? ($bankAccount->currency_code ?? 'ETB'),
                (float) ($customerPayment->exchange_rate ?? 1.0)
            );

            $customerName = $customerPayment->customer?->name ?? 'Customer';
            if ($cashAmount > 0) {
                $this->addLine($journalEntry, $bankGLAccount->id, 'Payment received from ' . $customerName, $cashAmount, 0, $actorId, $companyId);
            }
            if ($whtAmount > 0 && $whtReceivableAccount) {
                $this->addLine($journalEntry, $whtReceivableAccount->id, 'Withholding tax deducted by ' . $customerName, $whtAmount, 0, $actorId, $companyId);
            }
            if ($fxGainLoss < -0.005 && $fxLossAccount) {
                $this->addLine($journalEntry, $fxLossAccount->id, 'Realized FX Loss on customer receipt', abs($fxGainLoss), 0, $actorId, $companyId);
            }

            $this->addLine($journalEntry, $arAccount->id, 'Payment from customer', 0, $arSettled, $actorId, $companyId);

            if ($fxGainLoss > 0.005 && $fxGainAccount) {
                $this->addLine($journalEntry, $fxGainAccount->id, 'Realized FX Gain on customer receipt', 0, $fxGainLoss, $actorId, $companyId);
            }

            $this->dispatchBudgetUpdate($journalEntry);
            $this->updateAccountBalances($journalEntry);
            return $journalEntry;
        });
    }

    /**
     * Vendor Payment Disbursement with Ethiopian 2% WHT Payable (2230) & Realized FX Gain/Loss (4310 / 5520).
     */
    public function createVendorPaymentJournal($vendorPayment)
    {
        $companyId = $this->resolveCompanyId($vendorPayment);
        $actorId = $this->resolveActorId($companyId, $vendorPayment);

        if ($this->hasPostedJournal('vendor_payment', (int) $vendorPayment->id, $companyId)) {
            return null;
        }

        return DB::transaction(function () use ($vendorPayment, $companyId, $actorId) {
            [$bankAccount, $bankGLAccount] = $this->findBankGLAccount((int) $vendorPayment->bank_account_id, $companyId);
            $apAccount = $this->getAccountByCode('2000', $companyId);

            $cashAmount = round((float) $vendorPayment->payment_amount, 2);
            $whtAmount = round((float) ($vendorPayment->wht_amount ?? 0), 2);
            $fxGainLoss = round((float) ($vendorPayment->fx_gain_loss ?? 0), 2);
            $apSettled = round((float) ($vendorPayment->gross_settled_amount ?? ($cashAmount + $whtAmount + $fxGainLoss)), 2);

            $whtPayableAccount = $whtAmount > 0 ? $this->getAccountByCode('2230', $companyId) : null;
            $fxGainAccount = $fxGainLoss > 0.005 ? $this->getAccountByCode('4310', $companyId) : null;
            $fxLossAccount = $fxGainLoss < -0.005 ? $this->getAccountByCode('5520', $companyId) : null;

            $totalDebit = round($apSettled + ($fxGainLoss < -0.005 ? abs($fxGainLoss) : 0.0), 2);
            $totalCredit = round($cashAmount + $whtAmount + ($fxGainLoss > 0.005 ? $fxGainLoss : 0.0), 2);

            $journalEntry = $this->createHeader(
                $companyId,
                $actorId,
                $vendorPayment->payment_date ?? now(),
                'vendor_payment',
                (int) $vendorPayment->id,
                'Vendor Payment #' . $vendorPayment->payment_number,
                $totalDebit,
                $totalCredit,
                $vendorPayment->currency_code ?? ($bankAccount->currency_code ?? 'ETB'),
                (float) ($vendorPayment->exchange_rate ?? 1.0)
            );

            $vendorName = $vendorPayment->vendor?->name ?? 'Vendor';
            $this->addLine($journalEntry, $apAccount->id, 'Payment to ' . $vendorName, $apSettled, 0, $actorId, $companyId);

            if ($fxGainLoss < -0.005 && $fxLossAccount) {
                $this->addLine($journalEntry, $fxLossAccount->id, 'Realized FX Loss on vendor settlement', abs($fxGainLoss), 0, $actorId, $companyId);
            }
            if ($cashAmount > 0) {
                $this->addLine($journalEntry, $bankGLAccount->id, 'Payment from ' . ($bankAccount->account_name ?? 'Bank'), 0, $cashAmount, $actorId, $companyId);
            }
            if ($whtAmount > 0 && $whtPayableAccount) {
                $this->addLine($journalEntry, $whtPayableAccount->id, '2% MoR WHT withheld from ' . $vendorName, 0, $whtAmount, $actorId, $companyId);
            }
            if ($fxGainLoss > 0.005 && $fxGainAccount) {
                $this->addLine($journalEntry, $fxGainAccount->id, 'Realized FX Gain on vendor settlement', 0, $fxGainLoss, $actorId, $companyId);
            }

            $this->dispatchBudgetUpdate($journalEntry);
            $this->updateAccountBalances($journalEntry);
            return $journalEntry;
        });
    }

    public function createRevenueEntryJournal($revenueEntry)
    {
        $companyId = $this->resolveCompanyId($revenueEntry);
        $actorId = $this->resolveActorId($companyId, $revenueEntry);

        if ($this->hasPostedJournal('revenue', (int) $revenueEntry->id, $companyId)) {
            return null;
        }

        return DB::transaction(function () use ($revenueEntry, $companyId, $actorId) {
            [, $bankGLAccount] = $this->findBankGLAccount((int) $revenueEntry->bank_account_id, $companyId);
            $revenueAccount = ChartOfAccount::withoutGlobalScope('tenant')
                ->where('created_by', $companyId)
                ->find($revenueEntry->chart_of_account_id);

            if (!$revenueAccount) {
                throw new \Exception('Revenue account not found');
            }

            $amount = round((float) $revenueEntry->amount, 2);
            $journalDate = $revenueEntry->revenue_date ?? $revenueEntry->entry_date ?? now();

            $journalEntry = $this->createHeader(
                $companyId,
                $actorId,
                $journalDate,
                'revenue',
                (int) $revenueEntry->id,
                'Revenue Entry - #' . $revenueEntry->revenue_number,
                $amount,
                $amount
            );

            $this->addLine($journalEntry, $bankGLAccount->id, 'Revenue received', $amount, 0, $actorId, $companyId);
            $this->addLine($journalEntry, $revenueAccount->id, 'Revenue earned', 0, $amount, $actorId, $companyId);

            $this->dispatchBudgetUpdate($journalEntry);
            $this->updateAccountBalances($journalEntry);
            return $journalEntry;
        });
    }

    public function createExpenseEntryJournal($expenseEntry)
    {
        $companyId = $this->resolveCompanyId($expenseEntry);
        $actorId = $this->resolveActorId($companyId, $expenseEntry);

        if ($this->hasPostedJournal('expense', (int) $expenseEntry->id, $companyId)) {
            return null;
        }

        return DB::transaction(function () use ($expenseEntry, $companyId, $actorId) {
            [, $bankGLAccount] = $this->findBankGLAccount((int) $expenseEntry->bank_account_id, $companyId);
            $expenseAccount = ChartOfAccount::withoutGlobalScope('tenant')
                ->where('created_by', $companyId)
                ->find($expenseEntry->chart_of_account_id);

            if (!$expenseAccount) {
                throw new \Exception('Expense account not found');
            }

            $amount = round((float) $expenseEntry->amount, 2);
            $journalDate = $expenseEntry->expense_date ?? $expenseEntry->entry_date ?? now();

            $journalEntry = $this->createHeader(
                $companyId,
                $actorId,
                $journalDate,
                'expense',
                (int) $expenseEntry->id,
                'Expense Entry - #' . $expenseEntry->expense_number,
                $amount,
                $amount
            );

            $this->addLine($journalEntry, $expenseAccount->id, 'Expense incurred', $amount, 0, $actorId, $companyId);
            $this->addLine($journalEntry, $bankGLAccount->id, 'Payment made', 0, $amount, $actorId, $companyId);

            $this->dispatchBudgetUpdate($journalEntry);
            $this->updateAccountBalances($journalEntry);
            return $journalEntry;
        });
    }

    public function createBankFeeJournal(int $bankAccountId, float $feeAmount, $date, string $reference, string $description, ?int $companyId = null): ?JournalEntry
    {
        if ($feeAmount <= 0.005) {
            return null;
        }

        $tenantId = $companyId ?? (Auth::check() ? (int) creatorId() : 1);
        $actorId = $this->resolveActorId($tenantId);

        return DB::transaction(function () use ($bankAccountId, $feeAmount, $date, $reference, $description, $tenantId, $actorId) {
            [, $bankGLAccount] = $this->findBankGLAccount($bankAccountId, $tenantId);
            $chargesAccount = $this->getAccountByCode('5510', $tenantId);

            $amount = round($feeAmount, 2);
            $journalEntry = $this->createHeader(
                $tenantId,
                $actorId,
                $date ?? now(),
                'bank_fee',
                $bankAccountId,
                $description . ' (' . $reference . ')',
                $amount,
                $amount
            );

            $this->addLine($journalEntry, $chargesAccount->id, $description, $amount, 0, $actorId, $tenantId);
            $this->addLine($journalEntry, $bankGLAccount->id, 'Bank/Wallet commission deducted', 0, $amount, $actorId, $tenantId);

            $this->updateAccountBalances($journalEntry);
            return $journalEntry;
        });
    }

    public function createStockTransferJournal($stockTransfer)
    {
        $companyId = $this->resolveCompanyId($stockTransfer);
        $actorId = $this->resolveActorId($companyId, $stockTransfer);

        $unitCost = $this->resolveWarehouseUnitCost(
            (int) $stockTransfer->product_id,
            $stockTransfer->from_warehouse ? (int) $stockTransfer->from_warehouse : null,
            $companyId,
            (float) ($stockTransfer->product?->purchase_price ?? 0)
        );

        $transferValue = round((float) $stockTransfer->quantity * $unitCost, 2);
        if ($transferValue <= 0.01) {
            return null;
        }

        return DB::transaction(function () use ($stockTransfer, $transferValue, $companyId, $actorId) {
            $inventoryAccount = $this->getAccountByCode('1200', $companyId);
            $customInvId = $this->resolveValidCustomAccountId($stockTransfer->product?->inventory_chartaccount_id ?? null, $companyId);
            $accountId = $customInvId ?: $inventoryAccount->id;

            $fromName = $stockTransfer->fromWarehouse?->name ?? 'Source Warehouse';
            $toName = $stockTransfer->toWarehouse?->name ?? 'Destination Warehouse';

            $journalEntry = $this->createHeader(
                $companyId,
                $actorId,
                $stockTransfer->date ?? now(),
                'stock_transfer',
                (int) $stockTransfer->id,
                'Stock Transfer #' . $stockTransfer->id . ' - ' . $fromName . ' to ' . $toName,
                $transferValue,
                $transferValue
            );

            $this->addLine($journalEntry, $accountId, 'Stock received at ' . $toName, $transferValue, 0, $actorId, $companyId);
            $this->addLine($journalEntry, $accountId, 'Stock transferred from ' . $fromName, 0, $transferValue, $actorId, $companyId);

            $this->updateAccountBalances($journalEntry);
            return $journalEntry;
        });
    }

    public function deleteStockTransferJournal($transferId): void
    {
        DB::transaction(function () use ($transferId) {
            $journalEntry = JournalEntry::withoutGlobalScope('tenant')
                ->where('reference_type', 'stock_transfer')
                ->where('reference_id', $transferId)
                ->when(Auth::check(), fn ($q) => $q->where('created_by', creatorId()))
                ->first();

            if ($journalEntry && $journalEntry->status === 'posted') {
                $this->reverseJournalEntry($journalEntry, 'Stock transfer deleted');
            }
        });
    }

    public function createDebitNoteJournal($debitNote)
    {
        $companyId = $this->resolveCompanyId($debitNote);
        $actorId = $this->resolveActorId($companyId, $debitNote);

        if ($this->hasPostedJournal('debit_note', (int) $debitNote->id, $companyId)) {
            return null;
        }

        return DB::transaction(function () use ($debitNote, $companyId, $actorId) {
            $taxAmount = round((float) ($debitNote->tax_amount ?? 0), 2);
            $netAmount = round((float) $debitNote->subtotal - (float) $debitNote->discount_amount, 2);
            $totalAmount = round((float) $debitNote->total_amount, 2);

            $apAccount = $this->getAccountByCode('2000', $companyId);
            $inventoryAccount = $this->getAccountByCode('1200', $companyId);
            $expenseFallback = $this->getAccountByCode('5100', $companyId);
            $taxAccount = $taxAmount > 0 ? $this->getAccountByCode('1500', $companyId) : null;

            $journalEntry = $this->createHeader(
                $companyId,
                $actorId,
                $debitNote->debit_note_date ?? now(),
                'debit_note',
                (int) $debitNote->id,
                'Debit Note #' . $debitNote->debit_note_number,
                $totalAmount,
                $netAmount + $taxAmount
            );

            $vendorName = $debitNote->vendor?->name ?? 'Vendor';
            $this->addLine($journalEntry, $apAccount->id, 'Debit Note - ' . $vendorName, $totalAmount, 0, $actorId, $companyId);

            $debitNote->loadMissing('items.product');
            $creditBuckets = [];
            foreach ($debitNote->items as $item) {
                $product = $item->product;
                $isService = ($product?->type ?? 'product') === 'service';
                $targetId = $isService
                    ? ($this->resolveValidCustomAccountId($product->expense_chartaccount_id ?? null, $companyId) ?: $expenseFallback->id)
                    : ($this->resolveValidCustomAccountId($product->inventory_chartaccount_id ?? null, $companyId) ?: $inventoryAccount->id);
                $lineNet = round(max(0, ((float) $item->quantity * (float) $item->unit_price) - (float) ($item->discount_amount ?? 0)), 2);
                $creditBuckets[$targetId] = ($creditBuckets[$targetId] ?? 0.0) + $lineNet;
            }

            $this->distributeBuckets($journalEntry, $creditBuckets, $netAmount, $inventoryAccount->id, 'Purchase return reduction', false, $actorId, $companyId);

            if ($taxAmount > 0 && $taxAccount) {
                $this->addLine($journalEntry, $taxAccount->id, 'Tax reduction from debit note', 0, $taxAmount, $actorId, $companyId);
            }

            $this->updateAccountBalances($journalEntry);
            return $journalEntry;
        });
    }

    public function createCreditNoteJournal($creditNote)
    {
        $companyId = $this->resolveCompanyId($creditNote);
        $actorId = $this->resolveActorId($companyId, $creditNote);

        if ($this->hasPostedJournal('credit_note', (int) $creditNote->id, $companyId)) {
            return null;
        }

        return DB::transaction(function () use ($creditNote, $companyId, $actorId) {
            $taxAmount = round((float) ($creditNote->tax_amount ?? 0), 2);
            $netRevenue = round((float) $creditNote->subtotal - (float) $creditNote->discount_amount, 2);
            $totalAmount = round((float) $creditNote->total_amount, 2);

            $arAccount = $this->getAccountByCode('1100', $companyId);
            $salesAccount = $this->getAccountByCode('4100', $companyId);
            $taxAccount = $taxAmount > 0 ? $this->getAccountByCode('2210', $companyId) : null;

            $journalEntry = $this->createHeader(
                $companyId,
                $actorId,
                $creditNote->credit_note_date ?? now(),
                'credit_note',
                (int) $creditNote->id,
                'Credit Note #' . $creditNote->credit_note_number,
                $netRevenue + $taxAmount,
                $totalAmount
            );

            $creditNote->loadMissing('items.product');
            $debitBuckets = [];
            foreach ($creditNote->items as $item) {
                $customAccId = $this->resolveValidCustomAccountId($item->product->sale_chartaccount_id ?? null, $companyId);
                $targetId = $customAccId ?: $salesAccount->id;
                $lineNet = round(max(0, ((float) $item->quantity * (float) $item->unit_price) - (float) ($item->discount_amount ?? 0)), 2);
                $debitBuckets[$targetId] = ($debitBuckets[$targetId] ?? 0.0) + $lineNet;
            }

            $this->distributeBuckets($journalEntry, $debitBuckets, $netRevenue, $salesAccount->id, 'Credit Note adjustment', true, $actorId, $companyId);

            if ($taxAmount > 0 && $taxAccount) {
                $this->addLine($journalEntry, $taxAccount->id, 'Tax reduction from credit note', $taxAmount, 0, $actorId, $companyId);
            }

            $customerName = $creditNote->customer?->name ?? 'Customer';
            $this->addLine($journalEntry, $arAccount->id, 'Credit Note - ' . $customerName, 0, $totalAmount, $actorId, $companyId);

            $this->updateAccountBalances($journalEntry);
            return $journalEntry;
        });
    }

    public function createBankTransferJournal($bankTransfer)
    {
        $companyId = $this->resolveCompanyId($bankTransfer);
        $actorId = $this->resolveActorId($companyId, $bankTransfer);

        if ($this->hasPostedJournal('bank_transfer', (int) $bankTransfer->id, $companyId)) {
            return null;
        }

        return DB::transaction(function () use ($bankTransfer, $companyId, $actorId) {
            [$fromBank, $fromBankGLAccount] = $this->findBankGLAccount((int) $bankTransfer->from_account_id, $companyId);
            [$toBank, $toBankGLAccount] = $this->findBankGLAccount((int) $bankTransfer->to_account_id, $companyId);

            $transferAmount = round((float) $bankTransfer->transfer_amount, 2);
            $charges = round((float) ($bankTransfer->transfer_charges ?? 0), 2);
            $bankChargesAccount = $charges > 0 ? $this->getAccountByCode('5510', $companyId) : null;

            $totalDebit = round($transferAmount + $charges, 2);
            $totalCredit = $totalDebit;

            $journalEntry = $this->createHeader(
                $companyId,
                $actorId,
                $bankTransfer->transfer_date ?? now(),
                'bank_transfer',
                (int) $bankTransfer->id,
                'Bank Transfer #' . $bankTransfer->transfer_number,
                $totalDebit,
                $totalCredit
            );

            $this->addLine($journalEntry, $toBankGLAccount->id, 'Transfer received from ' . ($fromBank->account_name ?? 'Source'), $transferAmount, 0, $actorId, $companyId);

            if ($charges > 0 && $bankChargesAccount) {
                $this->addLine($journalEntry, $bankChargesAccount->id, 'Bank transfer charges', $charges, 0, $actorId, $companyId);
            }

            $this->addLine($journalEntry, $fromBankGLAccount->id, 'Transfer sent to ' . ($toBank->account_name ?? 'Destination'), 0, $totalDebit, $actorId, $companyId);

            $this->updateAccountBalances($journalEntry);
            return $journalEntry;
        });
    }

    public function createRetainerPaymentJournal($retainerPayment)
    {
        $companyId = $this->resolveCompanyId($retainerPayment);
        $actorId = $this->resolveActorId($companyId, $retainerPayment);

        if ($this->hasPostedJournal('retainer_payment', (int) $retainerPayment->id, $companyId)) {
            return null;
        }

        return DB::transaction(function () use ($retainerPayment, $companyId, $actorId) {
            [, $bankGLAccount] = $this->findBankGLAccount((int) $retainerPayment->bank_account_id, $companyId);
            $unearnedRevenueAccount = $this->getAccountByCode('2350', $companyId);
            $amount = round((float) $retainerPayment->payment_amount, 2);
            $customerName = $retainerPayment->customer?->name ?? 'Customer';

            $journalEntry = $this->createHeader(
                $companyId,
                $actorId,
                $retainerPayment->payment_date ?? now(),
                'retainer_payment',
                (int) $retainerPayment->id,
                'Retainer Payment from ' . $customerName,
                $amount,
                $amount
            );

            $this->addLine($journalEntry, $bankGLAccount->id, 'Retainer advance payment received', $amount, 0, $actorId, $companyId);
            $this->addLine($journalEntry, $unearnedRevenueAccount->id, 'Retainer advance payment received', 0, $amount, $actorId, $companyId);

            $this->dispatchBudgetUpdate($journalEntry);
            $this->updateAccountBalances($journalEntry);
            return $journalEntry;
        });
    }

    public function createCommissionPaymentJournal($commissionPayment)
    {
        $companyId = $this->resolveCompanyId($commissionPayment);
        $actorId = $this->resolveActorId($companyId, $commissionPayment);

        if ($this->hasPostedJournal('commission_payment', (int) $commissionPayment->id, $companyId)) {
            return null;
        }

        return DB::transaction(function () use ($commissionPayment, $companyId, $actorId) {
            [$bankAccount, $bankGLAccount] = $this->findBankGLAccount((int) $commissionPayment->bank_account_id, $companyId);
            $commissionExpenseAccount = $this->getAccountByCode('5220', $companyId);
            $amount = round((float) $commissionPayment->payment_amount, 2);
            $agentName = $commissionPayment->agent?->name ?? 'Agent';

            $journalEntry = $this->createHeader(
                $companyId,
                $actorId,
                $commissionPayment->payment_date ?? now(),
                'commission_payment',
                (int) $commissionPayment->id,
                'Commission Payment to ' . $agentName,
                $amount,
                $amount
            );

            $this->addLine($journalEntry, $commissionExpenseAccount->id, 'Commission paid to agent', $amount, 0, $actorId, $companyId);
            $this->addLine($journalEntry, $bankGLAccount->id, 'Commission payment from ' . ($bankAccount->account_name ?? 'Bank'), 0, $amount, $actorId, $companyId);

            $this->dispatchBudgetUpdate($journalEntry);
            $this->updateAccountBalances($journalEntry);
            return $journalEntry;
        });
    }

    public function createPayrollJournal($payrollEntry)
    {
        $companyId = $this->resolveCompanyId($payrollEntry);
        $actorId = $this->resolveActorId($companyId, $payrollEntry);

        if ($this->hasPostedJournal('payroll', (int) $payrollEntry->id, $companyId)) {
            return null;
        }

        return DB::transaction(function () use ($payrollEntry, $companyId, $actorId) {
            [, $bankGLAccount] = $this->findBankGLAccount((int) ($payrollEntry->payroll?->bank_account_id ?? 0), $companyId);

            $salaryExpenseAccount = $this->getAccountByCode('5200', $companyId);
            $benefitsExpenseAccount = ChartOfAccount::withoutGlobalScope('tenant')
                ->where('account_code', '5210')->where('created_by', $companyId)->first() ?: $salaryExpenseAccount;
            $taxPayableAccount = ChartOfAccount::withoutGlobalScope('tenant')
                ->where('account_code', '2200')->where('created_by', $companyId)->first();
            $payrollLiabilityAccount = ChartOfAccount::withoutGlobalScope('tenant')
                ->where('account_code', '2400')->where('created_by', $companyId)->first()
                ?: ($taxPayableAccount ?: $bankGLAccount);
            $taxPayableAccount = $taxPayableAccount ?: $payrollLiabilityAccount;

            $deductions = is_array($payrollEntry->deductions_breakdown)
                ? $payrollEntry->deductions_breakdown
                : (json_decode($payrollEntry->deductions_breakdown ?? '[]', true) ?: []);

            $netPay = round((float) $payrollEntry->net_pay, 2);
            $grossPay = round((float) ($payrollEntry->gross_pay ?? $netPay), 2);
            $pitAmount = round((float) (($payrollEntry->income_tax ?? 0) > 0
                ? $payrollEntry->income_tax
                : ($deductions['Income Tax (PIT) / የስራ ግብር'] ?? $deductions['Employment Income Tax (PIT)'] ?? 0)), 2);
            $empPension = round((float) (($payrollEntry->employee_pension ?? 0) > 0
                ? $payrollEntry->employee_pension
                : ($deductions['Employee Pension (7%) / የሰራተኛ ጡረታ'] ?? $deductions['Pension Contribution (7%)'] ?? 0)), 2);
            $emprPension = round((float) (($payrollEntry->employer_pension ?? 0) > 0
                ? $payrollEntry->employer_pension
                : ($deductions['Employer Pension (11% Cost) / የአሰሪ ጡረታ'] ?? $deductions['Employer Pension (11% - Cost)'] ?? 0)), 2);

            $otherWithholdings = max(0.0, round($grossPay - $netPay - $pitAmount - $empPension, 2));
            $totalPensionPayable = round($empPension + $emprPension, 2);
            $totalPayrollLiabilities = round($totalPensionPayable + $otherWithholdings, 2);

            $totalDebit = round($grossPay + $emprPension, 2);
            $totalCredit = round($netPay + $pitAmount + $totalPayrollLiabilities, 2);

            if (abs($totalDebit - $totalCredit) > 0.02 || $grossPay <= 0) {
                $grossPay = $netPay;
                $emprPension = 0.0;
                $pitAmount = 0.0;
                $totalPayrollLiabilities = 0.0;
                $totalDebit = $netPay;
                $totalCredit = $netPay;
            }

            $empName = $payrollEntry->employee?->user?->name ?? 'Employee';
            $journalEntry = $this->createHeader(
                $companyId,
                $actorId,
                now(),
                'payroll',
                (int) $payrollEntry->id,
                'Payroll Disbursement & Statutory Accrual - ' . $empName,
                $totalDebit,
                $totalCredit
            );

            $this->addLine($journalEntry, $salaryExpenseAccount->id, 'Gross salary expense - ' . $empName, $grossPay, 0, $actorId, $companyId);
            if ($emprPension > 0) {
                $this->addLine($journalEntry, $benefitsExpenseAccount->id, 'Employer pension contribution (11%) - ' . $empName, $emprPension, 0, $actorId, $companyId);
            }
            $this->addLine($journalEntry, $bankGLAccount->id, 'Net salary payment - ' . $empName, 0, $netPay, $actorId, $companyId);
            if ($pitAmount > 0) {
                $this->addLine($journalEntry, $taxPayableAccount->id, 'MoR Employment Income Tax (PIT) withheld - ' . $empName, 0, $pitAmount, $actorId, $companyId);
            }
            if ($totalPayrollLiabilities > 0) {
                $this->addLine($journalEntry, $payrollLiabilityAccount->id, 'Pension payable (18%) & statutory/loan withholdings - ' . $empName, 0, $totalPayrollLiabilities, $actorId, $companyId);
            }

            $this->dispatchBudgetUpdate($journalEntry);
            $this->updateAccountBalances($journalEntry);
            return $journalEntry;
        });
    }

    public function createPosJournal($posSale)
    {
        $companyId = $this->resolveCompanyId($posSale);
        $actorId = $this->resolveActorId($companyId, $posSale);

        if ($this->hasPostedJournal('pos_sale', (int) $posSale->id, $companyId)) {
            return null;
        }

        return DB::transaction(function () use ($posSale, $companyId, $actorId) {
            [, $bankGLAccount] = $this->findBankGLAccount((int) $posSale->bank_account_id, $companyId);
            $posSale->loadMissing(['payment', 'items.product']);

            $taxAmount = round((float) $posSale->items->sum('tax_amount'), 2);
            $totalAmount = round((float) ($posSale->payment?->discount_amount ?? 0), 2);
            $subtotal = round((float) $posSale->items->sum('subtotal'), 2);
            $discount = round((float) ($posSale->payment?->discount ?? 0), 2);
            $netSales = round($subtotal - $discount, 2);

            $salesAccount = $this->getAccountByCode('4100', $companyId);
            $taxAccount = $taxAmount > 0 ? $this->getAccountByCode('2210', $companyId) : null;

            $journalEntry = $this->createHeader(
                $companyId,
                $actorId,
                $posSale->pos_date ?? now(),
                'pos_sale',
                (int) $posSale->id,
                'POS Sale ' . $posSale->sale_number,
                $totalAmount,
                $netSales + $taxAmount
            );

            $this->addLine($journalEntry, $bankGLAccount->id, 'POS cash sale', $totalAmount, 0, $actorId, $companyId);

            $revenueBuckets = [];
            foreach ($posSale->items as $posItem) {
                $customAccId = $this->resolveValidCustomAccountId($posItem->product->sale_chartaccount_id ?? null, $companyId);
                $targetAccId = $customAccId ?: $salesAccount->id;
                $lineNet = round((float) ($posItem->subtotal ?? ((float) $posItem->quantity * (float) $posItem->price)), 2);
                $revenueBuckets[$targetAccId] = ($revenueBuckets[$targetAccId] ?? 0.0) + $lineNet;
            }
            $this->distributeBuckets($journalEntry, $revenueBuckets, $netSales, $salesAccount->id, 'POS product sales', false, $actorId, $companyId);

            if ($taxAmount > 0 && $taxAccount) {
                $this->addLine($journalEntry, $taxAccount->id, 'Sales tax collected', 0, $taxAmount, $actorId, $companyId);
            }

            $this->dispatchBudgetUpdate($journalEntry);
            $this->updateAccountBalances($journalEntry);
            return $journalEntry;
        });
    }

    private function postCOGSJournal(
        $document,
        string $referenceType,
        string $descriptionPrefix,
        $date,
        bool $isReversal = false
    ): ?JournalEntry {
        $companyId = $this->resolveCompanyId($document);
        $actorId = $this->resolveActorId($companyId, $document);

        if ($this->hasPostedJournal($referenceType, (int) $document->id, $companyId)) {
            return null;
        }

        $document->loadMissing('items.product');
        $warehouseId = isset($document->warehouse_id) && $document->warehouse_id ? (int) $document->warehouse_id : null;

        $cogsAccount = $this->getAccountByCode('5100', $companyId);
        $inventoryAccount = $this->getAccountByCode('1200', $companyId);

        $cogsBuckets = [];
        $invBuckets = [];
        $totalCost = 0.0;

        foreach ($document->items as $item) {
            if (!$item->product || ($item->product->type ?? 'product') === 'service') {
                continue;
            }

            $costPrice = $this->resolveWarehouseUnitCost(
                (int) $item->product->id,
                $warehouseId,
                $companyId,
                (float) ($item->product->purchase_price ?? 0)
            );
            $lineCost = round((float) $item->quantity * $costPrice, 2);
            if ($lineCost <= 0) {
                continue;
            }

            $totalCost += $lineCost;
            $targetCogsId = $this->resolveValidCustomAccountId($item->product->expense_chartaccount_id ?? null, $companyId) ?: $cogsAccount->id;
            $targetInvId = $this->resolveValidCustomAccountId($item->product->inventory_chartaccount_id ?? null, $companyId) ?: $inventoryAccount->id;

            $cogsBuckets[$targetCogsId] = ($cogsBuckets[$targetCogsId] ?? 0.0) + $lineCost;
            $invBuckets[$targetInvId] = ($invBuckets[$targetInvId] ?? 0.0) + $lineCost;
        }

        $totalCost = round($totalCost, 2);
        if ($totalCost <= 0.01) {
            return null;
        }

        return DB::transaction(function () use ($document, $referenceType, $descriptionPrefix, $date, $isReversal, $companyId, $actorId, $totalCost, $cogsBuckets, $invBuckets, $cogsAccount, $inventoryAccount) {
            $journalEntry = $this->createHeader(
                $companyId,
                $actorId,
                $date ?? now(),
                $referenceType,
                (int) $document->id,
                $descriptionPrefix,
                $totalCost,
                $totalCost
            );

            if ($isReversal) {
                $this->distributeBuckets($journalEntry, $invBuckets, $totalCost, $inventoryAccount->id, 'Inventory returned', true, $actorId, $companyId);
                $this->distributeBuckets($journalEntry, $cogsBuckets, $totalCost, $cogsAccount->id, 'COGS reversal', false, $actorId, $companyId);
            } else {
                $this->distributeBuckets($journalEntry, $cogsBuckets, $totalCost, $cogsAccount->id, 'Cost of goods sold', true, $actorId, $companyId);
                $this->distributeBuckets($journalEntry, $invBuckets, $totalCost, $inventoryAccount->id, 'Inventory reduction', false, $actorId, $companyId);
            }

            $this->updateAccountBalances($journalEntry);
            return $journalEntry;
        });
    }

    public function createSalesCOGSJournal($salesInvoice)
    {
        return $this->postCOGSJournal(
            $salesInvoice,
            'sales_invoice_cogs',
            'COGS for Sales Invoice #' . $salesInvoice->invoice_number,
            $salesInvoice->invoice_date ?? now(),
            false
        );
    }

    public function createPosCOGSJournal($posSale)
    {
        return $this->postCOGSJournal(
            $posSale,
            'pos_sale_cogs',
            'COGS for POS Sale #' . $posSale->sale_number,
            $posSale->pos_date ?? now(),
            false
        );
    }

    public function createCreditNoteCOGSJournal($creditNote)
    {
        return $this->postCOGSJournal(
            $creditNote,
            'credit_note_cogs',
            'COGS Reversal for Credit Note #' . $creditNote->credit_note_number,
            $creditNote->credit_note_date ?? now(),
            true
        );
    }

    public function createMobileServicePaymentJournal($payment)
    {
        return $this->createSimpleBankReceiptJournal($payment, 'mobile_service_payment', '4200', 'Mobile Service Payment - ' . ($payment->notes ?? ''), 'Mobile service payment received', 'Mobile service revenue earned');
    }

    public function createMarkFleetBookingPaymentJournal($payment)
    {
        return $this->createSimpleBankReceiptJournal($payment, 'fleet_booking_payment', '4300', 'Fleet Booking Payment - ' . ($payment->notes ?? 'Fleet service payment'), 'Fleet booking payment received', 'Fleet service revenue earned');
    }

    public function createBeautyBookingPaymentJournal($booking)
    {
        $companyId = $this->resolveCompanyId($booking);
        $actorId = $this->resolveActorId($companyId, $booking);

        if ($this->hasPostedJournal('beauty_booking_payment', (int) $booking->id, $companyId)) {
            return null;
        }

        return DB::transaction(function () use ($booking, $companyId, $actorId) {
            $bankAccount = BankAccount::withoutGlobalScope('tenant')
                ->with('glAccount')
                ->where('payment_gateway', $booking->payment_option)
                ->where('created_by', $companyId)
                ->first();

            if (!$bankAccount || !$bankAccount->glAccount) {
                throw new \Exception('Bank account with GL account not found for payment gateway: ' . $booking->payment_option);
            }

            $revenueAccount = $this->getAccountByCode('4200', $companyId);
            $amount = round((float) $booking->price, 2);

            $journalEntry = $this->createHeader(
                $companyId,
                $actorId,
                $booking->date ?? now(),
                'beauty_booking_payment',
                (int) $booking->id,
                'Beauty Booking Payment via ' . $booking->payment_option,
                $amount,
                $amount
            );

            $this->addLine($journalEntry, $bankAccount->glAccount->id, 'Beauty booking payment received', $amount, 0, $actorId, $companyId);
            $this->addLine($journalEntry, $revenueAccount->id, 'Beauty service revenue earned', 0, $amount, $actorId, $companyId);

            $this->dispatchBudgetUpdate($journalEntry);
            $this->updateAccountBalances($journalEntry);
            return $journalEntry;
        });
    }

    public function createDairyCattlePaymentJournal($dairyCattlePayment)
    {
        return $this->createSimpleBankReceiptJournal($dairyCattlePayment, 'dairy_cattle_payment', '4200', 'Dairy Cattle Payment - ' . ($dairyCattlePayment->notes ?? 'Dairy cattle service payment'), 'Dairy cattle payment received', 'Dairy cattle service revenue earned');
    }

    public function createCateringOrderPaymentJournal($payment)
    {
        return $this->createSimpleBankReceiptJournal($payment, 'catering_order_payment', '4200', 'Catering Order Payment - ' . ($payment->notes ?? 'Catering service payment'), 'Catering order payment received', 'Catering service revenue earned', 'amount');
    }

    private function createSimpleBankReceiptJournal(
        $payment,
        string $referenceType,
        string $creditAccountCode,
        string $description,
        string $debitDesc,
        string $creditDesc,
        string $amountColumn = 'payment_amount'
    ): ?JournalEntry {
        $companyId = $this->resolveCompanyId($payment);
        $actorId = $this->resolveActorId($companyId, $payment);

        if ($this->hasPostedJournal($referenceType, (int) $payment->id, $companyId)) {
            return null;
        }

        return DB::transaction(function () use ($payment, $referenceType, $creditAccountCode, $description, $debitDesc, $creditDesc, $amountColumn, $companyId, $actorId) {
            [, $bankGLAccount] = $this->findBankGLAccount((int) $payment->bank_account_id, $companyId);
            $revenueAccount = $this->getAccountByCode($creditAccountCode, $companyId);
            $amount = round((float) ($payment->{$amountColumn} ?? 0), 2);

            $journalEntry = $this->createHeader(
                $companyId,
                $actorId,
                $payment->payment_date ?? now(),
                $referenceType,
                (int) $payment->id,
                $description,
                $amount,
                $amount
            );

            $this->addLine($journalEntry, $bankGLAccount->id, $debitDesc, $amount, 0, $actorId, $companyId);
            $this->addLine($journalEntry, $revenueAccount->id, $creditDesc, 0, $amount, $actorId, $companyId);

            $this->dispatchBudgetUpdate($journalEntry);
            $this->updateAccountBalances($journalEntry);
            return $journalEntry;
        });
    }

    public function createUpdateSalesAgentCommissionPaymentJournal($payment)
    {
        $companyId = $this->resolveCompanyId($payment);
        $actorId = $this->resolveActorId($companyId, $payment);

        if ($this->hasPostedJournal('sales_agent_commission_payment', (int) $payment->id, $companyId)) {
            return null;
        }

        return DB::transaction(function () use ($payment, $companyId, $actorId) {
            [$bankAccount, $bankGLAccount] = $this->findBankGLAccount((int) $payment->bank_account_id, $companyId);
            $commissionExpenseAccount = $this->getAccountByCode('5220', $companyId);
            $amount = round((float) $payment->payment_amount, 2);
            $agentName = $payment->agent?->user?->name ?? 'Agent';

            $journalEntry = $this->createHeader(
                $companyId,
                $actorId,
                $payment->payment_date ?? now(),
                'sales_agent_commission_payment',
                (int) $payment->id,
                'Commission Payment #' . $payment->payment_number . ' - ' . $agentName,
                $amount,
                $amount
            );

            $this->addLine($journalEntry, $commissionExpenseAccount->id, 'Commission expense - ' . $agentName, $amount, 0, $actorId, $companyId);
            $this->addLine($journalEntry, $bankGLAccount->id, 'Payment from ' . ($bankAccount->account_name ?? 'Bank'), 0, $amount, $actorId, $companyId);

            $this->dispatchBudgetUpdate($journalEntry);
            $this->updateAccountBalances($journalEntry);
            return $journalEntry;
        });
    }

    public function createCommissionAdjustmentJournal($adjustment)
    {
        $companyId = $this->resolveCompanyId($adjustment);
        $actorId = $this->resolveActorId($companyId, $adjustment);

        if ($this->hasPostedJournal('commission_adjustment', (int) $adjustment->id, $companyId)) {
            return null;
        }

        return DB::transaction(function () use ($adjustment, $companyId, $actorId) {
            $commissionExpenseAccount = $this->getAccountByCode('5220', $companyId);
            $commissionPayableAccount = $this->getAccountByCode('2400', $companyId);
            $otherIncomeAccount = $this->getAccountByCode('4300', $companyId);

            $amount = round(abs((float) $adjustment->adjustment_amount), 2);
            $agentName = $adjustment->agent?->user?->name ?? 'Agent';

            $journalEntry = $this->createHeader(
                $companyId,
                $actorId,
                $adjustment->adjustment_date ?? now(),
                'commission_adjustment',
                (int) $adjustment->id,
                'Commission Adjustment (' . ucfirst($adjustment->adjustment_type) . ') - ' . $agentName,
                $amount,
                $amount
            );

            if ($adjustment->adjustment_type === 'bonus') {
                $this->addLine($journalEntry, $commissionExpenseAccount->id, 'Commission bonus - ' . $adjustment->adjustment_reason, $amount, 0, $actorId, $companyId);
                $this->addLine($journalEntry, $commissionPayableAccount->id, 'Commission payable to agent', 0, $amount, $actorId, $companyId);
            } elseif ($adjustment->adjustment_type === 'penalty') {
                $this->addLine($journalEntry, $commissionPayableAccount->id, 'Commission deduction from agent', $amount, 0, $actorId, $companyId);
                $this->addLine($journalEntry, $otherIncomeAccount->id, 'Commission penalty - ' . $adjustment->adjustment_reason, 0, $amount, $actorId, $companyId);
            } elseif ((float) $adjustment->adjustment_amount > 0) {
                $this->addLine($journalEntry, $commissionExpenseAccount->id, 'Commission correction - ' . $adjustment->adjustment_reason, $amount, 0, $actorId, $companyId);
                $this->addLine($journalEntry, $commissionPayableAccount->id, 'Commission payable adjustment', 0, $amount, $actorId, $companyId);
            } else {
                $this->addLine($journalEntry, $commissionPayableAccount->id, 'Commission payable adjustment', $amount, 0, $actorId, $companyId);
                $this->addLine($journalEntry, $commissionExpenseAccount->id, 'Commission correction - ' . $adjustment->adjustment_reason, 0, $amount, $actorId, $companyId);
            }

            $this->dispatchBudgetUpdate($journalEntry);
            $this->updateAccountBalances($journalEntry);
            return $journalEntry;
        });
    }

    public function createInventoryAdjustmentJournal($adjustment)
    {
        $companyId = $this->resolveCompanyId($adjustment);
        $actorId = $this->resolveActorId($companyId, $adjustment);

        if ($this->hasPostedJournal('inventory_adjustment', (int) $adjustment->id, $companyId)) {
            return null;
        }

        $unitCost = (float) ($adjustment->unit_cost ?? 0);
        if ($unitCost <= 0) {
            $unitCost = $this->resolveWarehouseUnitCost(
                (int) $adjustment->product_id,
                isset($adjustment->warehouse_id) ? (int) $adjustment->warehouse_id : null,
                $companyId,
                (float) ($adjustment->product?->purchase_price ?? 0)
            );
        }

        $totalValue = round((float) $adjustment->quantity * $unitCost, 2);
        if ($totalValue <= 0.01) {
            return null;
        }

        $inventoryAccount = ChartOfAccount::withoutGlobalScope('tenant')->where('account_code', '1200')->where('created_by', $companyId)->first();
        $cogsAccount = ChartOfAccount::withoutGlobalScope('tenant')->where('account_code', '5100')->where('created_by', $companyId)->first();
        if (!$inventoryAccount || !$cogsAccount) {
            return null;
        }

        return DB::transaction(function () use ($adjustment, $companyId, $actorId, $totalValue, $inventoryAccount, $cogsAccount) {
            $isSubtraction = $adjustment->adjustment_type === 'subtraction';
            $targetInvId = $this->resolveValidCustomAccountId($adjustment->product?->inventory_chartaccount_id ?? null, $companyId) ?: $inventoryAccount->id;
            $targetCogsId = $this->resolveValidCustomAccountId($adjustment->product?->expense_chartaccount_id ?? null, $companyId) ?: $cogsAccount->id;

            $journalEntry = $this->createHeader(
                $companyId,
                $actorId,
                now()->toDateString(),
                'inventory_adjustment',
                (int) $adjustment->id,
                sprintf(
                    'Inventory %s (#%d): %s',
                    $isSubtraction ? 'Write-Off / Shrinkage' : 'Stocktake Gain',
                    $adjustment->id,
                    $adjustment->reason
                ),
                $totalValue,
                $totalValue
            );

            if ($isSubtraction) {
                $this->addLine($journalEntry, $targetCogsId, 'Inventory shrinkage / adjustment write-off', $totalValue, 0, $actorId, $companyId);
                $this->addLine($journalEntry, $targetInvId, 'Inventory asset reduction', 0, $totalValue, $actorId, $companyId);
            } else {
                $this->addLine($journalEntry, $targetInvId, 'Inventory stocktake gain', $totalValue, 0, $actorId, $companyId);
                $this->addLine($journalEntry, $targetCogsId, 'Inventory adjustment offset', 0, $totalValue, $actorId, $companyId);
            }

            $this->updateAccountBalances($journalEntry);
            return $journalEntry;
        });
    }
}
