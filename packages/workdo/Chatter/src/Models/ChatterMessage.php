<?php

namespace Workdo\Chatter\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class ChatterMessage extends Model
{
    protected $table = 'chatter_messages';

    protected $fillable = [
        'created_by',
        'model_type',
        'model_id',
        'user_id',
        'message',
        'type', // 'note', 'message', 'activity', 'diff'
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function attachments()
    {
        return $this->hasMany(ChatterAttachment::class, 'chatter_message_id', 'id');
    }
}
