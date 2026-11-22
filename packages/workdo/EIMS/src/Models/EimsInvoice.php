<?php

namespace Workdo\EIMS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Traits\TenantScoped;
use App\Models\SalesInvoice;

class EimsInvoice extends Model
{
    use TenantScoped;

    protected $table = 'eims_invoices';

    const STATUS_PENDING = 'PENDING';
    const STATUS_CLEARED = 'CLEARED';
    const STATUS_PENDING_OFFLINE = 'PENDING_OFFLINE';
    const STATUS_CANCELLED = 'CANCELLED';
    const STATUS_FAILED = 'FAILED';

    const CANCEL_REASONS = [
        1 => 'Duplicate Invoice',
        2 => 'Data Entry Mistake',
        3 => 'Order Cancelled by Customer',
        4 => 'Other Regulatory Reason',
    ];

    protected $fillable = [
        'created_by',
        'sales_invoice_id',
        'invoice_number',
        'document_type',
        'previous_irn',
        'irn',
        'rrn',
        'fiscal_counter',
        'qr_code_data',
        'qr_code_image',
        'buyer_type',
        'buyer_tin',
        'buyer_name',
        'buyer_id_type',
        'buyer_id_number',
        'currency',
        'pre_tax_amount',
        'tax_amount',
        'excise_tax_amount',
        'discount_amount',
        'total_amount',
        'payment_mode',
        'payment_term',
        'status',
        'cancel_reason_code',
        'cancel_remark',
        'cancelled_at',
        'cleared_at',
        'eims_payload',
        'eims_response',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'pre_tax_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'excise_tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'eims_payload' => 'array',
            'eims_response' => 'array',
            'cancelled_at' => 'datetime',
            'cleared_at' => 'datetime',
        ];
    }

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    public function offlineQueue(): HasOne
    {
        return $this->hasOne(EimsOfflineQueue::class, 'eims_invoice_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(EimsAuditLog::class, 'eims_invoice_id');
    }

    public function getCancelReasonTextAttribute(): ?string
    {
        return $this->cancel_reason_code ? (self::CANCEL_REASONS[$this->cancel_reason_code] ?? 'Other') : null;
    }
}
