<?php

namespace Workdo\EIMS\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Workdo\EIMS\Models\EimsOfflineQueue;
use Workdo\EIMS\Services\EimsOfflineResiliencyService;
use Workdo\EIMS\Traits\EimsControllerHelpers;
use Workdo\EIMS\Traits\EimsDateHelpers;

class EimsOfflineQueueController extends Controller
{
    use EimsControllerHelpers, EimsDateHelpers;

    public function index(Request $request): Response
    {
        $query = EimsOfflineQueue::with('eimsInvoice');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $this->applySafeSort($query, ['id', 'status', 'attempts', 'created_at'], $request->sort, $request->direction, 'id', 'desc');
        $items = $query->paginate($this->safePerPage(15, 100))->withQueryString();

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
        if (!$this->assertBelongsToTenant($item)) {
            return redirect()->back()->with('error', __('Permission denied'));
        }
        DB::transaction(function () use ($item) {
            $item->delete();
        });

        return redirect()->back()->with('success', 'Offline queue item removed.');
    }
}
