<?php

namespace Workdo\Chatter\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantScoped;

class ChatterAttachment extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'chatter_attachments';

    protected $fillable = [
        'created_by',
        'chatter_message_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
    ];
}
