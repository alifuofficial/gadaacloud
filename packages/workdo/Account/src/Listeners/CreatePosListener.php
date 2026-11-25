<?php

namespace Workdo\Account\Listeners;

use Illuminate\Support\Facades\DB;
use Workdo\Account\Models\BankAccount;
use Workdo\Pos\Events\CreatePos;
use Workdo\Account\Services\JournalService;
use Workdo\Account\Services\BankTransactionsService;

class CreatePosListener
{
    protected $journalService;
    protected $bankTransactionsService;

    public function __construct(JournalService $journalService, BankTransactionsService $bankTransactionsService)
    {
        $this->journalService = $journalService;
        $this->bankTransactionsService = $bankTransactionsService;
    }

    public function handle(CreatePos $event)
    {
        if (Module_is_active('Account')) {
            DB::transaction(function () use ($event) {
                $companyId = (int) ($event->posSale->created_by ?? creatorId());
                $bankAccount = BankAccount::withoutGlobalScope('tenant')
                    ->where('id', $event->posSale->bank_account_id)
                    ->where('created_by', $companyId)
                    ->first();

                if ($bankAccount) {
                    $this->bankTransactionsService->createPosPayment($event->posSale, $bankAccount->id);
                }

                $this->journalService->createPosJournal($event->posSale);
                $this->journalService->createPosCOGSJournal($event->posSale);
            });
        }
    }
}
