<?php

namespace Workdo\EIMS\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Workdo\EIMS\Models\EimsInvoice;
use Workdo\EIMS\Models\EimsTenantSetting;
use Workdo\EIMS\Services\EimsClientService;
use Workdo\EIMS\Services\EimsPayloadBuilderService;
use Workdo\EIMS\Services\EimsAuditLoggingService;
use Workdo\EIMS\Services\EimsQrCodeService;
use Workdo\EIMS\Services\EimsOfflineResiliencyService;
use Workdo\EIMS\Services\EimsNetworkException;
use App\Models\SalesInvoice;
use Carbon\Carbon;
use Throwable;

class EimsInvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $query = EimsInvoice::query()->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('buyer_type')) {
            $query->where('buyer_type', $request->buyer_type);
        }

        if ($request->filled('document_type')) {
            $query->where('document_type', $request->document_type);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('buyer_name', 'like', "%{$search}%")
                    ->orWhere('buyer_tin', 'like', "%{$search}%")
                    ->orWhere('irn', 'like', "%{$search}%");
            });
        }

        $invoices = $query->paginate(15)->withQueryString();

        return Inertia::render('eims/Invoices', [
            'invoices' => $invoices,
            'filters' => $request->only(['status', 'buyer_type', 'document_type', 'search']),
        ]);
    }

    public function show(int $id): Response
    {
        $invoice = EimsInvoice::with(['salesInvoice.items.product', 'auditLogs.user', 'offlineQueue'])->findOrFail($id);

        return Inertia::render('eims/InvoiceDetail', [
            'invoice' => $invoice,
        ]);
    }

    public function registerManual(
        int $salesInvoiceId,
        EimsClientService $client,
        EimsPayloadBuilderService $payloadBuilder,
        EimsAuditLoggingService $audit,
        EimsQrCodeService $qrService,
        EimsOfflineResiliencyService $offlineService
    ): RedirectResponse {
        $salesInvoice = SalesInvoice::findOrFail($salesInvoiceId);
        $tenantId = creatorId();

        $setting = EimsTenantSetting::getForTenant($tenantId);
        if (!$setting || empty($setting->tin)) {
            return redirect()->back()->with('error', 'Please configure your EIMS MoR Settings (TIN & Branch) before registering.');
        }

        try {
            $payload = $payloadBuilder->buildInvoicePayload($salesInvoice, $setting);

            $buyer = $payload['buyer'] ?? [];
            $summary = $payload['summary'] ?? [];
            $payment = $payload['payment'] ?? [];

            $eimsInvoice = EimsInvoice::updateOrCreate(
                ['sales_invoice_id' => $salesInvoice->id],
                [
                    'created_by' => $tenantId,
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
                ]
            );

            try {
                $clearance = $client->registerInvoice($payload, $setting);

                if (!empty($clearance['success']) && ($clearance['status'] === 'CLEARED')) {
                    $qrCodeSvg = $qrService->generateSvg($clearance['qrCodeData'] ?? $clearance['irn']);

                    $eimsInvoice->update([
                        'status' => EimsInvoice::STATUS_CLEARED,
                        'irn' => $clearance['irn'],
                        'rrn' => $clearance['rrn'] ?? null,
                        'qr_code_data' => $clearance['qrCodeData'] ?? null,
                        'qr_code_image' => $qrCodeSvg,
                        'cleared_at' => Carbon::now(),
                        'eims_response' => $clearance,
                        'error_message' => null,
                    ]);

                    $audit->log('REGISTER', $payload, $eimsInvoice->id, $tenantId, [
                        'irn' => $clearance['irn'],
                        'source' => 'manual_action',
                    ]);

                    return redirect()->back()->with('success', 'Invoice registered and cleared with MoR successfully! IRN: ' . substr($clearance['irn'], 0, 16) . '...');
                }

                $eimsInvoice->update([
                    'status' => EimsInvoice::STATUS_FAILED,
                    'error_message' => $clearance['error'] ?? 'Clearance rejected by gateway',
                    'eims_response' => $clearance,
                ]);

                return redirect()->back()->with('error', 'MoR Clearance failed: ' . ($clearance['error'] ?? 'Unknown error'));
            } catch (EimsNetworkException $ne) {
                if ($setting->offline_resiliency_enabled) {
                    $offlineService->enqueueOfflineInvoice($eimsInvoice, $payload, $tenantId);
                    return redirect()->back()->with('warning', 'Network unreachable. Invoice queued locally for 72-hour offline sync per Directive 1142.');
                }
                return redirect()->back()->with('error', 'Network failure: ' . $ne->getMessage());
            }
        } catch (Throwable $e) {
            return redirect()->back()->with('error', 'Failed to generate clearance payload: ' . $e->getMessage());
        }
    }

    public function cancel(
        int $id,
        Request $request,
        EimsClientService $client,
        EimsAuditLoggingService $audit
    ): RedirectResponse {
        $validated = $request->validate([
            'reason_code' => 'required|integer|in:1,2,3,4',
            'remark' => 'nullable|string|max:100',
        ]);

        $invoice = EimsInvoice::findOrFail($id);
        $setting = EimsTenantSetting::getForTenant($invoice->created_by);

        if (!$setting) {
            return redirect()->back()->with('error', 'Tenant EIMS settings not found.');
        }

        if (empty($invoice->irn) || $invoice->status !== EimsInvoice::STATUS_CLEARED) {
            return redirect()->back()->with('error', 'Only cleared invoices with an IRN can be cancelled.');
        }

        $result = $client->cancelInvoice(
            $invoice->irn,
            (int) $validated['reason_code'],
            $validated['remark'] ?? null,
            $setting
        );

        if (!empty($result['success'])) {
            $invoice->update([
                'status' => EimsInvoice::STATUS_CANCELLED,
                'cancel_reason_code' => $validated['reason_code'],
                'cancel_remark' => $validated['remark'] ?? null,
                'cancelled_at' => Carbon::now(),
            ]);

            $audit->log('CANCEL', [
                'irn' => $invoice->irn,
                'reason_code' => $validated['reason_code'],
                'remark' => $validated['remark'] ?? null,
            ], $invoice->id, $invoice->created_by);

            return redirect()->back()->with('success', 'Invoice ' . $invoice->invoice_number . ' cancelled successfully in EIMS.');
        }

        return redirect()->back()->with('error', 'Failed to cancel invoice: ' . ($result['error'] ?? 'Gateway rejected cancellation.'));
    }

    public function verify(int $id, EimsClientService $client): JsonResponse
    {
        $invoice = EimsInvoice::findOrFail($id);
        $setting = EimsTenantSetting::getForTenant($invoice->created_by);

        if (empty($invoice->irn)) {
            return response()->json(['success' => false, 'error' => 'No IRN recorded for this invoice'], 400);
        }

        $result = $client->verifyInvoice($invoice->irn, $setting);
        return response()->json($result);
    }
}
