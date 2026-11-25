<?php

namespace Workdo\Account\Services;

use App\Models\PurchaseInvoice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Workdo\Account\Models\DebitNote;
use Workdo\Account\Models\DebitNoteApplication;

class DebitNoteService
{
    public function autoApplyToInvoice($invoiceId)
    {
        $companyId = Auth::check() ? creatorId() : null;

        return DB::transaction(function () use ($invoiceId, $companyId) {
            $invoiceQuery = PurchaseInvoice::withoutGlobalScope('tenant')
                ->where('id', $invoiceId)
                ->lockForUpdate();

            if ($companyId) {
                $invoiceQuery->where('created_by', $companyId);
            }

            $invoice = $invoiceQuery->firstOrFail();
            $tenantId = (int) $invoice->created_by;
            $remainingInvoiceBalance = max(0.0, round((float) ($invoice->balance_amount ?? $invoice->total_amount), 2));

            if ($remainingInvoiceBalance <= 0.0) {
                return 0;
            }

            $availableDebitNotes = DebitNote::withoutGlobalScope('tenant')
                ->where('created_by', $tenantId)
                ->where('vendor_id', $invoice->vendor_id)
                ->whereIn('status', ['approved', 'partial'])
                ->where('balance_amount', '>', 0)
                ->orderBy('debit_note_date', 'asc')
                ->lockForUpdate()
                ->get();

            $totalApplied = 0.0;

            foreach ($availableDebitNotes as $debitNote) {
                $applyAmount = round(min((float) $debitNote->balance_amount, $remainingInvoiceBalance - $totalApplied), 2);
                if ($applyAmount <= 0) {
                    break;
                }

                DebitNoteApplication::create([
                    'debit_note_id'    => $debitNote->id,
                    'invoice_id'       => $invoice->id,
                    'applied_amount'   => $applyAmount,
                    'application_date' => now(),
                    'creator_id'       => Auth::id() ?? $tenantId,
                    'created_by'       => $tenantId,
                ]);

                $debitNote->applied_amount = round((float) $debitNote->applied_amount + $applyAmount, 2);
                $debitNote->balance_amount = max(0.0, round((float) $debitNote->total_amount - (float) $debitNote->applied_amount, 2));
                $debitNote->status = $debitNote->balance_amount <= 0.01 ? 'applied' : 'partial';
                $debitNote->save();

                $totalApplied = round($totalApplied + $applyAmount, 2);

                if ($totalApplied >= $remainingInvoiceBalance) {
                    break;
                }
            }

            if ($totalApplied > 0) {
                $invoice->debit_note_applied = round((float) ($invoice->debit_note_applied ?? 0) + $totalApplied, 2);
                $invoice->balance_amount = max(0.0, round($remainingInvoiceBalance - $totalApplied, 2));
                if ($invoice->balance_amount <= 0.01) {
                    $invoice->balance_amount = 0;
                    $invoice->status = 'paid';
                } elseif ($invoice->status === 'posted') {
                    $invoice->status = 'partial';
                }
                $invoice->save();
            }

            return $totalApplied;
        });
    }

    public function getAvailableForVendor($vendorId)
    {
        return DebitNote::where('vendor_id', $vendorId)
            ->whereIn('status', ['approved', 'partial'])
            ->where('balance_amount', '>', 0)
            ->where('created_by', creatorId())
            ->orderBy('debit_note_date', 'asc')
            ->get();
    }
}
