<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * A working pattern: when the day starts, when it ends, and how much lateness is
 * forgiven.
 *
 * Shifts here run through midnight (19:00–04:00), so almost everything about a
 * shift has to be asked as "for which shift date?" rather than "at what time?".
 * The helpers below take that date and hand back real moments, which is what the
 * attendance rules then compare against.
 */
class Shift extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'start_time', 'end_time',
        'grace_minutes', 'break_minutes', 'crosses_midnight', 'is_active',
    ];

    protected $casts = [
        'grace_minutes' => 'integer',
        'break_minutes' => 'integer',
        'crosses_midnight' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Whether a shift crosses midnight is a fact about its times, not a
        // choice, so it is worked out on the way in rather than asked for.
        static::saving(function (Shift $shift) {
            $shift->crosses_midnight = $shift->endsNextDay();
        });
    }

    public function assignments()
    {
        return $this->hasMany(EmployeeShift::class);
    }

    public function endsNextDay(): bool
    {
        return $this->minutesOfDay($this->end_time) <= $this->minutesOfDay($this->start_time);
    }

    /** When this shift begins on a given shift date. */
    public function startOn(string|Carbon $date): Carbon
    {
        return $this->at($date, $this->start_time);
    }

    /** When it ends — the next calendar day if it runs through midnight. */
    public function endOn(string|Carbon $date): Carbon
    {
        $end = $this->at($date, $this->end_time);

        return $this->endsNextDay() ? $end->addDay() : $end;
    }

    /** The latest arrival that is still not late. */
    public function graceEndsOn(string|Carbon $date): Carbon
    {
        return $this->startOn($date)->addMinutes((int) $this->grace_minutes);
    }

    /** How long the shift is, in minutes. */
    public function lengthMinutes(string|Carbon|null $date = null): int
    {
        $date = $date ?: Carbon::today();

        return (int) $this->startOn($date)->diffInMinutes($this->endOn($date));
    }

    /** Hours actually owed: the shift less its break. */
    public function workingMinutes(string|Carbon|null $date = null): int
    {
        return max(0, $this->lengthMinutes($date) - (int) $this->break_minutes);
    }

    public function breakText(): string
    {
        $minutes = (int) $this->break_minutes;

        if ($minutes <= 0) {
            return 'no break';
        }

        return $minutes >= 60
            ? rtrim(rtrim(number_format($minutes / 60, 1), '0'), '.') . 'h break'
            : $minutes . 'm break';
    }

    public function timesText(): string
    {
        return Carbon::parse($this->start_time)->format('h:i A') . ' – ' . Carbon::parse($this->end_time)->format('h:i A');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    private function at(string|Carbon $date, string $time): Carbon
    {
        [$h, $m] = array_pad(explode(':', $time), 2, '0');

        return Carbon::parse($date instanceof Carbon ? $date->toDateString() : $date)
            ->setTime((int) $h, (int) $m, 0);
    }

    private function minutesOfDay(?string $time): int
    {
        [$h, $m] = array_pad(explode(':', (string) $time), 2, '0');

        return ((int) $h) * 60 + (int) $m;
    }
}
