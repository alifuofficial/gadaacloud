<?php

namespace Workdo\Performance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Traits\TenantScoped;

class FeedbackType extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'feedback_types';

    protected $fillable = [
        'name',
        'description',
        'status',
        'creator_id',
        'created_by',
    ];

    public function feedbacks()
    {
        return $this->hasMany(EmployeeFeedback::class, 'feedback_type_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
}
