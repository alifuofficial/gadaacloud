<?php

namespace Workdo\Lead\Models;

use App\Models\User;
use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class UserLead extends Model
{
    use TenantScoped;

    protected $fillable = [
        'user_id',
        'lead_id',
        'created_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }
}