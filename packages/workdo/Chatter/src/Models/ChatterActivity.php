<?php

namespace Workdo\Chatter\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantScoped;
use App\Models\User;

class ChatterActivity extends Model
{
    use TenantScoped;

    protected $table = 'chatter_activities';

    protected $fillable = [
        'created_by',
        'model_type',
        'model_id',
        'user_id',
        'assigned_to',
        'activity_type', // 'call', 'email', 'meeting', 'todo', 'review'
        'title',
        'due_date',
        'status', // 'pending', 'completed'
        'notes',
        'feedback',
        'completed_at',
    ];

    protected $casts = [
        'due_date'     => 'date',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to', 'id');
    }

    /**
     * Check if this activity is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->status === 'pending'
            && $this->due_date !== null
            && $this->due_date->isPast();
    }
}
