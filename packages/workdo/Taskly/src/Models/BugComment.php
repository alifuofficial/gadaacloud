<?php

namespace Workdo\Taskly\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Traits\TenantScoped;

class BugComment extends Model
{
    use TenantScoped;

    protected $fillable = [
        'bug_id',
        'user_id',
        'comment',
        'created_by',
    ];

    public function bug()
    {
        return $this->belongsTo(ProjectBug::class, 'bug_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
