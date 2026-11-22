<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantScoped;

class EcxExportContract extends Model
{
    use TenantScoped;

    protected $table = 'ecx_export_contracts';

    protected $fillable = [
        'created_by',
        'contract_number',
        'commodity_type',
        'ecx_grade',
        'quantity_metric_tons',
        'contract_value_usd',
        'destination_country',
        'lc_status',
        'mep_rate_usd',
        'notes',
    ];

    protected $casts = [
        'quantity_metric_tons' => 'decimal:2',
        'contract_value_usd'   => 'decimal:2',
        'mep_rate_usd'         => 'decimal:2',
    ];
}
