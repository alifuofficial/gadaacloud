<?php

namespace Workdo\Account\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\BankTransaction;

class BankTransactionsService
{
    /**
     * Atomically record a bank ledger transaction and update the account balance under row lock.
     */
    private function recordBankTransaction(
        int $bankAccountId,
        string $transactionType,
        float $amount,
        $transactionDate,
        ?string $referenceNumber,
        string $description,
        ?int $companyId = null,
        string $reconciliationStatus = 'unreconciled',
        ?string $matchedType = null,
        ?int $matchedId = null,
        ?string $batchReference = null
    ): BankTransaction {
        $tenantId = $companyId ?? (Auth::check() ? creatorId() : null);

        return DB::transaction(function () use (
            $bankAccountId,
            $transactionType,
            $amount,
            $transactionDate,
            $referenceNumber,
            $description,
            $tenantId,
            $reconciliationStatus,
            $matchedType,
            $matchedId,
            $batchReference
        ) {
            $query = BankAccount::withoutGlobalScope('tenant')->where('id', $bankAccountId);
            if ($tenantId) {
                $query->where('created_by', $tenantId);
            }
            $bankAccount = $query->lockForUpdate()->first();
            if (!$bankAccount) {
                throw new \Exception(__('Bank account not found or access denied.'));
            }

            $signedDelta = $transactionType === 'credit' ? abs($amount) : -abs($amount);
            $newBalance = round((float) ($bankAccount->current_balance ?? 0) + $signedDelta, 2);

            $bankTransaction = new BankTransaction();
            $bankTransaction->bank_account_id = $bankAccount->id;
            $bankTransaction->transaction_date = $transactionDate ?? now();
            $bankTransaction->transaction_type = $transactionType;
            $bankTransaction->reference_number = $referenceNumber ?? ('TXN-' . strtoupper(substr(uniqid(), -8)));
            $bankTransaction->description = $description;
            $bankTransaction->amount = abs($amount);
            $bankTransaction->running_balance = $newBalance;
            $bankTransaction->transaction_status = 'cleared';
            $bankTransaction->reconciliation_status = $reconciliationStatus;
            $bankTransaction->matched_type = $matchedType;
            $bankTransaction->matched_id = $matchedId;
            if ($reconciliationStatus === 'reconciled') {
                $bankTransaction->reconciled_at = now();
                $bankTransaction->reconciled_by = Auth::id();
            }
            $bankTransaction->batch_reference = $batchReference;
            $bankTransaction->creator_id = Auth::id();
            $bankTransaction->created_by = $bankAccount->created_by;
            $bankTransaction->save();

            $bankAccount->current_balance = $newBalance;
            $bankAccount->save();

            return $bankTransaction;
        });
    }

    public function updateBankBalance($bankAccountId, $amount, ?int $companyId = null): BankAccount
    {
        $tenantId = $companyId ?? (Auth::check() ? creatorId() : null);

        return DB::transaction(function () use ($bankAccountId, $amount, $tenantId) {
            $query = BankAccount::withoutGlobalScope('tenant')->where('id', $bankAccountId);
            if ($tenantId) {
                $query->where('created_by', $tenantId);
            }
            $bankAccount = $query->lockForUpdate()->first();
            if (!$bankAccount) {
                throw new \Exception(__('Bank account not found or access denied.'));
            }

            $bankAccount->current_balance = round((float) ($bankAccount->current_balance ?? 0) + (float) $amount, 2);
            $bankAccount->save();

            $latestTransaction = BankTransaction::withoutGlobalScope('tenant')
                ->where('bank_account_id', $bankAccount->id)
                ->orderByDesc('id')
                ->first();

            if ($latestTransaction) {
                $latestTransaction->running_balance = $bankAccount->current_balance;
                $latestTransaction->save();
            }

            return $bankAccount;
        });
    }

    public function createVendorPayment($vendorPayment): void
    {
        $vendorName = $vendorPayment->vendor?->name ?? 'Vendor';
        $this->recordBankTransaction(
            (int) $vendorPayment->bank_account_id,
            'debit',
            (float) $vendorPayment->payment_amount,
            $vendorPayment->payment_date,
            $vendorPayment->payment_number,
            'Vendor Payment #' . $vendorPayment->payment_number . ' - ' . $vendorName,
            (int) ($vendorPayment->created_by ?? creatorId()),
            'unreconciled',
            'vendor_payment',
            (int) $vendorPayment->id
        );
    }

    public function createCustomerPayment($customerPayment): void
    {
        $customerName = $customerPayment->customer?->name ?? 'Customer';
        $this->recordBankTransaction(
            (int) $customerPayment->bank_account_id,
            'credit',
            (float) $customerPayment->payment_amount,
            $customerPayment->payment_date,
            $customerPayment->payment_number,
            'Customer Payment #' . $customerPayment->payment_number . ' - ' . $customerName,
            (int) ($customerPayment->created_by ?? creatorId()),
            'unreconciled',
            'customer_payment',
            (int) $customerPayment->id
        );
    }

