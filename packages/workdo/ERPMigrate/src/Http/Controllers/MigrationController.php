<?php

namespace Workdo\ERPMigrate\Http\Controllers;

use App\Http\Controllers\Controller;
use Workdo\ERPMigrate\Models\MigrationBatch;
use Workdo\ERPMigrate\Models\MigrationConnection;
use Workdo\ERPMigrate\Models\MigrationLog;
use Workdo\ERPMigrate\Models\MigrationFieldMapping;
use Workdo\ERPMigrate\Services\OdooConnectorService;
use Workdo\ERPMigrate\Services\ErpNextConnectorService;
use Workdo\ERPMigrate\Services\CsvSpreadsheetParserService;
use Workdo\ERPMigrate\Services\DataTransformationService;
use Workdo\ERPMigrate\Services\MigrationRollbackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Workdo\ERPMigrate\Traits\ERPMigrateControllerHelpers;
use Workdo\ERPMigrate\Traits\ERPMigrateDateHelpers;

class MigrationController extends Controller
{
    use ERPMigrateControllerHelpers, ERPMigrateDateHelpers;

    /**
     * Dashboard view of all ERP migration runs, connections, and system health
     */
    public function index()
    {
        $companyId = creatorId();

        $batches = MigrationBatch::where('created_by', $companyId)
            ->with('connection')
            ->orderBy('id', 'desc')
            ->take(10)
            ->get();

        $connections = MigrationConnection::where('created_by', $companyId)
            ->orderBy('id', 'desc')
            ->get();

        $totalBatches = MigrationBatch::where('created_by', $companyId)->count();
        $completedBatches = MigrationBatch::where('created_by', $companyId)->where('status', 'completed')->count();
        $totalRecordsImported = MigrationBatch::where('created_by', $companyId)->where('status', 'completed')->sum('imported_count');
        $activeConnections = $connections->where('connection_status', 'connected')->count();

        return Inertia::render('erp-migrate/Index', [
            'batches' => $batches,
            'connections' => $connections,
            'stats' => [
                'totalBatches' => $totalBatches,
                'completedBatches' => $completedBatches,
                'totalRecordsImported' => (int)$totalRecordsImported,
                'totalConnections' => $connections->count(),
                'activeConnections' => $activeConnections,
            ],
        ]);
    }

    /**
     * 5-Stage Interactive Migration Wizard
     */
    public function wizard(Request $request, CsvSpreadsheetParserService $parser)
    {
        $companyId = creatorId();

        $connections = MigrationConnection::where('created_by', $companyId)->get();

        // Sample field dictionaries for all supported entities
        $entityFields = [
            'customers' => $parser->getTargetFieldsForEntity('customers'),
            'vendors' => $parser->getTargetFieldsForEntity('vendors'),
            'products' => $parser->getTargetFieldsForEntity('products'),
            'chart_of_accounts' => $parser->getTargetFieldsForEntity('chart_of_accounts'),
            'employees' => $parser->getTargetFieldsForEntity('employees'),
        ];

        return Inertia::render('erp-migrate/Wizard', [
            'connections' => $connections,
            'entityFields' => $entityFields,
        ]);
    }

    /**
     * Saved ERP API Connections List View
     */
    public function connections()
    {
        $companyId = creatorId();
        $connections = MigrationConnection::where('created_by', $companyId)
            ->withCount('batches')
            ->orderBy('id', 'desc')
            ->get();

        return Inertia::render('erp-migrate/Connections', [
            'connections' => $connections,
        ]);
    }

