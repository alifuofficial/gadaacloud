<?php

namespace Workdo\Chatter\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantScoped;
use App\Models\User;

class ChatterFollower extends Model
{
    use TenantScoped;

    protected $table = 'chatter_followers';

    protected $fillable = [
        'created_by',
        'model_type',
        'model_id',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
