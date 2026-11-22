<?php

namespace Workdo\EIMS\Services;

use Workdo\EIMS\Models\EimsInvoice;
use Workdo\EIMS\Models\EimsOfflineQueue;
use Workdo\EIMS\Models\EimsTenantSetting;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Throwable;

class EimsOfflineResiliencyService
{
    protected EimsClientService $client;
    protected EimsAuditLoggingService $audit;
    protected EimsQrCodeService $qrService;

    public function __construct(
        EimsClientService $client,
        EimsAuditLoggingService $audit,
        ?EimsQrCodeService $qrService = null
    ) {
        $this->client = $client;
        $this->audit = $audit;
        $this->qrService = $qrService ?? new EimsQrCodeService();
    }

    /**
     * Enqueue an offline invoice payload to survive network drops
     * Strictly limits validity to 72 hours per Directive 1142 / Annex 2
     */
    public function enqueueOfflineInvoice(EimsInvoice $invoice, array $payload, ?int $tenantId = null): EimsOfflineQueue
    {
        $tenant = $tenantId ?? $invoice->created_by;

        $idempotencyKey = 'EIMS-OFFLINE-' . $invoice->id . '-' . Str::random(12);

        $queue = EimsOfflineQueue::create([
            'created_by' => $tenant,
            'eims_invoice_id' => $invoice->id,
            'idempotency_key' => $idempotencyKey,
            'payload' => $payload, // Auto-encrypted by Model cast
            'attempt_count' => 0,
            'max_attempts' => 10,
            'expires_at' => Carbon::now()->addHours(72),
            'status' => EimsOfflineQueue::STATUS_QUEUED,
        ]);

        // Update EimsInvoice to PENDING_OFFLINE status
        $invoice->update([
            'status' => EimsInvoice::STATUS_PENDING_OFFLINE,
            'irn' => 'PENDING_OFFLINE_' . substr(hash('sha256', $idempotencyKey), 0, 16),
            'error_message' => 'Network offline. Invoice buffered for MoR clearance within 72-hour window.',
        ]);

        $this->audit->log('OFFLINE_QUEUE', $payload, $invoice->id, $tenant, [
            'idempotency_key' => $idempotencyKey,
            'expires_at' => $queue->expires_at->toIso8601String(),
        ]);

        return $queue;
    }

    /**
     * Flush all queued items for a specific tenant or all tenants
     */
    public function flushQueue(?int $tenantId = null, int $limit = 50): array
    {
        $query = EimsOfflineQueue::withoutGlobalScope('tenant')
            ->whereIn('status', [EimsOfflineQueue::STATUS_QUEUED, EimsOfflineQueue::STATUS_PROCESSING])
            ->where('expires_at', '>', Carbon::now())
            ->orderBy('id', 'asc')
            ->limit($limit);

        if ($tenantId) {
            $query->where('created_by', $tenantId);
        }

        $items = $query->get();
        $results = [
            'total' => $items->count(),
            'synced' => 0,
            'failed' => 0,
            'expired' => 0,
        ];

        foreach ($items as $queueItem) {
            if ($queueItem->isExpired()) {
                $queueItem->update(['status' => EimsOfflineQueue::STATUS_EXPIRED]);
                $results['expired']++;
                continue;
            }

            $queueItem->update([
                'status' => EimsOfflineQueue::STATUS_PROCESSING,
                'attempt_count' => $queueItem->attempt_count + 1,
                'last_attempt_at' => Carbon::now(),
            ]);

            $invoice = EimsInvoice::withoutGlobalScope('tenant')->find($queueItem->eims_invoice_id);
            if (!$invoice) {
                $queueItem->update(['status' => EimsOfflineQueue::STATUS_DEAD_LETTER]);
                continue;
            }

            $setting = EimsTenantSetting::getForTenant($queueItem->created_by);
            if (!$setting) {
                $queueItem->update([
                    'status' => EimsOfflineQueue::STATUS_QUEUED,
                    'last_error' => 'Tenant settings missing',
                ]);
                $results['failed']++;
                continue;
            }

            try {
                $payload = is_array($queueItem->payload) ? $queueItem->payload : json_decode($queueItem->payload, true);
                $clearance = $this->client->registerInvoice($payload, $setting);

                if (!empty($clearance['success']) && ($clearance['status'] === 'CLEARED')) {
                    $qrCodeSvg = $this->qrService->generateSvg($clearance['qrCodeData'] ?? $clearance['irn']);

                    $invoice->update([
                        'status' => EimsInvoice::STATUS_CLEARED,
                        'irn' => $clearance['irn'],
                        'rrn' => $clearance['rrn'] ?? null,
                        'qr_code_data' => $clearance['qrCodeData'] ?? null,
                        'qr_code_image' => $qrCodeSvg,
                        'cleared_at' => Carbon::now(),
                        'eims_response' => $clearance,
                        'error_message' => null,
                    ]);

                    $queueItem->update([
                        'status' => EimsOfflineQueue::STATUS_COMPLETED,
                    ]);

                    $this->audit->log('SYNC_OFFLINE', $payload, $invoice->id, $queueItem->created_by, [
                        'irn' => $clearance['irn'],
                        'rrn' => $clearance['rrn'] ?? null,
                    ]);

                    $results['synced']++;
                } else {
                    $queueItem->update([
                        'status' => EimsOfflineQueue::STATUS_QUEUED,
                        'last_error' => $clearance['error'] ?? 'Unknown gateway rejection',
                        'next_attempt_at' => Carbon::now()->addMinutes(5 * ($queueItem->attempt_count + 1)),
                    ]);
                    $results['failed']++;
                }
            } catch (EimsNetworkException $ne) {
                // Network still down; schedule next retry
                $queueItem->update([
                    'status' => EimsOfflineQueue::STATUS_QUEUED,
                    'last_error' => $ne->getMessage(),
                    'next_attempt_at' => Carbon::now()->addMinutes(min(30, 2 * ($queueItem->attempt_count + 1))),
                ]);
                $results['failed']++;
            } catch (Throwable $e) {
                $queueItem->update([
                    'status' => EimsOfflineQueue::STATUS_QUEUED,
                    'last_error' => $e->getMessage(),
                ]);
                $results['failed']++;
            }
        }

        return $results;
    }
}
