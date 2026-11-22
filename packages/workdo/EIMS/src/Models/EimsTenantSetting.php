<?php

namespace Workdo\EIMS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\TenantScoped;

class EimsTenantSetting extends Model
{
    use HasFactory, TenantScoped;

    protected $table = 'eims_tenant_settings';

    protected $fillable = [
        'created_by',
        'tin',
        'vat_number',
        'legal_name',
        'trade_name',
        'branch_code',
        'device_id',
        'environment',
        'api_endpoint',
        'api_key',
        'api_secret',
        'offline_resiliency_enabled',
        'auto_clear_on_post',
        'custom_fields',
    ];

    protected function casts(): array
    {
        return [
            'offline_resiliency_enabled' => 'boolean',
            'auto_clear_on_post' => 'boolean',
            'custom_fields' => 'array',
            'api_key' => 'encrypted',
            'api_secret' => 'encrypted',
        ];
    }

    public static function getForTenant(?int $tenantId = null): ?self
    {
        $id = $tenantId ?? (auth()->check() ? creatorId() : null);
        if (!$id) {
            return null;
        }

        return static::withoutGlobalScope('tenant')
            ->where('created_by', $id)
            ->first();
    }
}
