<?php

namespace Workdo\ERPMigrate\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MigrationBatch extends Model
{
    use TenantScoped;

    protected $table = 'erp_migration_batches';

    protected $fillable = [
        'batch_code',
        'source_type',
        'entity_type',
        'connection_id',
        'original_filename',
        'total_records',
        'imported_count',
        'failed_count',
        'skipped_count',
        'status',
        'field_mappings',
        'validation_summary',
        'created_entity_ids',
        'created_by',
        'creator_id',
        'completed_at',
    ];

    protected $casts = [
        'field_mappings' => 'array',
        'validation_summary' => 'array',
        'created_entity_ids' => 'array',
        'completed_at' => 'datetime',
        'total_records' => 'integer',
        'imported_count' => 'integer',
        'failed_count' => 'integer',
        'skipped_count' => 'integer',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(MigrationConnection::class, 'connection_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MigrationLog::class, 'batch_id')->orderBy('row_index', 'asc');
    }
}
