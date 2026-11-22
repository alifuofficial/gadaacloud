<?php

namespace Workdo\EIMS\Http\Controllers;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Workdo\EIMS\Models\EimsInvoice;
use Workdo\EIMS\Models\EimsOfflineQueue;
use Workdo\EIMS\Models\EimsTenantSetting;
use Workdo\EIMS\Services\EimsClientService;
use Carbon\Carbon;

class EimsDashboardController extends Controller
{
    public function index(EimsClientService $client): Response
    {
        $tenantId = creatorId();
        $setting = EimsTenantSetting::getForTenant($tenantId);

        $totalClearedCount = EimsInvoice::where('status', EimsInvoice::STATUS_CLEARED)->count();
        $totalClearedAmount = (float) EimsInvoice::where('status', EimsInvoice::STATUS_CLEARED)->sum('total_amount');
        $totalVatAmount = (float) EimsInvoice::where('status', EimsInvoice::STATUS_CLEARED)->sum('tax_amount');

        $pendingOfflineCount = EimsOfflineQueue::whereIn('status', [EimsOfflineQueue::STATUS_QUEUED, EimsOfflineQueue::STATUS_PROCESSING])
            ->where('expires_at', '>', Carbon::now())
            ->count();

        $failedCount = EimsInvoice::where('status', EimsInvoice::STATUS_FAILED)->count();
        $cancelledCount = EimsInvoice::where('status', EimsInvoice::STATUS_CANCELLED)->count();

        $recentInvoices = EimsInvoice::latest('id')
            ->limit(8)
            ->get([
                'id', 'sales_invoice_id', 'invoice_number', 'document_type',
                'buyer_type', 'buyer_name', 'total_amount', 'tax_amount',
                'status', 'irn', 'cleared_at', 'created_at'
            ]);

        // Urgent offline warning (< 24h remaining)
        $urgentOfflineCount = EimsOfflineQueue::whereIn('status', [EimsOfflineQueue::STATUS_QUEUED, EimsOfflineQueue::STATUS_PROCESSING])
            ->where('expires_at', '<', Carbon::now()->addHours(24))
            ->where('expires_at', '>', Carbon::now())
            ->count();

        return Inertia::render('eims/Dashboard', [
            'metrics' => [
                'totalClearedCount' => $totalClearedCount,
                'totalClearedAmount' => $totalClearedAmount,
                'totalVatAmount' => $totalVatAmount,
                'pendingOfflineCount' => $pendingOfflineCount,
                'failedCount' => $failedCount,
                'cancelledCount' => $cancelledCount,
                'urgentOfflineCount' => $urgentOfflineCount,
            ],
            'setting' => $setting ? [
                'tin' => $setting->tin,
                'vat_number' => $setting->vat_number,
                'legal_name' => $setting->legal_name,
                'branch_code' => $setting->branch_code,
                'device_id' => $setting->device_id,
                'environment' => $setting->environment,
                'isConfigured' => !empty($setting->tin),
            ] : null,
            'recentInvoices' => $recentInvoices,
        ]);
    }
}
