<?php

namespace Workdo\EIMS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use App\Traits\TenantScoped;

class EimsAuditLog extends Model
{
    use TenantScoped;

    protected $table = 'eims_audit_logs';

    protected $fillable = [
        'created_by',
        'user_id',
        'eims_invoice_id',
        'action',
        'previous_hash',
        'payload_hash',
        'signature',
        'ip_address',
        'user_agent',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
        ];
    }

    public function eimsInvoice(): BelongsTo
    {
        return $this->belongsTo(EimsInvoice::class, 'eims_invoice_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
