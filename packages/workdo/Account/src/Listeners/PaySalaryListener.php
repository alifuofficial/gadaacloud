<?php

namespace Workdo\Account\Listeners;

use Illuminate\Support\Facades\DB;
use Workdo\Hrm\Events\PaySalary;
use Workdo\Account\Services\JournalService;
use Workdo\Account\Services\BankTransactionsService;

class PaySalaryListener
{
    protected $journalService;
    protected $bankTransactionsService;

    public function __construct(JournalService $journalService, BankTransactionsService $bankTransactionsService)
    {
        $this->journalService = $journalService;
        $this->bankTransactionsService = $bankTransactionsService;
    }

    public function handle(PaySalary $event)
    {
        if (Module_is_active('Account')) {
            DB::transaction(function () use ($event) {
                $this->journalService->createPayrollJournal($event->payrollEntry);
                $this->bankTransactionsService->createPayrollPayment($event->payrollEntry);
            });
        }
    }
}

