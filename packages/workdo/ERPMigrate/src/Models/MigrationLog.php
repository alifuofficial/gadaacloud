<?php

namespace Workdo\ERPMigrate\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MigrationLog extends Model
{
    use TenantScoped;

    protected $table = 'erp_migration_logs';

    protected $fillable = [
        'batch_id',
        'row_index',
        'record_identifier',
        'status',
        'target_model',
        'target_id',
        'message',
        'raw_data',
        'created_by',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'row_index' => 'integer',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(MigrationBatch::class, 'batch_id');
    }
}
