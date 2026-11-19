<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Something called out to the whole floor: rush hour, a break, prayer time.
 *
 * Rush hour used to be the only one and it lived in a controller — an hour
 * long, one message, no way to have a second kind. They are rows now, which is
 * the only difference that matters: a floor that wants a forty-five minute
 * lunch adds a row rather than asking for a change to the code.
 *
 * What is running is kept in the cache rather than here, the way rush hour
 * always was. A moment that ended is of no interest afterwards, and the
 * scoreboard reads it every few seconds.
 */
class FloorMoment extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'message', 'icon', 'tone', 'minutes', 'is_active', 'position',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'minutes' => 'integer',
        'position' => 'integer',
    ];

    public function scopeInOrder($query)
    {
        return $query->orderBy('position')->orderBy('id');
    }

    public function scopeLive($query)
    {
        return $query->where('is_active', true);
    }

    /* ---- what is running right now ---------------------------------------- */

    /**
     * Where a company's running moment is kept.
     *
     * The same key rush hour has always used, so the scoreboard and everything
     * else that reads it carry on reading it.
     */
    public static function keyFor(int $companyId): string
    {
        return "rush_hour_data_{$companyId}";
    }

    /** Start it, and say when it ends. */
    public function begin(?string $message = null): array
    {
        $endsAt = now()->addMinutes(max(1, $this->minutes));

        $running = [
            'end_time' => $endsAt,
            'message' => $message ?: $this->message,
            // What it was, so the floor sees a break as a break rather than as
            // rush hour wearing a different hat.
            'moment_id' => $this->id,
            'moment' => $this->name,
            'icon' => $this->icon,
            'tone' => $this->tone,
        ];

        // The moment it should disappear, rather than a number of seconds:
        // Carbon counts differences with a sign, so asking how far the end is
        // from now gives a negative, and a negative lifetime stores nothing.
        Cache::put(self::keyFor($this->company_id), $running, $endsAt->copy()->addMinute());

        return $running;
    }

    public static function running(int $companyId): ?array
    {
        $running = Cache::get(self::keyFor($companyId));

        if (!$running || !isset($running['end_time'])) {
            return null;
        }

        $endsAt = $running['end_time'] instanceof \DateTimeInterface
            ? \Carbon\Carbon::instance($running['end_time'])
            : \Carbon\Carbon::parse($running['end_time']);

        if ($endsAt->isPast()) {
            Cache::forget(self::keyFor($companyId));
            return null;
        }

        return $running + ['ends_at' => $endsAt];
    }

    public static function stop(int $companyId): void
    {
        Cache::forget(self::keyFor($companyId));
    }
}