    /**
     * Test connection to Odoo or ERPNext
     */
    public function testConnection(Request $request, OdooConnectorService $odoo, ErpNextConnectorService $erpnext)
    {
        $request->validate([
            'source_type' => 'required|string|in:odoo,erpnext',
            'host' => 'required|string|url',
        ]);

        if (!self::isValidConnectorHost($request->host)) {
            return response()->json([
                'success' => false,
                'message' => __('The target ERP host URL is invalid or points to a restricted internal network address.'),
            ], 422);
        }

        if ($request->source_type === 'odoo') {
            $request->validate([
                'database_name' => 'required|string',
                'username' => 'required|string',
                'api_key' => 'required|string',
            ]);
            $res = $odoo->testConnection(
                $request->host,
                $request->database_name,
                $request->username,
                $request->api_key
            );
            return response()->json($res);
        } else {
            $request->validate([
                'api_key' => 'required|string',
                'api_secret' => 'required|string',
            ]);
            $res = $erpnext->testConnection(
                $request->host,
                $request->api_key,
                $request->api_secret
            );
            return response()->json($res);
        }
    }

    /**
     * Store or update ERP API connection
     */
    public function storeConnection(Request $request, OdooConnectorService $odoo, ErpNextConnectorService $erpnext)
    {
        $companyId = creatorId();

        $request->validate([
            'name' => 'required|string|max:100',
            'source_type' => 'required|string|in:odoo,erpnext',
            'host' => 'required|string|url',
        ]);

        if (!self::isValidConnectorHost($request->host)) {
            return redirect()->back()->with('error', __('The target ERP host URL is invalid or points to a restricted internal network address.'));
        }

        // Run automated verification
        $status = 'untested';
        $lastTestedAt = now();
        $lastError = null;

        if ($request->source_type === 'odoo') {
            $test = $odoo->testConnection($request->host, $request->database_name ?? '', $request->username ?? '', $request->api_key ?? '');
            $status = $test['success'] ? 'connected' : 'failed';
            if (!$test['success']) $lastError = $test['message'] ?? 'Connection failed';
        } else {
            $test = $erpnext->testConnection($request->host, $request->api_key ?? '', $request->api_secret ?? '');
            $status = $test['success'] ? 'connected' : 'failed';
            if (!$test['success']) $lastError = $test['message'] ?? 'Connection failed';
        }

        $connection = DB::transaction(function () use ($request, $companyId, $status, $lastTestedAt, $lastError) {
            return MigrationConnection::updateOrCreate(
                ['id' => $request->id, 'created_by' => $companyId],
                [
                    'name' => $request->name,
                    'source_type' => $request->source_type,
                    'host' => $request->host,
                    'database_name' => $request->database_name,
                    'username' => $request->username,
                    'api_key' => $request->api_key,
                    'api_secret' => $request->api_secret,
                    'connection_status' => $status,
                    'last_tested_at' => $lastTestedAt,
                    'last_error' => $lastError,
                    'created_by' => $companyId,
                    'creator_id' => Auth::id(),
                ]
            );
        });

        return redirect()->back()->with('success', __('ERP connection saved and tested successfully.'));
    }

    /**
     * Delete ERP connection
     */
    public function destroyConnection($id)
    {
        $companyId = creatorId();
        $connection = MigrationConnection::where('created_by', $companyId)->findOrFail($id);
        DB::transaction(function () use ($connection) {
            $connection->delete();
        });

        return redirect()->back()->with('success', __('ERP connection deleted successfully.'));
    }

