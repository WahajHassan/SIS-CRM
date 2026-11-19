<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Every rule the HR panel judges a day by, in one editable row.
 *
 * Nothing in the attendance or leave code is allowed to hard-code a number; it
 * asks here. A company that has never opened the settings page still gets a
 * working set of defaults, which are the ones agreed for this office.
 */
class HrSetting extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'weekly_offs', 'early_checkin_minutes', 'early_off_minutes',
        'half_day_enabled', 'half_day_minutes', 'lates_per_off', 'free_signups_per_day', 'late_deduction_order',
        'require_biometric_clock',
        'early_off_counts_toward_lates', 'auto_absent_enabled', 'leave_approval_flow',
        'sick_leave_requires_attachment',
    ];

    protected $casts = [
        'early_checkin_minutes' => 'integer',
        'early_off_minutes' => 'integer',
        'half_day_enabled' => 'boolean',
        'half_day_minutes' => 'integer',
        'lates_per_off' => 'integer',
        'require_biometric_clock' => 'boolean',
        'early_off_counts_toward_lates' => 'boolean',
        'auto_absent_enabled' => 'boolean',
        'sick_leave_requires_attachment' => 'boolean',
    ];

    public const DEDUCT_CASUAL_THEN_UNPAID = 'casual_then_unpaid';
    public const DEDUCT_UNPAID = 'unpaid';

    public const FLOW_TL_THEN_HR = 'tl_then_hr';
    public const FLOW_HR_ONLY = 'hr_only';

    /**
     * The rules in force, saved or not.
     *
     * Returns an unsaved model with the defaults when nothing has been set, so
     * the panel works on day one without a seeder having run.
     */
    public static function current(): self
    {
        $existing = static::query()->first();

        if ($existing) {
            return $existing;
        }

        return new static([
            'company_id' => auth()->check() ? auth()->user()->company_id : null,
            'weekly_offs' => '6,0',                       // Saturday and Sunday
            'early_checkin_minutes' => 120,
            'early_off_minutes' => 15,
            'half_day_enabled' => true,
            'half_day_minutes' => 240,
            'lates_per_off' => 3,
            'require_biometric_clock' => false,
            'free_signups_per_day' => 5,
            'late_deduction_order' => self::DEDUCT_CASUAL_THEN_UNPAID,
            'early_off_counts_toward_lates' => false,
            'auto_absent_enabled' => true,
            'leave_approval_flow' => self::FLOW_TL_THEN_HR,
            'sick_leave_requires_attachment' => false,
        ]);
    }

    /** Day numbers that are weekly offs, as Carbon counts them (0 = Sunday). */
    public function weeklyOffDays(): array
    {
        return collect(explode(',', (string) $this->weekly_offs))
            ->map(fn ($d) => trim($d))
            ->filter(fn ($d) => $d !== '' && is_numeric($d))
            ->map(fn ($d) => (int) $d)
            ->unique()
            ->values()
            ->all();
    }

    public function isWeeklyOff(Carbon $date): bool
    {
        return in_array((int) $date->dayOfWeek, $this->weeklyOffDays(), true);
    }

    public function requiresTeamLeadApproval(): bool
    {
        return $this->leave_approval_flow === self::FLOW_TL_THEN_HR;
    }

    public function weeklyOffsText(): string
    {
        $names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $days = array_map(fn ($d) => $names[$d] ?? $d, $this->weeklyOffDays());

        return $days ? implode(', ', $days) : 'none';
    }
}
