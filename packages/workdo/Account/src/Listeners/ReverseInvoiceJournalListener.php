<?php

namespace Workdo\Account\Listeners;

use App\Events\DestroyPurchaseInvoice;
use App\Events\DestroySalesInvoice;
use Illuminate\Support\Facades\DB;
use Workdo\Account\Services\JournalService;

class ReverseInvoiceJournalListener
{
    protected $journalService;

    public function __construct(JournalService $journalService)
    {
        $this->journalService = $journalService;
    }

    public function handle($event): void
    {
        if (!Module_is_active('Account')) {
            return;
        }

        DB::transaction(function () use ($event) {
            if ($event instanceof DestroySalesInvoice && !empty($event->salesInvoice)) {
                $invoice = $event->salesInvoice;
                $companyId = (int) ($invoice->created_by ?? creatorId());
                $this->journalService->reverseJournalEntryByReference(
                    ['sales_invoice', 'service_invoice', 'sales_invoice_cogs'],
                    (int) $invoice->id,
                    $companyId,
                    'Sales invoice voided/deleted #' . ($invoice->invoice_number ?? $invoice->id)
                );
            } elseif ($event instanceof DestroyPurchaseInvoice && !empty($event->purchaseInvoice)) {
                $bill = $event->purchaseInvoice;
                $companyId = (int) ($bill->created_by ?? creatorId());
                $this->journalService->reverseJournalEntryByReference(
                    ['purchase_invoice'],
                    (int) $bill->id,
                    $companyId,
                    'Purchase invoice voided/deleted #' . ($bill->invoice_number ?? $bill->id)
                );
            }
        });
    }
}
