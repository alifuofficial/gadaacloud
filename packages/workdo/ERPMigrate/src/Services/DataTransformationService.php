<?php

namespace Workdo\ERPMigrate\Services;

use Workdo\ERPMigrate\Models\MigrationBatch;
use Workdo\ERPMigrate\Models\MigrationLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

// Target Models
use Workdo\Account\Models\Customer;
use Workdo\Account\Models\Vendor;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\Hrm\Models\Employee;

class DataTransformationService
{
    /**
     * Perform dry-run validation against target GadaaCloud schemas and Ethiopian statutory rules
     */
    public function validateDryRun(MigrationBatch $batch, array $sourceRows): array
    {
        $companyId = $batch->created_by;
        $mappings = $batch->field_mappings ?? [];
        $entityType = $batch->entity_type;

        $issues = [];
        $validCount = 0;
        $warningCount = 0;
        $errorCount = 0;

        foreach ($sourceRows as $idx => $row) {
            $rowNum = $idx + 1;
            $rowErrors = [];
            $rowWarnings = [];
            $identifier = "Row #{$rowNum}";

            if ($entityType === 'customers' || $entityType === 'vendors') {
                $nameCol = $mappings['name'] ?? null;
                $tinCol = $mappings['tin_number'] ?? null;
                $emailCol = $mappings['email'] ?? null;

                $name = $nameCol && isset($row[$nameCol]) ? trim((string)$row[$nameCol]) : '';
                $tin = $tinCol && isset($row[$tinCol]) ? trim((string)$row[$tinCol]) : '';
                $email = $emailCol && isset($row[$emailCol]) ? trim((string)$row[$emailCol]) : '';

                if (!empty($name)) {
                    $identifier = $name;
                }

                // Validation 1: Required Name
                if (empty($name)) {
                    $rowErrors[] = "Missing required company or contact name.";
                }

                // Validation 2: Ethiopian TIN Format (10 numeric digits)
                if (!empty($tin)) {
                    // Strip hyphens or spaces
                    $cleanTin = preg_replace('/[^0-9]/', '', $tin);
                    if (strlen($cleanTin) !== 10) {
                        $rowWarnings[] = "TIN '{$tin}' does not conform to the Ethiopian 10-digit tax format.";
                    }

                    // Check duplicate TIN in database
                    $existsTin = false;
                    if ($entityType === 'customers' && class_exists(Customer::class)) {
                        $existsTin = Customer::where('created_by', $companyId)->where('tax_number', $cleanTin)->exists();
                    } elseif ($entityType === 'vendors' && class_exists(Vendor::class)) {
                        $existsTin = Vendor::where('created_by', $companyId)->where('tax_number', $cleanTin)->exists();
                    }
                    if ($existsTin) {
                        $rowWarnings[] = "TIN '{$cleanTin}' already exists in your records. Duplicate may be created.";
                    }
                }

                // Validation 3: Email format
                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $rowWarnings[] = "Invalid email address format: '{$email}'.";
                }
            } elseif ($entityType === 'products') {
                $nameCol = $mappings['name'] ?? null;
                $skuCol = $mappings['sku'] ?? null;
                $priceCol = $mappings['sale_price'] ?? null;

                $name = $nameCol && isset($row[$nameCol]) ? trim((string)$row[$nameCol]) : '';
                $sku = $skuCol && isset($row[$skuCol]) ? trim((string)$row[$skuCol]) : '';
                $price = $priceCol && isset($row[$priceCol]) ? $row[$priceCol] : null;

                if (!empty($name)) {
                    $identifier = "SKU: {$sku} ({$name})";
                }

                // Required Name & SKU
                if (empty($name)) {
                    $rowErrors[] = "Product name is required.";
                }
                if (empty($sku)) {
                    $rowErrors[] = "Product SKU / Item Code is required.";
                } else {
                    // Check duplicate SKU
                    if (class_exists(ProductServiceItem::class)) {
                        if (ProductServiceItem::where('created_by', $companyId)->where('sku', $sku)->exists()) {
                            $rowErrors[] = "SKU '{$sku}' already exists in your product inventory.";
                        }
                    }
                }

                // Price numeric check
                if ($price !== null && !is_numeric($price)) {
                    $rowWarnings[] = "Sale price '{$price}' is non-numeric, defaulting to 0.";
                }
            } elseif ($entityType === 'chart_of_accounts') {
                $codeCol = $mappings['code'] ?? null;
                $nameCol = $mappings['name'] ?? null;

                $code = $codeCol && isset($row[$codeCol]) ? trim((string)$row[$codeCol]) : '';
                $name = $nameCol && isset($row[$nameCol]) ? trim((string)$row[$nameCol]) : '';

                if (!empty($code)) {
                    $identifier = "Account: {$code} - {$name}";
                }

                if (empty($code)) {
                    $rowErrors[] = "Account code is required.";
                } else {
                    if (class_exists(ChartOfAccount::class)) {
                        if (ChartOfAccount::where('created_by', $companyId)->where('account_code', $code)->exists()) {
                            $rowWarnings[] = "Account code '{$code}' already exists. Migration will skip or merge.";
                        }
                    }
                }
                if (empty($name)) {
                    $rowErrors[] = "Account name is required.";
                }
            } elseif ($entityType === 'employees') {
                $nameCol = $mappings['name'] ?? null;
                $name = $nameCol && isset($row[$nameCol]) ? trim((string)$row[$nameCol]) : '';

                if (!empty($name)) {
                    $identifier = $name;
                }

                if (empty($name)) {
                    $rowErrors[] = "Employee full name is required.";
                }
            }

            if (!empty($rowErrors)) {
                $errorCount++;
                foreach ($rowErrors as $err) {
                    $issues[] = ['row' => $rowNum, 'identifier' => $identifier, 'type' => 'error', 'message' => $err];
                }
            } elseif (!empty($rowWarnings)) {
                $warningCount++;
                $validCount++;
                foreach ($rowWarnings as $warn) {
                    $issues[] = ['row' => $rowNum, 'identifier' => $identifier, 'type' => 'warning', 'message' => $warn];
                }
            } else {
                $validCount++;
            }
        }