    public function createTransferBankTransactions($transfer): void
    {
        $companyId = (int) ($transfer->created_by ?? creatorId());
        $transfer->loadMissing(['fromAccount', 'toAccount']);
        $toName = $transfer->toAccount?->account_name ?? 'Destination';
        $fromName = $transfer->fromAccount?->account_name ?? 'Source';

        DB::transaction(function () use ($transfer, $companyId, $toName, $fromName) {
            $this->recordBankTransaction(
                (int) $transfer->from_account_id,
                'debit',
                (float) $transfer->transfer_amount,
                $transfer->transfer_date,
                $transfer->transfer_number,
                'Transfer to ' . $toName,
                $companyId,
                'unreconciled',
                'bank_transfer',
                (int) $transfer->id
            );

            $this->recordBankTransaction(
                (int) $transfer->to_account_id,
                'credit',
                (float) $transfer->transfer_amount,
                $transfer->transfer_date,
                $transfer->transfer_number,
                'Transfer from ' . $fromName,
                $companyId,
                'unreconciled',
                'bank_transfer',
                (int) $transfer->id
            );

            if ((float) $transfer->transfer_charges > 0) {
                $this->recordBankTransaction(
                    (int) $transfer->from_account_id,
                    'debit',
                    (float) $transfer->transfer_charges,
                    $transfer->transfer_date,
                    $transfer->transfer_number . '-CHARGES',
                    'Transfer charges for ' . $transfer->transfer_number,
                    $companyId,
                    'unreconciled',
                    'bank_transfer_fee',
                    (int) $transfer->id
                );
            }
        });
    }

    public function createRetainerPayment($retainerPayment): void
    {
        $customerName = $retainerPayment->customer?->name ?? 'Customer';
        $this->recordBankTransaction(
            (int) $retainerPayment->bank_account_id,
            'credit',
            (float) $retainerPayment->payment_amount,
            $retainerPayment->payment_date,
            $retainerPayment->payment_number,
            'Retainer Payment #' . $retainerPayment->payment_number . ' - ' . $customerName,
            (int) ($retainerPayment->created_by ?? creatorId()),
            'unreconciled',
            'retainer_payment',
            (int) $retainerPayment->id
        );
    }

    public function createRevenuePayment($revenue): void
    {
        $this->recordBankTransaction(
            (int) $revenue->bank_account_id,
            'credit',
            (float) $revenue->amount,
            $revenue->revenue_date,
            $revenue->revenue_number,
            'Revenue Posted: ' . ($revenue->description ?? 'Revenue transaction'),
            (int) ($revenue->created_by ?? creatorId()),
            'unreconciled',
            'revenue',
            (int) $revenue->id
        );
    }

    public function createExpensePayment($expense): void
    {
        $this->recordBankTransaction(
            (int) $expense->bank_account_id,
            'debit',
            (float) $expense->amount,
            $expense->expense_date,
            $expense->expense_number,
            'Expense Posted: ' . ($expense->description ?? 'Expense transaction'),
            (int) ($expense->created_by ?? creatorId()),
            'unreconciled',
            'expense',
            (int) $expense->id
        );
    }

    public function createCommissionPayment($commissionPayment): void
    {
        $agentName = $commissionPayment->agent?->name ?? 'Agent';
        $this->recordBankTransaction(
            (int) $commissionPayment->bank_account_id,
            'debit',
            (float) $commissionPayment->payment_amount,
            $commissionPayment->payment_date,
            $commissionPayment->payment_number,
            'Commission Payment #' . $commissionPayment->payment_number . ' - ' . $agentName,
            (int) ($commissionPayment->created_by ?? creatorId()),
            'unreconciled',
            'commission_payment',
            (int) $commissionPayment->id
        );
    }

    public function createPayrollPayment($payrollEntry): void
    {
        $bankAccountId = (int) ($payrollEntry->payroll?->bank_account_id ?? 0);
        if ($bankAccountId <= 0) {
            throw new \Exception(__('Payroll batch must have a bank account assigned.'));
        }
        $employeeName = $payrollEntry->employee?->user?->name ?? 'Employee';

        $this->recordBankTransaction(
            $bankAccountId,
            'debit',
            (float) $payrollEntry->net_pay,
            now(),
            'PAYROLL-' . $payrollEntry->id,
            'Salary Payment - ' . $employeeName,
            (int) ($payrollEntry->created_by ?? creatorId()),
            'unreconciled',
            'payroll',
            (int) $payrollEntry->id
        );
    }

