<?php

namespace Workdo\Account\Listeners;

use App\Events\ApprovePurchaseReturn;
use Workdo\Account\Models\DebitNote;
use Illuminate\Support\Facades\Auth;

class CreateDebitNoteFromReturn
{
    public function handle(ApprovePurchaseReturn $event): void
    {
        $purchaseReturn = $event->return;
        $companyId = (int) ($purchaseReturn->created_by ?? (Auth::check() ? creatorId() : 1));
        $actorId = Auth::id() ?? $purchaseReturn->creator_id ?? $companyId;

        if (DebitNote::withoutGlobalScope('tenant')->where('created_by', $companyId)->where('return_id', $purchaseReturn->id)->exists()) {
            return;
        }

        // Create debit note from purchase return in draft status
        $debitNote = new DebitNote();
        $debitNote->vendor_id = $purchaseReturn->vendor_id;
        $debitNote->invoice_id = $purchaseReturn->original_invoice_id;
        $debitNote->return_id = $purchaseReturn->id;
        $debitNote->reason = 'Purchase return - ' . $purchaseReturn->reason;
        $debitNote->status = 'draft';
        $debitNote->subtotal = $purchaseReturn->subtotal;
        $debitNote->tax_amount = $purchaseReturn->tax_amount;
        $debitNote->discount_amount = $purchaseReturn->discount_amount;
        $debitNote->total_amount = $purchaseReturn->total_amount;
        $debitNote->applied_amount = 0;
        $debitNote->balance_amount = $purchaseReturn->total_amount;
        $debitNote->creator_id = $actorId;
        $debitNote->created_by = $companyId;
        $debitNote->save();

        // Copy items and attach taxes directly to each created item
        foreach ($purchaseReturn->items as $returnItem) {
            $debitItem = $debitNote->items()->create([
                'product_id' => $returnItem->product_id,
                'quantity' => $returnItem->return_quantity,
                'unit_price' => $returnItem->unit_price,
                'discount_percentage' => $returnItem->discount_percentage ?? 0,
                'discount_amount' => $returnItem->discount_amount ?? 0,
                'tax_percentage' => $returnItem->tax_percentage ?? 0,
                'tax_amount' => $returnItem->tax_amount,
                'total_amount' => $returnItem->total_amount,
                'creator_id' => $actorId,
                'created_by' => $companyId
            ]);

            foreach ($returnItem->taxes as $tax) {
                $debitItem->taxes()->create([
                    'tax_name' => $tax->tax_name,
                    'tax_rate' => $tax->tax_rate
                ]);
            }
        }
    }
}
