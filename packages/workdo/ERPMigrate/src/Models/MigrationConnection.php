<?php

namespace Workdo\ERPMigrate\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MigrationConnection extends Model
{
    use TenantScoped;

    protected $table = 'erp_migration_connections';

    protected $fillable = [
        'name',
        'source_type',
        'host',
        'database_name',
        'username',
        'api_key',
        'api_secret',
        'connection_status',
        'last_tested_at',
        'last_error',
        'created_by',
        'creator_id',
    ];

    protected $casts = [
        'last_tested_at' => 'datetime',
    ];

    public function batches(): HasMany
    {
        return $this->hasMany(MigrationBatch::class, 'connection_id');
    }
}
