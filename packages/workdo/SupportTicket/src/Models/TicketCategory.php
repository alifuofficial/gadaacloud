<?php

namespace Workdo\SupportTicket\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\TenantScoped;

class TicketCategory extends Model
{
    use HasFactory, TenantScoped;
    protected $fillable = [
        'name',
        'color',
        'creator_id',
        'created_by'
    ];

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'category');
    }
}