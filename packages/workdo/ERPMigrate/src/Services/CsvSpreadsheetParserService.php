<?php

namespace Workdo\ERPMigrate\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use Illuminate\Support\Facades\Log;

class CsvSpreadsheetParserService
{
    /**
     * Inspect file and return headers, first 5 sample rows, and total row count
     */
    public function inspectFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found at path: {$filePath}");
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (in_array($extension, ['xlsx', 'xls', 'ods'])) {
            return $this->inspectSpreadsheet($filePath);
        }

        return $this->inspectCsv($filePath);
    }

    /**
     * Inspect CSV/TSV file with delimiter detection
     */
    protected function inspectCsv(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \Exception("Unable to read CSV file: {$filePath}");
        }

        // Detect delimiter: check first line with common delimiters
        $firstLine = fgets($handle);
        rewind($handle);

        $delimiter = $this->detectDelimiter($firstLine);

        // Read header
        $headers = fgetcsv($handle, 0, $delimiter);
        if (!$headers) {
            fclose($handle);
            throw new \Exception("Failed to read CSV header row.");
        }

        // Clean UTF-8 BOM from first header
        $headers[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $headers[0]);
        $headers = array_map('trim', $headers);

        $samples = [];
        $rowCount = 0;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            if (count($samples) < 5) {
                $assoc = [];
                foreach ($headers as $idx => $hdr) {
                    $assoc[$hdr] = $row[$idx] ?? '';
                }
                $samples[] = $assoc;
            }
            $rowCount++;
        }

        fclose($handle);

        return [
            'type' => 'csv',
            'delimiter' => $delimiter,
            'headers' => $headers,
            'sample_rows' => $samples,
            'total_rows' => $rowCount,
        ];
    }

    /**
     * Inspect Excel (.xlsx, .xls) file using PhpOffice\PhpSpreadsheet
     */
    protected function inspectSpreadsheet(string $filePath): array
    {
        try {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();

            $rows = $worksheet->toArray(null, true, true, false);

            if (empty($rows)) {
                throw new \Exception("Spreadsheet contains no data rows.");
            }

            $headers = array_map(function ($val) {
                return trim((string)$val);
            }, array_filter($rows[0] ?? []));

            $headers = array_values($headers);
            $samples = [];
            $totalRows = 0;

            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                if (empty(array_filter($row))) {
                    continue;
                }

                if (count($samples) < 5) {
                    $assoc = [];
                    foreach ($headers as $idx => $hdr) {
                        $assoc[$hdr] = (string)($row[$idx] ?? '');
                    }
                    $samples[] = $assoc;
                }
                $totalRows++;
            }

            return [
                'type' => 'excel',
                'headers' => $headers,
                'sample_rows' => $samples,
                'total_rows' => $totalRows,
            ];

        } catch (\Throwable $e) {
            Log::error("Spreadsheet inspection error: " . $e->getMessage());
            throw new \Exception("Error parsing Excel file: " . $e->getMessage());
        }
    }

    /**
     * Stream rows in batches with offset and limit
     */
    public function getRows(string $filePath, int $offset = 0, int $limit = 500): array
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (in_array($extension, ['xlsx', 'xls', 'ods'])) {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray(null, true, true, false);

            $headers = array_map('trim', array_filter($rows[0] ?? []));
            $headers = array_values($headers);

            $data = [];
            $currentIndex = 0;

            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                if (empty(array_filter($row))) continue;

                if ($currentIndex >= $offset && count($data) < $limit) {
                    $assoc = [];
                    foreach ($headers as $idx => $hdr) {
                        $assoc[$hdr] = (string)($row[$idx] ?? '');
                    }
                    $data[] = $assoc;
                }
                $currentIndex++;
                if (count($data) >= $limit) break;
            }

            return $data;
        }

        // CSV streaming
        $handle = fopen($filePath, 'r');
        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = $this->detectDelimiter($firstLine);

        $headers = fgetcsv($handle, 0, $delimiter);
        $headers[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $headers[0]);
        $headers = array_map('trim', $headers);

        $data = [];
        $currentIndex = 0;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (empty(array_filter($row))) continue;

            if ($currentIndex >= $offset && count($data) < $limit) {
                $assoc = [];
                foreach ($headers as $idx => $hdr) {
                    $assoc[$hdr] = $row[$idx] ?? '';
                }
                $data[] = $assoc;
            }
            $currentIndex++;
            if (count($data) >= $limit) break;
        }

        fclose($handle);
        return $data;
    }

    /**
     * Automatically suggest field mappings by comparing source headers to target fields
     */
    public function suggestFieldMappings(array $headers, string $entityType): array
    {
        $targetFields = $this->getTargetFieldsForEntity($entityType);
        $suggestions = [];

        foreach ($targetFields as $field => $meta) {
            $bestMatch = null;
            $bestScore = 0;

            foreach ($headers as $hdr) {
                $hdrLower = strtolower(trim($hdr));
                $aliasMatches = false;

                // 1. Direct synonym match
                foreach ($meta['synonyms'] as $syn) {
                    if ($hdrLower === strtolower($syn) || str_contains($hdrLower, strtolower($syn))) {
                        $bestMatch = $hdr;
                        $aliasMatches = true;
                        break;
                    }
                }

                if ($aliasMatches) break;

                // 2. Levenshtein / similar_text match
                similar_text($hdrLower, strtolower($field), $percent);
                if ($percent > $bestScore && $percent > 70) {
                    $bestScore = $percent;
                    $bestMatch = $hdr;
                }
            }

            $suggestions[$field] = $bestMatch;
        }

        return $suggestions;
    }

    /**
     * Standard dictionary of target fields, labels, and common ERP synonyms
     */
    public function getTargetFieldsForEntity(string $entityType): array
    {
        if ($entityType === 'customers' || $entityType === 'vendors') {
            return [
                'name' => ['label' => 'Company / Person Name', 'required' => true, 'synonyms' => ['partner_name', 'customer_name', 'supplier_name', 'client', 'name', 'company_name']],
                'tin_number' => ['label' => 'Tax Identification Number (TIN)', 'required' => false, 'synonyms' => ['tin', 'vat', 'tax_id', 'vat_number', 'tin_number', 'tax_number']],
                'email' => ['label' => 'Email Address', 'required' => false, 'synonyms' => ['email', 'email_id', 'work_email', 'contact_email', 'mail']],
                'phone' => ['label' => 'Phone / Mobile', 'required' => false, 'synonyms' => ['phone', 'mobile', 'mobile_no', 'telephone', 'cell']],
                'address' => ['label' => 'Street Address', 'required' => false, 'synonyms' => ['street', 'address', 'address_line_1', 'location']],
                'city' => ['label' => 'City', 'required' => false, 'synonyms' => ['city', 'town', 'region']],
            ];
        }

        if ($entityType === 'products') {
            return [
                'name' => ['label' => 'Product Name', 'required' => true, 'synonyms' => ['item_name', 'product_name', 'description', 'title', 'name']],
                'sku' => ['label' => 'SKU / Item Code', 'required' => true, 'synonyms' => ['item_code', 'default_code', 'sku', 'code', 'part_number']],
                'barcode' => ['label' => 'Barcode / EAN / UPC', 'required' => false, 'synonyms' => ['barcode', 'upc', 'ean', 'isbn']],
                'sale_price' => ['label' => 'Sale Price', 'required' => true, 'synonyms' => ['list_price', 'standard_rate', 'sale_price', 'price', 'unit_price', 'selling_price']],
                'purchase_price' => ['label' => 'Purchase / Cost Price', 'required' => false, 'synonyms' => ['standard_price', 'valuation_rate', 'cost_price', 'cost', 'purchase_price']],
                'category' => ['label' => 'Category', 'required' => false, 'synonyms' => ['categ_id', 'item_group', 'category', 'group', 'type']],
                'unit' => ['label' => 'Unit of Measure', 'required' => false, 'synonyms' => ['uom_id', 'stock_uom', 'unit', 'uom']],
                'opening_stock' => ['label' => 'Opening Stock Quantity', 'required' => false, 'synonyms' => ['quantity', 'qty', 'opening_stock', 'stock_qty', 'balance_qty']],
            ];
        }

        if ($entityType === 'chart_of_accounts') {
            return [
                'code' => ['label' => 'Account Code', 'required' => true, 'synonyms' => ['account_number', 'code', 'account_code', 'number']],
                'name' => ['label' => 'Account Name', 'required' => true, 'synonyms' => ['account_name', 'name', 'title']],
                'type' => ['label' => 'Account Type (Asset/Liability/Equity/Revenue/Expense)', 'required' => true, 'synonyms' => ['account_type', 'root_type', 'type', 'internal_group']],
                'parent_code' => ['label' => 'Parent Account Code', 'required' => false, 'synonyms' => ['parent_account', 'parent_code', 'parent']],
            ];
        }

        if ($entityType === 'employees') {
            return [
                'name' => ['label' => 'Full Employee Name', 'required' => true, 'synonyms' => ['employee_name', 'name', 'full_name']],
                'email' => ['label' => 'Work / Personal Email', 'required' => false, 'synonyms' => ['work_email', 'company_email', 'email', 'personal_email']],
                'phone' => ['label' => 'Mobile Phone Number', 'required' => false, 'synonyms' => ['work_phone', 'cell_number', 'mobile_no', 'phone']],
                'department' => ['label' => 'Department', 'required' => false, 'synonyms' => ['department_id', 'department', 'unit']],
                'designation' => ['label' => 'Job Title / Designation', 'required' => false, 'synonyms' => ['job_title', 'designation', 'position', 'role']],
                'national_id' => ['label' => 'National ID / Pension Number', 'required' => false, 'synonyms' => ['identification_id', 'national_id', 'pension_no', 'tin']],
            ];
        }

        return [];
    }

    /**
     * Detect CSV Delimiter
     */
    protected function detectDelimiter(string $line): string
    {
        $delimiters = [',', ';', "\t", '|'];
        $bestDelimiter = ',';
        $maxCount = 0;

        foreach ($delimiters as $delim) {
            $count = substr_count($line, $delim);
            if ($count > $maxCount) {
                $maxCount = $count;
                $bestDelimiter = $delim;
            }
        }

        return $bestDelimiter;
    }
}
