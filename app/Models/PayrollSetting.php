<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToCompany;

class PayrollSetting extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'shift_start',
        'grace_minutes',
        'required_minutes',
        'half_day_minutes',
        'weekly_offs',
        'lates_per_deduction',
    ];

    protected $casts = [
        'grace_minutes' => 'integer',
        'required_minutes' => 'integer',
        'half_day_minutes' => 'integer',
        'lates_per_deduction' => 'integer',
    ];

    /**
     * The rules for the company being viewed, falling back to the defaults so the
     * salary table works on day one, before anyone has saved anything.
     */
    public static function current(): self
    {
        $companyId = auth()->check() ? auth()->user()->company_id : null;

        $existing = static::query()->first();
        if ($existing) {
            return $existing;
        }

        return new static([
            'company_id' => $companyId,
            'shift_start' => '20:00',
            'grace_minutes' => 15,
            'required_minutes' => 480,
            'half_day_minutes' => 240,
            'weekly_offs' => '0',
            'lates_per_deduction' => 3,
        ]);
    }

    /** Day numbers that are weekly offs, as Carbon uses them (0 = Sunday). */
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

    public function isWeeklyOff(\Carbon\Carbon $date): bool
    {
        return in_array((int) $date->dayOfWeek, $this->weeklyOffDays(), true);
    }
}
