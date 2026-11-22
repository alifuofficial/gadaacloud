<?php

namespace Workdo\ERPMigrate\Services;

use Workdo\ERPMigrate\Models\MigrationConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OdooConnectorService
{
    /**
     * Test authentication against an Odoo instance via JSON-RPC
     */
    public function testConnection(string $host, string $db, string $user, string $apiKeyOrPassword): array
    {
        $host = rtrim($host, '/');

        // Check if demo or offline testing
        if (str_contains(strtolower($host), 'demo') || $apiKeyOrPassword === 'test_odoo_key') {
            return [
                'success' => true,
                'uid' => 2,
                'server_version' => 'Odoo 16.0+e (Simulated)',
                'database' => $db,
                'message' => 'Connection to Odoo instance successful.',
            ];
        }

        try {
            $endpoint = "{$host}/jsonrpc";

            $payload = [
                'jsonrpc' => '2.0',
                'method' => 'call',
                'params' => [
                    'service' => 'common',
                    'method' => 'authenticate',
                    'args' => [$db, $user, $apiKeyOrPassword, []],
                ],
                'id' => rand(1000, 9999),
            ];

            $response = Http::timeout(10)->post($endpoint, $payload);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['result']) && is_numeric($data['result']) && $data['result'] > 0) {
                    // Fetch version info
                    $verPayload = [
                        'jsonrpc' => '2.0',
                        'method' => 'call',
                        'params' => [
                            'service' => 'common',
                            'method' => 'version',
                            'args' => [],
                        ],
                        'id' => rand(1000, 9999),
                    ];
                    $verResp = Http::timeout(5)->post($endpoint, $verPayload);
                    $verData = $verResp->json();
                    $version = $verData['result']['server_version'] ?? 'Odoo Server';

                    return [
                        'success' => true,
                        'uid' => $data['result'],
                        'server_version' => $version,
                        'database' => $db,
                        'message' => "Successfully authenticated as user ID {$data['result']}.",
                    ];
                }

                $err = $data['error']['data']['message'] ?? $data['error']['message'] ?? 'Invalid credentials or database name.';
                return ['success' => false, 'message' => $err];
            }

            return ['success' => false, 'message' => "HTTP {$response->status()}: Unable to reach Odoo server at {$host}."];

        } catch (\Throwable $e) {
            Log::error("Odoo connection error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Query Odoo models via search_read
     */
    public function searchRead(MigrationConnection $conn, string $model, array $fields = [], array $domain = [], int $limit = 500, int $offset = 0): array
    {
        $host = rtrim($conn->host, '/');

        // Check if demo connection
        if (str_contains(strtolower($host), 'demo') || $conn->api_key === 'test_odoo_key') {
            return $this->getMockOdooData($model, $limit);
        }

        $auth = $this->testConnection($conn->host, $conn->database_name, $conn->username, $conn->api_key);
        if (!$auth['success']) {
            throw new \Exception("Odoo authentication failed: " . $auth['message']);
        }

        $uid = $auth['uid'];
        $endpoint = "{$host}/jsonrpc";

        $payload = [
            'jsonrpc' => '2.0',
            'method' => 'call',
            'params' => [
                'service' => 'object',
                'method' => 'execute_kw',
                'args' => [
                    $conn->database_name,
                    $uid,
                    $conn->api_key,
                    $model,
                    'search_read',
                    [$domain],
                    [
                        'fields' => $fields,
                        'limit' => $limit,
                        'offset' => $offset,
                    ],
                ],
            ],
            'id' => rand(1000, 9999),
        ];

        $response = Http::timeout(25)->post($endpoint, $payload);
        $data = $response->json();

        if (isset($data['result']) && is_array($data['result'])) {
            return $data['result'];
        }

        $err = $data['error']['data']['message'] ?? $data['error']['message'] ?? 'Odoo query failed.';
        throw new \Exception($err);
    }

    /**
     * Fetch Odoo Customers (res.partner where customer_rank > 0 or is_company)
     */
    public function fetchCustomers(MigrationConnection $conn, int $limit = 500): array
    {
        return $this->searchRead(
            $conn,
            'res.partner',
            ['id', 'name', 'vat', 'email', 'phone', 'mobile', 'street', 'city', 'country_id', 'is_company', 'credit_limit'],
            [['customer_rank', '>', 0]],
            $limit
        );
    }

    /**
     * Fetch Odoo Vendors (res.partner where supplier_rank > 0)
     */
    public function fetchVendors(MigrationConnection $conn, int $limit = 500): array
    {
        return $this->searchRead(
            $conn,
            'res.partner',
            ['id', 'name', 'vat', 'email', 'phone', 'street', 'city', 'country_id', 'is_company'],
            [['supplier_rank', '>', 0]],
            $limit
        );
    }

    /**
     * Fetch Odoo Chart of Accounts (account.account)
     */
    public function fetchAccounts(MigrationConnection $conn, int $limit = 500): array
    {
        return $this->searchRead(
            $conn,
            'account.account',
            ['id', 'code', 'name', 'account_type', 'internal_group', 'reconcile', 'currency_id'],
            [],
            $limit
        );
    }

    /**
     * Fetch Odoo Products (product.template)
     */
    public function fetchProducts(MigrationConnection $conn, int $limit = 500): array
    {
        return $this->searchRead(
            $conn,
            'product.template',
            ['id', 'name', 'default_code', 'barcode', 'list_price', 'standard_price', 'type', 'categ_id', 'uom_id', 'weight'],
            [],
            $limit
        );
    }

    /**
     * Fetch Odoo Employees (hr.employee)
     */
    public function fetchEmployees(MigrationConnection $conn, int $limit = 500): array
    {
        return $this->searchRead(
            $conn,
            'hr.employee',
            ['id', 'name', 'work_email', 'work_phone', 'job_title', 'department_id', 'identification_id', 'gender', 'birthday'],
            [],
            $limit
        );
    }

    /**
     * Mock Odoo data generator for demonstrations and tests
     */
    protected function getMockOdooData(string $model, int $limit): array
    {
        if ($model === 'res.partner') {
            return [
                ['id' => 101, 'name' => 'Bole Commercial Enterprise PLC', 'vat' => '0019283746', 'email' => 'finance@bolecommercial.et', 'phone' => '+251911223344', 'street' => 'Bole Medhanialem', 'city' => 'Addis Ababa', 'is_company' => true, 'customer_rank' => 1, 'supplier_rank' => 0],
                ['id' => 102, 'name' => 'Abyssinia Coffee Roasters', 'vat' => '0028374651', 'email' => 'sales@abyssiniacoffee.com', 'phone' => '+251922334455', 'street' => 'Kazanchis Total', 'city' => 'Addis Ababa', 'is_company' => true, 'customer_rank' => 1, 'supplier_rank' => 0],
                ['id' => 103, 'name' => 'Oromia Industrial Suppliers', 'vat' => '0037465182', 'email' => 'info@oromiaindustrial.et', 'phone' => '+251933445566', 'street' => 'Dukem Industrial Park', 'city' => 'Dukem', 'is_company' => true, 'customer_rank' => 0, 'supplier_rank' => 1],
            ];
        }

        if ($model === 'account.account') {
            return [
                ['id' => 201, 'code' => '101000', 'name' => 'Cash in Hand (Birr)', 'account_type' => 'asset_cash', 'internal_group' => 'asset', 'reconcile' => false],
                ['id' => 202, 'code' => '102000', 'name' => 'Commercial Bank of Ethiopia (CBE)', 'account_type' => 'asset_cash', 'internal_group' => 'asset', 'reconcile' => true],
                ['id' => 203, 'code' => '110000', 'name' => 'Accounts Receivable (Trade Debtors)', 'account_type' => 'asset_receivable', 'internal_group' => 'asset', 'reconcile' => true],
                ['id' => 204, 'code' => '210000', 'name' => 'Accounts Payable (Trade Creditors)', 'account_type' => 'liability_payable', 'internal_group' => 'liability', 'reconcile' => true],
                ['id' => 205, 'code' => '400000', 'name' => 'Sales Revenue (Local 15% VAT)', 'account_type' => 'income', 'internal_group' => 'income', 'reconcile' => false],
            ];
        }

        if ($model === 'product.template') {
            return [
                ['id' => 301, 'name' => 'Yirgacheffe Grade 1 Green Coffee', 'default_code' => 'COF-YIRG-01', 'barcode' => '600123456789', 'list_price' => 450.00, 'standard_price' => 320.00, 'type' => 'consu', 'categ_id' => [1, 'Agricultural Commodities']],
                ['id' => 302, 'name' => 'Sidama Specialty Arabica Coffee', 'default_code' => 'COF-SID-02', 'barcode' => '600987654321', 'list_price' => 520.00, 'standard_price' => 380.00, 'type' => 'consu', 'categ_id' => [1, 'Agricultural Commodities']],
                ['id' => 303, 'name' => 'High-Density Polyethylene Granules (25kg)', 'default_code' => 'RAW-HDPE-25', 'barcode' => '600456789012', 'list_price' => 1850.00, 'standard_price' => 1400.00, 'type' => 'product', 'categ_id' => [2, 'Industrial Raw Materials']],
            ];
        }

        if ($model === 'hr.employee') {
            return [
                ['id' => 401, 'name' => 'Dawit Mengistu', 'work_email' => 'dawit.m@company.et', 'work_phone' => '+251911001122', 'job_title' => 'Senior Accountant', 'department_id' => [1, 'Finance'], 'identification_id' => 'ETH-849201'],
                ['id' => 402, 'name' => 'Tigist Haile', 'work_email' => 'tigist.h@company.et', 'work_phone' => '+251922112233', 'job_title' => 'Supply Chain Manager', 'department_id' => [2, 'Logistics'], 'identification_id' => 'ETH-739102'],
            ];
        }

        return [];
    }
}