    /**
     * Upload spreadsheet file (.csv, .xlsx) and parse metadata
     */
    public function uploadFile(Request $request, CsvSpreadsheetParserService $parser)
    {
        $companyId = creatorId();

        $request->validate([
            'file' => 'required|file|mimes:csv,txt,tsv,xlsx,xls|max:20480', // Max 20MB
            'entity_type' => 'required|string|in:customers,vendors,products,chart_of_accounts,employees',
        ]);

        $file = $request->file('file');
        $origName = $file->getClientOriginalName();
        $ext = strtolower($file->getClientOriginalExtension());

        $tempPath = $file->storeAs('migration_temp', 'batch_' . time() . '_' . Str::random(8) . '.' . $ext);
        $fullPath = storage_path('app/' . $tempPath);

        $inspect = $parser->inspectFile($fullPath);
        $suggestedMappings = $parser->suggestFieldMappings($inspect['headers'], $request->entity_type);

        $batch = DB::transaction(function () use ($request, $companyId, $ext, $origName, $inspect, $suggestedMappings) {
            $batch = new MigrationBatch();
            $batch->batch_code = 'MIG-' . date('Ymd') . '-' . strtoupper(Str::random(4));
            $batch->source_type = ($ext === 'csv' || $ext === 'tsv') ? 'universal_csv' : 'universal_excel';
            $batch->entity_type = $request->entity_type;
            $batch->original_filename = $origName;
            $batch->total_records = $inspect['total_rows'];
            $batch->status = 'draft';
            $batch->field_mappings = $suggestedMappings;
            $batch->created_by = $companyId;
            $batch->creator_id = Auth::id();
            $batch->save();
            return $batch;
        });

        // Store relative path in session for subsequent wizard steps
        session(["migration_file_{$batch->id}" => $tempPath]);

        return response()->json([
            'success' => true,
            'batch_id' => $batch->id,
            'batch_code' => $batch->batch_code,
            'original_filename' => $origName,
            'headers' => $inspect['headers'],
            'sample_rows' => $inspect['sample_rows'],
            'suggested_mappings' => $suggestedMappings,
            'total_rows' => $inspect['total_rows'],
        ]);
    }

    /**
     * Fetch remote data from Odoo or ERPNext API for the wizard
     */
    public function fetchRemote(Request $request, OdooConnectorService $odoo, ErpNextConnectorService $erpnext, CsvSpreadsheetParserService $parser)
    {
        $companyId = creatorId();

        $request->validate([
            'connection_id' => 'required|integer',
            'entity_type' => 'required|string|in:customers,vendors,products,chart_of_accounts,employees',
        ]);

        $conn = MigrationConnection::where('created_by', $companyId)->findOrFail($request->connection_id);
        $records = [];

        if ($conn->source_type === 'odoo') {
            if ($request->entity_type === 'customers') $records = $odoo->fetchCustomers($conn);
            elseif ($request->entity_type === 'vendors') $records = $odoo->fetchVendors($conn);
            elseif ($request->entity_type === 'products') $records = $odoo->fetchProducts($conn);
            elseif ($request->entity_type === 'chart_of_accounts') $records = $odoo->fetchAccounts($conn);
            elseif ($request->entity_type === 'employees') $records = $odoo->fetchEmployees($conn);
        } else {
            if ($request->entity_type === 'customers') $records = $erpnext->fetchCustomers($conn);
            elseif ($request->entity_type === 'vendors') $records = $erpnext->fetchSuppliers($conn);
            elseif ($request->entity_type === 'products') $records = $erpnext->fetchItems($conn);
            elseif ($request->entity_type === 'chart_of_accounts') $records = $erpnext->fetchAccounts($conn);
            elseif ($request->entity_type === 'employees') $records = $erpnext->fetchEmployees($conn);
        }

        if (empty($records)) {
            return response()->json([
                'success' => false,
                'message' => 'No records found on the remote ERP instance for this entity type.',
            ], 422);
        }

        $headers = array_keys($records[0]);
        $suggestedMappings = $parser->suggestFieldMappings($headers, $request->entity_type);

        $batch = new MigrationBatch();
        $batch->batch_code = 'MIG-' . date('Ymd') . '-' . strtoupper(Str::random(4));
        $batch->source_type = $conn->source_type . '_api';
        $batch->entity_type = $request->entity_type;
        $batch->connection_id = $conn->id;
        $batch->original_filename = "{$conn->name} API Sync";
        $batch->total_records = count($records);
        $batch->status = 'draft';
        $batch->field_mappings = $suggestedMappings;
        $batch->created_by = $companyId;
        $batch->creator_id = Auth::id();
        $batch->save();

        // Cache remote records in temp storage for execution
        $tempPath = "migration_temp/remote_{$batch->id}.json";
        Storage::put($tempPath, json_encode($records));
        session(["migration_file_{$batch->id}" => $tempPath]);

        return response()->json([
            'success' => true,
            'batch_id' => $batch->id,
            'batch_code' => $batch->batch_code,
            'headers' => $headers,
            'sample_rows' => array_slice($records, 0, 5),
            'suggested_mappings' => $suggestedMappings,
            'total_rows' => count($records),
        ]);
    }

