<?php

namespace Workdo\SupportTicket\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantScoped;

class Contact extends Model
{
    use HasFactory, TenantScoped;
    protected $table = 'support_contacts';
    
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'subject',
        'message',
        'creator_id',
        'created_by'
    ];
}