<?php

namespace Workdo\Lead\Models;

use App\Models\User;
use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class UserDeal extends Model
{
    use TenantScoped;

    protected $fillable = [
        'user_id',
        'deal_id',
        'created_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function deal()
    {
        return $this->belongsTo(Deal::class, 'deal_id');
    }
}