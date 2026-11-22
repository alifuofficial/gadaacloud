<?php

namespace Workdo\EIMS\Services;

use Workdo\EIMS\Models\EimsAuditLog;
use Workdo\EIMS\Models\EimsTenantSetting;
use Illuminate\Support\Facades\Request;

class EimsAuditLoggingService
{
    /**
     * Record a tamper-evident INSA audit log with cryptographic chaining
     */
    public function log(
        string $action,
        array $payload,
        ?int $eimsInvoiceId = null,
        ?int $tenantId = null,
        array $extraDetails = []
    ): EimsAuditLog {
        $tenant = $tenantId ?? (auth()->check() ? creatorId() : null);

        // 1. Get the hash of the latest audit log for this tenant (Chain link)
        $lastLog = EimsAuditLog::withoutGlobalScope('tenant')
            ->where('created_by', $tenant)
            ->latest('id')
            ->first();

        $previousHash = $lastLog ? $lastLog->signature : hash('sha256', 'GENESIS_EIMS_DIRECTIVE_1142');

        // 2. Compute canonical payload hash
        $canonicalJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $payloadHash = hash('sha256', $canonicalJson);

        // 3. Obtain HMAC secret key (from tenant setting or fallback app key)
        $setting = EimsTenantSetting::getForTenant($tenant);
        $secret = $setting->api_secret ?? config('app.key', 'gadaacloud-eims-secret-key');

        // 4. Compute cryptographic HMAC-SHA256 signature chaining previousHash + payloadHash + action
        $signatureString = $previousHash . '|' . $payloadHash . '|' . $action . '|' . ($eimsInvoiceId ?? 0);
        $signature = hash_hmac('sha256', $signatureString, $secret);

        return EimsAuditLog::create([
            'created_by' => $tenant,
            'user_id' => auth()->id() ?? null,
            'eims_invoice_id' => $eimsInvoiceId,
            'action' => strtoupper($action),
            'previous_hash' => $previousHash,
            'payload_hash' => $payloadHash,
            'signature' => $signature,
            'ip_address' => Request::ip() ?? '127.0.0.1',
            'user_agent' => Request::userAgent() ?? 'GadaaCloud-EIMS/1.0',
            'details' => $extraDetails,
        ]);
    }
}
