<?php

namespace Workdo\Chatter\Models;

use Illuminate\Database\Eloquent\Model;

class ChatterAttachment extends Model
{
    protected $table = 'chatter_attachments';

    protected $fillable = [
        'chatter_message_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
    ];
}
