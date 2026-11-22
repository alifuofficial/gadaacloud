<?php

namespace Workdo\EIMS\Console;

use Illuminate\Console\Command;
use Workdo\EIMS\Services\EimsOfflineResiliencyService;

class FlushOfflineQueueCommand extends Command
{
    protected $signature = 'eims:flush-queue {--tenant= : Specific tenant company ID} {--limit=50 : Max items to process}';

    protected $description = 'Flush and synchronize offline EIMS invoice queue with MoR within Directive 1142 72-hour window';

    public function handle(EimsOfflineResiliencyService $service): int
    {
        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;
        $limit = (int) $this->option('limit');

        $this->info("Starting EIMS offline queue flush (Directive 1142/2026 Resiliency)...");

        $results = $service->flushQueue($tenantId, $limit);

        $this->table(
            ['Total Processed', 'Synced to MoR', 'Retried / Failed', 'Expired (>72h)'],
            [[$results['total'], $results['synced'], $results['failed'], $results['expired']]]
        );

        $this->info("EIMS queue flush completed.");
        return self::SUCCESS;
    }
}
