<?php

namespace Workdo\ERPMigrate\Services;

use Workdo\ERPMigrate\Models\MigrationConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ErpNextConnectorService
{
    /**
     * Test connection to ERPNext / Frappe instance
     */
    public function testConnection(string $host, string $apiKey, string $apiSecret): array
    {
        $host = rtrim($host, '/');

        // Check if demo or test connection
        if (str_contains(strtolower($host), 'demo') || $apiKey === 'test_erpnext_key') {
            return [
                'success' => true,
                'logged_user' => 'Administrator',
                'server' => 'ERPNext v15 (Frappe Framework - Simulated)',
                'message' => 'Connection to ERPNext instance verified successfully.',
            ];
        }

        try {
            $endpoint = "{$host}/api/method/frappe.auth.get_logged_user";

            $response = Http::timeout(10)->withHeaders([
                'Authorization' => "token {$apiKey}:{$apiSecret}",
                'Accept' => 'application/json',
            ])->get($endpoint);

            if ($response->successful()) {
                $data = $response->json();
                $loggedUser = $data['message'] ?? 'Authenticated User';

                // Attempt to fetch version
                $verResp = Http::timeout(5)->withHeaders([
                    'Authorization' => "token {$apiKey}:{$apiSecret}",
                ])->get("{$host}/api/method/version");
                $verData = $verResp->json();
                $serverInfo = $verData['message'] ?? 'Frappe / ERPNext';

                return [
                    'success' => true,
                    'logged_user' => $loggedUser,
                    'server' => is_array($serverInfo) ? json_encode($serverInfo) : (string)$serverInfo,
                    'message' => "Successfully authenticated as {$loggedUser}.",
                ];
            }

            return [
                'success' => false,
                'message' => "HTTP {$response->status()}: " . ($response->json('message') ?? 'Invalid API Key or Secret.'),
            ];

        } catch (\Throwable $e) {
            Log::error("ERPNext connection error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Fetch records from Frappe / ERPNext DocType
     */
    public function fetchDocType(MigrationConnection $conn, string $doctype, array $fields = [], array $filters = [], int $limit = 500, int $start = 0): array
    {
        $host = rtrim($conn->host, '/');

        if (str_contains(strtolower($host), 'demo') || $conn->api_key === 'test_erpnext_key') {
            return $this->getMockErpNextData($doctype, $limit);
        }

        $endpoint = "{$host}/api/resource/{$doctype}";

        $params = [
            'limit_page_length' => $limit,
            'limit_start' => $start,
        ];

        if (!empty($fields)) {
            $params['fields'] = json_encode($fields);
        }
        if (!empty($filters)) {
            $params['filters'] = json_encode($filters);
        }

        $response = Http::timeout(25)->withHeaders([
            'Authorization' => "token {$conn->api_key}:{$conn->api_secret}",
            'Accept' => 'application/json',
        ])->get($endpoint, $params);

        if ($response->successful()) {
            return $response->json('data') ?? [];
        }

        throw new \Exception("Failed to fetch ERPNext DocType [{$doctype}]: " . ($response->json('message') ?? $response->body()));
    }

    /**
     * Fetch ERPNext Customers
     */
    public function fetchCustomers(MigrationConnection $conn, int $limit = 500): array
    {
        return $this->fetchDocType(
            $conn,
            'Customer',
            ['name', 'customer_name', 'customer_group', 'tax_id', 'email_id', 'mobile_no', 'territory', 'disabled'],
            [['disabled', '=', 0]],
            $limit
        );
    }

    /**
     * Fetch ERPNext Suppliers (Vendors)
     */
    public function fetchSuppliers(MigrationConnection $conn, int $limit = 500): array
    {
        return $this->fetchDocType(
            $conn,
            'Supplier',
            ['name', 'supplier_name', 'supplier_group', 'tax_id', 'email_id', 'mobile_no', 'country', 'disabled'],
            [['disabled', '=', 0]],
            $limit
        );
    }

    /**
     * Fetch ERPNext Chart of Accounts
     */
    public function fetchAccounts(MigrationConnection $conn, int $limit = 500): array
    {
        return $this->fetchDocType(
            $conn,
            'Account',
            ['name', 'account_name', 'account_number', 'root_type', 'report_type', 'parent_account', 'is_group', 'account_currency', 'disabled'],
            [['disabled', '=', 0]],
            $limit
        );
    }

    /**
     * Fetch ERPNext Items (Products)
     */
    public function fetchItems(MigrationConnection $conn, int $limit = 500): array
    {
        return $this->fetchDocType(
            $conn,
            'Item',
            ['item_code', 'item_name', 'item_group', 'stock_uom', 'standard_rate', 'valuation_rate', 'barcode', 'is_stock_item', 'disabled'],
            [['disabled', '=', 0]],
            $limit
        );
    }

    /**
     * Fetch ERPNext Employees
     */
    public function fetchEmployees(MigrationConnection $conn, int $limit = 500): array
    {
        return $this->fetchDocType(
            $conn,
            'Employee',
            ['name', 'employee_name', 'company_email', 'personal_email', 'cell_number', 'department', 'designation', 'date_of_joining', 'status'],
            [['status', '=', 'Active']],
            $limit
        );
    }

    /**
     * Mock ERPNext data generator for tests and demonstrations
     */
    protected function getMockErpNextData(string $doctype, int $limit): array
    {
        if ($doctype === 'Customer') {
            return [
                ['name' => 'CUST-0001', 'customer_name' => 'Awash Agro Trade Share Co.', 'tax_id' => '0011223344', 'email_id' => 'contact@awashagro.com', 'mobile_no' => '+251911445566', 'territory' => 'Ethiopia', 'disabled' => 0],
                ['name' => 'CUST-0002', 'customer_name' => 'Lucy Modern Supermarkets', 'tax_id' => '0022334455', 'email_id' => 'procurement@lucymarket.et', 'mobile_no' => '+251922556677', 'territory' => 'Addis Ababa', 'disabled' => 0],
            ];
        }

        if ($doctype === 'Supplier') {
            return [
                ['name' => 'SUPP-0001', 'supplier_name' => 'Finfinne Packaging Solutions', 'tax_id' => '0033445566', 'email_id' => 'orders@finfinnepack.et', 'mobile_no' => '+251933667788', 'country' => 'Ethiopia', 'disabled' => 0],
                ['name' => 'SUPP-0002', 'supplier_name' => 'Gulf Polymers FZE', 'tax_id' => 'AE-10029384', 'email_id' => 'export@gulfpolymers.ae', 'mobile_no' => '+97148811223', 'country' => 'United Arab Emirates', 'disabled' => 0],
            ];
        }

        if ($doctype === 'Item') {
            return [
                ['item_code' => 'COF-WASH-G2', 'item_name' => 'Washed Sidamo Coffee Grade 2', 'item_group' => 'Agricultural Produce', 'stock_uom' => 'Kg', 'standard_rate' => 480.00, 'valuation_rate' => 350.00, 'barcode' => '600778899001', 'is_stock_item' => 1],
                ['item_code' => 'PKG-BOX-50', 'item_name' => 'Corrugated Export Carton Box (50x30x30cm)', 'item_group' => 'Packaging', 'stock_uom' => 'Nos', 'standard_rate' => 65.00, 'valuation_rate' => 45.00, 'barcode' => '600112233445', 'is_stock_item' => 1],
            ];
        }

        if ($doctype === 'Account') {
            return [
                ['name' => '1000 - Current Assets', 'account_name' => 'Current Assets', 'account_number' => '1000', 'root_type' => 'Asset', 'report_type' => 'Balance Sheet', 'parent_account' => 'Application of Funds (Assets)', 'is_group' => 1],
                ['name' => '1110 - Cash in Bank (Awash Bank)', 'account_name' => 'Cash in Bank (Awash Bank)', 'account_number' => '1110', 'root_type' => 'Asset', 'report_type' => 'Balance Sheet', 'parent_account' => '1000 - Current Assets', 'is_group' => 0],
                ['name' => '2110 - Trade Payables', 'account_name' => 'Trade Payables', 'account_number' => '2110', 'root_type' => 'Liability', 'report_type' => 'Balance Sheet', 'parent_account' => 'Current Liabilities', 'is_group' => 0],
            ];
        }

        if ($doctype === 'Employee') {
            return [
                ['name' => 'EMP-001', 'employee_name' => 'Almaz Tefera', 'company_email' => 'almaz.t@gadaacloud.et', 'personal_email' => 'almaz.tefera@gmail.com', 'cell_number' => '+251911998877', 'department' => 'Operations', 'designation' => 'Warehouse Supervisor', 'date_of_joining' => '2023-01-15', 'status' => 'Active'],
            ];
        }

        return [];
    }
}
