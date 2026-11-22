<?php

namespace Workdo\EIMS\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Workdo\EIMS\Models\EimsOfflineQueue;
use Workdo\EIMS\Services\EimsOfflineResiliencyService;

class EimsOfflineQueueController extends Controller
{
    public function index(Request $request): Response
    {
        $query = EimsOfflineQueue::with('eimsInvoice')->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $items = $query->paginate(15)->withQueryString();

        // Calculate queue metrics
        $activeCount = EimsOfflineQueue::whereIn('status', [EimsOfflineQueue::STATUS_QUEUED, EimsOfflineQueue::STATUS_PROCESSING])->count();
        $expiredCount = EimsOfflineQueue::where('status', EimsOfflineQueue::STATUS_EXPIRED)->count();
        $completedCount = EimsOfflineQueue::where('status', EimsOfflineQueue::STATUS_COMPLETED)->count();

        return Inertia::render('eims/OfflineQueue', [
            'items' => $items,
            'filters' => $request->only(['status']),
            'metrics' => [
                'activeCount' => $activeCount,
                'expiredCount' => $expiredCount,
                'completedCount' => $completedCount,
            ]
        ]);
    }

    public function flush(EimsOfflineResiliencyService $service): RedirectResponse
    {
        $tenantId = creatorId();
        $results = $service->flushQueue($tenantId, 100);

        $msg = "Queue flush executed. {$results['synced']} invoices cleared, {$results['failed']} retried/failed, {$results['expired']} expired.";
        return redirect()->back()->with('success', $msg);
    }

    public function destroy(int $id): RedirectResponse
    {
        $item = EimsOfflineQueue::findOrFail($id);
        $item->delete();

        return redirect()->back()->with('success', 'Offline queue item removed.');
    }
}
