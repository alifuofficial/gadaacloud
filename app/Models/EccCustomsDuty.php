<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantScoped;

class EccCustomsDuty extends Model
{
    use TenantScoped;

    protected $table = 'ecc_customs_duties';

    protected $fillable = [
        'created_by',
        'hs_code',
        'description',
        'cif_value_etb',
        'duty_rate_percent',
        'excise_rate_percent',
        'vat_percent',
        'surtax_percent',
        'withholding_percent',
        'total_duty_payable_etb',
    ];

    protected $casts = [
        'cif_value_etb'          => 'decimal:2',
        'duty_rate_percent'      => 'decimal:2',
        'excise_rate_percent'    => 'decimal:2',
        'vat_percent'            => 'decimal:2',
        'surtax_percent'         => 'decimal:2',
        'withholding_percent'    => 'decimal:2',
        'total_duty_payable_etb' => 'decimal:2',
    ];
}
