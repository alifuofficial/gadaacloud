<?php

namespace Workdo\ERPMigrate\Models;

use App\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class MigrationFieldMapping extends Model
{
    use TenantScoped;

    protected $table = 'erp_migration_field_mappings';

    protected $fillable = [
        'template_name',
        'source_erp',
        'entity_type',
        'mappings',
        'is_system',
        'created_by',
    ];

    protected $casts = [
        'mappings' => 'array',
        'is_system' => 'boolean',
    ];
}