    public function createPosPayment($posSale, $bankAccountId): void
    {
        $posSale->loadMissing('payment');
        // Note: pos_payments.discount_amount stores net payable total after discount
        $amount = (float) ($posSale->payment?->discount_amount ?? 0);
        if ($amount <= 0) {
            return;
        }

        $this->recordBankTransaction(
            (int) $bankAccountId,
            'credit',
            $amount,
            $posSale->pos_date ?? now(),
            $posSale->sale_number,
            'POS Sale ' . $posSale->sale_number,
            (int) ($posSale->created_by ?? creatorId()),
            'unreconciled',
            'pos_sale',
            (int) $posSale->id
        );
    }

    public function createMobileServicePayment($payment): void
    {
        $this->recordBankTransaction(
            (int) $payment->bank_account_id,
            'credit',
            (float) $payment->payment_amount,
            $payment->payment_date,
            $payment->payment_number,
            'Mobile Service Payment: ' . ($payment->description ?? 'Mobile service transaction'),
            (int) ($payment->created_by ?? creatorId()),
            'unreconciled',
            'mobile_service_payment',
            (int) $payment->id
        );
    }

    public function createMarkFleetBookingPayment($payment): void
    {
        $this->recordBankTransaction(
            (int) $payment->bank_account_id,
            'credit',
            (float) $payment->payment_amount,
            $payment->payment_date,
            $payment->payment_number,
            'Fleet Booking Payment: ' . ($payment->description ?? 'Fleet booking transaction'),
            (int) ($payment->created_by ?? creatorId()),
            'unreconciled',
            'fleet_booking_payment',
            (int) $payment->id
        );
    }

    public function createBeautyBookingPayment($booking): void
    {
        $bankAccount = BankAccount::withoutGlobalScope('tenant')
            ->where('payment_gateway', $booking->payment_option)
            ->where('created_by', $booking->created_by)
            ->first();

        if (!$bankAccount) {
            throw new \Exception('Bank account not found for payment gateway: ' . $booking->payment_option);
        }

        $this->recordBankTransaction(
            (int) $bankAccount->id,
            'credit',
            (float) $booking->price,
            $booking->date ?? now(),
            $booking->payment_number ?? 'BEAUTY-' . $booking->id,
            'Beauty Booking Payment via ' . $booking->payment_option,
            (int) $booking->created_by,
            'unreconciled',
            'beauty_booking',
            (int) $booking->id
        );
    }

    public function createDairyCattlePayment($dairyCattlePayment): void
    {
        $this->recordBankTransaction(
            (int) $dairyCattlePayment->bank_account_id,
            'credit',
            (float) $dairyCattlePayment->payment_amount,
            $dairyCattlePayment->payment_date,
            $dairyCattlePayment->payment_number,
            'Dairy Cattle Payment: ' . ($dairyCattlePayment->description ?? 'Dairy cattle transaction'),
            (int) ($dairyCattlePayment->created_by ?? creatorId()),
            'unreconciled',
            'dairy_cattle_payment',
            (int) $dairyCattlePayment->id
        );
    }

    public function createCateringOrderPayment($payment): void
    {
        $this->recordBankTransaction(
            (int) $payment->bank_account_id,
            'credit',
            (float) $payment->amount,
            $payment->payment_date,
            $payment->reference_number,
            'Catering Order Payment #' . $payment->id,
            (int) ($payment->created_by ?? creatorId()),
            'unreconciled',
            'catering_order_payment',
            (int) $payment->id
        );
    }

    public function createUpdateSalesAgentCommissionPayment($payment): void
    {
        $agentName = $payment->agent?->user?->name ?? 'Agent';
        $this->recordBankTransaction(
            (int) $payment->bank_account_id,
            'debit',
            (float) $payment->payment_amount,
            $payment->payment_date,
            $payment->payment_number,
            'Commission Payment #' . $payment->payment_number . ' - ' . $agentName,
            (int) ($payment->created_by ?? creatorId()),
            'unreconciled',
            'sales_agent_commission_payment',
            (int) $payment->id
        );
    }

    public function createCommissionAdjustmentBankTransaction($adjustment): void
    {
        if (empty($adjustment->bank_account_id)) {
            return;
        }

        $agentName = $adjustment->agent?->user?->name ?? 'Agent';
        $amount = abs((float) $adjustment->adjustment_amount);
        $isOutflow = $adjustment->adjustment_type === 'bonus'
            || ($adjustment->adjustment_type === 'correction' && $adjustment->adjustment_amount > 0);

        $this->recordBankTransaction(
            (int) $adjustment->bank_account_id,
            $isOutflow ? 'debit' : 'credit',
            $amount,
            $adjustment->adjustment_date,
            'ADJ-' . $adjustment->id,
            'Commission Adjustment (' . ucfirst($adjustment->adjustment_type) . ') - ' . $agentName,
            (int) ($adjustment->created_by ?? creatorId()),
            'unreconciled',
            'sales_agent_commission_adjustment',
            (int) $adjustment->id
        );
    }
}
