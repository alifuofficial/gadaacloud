<?php

namespace Workdo\Chatter\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class ChatterActivity extends Model
{
    protected $table = 'chatter_activities';

    protected $fillable = [
        'created_by',
        'model_type',
        'model_id',
        'user_id',
        'assigned_to',
        'activity_type', // 'call', 'email', 'meeting', 'todo'
        'title',
        'due_date',
        'status', // 'pending', 'completed'
        'notes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to', 'id');
    }
}
