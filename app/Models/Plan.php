<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Classes\Module;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'number_of_users',
        'status',
        'free_plan',
        'modules',
        'package_price_yearly',
        'package_price_monthly',
        'storage_limit',
        'trial',
        'trial_days',
        'created_by',
    ];

    protected $casts = [
        'status' => 'boolean',
        'free_plan' => 'boolean',
        'trial' => 'boolean',
        'modules' => 'array',
        'package_price_yearly' => 'decimal:2',
        'package_price_monthly' => 'decimal:2',
        'storage_limit' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function getAvailableModulesForUser($userId)
    {
        return self::getUserSubscriptionModules($userId);
    }

    public static function getUserSubscriptionModules($userId = null)
    {
        return ActivatedModule($userId);
    }
}