<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'code', 'annual_quota', 'monthly_quota', 'is_paid',
        'requires_attachment', 'is_system', 'unit', 'max_hours', 'colour', 'is_active',
    ];

    protected $casts = [
        'annual_quota' => 'integer',
        'monthly_quota' => 'integer',
        'is_paid' => 'boolean',
        'requires_attachment' => 'boolean',
        'is_system' => 'boolean',
        'max_hours' => 'float',
        'is_active' => 'boolean',
    ];

    /** The code of the type the software creates for late-deducted offs. */
    public const LATE_DEDUCTED_OFF = 'LDO';
    public const CASUAL = 'CL';
    public const UNPAID = 'UL';
    public const SHORT = 'SHL';

    public function departmentQuotas()
    {
        return $this->hasMany(LeaveTypeDepartmentQuota::class);
    }

    public function balances()
    {
        return $this->hasMany(LeaveBalance::class);
    }

    /** Short leave is counted in hours; everything else in days. */
    public function isHourly(): bool
    {
        return $this->unit === 'hours';
    }

    /**
     * The allowance for somebody in a given department, which may differ from
     * the company default.
     *
     * @return array{yearly: int, monthly: int}
     */
    public function quotaFor(?int $departmentId): array
    {
        $override = $departmentId
            ? $this->departmentQuotas->firstWhere('department_id', $departmentId)
            : null;

        return [
            'yearly' => (int) ($override->yearly_quota ?? $this->annual_quota),
            'monthly' => (int) ($override->monthly_limit ?? $this->monthly_quota),
        ];
    }

    public function scopeApplicable($query)
    {
        // What an employee may actually ask for: system types are not applied for.
        return $query->where('is_active', true)->where('is_system', false);
    }

    /** How this type's allowance reads in a sentence. */
    public function allowanceText(): string
    {
        $parts = [];

        if ($this->monthly_quota > 0) {
            $parts[] = $this->monthly_quota . ' a month';
        }
        if ($this->annual_quota > 0) {
            $parts[] = $this->annual_quota . ' a year';
        }

        return $parts ? implode(', ', $parts) : 'no limit set';
    }

    public function requests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * The kinds of leave a company starts with, created the first time the leave
     * screen is opened so it is never an empty page with nothing to do.
     */
    public static function seedDefaults(): void
    {
        if (static::query()->exists()) {
            return;
        }

        $defaults = [
            ['name' => 'Casual Leave', 'annual_quota' => 10, 'monthly_quota' => 1, 'is_paid' => true, 'colour' => '#6366f1'],
            ['name' => 'Sick Leave', 'annual_quota' => 8, 'monthly_quota' => 1, 'is_paid' => true, 'colour' => '#10b981'],
            ['name' => 'Annual Leave', 'annual_quota' => 14, 'monthly_quota' => 0, 'is_paid' => true, 'colour' => '#f59e0b'],
            ['name' => 'Unpaid Leave', 'annual_quota' => 0, 'monthly_quota' => 0, 'is_paid' => false, 'colour' => '#ef4444'],
        ];

        foreach ($defaults as $type) {
            static::create($type);
        }
    }
}
