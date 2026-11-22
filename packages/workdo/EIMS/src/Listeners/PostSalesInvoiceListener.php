<?php

namespace Workdo\EIMS\Listeners;

use App\Events\PostSalesInvoice;
use Workdo\EIMS\Models\EimsInvoice;
use Workdo\EIMS\Models\EimsTenantSetting;
use Workdo\EIMS\Services\EimsClientService;
use Workdo\EIMS\Services\EimsPayloadBuilderService;
use Workdo\EIMS\Services\EimsOfflineResiliencyService;
use Workdo\EIMS\Services\EimsAuditLoggingService;
use Workdo\EIMS\Services\EimsQrCodeService;
use Workdo\EIMS\Services\EimsNetworkException;
use Throwable;

class PostSalesInvoiceListener
{
    protected EimsClientService $client;
    protected EimsPayloadBuilderService $payloadBuilder;
    protected EimsOfflineResiliencyService $offlineService;
    protected EimsAuditLoggingService $audit;
    protected EimsQrCodeService $qrService;

    public function __construct(
        EimsClientService $client,
        EimsPayloadBuilderService $payloadBuilder,
        EimsOfflineResiliencyService $offlineService,
        EimsAuditLoggingService $audit,
        EimsQrCodeService $qrService
    ) {
        $this->client = $client;
        $this->payloadBuilder = $payloadBuilder;
        $this->offlineService = $offlineService;
        $this->audit = $audit;
        $this->qrService = $qrService;
    }

    public function handle(PostSalesInvoice $event): void
    {
        if (!function_exists('Module_is_active') || !Module_is_active('EIMS', $event->salesInvoice->created_by)) {
            return;
        }

        $salesInvoice = $event->salesInvoice;
        $tenantId = $salesInvoice->created_by;

        $setting = EimsTenantSetting::getForTenant($tenantId);
        if (!$setting || empty($setting->tin)) {
            return; // EIMS tenant profile not yet configured
        }

        if (!$setting->auto_clear_on_post) {
            return;
        }

        // Avoid duplicate clearance requests for the same invoice
        $existing = EimsInvoice::withoutGlobalScope('tenant')
            ->where('sales_invoice_id', $salesInvoice->id)
            ->whereIn('status', [EimsInvoice::STATUS_CLEARED, EimsInvoice::STATUS_PENDING_OFFLINE])
            ->first();

        if ($existing) {
            return;
        }

        try {
            $payload = $this->payloadBuilder->buildInvoicePayload($salesInvoice, $setting);

            $buyer = $payload['buyer'] ?? [];
            $summary = $payload['summary'] ?? [];
            $payment = $payload['payment'] ?? [];

            $eimsInvoice = EimsInvoice::create([
                'created_by' => $tenantId,
                'sales_invoice_id' => $salesInvoice->id,
                'invoice_number' => $salesInvoice->invoice_number,
                'document_type' => 'INV',
                'buyer_type' => $buyer['buyerType'] ?? 'B2C',
                'buyer_tin' => $buyer['tin'] ?? null,
                'buyer_name' => $buyer['legalName'] ?? 'Consumer',
                'buyer_id_type' => $buyer['idType'] ?? null,
                'buyer_id_number' => $buyer['idNumber'] ?? null,
                'currency' => $summary['currency'] ?? 'ETB',
                'pre_tax_amount' => $summary['preTaxAmount'] ?? 0,
                'tax_amount' => $summary['taxAmount'] ?? 0,
                'excise_tax_amount' => $summary['exciseTaxAmount'] ?? 0,
                'discount_amount' => $summary['discountAmount'] ?? 0,
                'total_amount' => $summary['totalAmount'] ?? 0,
                'payment_mode' => $payment['mode'] ?? 'CASH',
                'payment_term' => $payment['paymentTerm'] ?? 'IMMIDIATE',
                'status' => EimsInvoice::STATUS_PENDING,
                'eims_payload' => $payload,
            ]);

            try {
                $clearance = $this->client->registerInvoice($payload, $setting);

                if (!empty($clearance['success']) && ($clearance['status'] === 'CLEARED')) {
                    $qrCodeSvg = $this->qrService->generateSvg($clearance['qrCodeData'] ?? $clearance['irn']);

                    $eimsInvoice->update([
                        'status' => EimsInvoice::STATUS_CLEARED,
                        'irn' => $clearance['irn'],
                        'rrn' => $clearance['rrn'] ?? null,
                        'qr_code_data' => $clearance['qrCodeData'] ?? null,
                        'qr_code_image' => $qrCodeSvg,
                        'cleared_at' => now(),
                        'eims_response' => $clearance,
                        'error_message' => null,
                    ]);

                    $this->audit->log('REGISTER', $payload, $eimsInvoice->id, $tenantId, [
                        'irn' => $clearance['irn'],
                        'rrn' => $clearance['rrn'] ?? null,
                    ]);
                } else {
                    $eimsInvoice->update([
                        'status' => EimsInvoice::STATUS_FAILED,
                        'error_message' => $clearance['error'] ?? 'Gateway validation rejection',
                        'eims_response' => $clearance,
                    ]);
                }
            } catch (EimsNetworkException $ne) {
                // Network unreachable -> Trigger 72-hour offline resiliency queue
                if ($setting->offline_resiliency_enabled) {
                    $this->offlineService->enqueueOfflineInvoice($eimsInvoice, $payload, $tenantId);
                } else {
                    $eimsInvoice->update([
                        'status' => EimsInvoice::STATUS_FAILED,
                        'error_message' => 'Network offline and offline resiliency disabled: ' . $ne->getMessage(),
                    ]);
                }
            }
        } catch (Throwable $e) {
            // Log silent catch to avoid breaking core transaction
            logger()->error("EIMS auto-registration error: " . $e->getMessage(), [
                'invoice_id' => $salesInvoice->id,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
