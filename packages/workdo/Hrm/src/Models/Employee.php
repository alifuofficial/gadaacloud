<?php

namespace Workdo\Hrm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use Workdo\Hrm\Models\Branch;
use Workdo\Hrm\Models\Department;
use Workdo\Hrm\Models\Designation;
use App\Traits\TenantScoped;

class Employee extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'employee_id',
        'date_of_birth',
        'gender',
        'shift_id',
        'date_of_joining',
        'employment_type',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'country',
        'postal_code',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_number',
        'bank_name',
        'account_holder_name',
        'account_number',
        'bank_identifier_code',
        'bank_branch',
        'tax_payer_id',
        'basic_salary',
        'is_pension_applicable',
        'is_tax_exempt',
        'hours_per_day',
        'days_per_week',
        'rate_per_hour',
        'user_id',
        'branch_id',
        'department_id',
        'designation_id',
        'manager_id',
        'creator_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'date_of_joining' => 'date',
            'is_pension_applicable' => 'boolean',
            'is_tax_exempt' => 'boolean',
        ];
    }

    /**
     * Safely resolve integer shift ID regardless of whether `shift` relation is eager-loaded.
     */
    public function getShiftIdValue(): ?int
    {
        $raw = $this->getRawOriginal('shift');
        if ($raw !== null && is_numeric($raw)) {
            return (int) $raw;
        }
        $attr = $this->attributes['shift'] ?? $this->attributes['shift_id'] ?? null;
        return is_numeric($attr) ? (int) $attr : null;
    }

    /**
     * Compute loaded hourly labor cost including 11% employer pension when applicable (for Project/EVM costing).
     */
    public function getLoadedHourlyCost(bool $includeEmployerPension = true): float
    {
        $basic = (float) ($this->basic_salary ?? 0);
        if ($basic <= 0) {
            return round((float) ($this->rate_per_hour ?? 0), 2);
        }
        $employerPension = ($includeEmployerPension && ($this->is_pension_applicable ?? true))
            ? round($basic * 0.11, 2)
            : 0.0;
        $monthlyHours = max(1.0, ((float) ($this->hours_per_day ?? 8)) * ((float) ($this->days_per_week ?? 6)) * 4.333);
        if ($monthlyHours <= 0 || $this->hours_per_day === null) {
            $monthlyHours = 208.0; // FDRE Proclamation 1156/2019 standard monthly divisor
        }
        return round(($basic + $employerPension) / $monthlyHours, 2);
    }



    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift', 'id');
    }

    public function manager()
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function subordinates()
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    public static function generateEmployeeId()
    {
        $prefix = 'EMP';
        $year = date('Y');
        $lastEmployee = self::where('employee_id', 'like', $prefix . $year . '%')
            ->orderBy('employee_id', 'desc')
            ->first();

        if ($lastEmployee) {
            $lastNumber = (int) substr($lastEmployee->employee_id, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . $year . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }
}
