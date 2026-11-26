<?php

namespace Workdo\SupportTicket\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\TenantScoped;

class KnowledgeBase extends Model
{
    use HasFactory, TenantScoped;
    protected $table = 'support_ticket_knowledge_bases';
    
    protected $fillable = [
        'title',
        'description',
        'content',
        'category',
        'creator_id',
        'created_by'
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBaseCategory::class, 'category');
    }
}