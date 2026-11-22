<?php

namespace Workdo\ERPMigrate\Services;

use Workdo\ERPMigrate\Models\MigrationBatch;
use Workdo\ERPMigrate\Models\MigrationLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Target Models
use Workdo\Account\Models\Customer;
use Workdo\Account\Models\Vendor;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\Hrm\Models\Employee;

class MigrationRollbackService
{
    /**
     * Rollback all records created by a migration batch
     */
    public function rollback(MigrationBatch $batch): array
    {
        if ($batch->status === 'rolled_back') {
            throw new \Exception("Batch #{$batch->batch_code} has already been rolled back.");
        }

        $companyId = $batch->created_by;
        $createdMap = $batch->created_entity_ids ?? [];
        $deletedTotal = 0;

        DB::beginTransaction();
        try {
            // 1. Rollback Customers
            if (!empty($createdMap['customers']) && class_exists(Customer::class)) {
                $count = Customer::where('created_by', $companyId)
                    ->whereIn('id', $createdMap['customers'])
                    ->delete();
                $deletedTotal += $count;
            }

            // 2. Rollback Vendors
            if (!empty($createdMap['vendors']) && class_exists(Vendor::class)) {
                $count = Vendor::where('created_by', $companyId)
                    ->whereIn('id', $createdMap['vendors'])
                    ->delete();
                $deletedTotal += $count;
            }

            // 3. Rollback Products
            if (!empty($createdMap['products']) && class_exists(ProductServiceItem::class)) {
                $count = ProductServiceItem::where('created_by', $companyId)
                    ->whereIn('id', $createdMap['products'])
                    ->delete();
                $deletedTotal += $count;
            }

            // 4. Rollback Chart of Accounts
            if (!empty($createdMap['chart_of_accounts']) && class_exists(ChartOfAccount::class)) {
                $count = ChartOfAccount::where('created_by', $companyId)
                    ->whereIn('id', $createdMap['chart_of_accounts'])
                    ->delete();
                $deletedTotal += $count;
            }

            // 5. Rollback Employees
            if (!empty($createdMap['employees']) && class_exists(Employee::class)) {
                $count = Employee::where('created_by', $companyId)
                    ->whereIn('id', $createdMap['employees'])
                    ->delete();
                $deletedTotal += $count;
            }

            // 6. Update logs status to rolled_back
            MigrationLog::where('batch_id', $batch->id)
                ->where('created_by', $companyId)
                ->update(['status' => 'rolled_back']);

            // 7. Update batch status
            $batch->status = 'rolled_back';
            $batch->save();

            DB::commit();

            return [
                'success' => true,
                'batch_code' => $batch->batch_code,
                'deleted_count' => $deletedTotal,
                'message' => "Successfully rolled back batch {$batch->batch_code} ({$deletedTotal} records removed).",
            ];

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Rollback failed for batch #{$batch->id}: " . $e->getMessage());
            throw new \Exception("Rollback transaction failed: " . $e->getMessage());
        }
    }
}
