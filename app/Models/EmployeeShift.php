<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Which shift an employee was on, and when.
 *
 * Kept as a run of dated rows so a month already worked keeps being judged
 * against the shift that was in force at the time.
 */
class EmployeeShift extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'user_id', 'shift_id', 'effective_from', 'effective_to', 'assigned_by',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * The assignment covering a given date, if any.
     *
     * Compared as dates rather than as strings: a date column can come back
     * carrying a midnight time, and '2026-06-01 00:00:00' <= '2026-06-01' is
     * false as text — which quietly lost the first day of every assignment.
     */
    public function scopeCovering($query, string|Carbon $date)
    {
        $date = $date instanceof Carbon ? $date->toDateString() : $date;

        return $query->whereDate('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date);
            });
    }

    /**
     * Put somebody on a shift from a date, closing whatever they were on the day
     * before — so the history has no overlaps and no gaps.
     */
    public static function assign(User $user, Shift $shift, string|Carbon $from, ?User $by = null): self
    {
        $from = $from instanceof Carbon ? $from->copy()->startOfDay() : Carbon::parse($from)->startOfDay();

        static::where('user_id', $user->id)
            ->where(function ($q) use ($from) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $from->toDateString());
            })
            ->get()
            ->each(function (self $row) use ($from) {
                if ($row->effective_from->gte($from)) {
                    $row->delete();                                   // fully replaced
                } else {
                    $row->update(['effective_to' => $from->copy()->subDay()->toDateString()]);
                }
            });

        return static::create([
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'effective_from' => $from->toDateString(),
            'assigned_by' => $by?->id ?? auth()->id(),
        ]);
    }
}
