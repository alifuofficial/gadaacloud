<?php

namespace Workdo\SupportTicket\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\TenantScoped;

class TicketFieldValue extends Model
{
    use HasFactory, TenantScoped;
    protected $fillable = [
        'record_id',
        'field_id',
        'value'
    ];

    public function field(): BelongsTo
    {
        return $this->belongsTo(TicketField::class, 'field_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'record_id');
    }
}