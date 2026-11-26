<?php

namespace Workdo\SupportTicket\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\TenantScoped;

class Faq extends Model
{
    use HasFactory, TenantScoped;
    protected $table = 'support_ticket_faqs';
    protected $fillable = [
        'title',
        'description',
        'creator_id',
        'created_by'
    ];
}