        $summary = [
            'total_rows' => count($sourceRows),
            'valid_rows' => $validCount,
            'warning_rows' => $warningCount,
            'error_rows' => $errorCount,
            'can_proceed' => ($errorCount === 0),
            'issues' => array_slice($issues, 0, 50), // Return first 50 issues
        ];

        $batch->validation_summary = $summary;
        $batch->status = 'ready';
        $batch->save();

        return $summary;
    }

    /**
     * Execute transactional migration and store created record IDs for rollback
     */
    public function executeMigration(MigrationBatch $batch, array $sourceRows, bool $skipErrors = true): array
    {
        $companyId = $batch->created_by;
        $creatorId = $batch->creator_id ?: (Auth::id() ?: 1);
        $mappings = $batch->field_mappings ?? [];
        $entityType = $batch->entity_type;

        $createdEntityIds = [];
        $importedCount = 0;
        $failedCount = 0;
        $skippedCount = 0;

        DB::beginTransaction();
        try {
            $batch->status = 'importing';
            $batch->save();

            foreach ($sourceRows as $idx => $row) {
                $rowNum = $idx + 1;
                $rowErrors = [];

                if ($entityType === 'customers' && class_exists(Customer::class)) {
                    $name = trim((string)($row[$mappings['name'] ?? ''] ?? ''));
                    $tin = trim((string)($row[$mappings['tin_number'] ?? ''] ?? ''));
                    $email = trim((string)($row[$mappings['email'] ?? ''] ?? ''));
                    $phone = trim((string)($row[$mappings['phone'] ?? ''] ?? ''));
                    $address = trim((string)($row[$mappings['address'] ?? ''] ?? ''));
                    $city = trim((string)($row[$mappings['city'] ?? ''] ?? ''));

                    if (empty($name)) {
                        if ($skipErrors) {
                            $skippedCount++;
                            $this->logRow($batch, $rowNum, 'Customer', 'skipped', 'Skipped: Name was empty.', $row);
                            continue;
                        }
                        throw new \Exception("Row #{$rowNum}: Name is required.");
                    }

                    $customer = new Customer();
                    $customer->created_by = $companyId;
                    $customer->creator_id = $creatorId;
                    $customer->company_name = $name;
                    $customer->contact_person_name = $name;
                    $customer->contact_person_email = $email;
                    $customer->contact_person_mobile = $phone;
                    $customer->tax_number = preg_replace('/[^0-9]/', '', $tin);
                    $customer->billing_address = ['address' => $address, 'city' => $city, 'country' => 'Ethiopia'];
                    $customer->shipping_address = ['address' => $address, 'city' => $city, 'country' => 'Ethiopia'];
                    $customer->save();

                    $createdEntityIds['customers'][] = $customer->id;
                    $importedCount++;
                    $this->logRow($batch, $rowNum, 'Customer', 'success', "Customer '{$name}' created.", $row, 'Customer', $customer->id);

                } elseif ($entityType === 'vendors' && class_exists(Vendor::class)) {
                    $name = trim((string)($row[$mappings['name'] ?? ''] ?? ''));
                    $tin = trim((string)($row[$mappings['tin_number'] ?? ''] ?? ''));
                    $email = trim((string)($row[$mappings['email'] ?? ''] ?? ''));
                    $phone = trim((string)($row[$mappings['phone'] ?? ''] ?? ''));
                    $address = trim((string)($row[$mappings['address'] ?? ''] ?? ''));
                    $city = trim((string)($row[$mappings['city'] ?? ''] ?? ''));

                    if (empty($name)) {
                        if ($skipErrors) {
                            $skippedCount++;
                            $this->logRow($batch, $rowNum, 'Vendor', 'skipped', 'Skipped: Name was empty.', $row);
                            continue;
                        }
                        throw new \Exception("Row #{$rowNum}: Name is required.");
                    }

                    $vendor = new Vendor();
                    $vendor->created_by = $companyId;
                    $vendor->creator_id = $creatorId;
                    $vendor->company_name = $name;
                    $vendor->contact_person_name = $name;
                    $vendor->primary_email = $email;
                    $vendor->primary_mobile = $phone;
                    $vendor->tax_number = preg_replace('/[^0-9]/', '', $tin);
                    $vendor->currency_code = 'ETB';
                    $vendor->billing_address = ['address' => $address, 'city' => $city, 'country' => 'Ethiopia'];
                    $vendor->save();

                    $createdEntityIds['vendors'][] = $vendor->id;
                    $importedCount++;
                    $this->logRow($batch, $rowNum, 'Vendor', 'success', "Vendor '{$name}' created.", $row, 'Vendor', $vendor->id);

                } elseif ($entityType === 'products' && class_exists(ProductServiceItem::class)) {
                    $name = trim((string)($row[$mappings['name'] ?? ''] ?? ''));
                    $sku = trim((string)($row[$mappings['sku'] ?? ''] ?? ''));
                    $salePrice = floatval($row[$mappings['sale_price'] ?? ''] ?? 0);
                    $purchasePrice = floatval($row[$mappings['purchase_price'] ?? ''] ?? 0);
                    $unit = trim((string)($row[$mappings['unit'] ?? ''] ?? 'Pcs'));

                    if (empty($name) || empty($sku)) {
                        if ($skipErrors) {
                            $skippedCount++;
                            $this->logRow($batch, $rowNum, 'Product', 'skipped', 'Skipped: Name or SKU was empty.', $row);
                            continue;
                        }
                        throw new \Exception("Row #{$rowNum}: Name and SKU are required.");
                    }

                    // Avoid duplicate SKU
                    if (ProductServiceItem::where('created_by', $companyId)->where('sku', $sku)->exists()) {
                        $sku = $sku . '-' . rand(100, 999);
                    }

                    $item = new ProductServiceItem();
                    $item->created_by = $companyId;
                    $item->creator_id = $creatorId;
                    $item->name = $name;
                    $item->sku = $sku;
                    $item->sale_price = $salePrice;
                    $item->purchase_price = $purchasePrice;
                    $item->unit = $unit ?: 'Pcs';
                    $item->type = 'product';
                    $item->inventory_type = 'item';
                    $item->is_active = true;
                    $item->save();

                    $createdEntityIds['products'][] = $item->id;
                    $importedCount++;
                    $this->logRow($batch, $rowNum, 'Product', 'success', "Product '{$name}' (SKU: {$sku}) created.", $row, 'ProductServiceItem', $item->id);

                } elseif ($entityType === 'chart_of_accounts' && class_exists(ChartOfAccount::class)) {
                    $code = trim((string)($row[$mappings['code'] ?? ''] ?? ''));
                    $name = trim((string)($row[$mappings['name'] ?? ''] ?? ''));
                    $typeStr = strtolower(trim((string)($row[$mappings['type'] ?? ''] ?? 'asset')));

                    if (empty($code) || empty($name)) {
                        if ($skipErrors) {
                            $skippedCount++;
                            $this->logRow($batch, $rowNum, 'Account', 'skipped', 'Skipped: Code or Name was empty.', $row);
                            continue;
                        }
                        throw new \Exception("Row #{$rowNum}: Code and Name are required.");
                    }

                    // Check if already exists
                    $existing = ChartOfAccount::where('created_by', $companyId)->where('account_code', $code)->first();
                    if ($existing) {
                        $skippedCount++;
                        $this->logRow($batch, $rowNum, 'Account', 'skipped', "Account code '{$code}' already exists.", $row);
                        continue;
                    }

                    $account = new ChartOfAccount();
                    $account->created_by = $companyId;
                    $account->creator_id = $creatorId;
                    $account->account_code = $code;
                    $account->account_name = $name;
                    $account->is_active = true;
                    $account->normal_balance = str_contains($typeStr, 'asset') || str_contains($typeStr, 'expense') ? 'debit' : 'credit';
                    $account->save();

                    $createdEntityIds['chart_of_accounts'][] = $account->id;
                    $importedCount++;
                    $this->logRow($batch, $rowNum, 'Account', 'success', "Account '{$code} - {$name}' created.", $row, 'ChartOfAccount', $account->id);

                } elseif ($entityType === 'employees' && class_exists(Employee::class)) {
                    $name = trim((string)($row[$mappings['name'] ?? ''] ?? ''));
                    $email = trim((string)($row[$mappings['email'] ?? ''] ?? ''));
                    $phone = trim((string)($row[$mappings['phone'] ?? ''] ?? ''));

                    if (empty($name)) {
                        if ($skipErrors) {
                            $skippedCount++;
                            $this->logRow($batch, $rowNum, 'Employee', 'skipped', 'Skipped: Name was empty.', $row);
                            continue;
                        }
                        throw new \Exception("Row #{$rowNum}: Employee name is required.");
                    }

                    $emp = new Employee();
                    $emp->created_by = $companyId;
                    $emp->creator_id = $creatorId;
                    $emp->name = $name;
                    $emp->email = $email;
                    $emp->phone = $phone;
                    $emp->is_active = 1;
                    $emp->save();

                    $createdEntityIds['employees'][] = $emp->id;
                    $importedCount++;
                    $this->logRow($batch, $rowNum, 'Employee', 'success', "Employee '{$name}' created.", $row, 'Employee', $emp->id);
                } else {
                    $skippedCount++;
                    $this->logRow($batch, $rowNum, $entityType, 'skipped', "Target module model not installed for '{$entityType}'.", $row);
                }
            }

            $batch->imported_count = $importedCount;
            $batch->skipped_count = $skippedCount;
            $batch->failed_count = $failedCount;
            $batch->created_entity_ids = $createdEntityIds;
            $batch->status = 'completed';
            $batch->completed_at = now();
            $batch->save();

            DB::commit();

            return [
                'success' => true,
                'imported_count' => $importedCount,
                'skipped_count' => $skippedCount,
                'failed_count' => $failedCount,
                'created_records' => $createdEntityIds,
            ];

        } catch (\Throwable $e) {
            DB::rollBack();
            $batch->status = 'failed';
            $batch->save();
            Log::error("Migration batch #{$batch->id} execution failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Helper to write a granular log entry for a row
     */
    protected function logRow(MigrationBatch $batch, int $rowIndex, string $identifier, string $status, string $message, array $raw, ?string $targetModel = null, ?int $targetId = null): void
    {
        MigrationLog::create([
            'batch_id' => $batch->id,
            'row_index' => $rowIndex,
            'record_identifier' => $identifier,
            'status' => $status,
            'target_model' => $targetModel,
            'target_id' => $targetId,
            'message' => $message,
            'raw_data' => $raw,
            'created_by' => $batch->created_by,
        ]);
    }
}