    /**
     * Run Dry-Run Validation on batch
     */
    public function dryRun(Request $request, DataTransformationService $transformer, CsvSpreadsheetParserService $parser)
    {
        $companyId = creatorId();

        $request->validate([
            'batch_id' => 'required|integer',
            'field_mappings' => 'required|array',
        ]);

        $batch = MigrationBatch::where('created_by', $companyId)->findOrFail($request->batch_id);
        $batch->field_mappings = $request->field_mappings;
        $batch->save();

        $rows = $this->loadBatchRows($batch, $parser);
        $summary = $transformer->validateDryRun($batch, $rows);

        return response()->json([
            'success' => true,
            'summary' => $summary,
        ]);
    }

    /**
     * Execute transactional migration
     */
    public function execute(Request $request, DataTransformationService $transformer, CsvSpreadsheetParserService $parser)
    {
        $companyId = creatorId();

        $request->validate([
            'batch_id' => 'required|integer',
            'skip_errors' => 'nullable|boolean',
        ]);

        $batch = MigrationBatch::where('created_by', $companyId)->findOrFail($request->batch_id);
        $rows = $this->loadBatchRows($batch, $parser);

        $result = $transformer->executeMigration($batch, $rows, $request->boolean('skip_errors', true));

        return response()->json([
            'success' => true,
            'batch_id' => $batch->id,
            'batch_code' => $batch->batch_code,
            'imported_count' => $result['imported_count'],
            'skipped_count' => $result['skipped_count'],
            'failed_count' => $result['failed_count'],
            'message' => "Successfully imported {$result['imported_count']} records into GadaaCloud.",
        ]);
    }

    /**
     * Batch Audit Log & Details View
     */
    public function batchDetails($id)
    {
        $companyId = creatorId();
        $batch = MigrationBatch::where('created_by', $companyId)
            ->with('connection')
            ->findOrFail($id);

        $logs = MigrationLog::where('batch_id', $batch->id)
            ->where('created_by', $companyId)
            ->orderBy('id', 'asc')
            ->paginate($this->safePerPage(50, 100));

        return Inertia::render('erp-migrate/BatchDetails', [
            'batch' => $batch,
            'logs' => $logs,
        ]);
    }

    /**
     * History list view
     */
    public function history()
    {
        $companyId = creatorId();
        $batches = MigrationBatch::where('created_by', $companyId)
            ->with('connection')
            ->orderBy('id', 'desc')
            ->paginate($this->safePerPage(20, 100));

        return Inertia::render('erp-migrate/History', [
            'batches' => $batches,
        ]);
    }

    /**
     * Rollback a migration batch with 1 click
     */
    public function rollback($id, MigrationRollbackService $rollbackService)
    {
        $companyId = creatorId();
        $batch = MigrationBatch::where('created_by', $companyId)->findOrFail($id);

        $result = $rollbackService->rollback($batch);

        return redirect()->back()->with('success', $result['message']);
    }

    /**
     * Helper to load rows from stored temp file
     */
    protected function loadBatchRows(MigrationBatch $batch, CsvSpreadsheetParserService $parser): array
    {
        $tempPath = session("migration_file_{$batch->id}");

        if ($tempPath && Storage::exists($tempPath)) {
            if (str_ends_with($tempPath, '.json')) {
                return json_decode(Storage::get($tempPath), true) ?? [];
            }
            return $parser->getRows(storage_path('app/' . $tempPath), 0, 5000);
        }

        return [];
    }
}
