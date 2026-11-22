<?php

namespace Workdo\EIMS\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Workdo\EIMS\Models\EimsTenantSetting;
use Workdo\EIMS\Models\EimsAuditLog;
use Workdo\EIMS\Services\EimsClientService;
use Workdo\EIMS\Services\EimsAuditLoggingService;

class EimsSettingsController extends Controller
{
    public function index(): Response
    {
        $tenantId = creatorId();
        $setting = EimsTenantSetting::getForTenant($tenantId);

        $recentLogs = EimsAuditLog::latest('id')->limit(10)->get();

        return Inertia::render('eims/Settings', [
            'setting' => $setting ? [
                'tin' => $setting->tin,
                'vat_number' => $setting->vat_number,
                'legal_name' => $setting->legal_name,
                'trade_name' => $setting->trade_name,
                'branch_code' => $setting->branch_code,
                'device_id' => $setting->device_id,
                'environment' => $setting->environment,
                'api_endpoint' => $setting->api_endpoint,
                'has_api_key' => !empty($setting->api_key),
                'has_api_secret' => !empty($setting->api_secret),
                'offline_resiliency_enabled' => (bool) $setting->offline_resiliency_enabled,
                'auto_clear_on_post' => (bool) $setting->auto_clear_on_post,
            ] : null,
            'recentLogs' => $recentLogs,
        ]);
    }

    public function update(Request $request, EimsAuditLoggingService $audit): RedirectResponse
    {
        $validated = $request->validate([
            'tin' => ['required', 'string', 'regex:/^\d{10}$/'],
            'vat_number' => ['nullable', 'string', 'max:20'],
            'legal_name' => ['required', 'string', 'max:191'],
            'trade_name' => ['nullable', 'string', 'max:191'],
            'branch_code' => ['required', 'string', 'max:50'],
            'device_id' => ['nullable', 'string', 'max:100'],
            'environment' => ['required', 'in:sandbox,production'],
            'api_endpoint' => ['nullable', 'url', 'max:255'],
            'api_key' => ['nullable', 'string'],
            'api_secret' => ['nullable', 'string'],
            'offline_resiliency_enabled' => ['boolean'],
            'auto_clear_on_post' => ['boolean'],
        ]);

        $tenantId = creatorId();
        $setting = EimsTenantSetting::getForTenant($tenantId);

        $data = [
            'tin' => $validated['tin'],
            'vat_number' => $validated['vat_number'] ?? ('VAT' . $validated['tin']),
            'legal_name' => $validated['legal_name'],
            'trade_name' => $validated['trade_name'] ?? $validated['legal_name'],
            'branch_code' => $validated['branch_code'],
            'device_id' => $validated['device_id'] ?? 'DEVICE-01',
            'environment' => $validated['environment'],
            'api_endpoint' => $validated['api_endpoint'] ?? null,
            'offline_resiliency_enabled' => $validated['offline_resiliency_enabled'] ?? true,
            'auto_clear_on_post' => $validated['auto_clear_on_post'] ?? true,
        ];

        if (!empty($validated['api_key'])) {
            $data['api_key'] = $validated['api_key'];
        }

        if (!empty($validated['api_secret'])) {
            $data['api_secret'] = $validated['api_secret'];
        }

        if ($setting) {
            $setting->update($data);
        } else {
            $data['created_by'] = $tenantId;
            $setting = EimsTenantSetting::create($data);
        }

        $audit->log('SETTINGS_UPDATE', [
            'tin' => $setting->tin,
            'branch_code' => $setting->branch_code,
            'environment' => $setting->environment,
        ], null, $tenantId);

        return redirect()->back()->with('success', 'EIMS Ministry settings updated successfully.');
    }

    public function testConnection(EimsClientService $client): JsonResponse
    {
        $tenantId = creatorId();
        $setting = EimsTenantSetting::getForTenant($tenantId);

        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Please save your settings before running a connection test.',
            ], 400);
        }

        $result = $client->testConnection($setting);
        return response()->json($result);
    }

    public function auditLogs(Request $request): Response
    {
        $query = EimsAuditLog::with(['user', 'eimsInvoice'])->latest('id');

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        $logs = $query->paginate(20)->withQueryString();

        return Inertia::render('eims/AuditLogs', [
            'logs' => $logs,
            'filters' => $request->only(['action']),
        ]);
    }
}
