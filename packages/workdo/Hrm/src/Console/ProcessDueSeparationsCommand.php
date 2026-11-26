<?php

namespace Workdo\Hrm\Console;

use Illuminate\Console\Command;
use Workdo\Hrm\Services\EmployeeLifecycleService;

class ProcessDueSeparationsCommand extends Command
{
    protected $signature = 'hrm:process-separations';

    protected $description = 'Finalize deactivation for approved resignations and terminations whose separation date has passed.';

    public function handle(EmployeeLifecycleService $lifecycleService): int
    {
        $count = $lifecycleService->processDueSeparations();
        $this->info("Processed {$count} due employee separation(s).");

        return self::SUCCESS;
    }
}
