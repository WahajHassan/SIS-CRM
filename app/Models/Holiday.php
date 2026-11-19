<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/** A public holiday, or a run of them. */
class Holiday extends Model
{
    use BelongsToCompany;

    /**
     * Anything the attendance calculator worked out from these rows is out of
     * date the moment one of them changes.
     */
    protected static function booted(): void
    {
        static::saved(fn () => \App\Services\AttendanceCalculator::$generation++);
        static::deleted(fn () => \App\Services\AttendanceCalculator::$generation++);
    }

    protected $fillable = ['company_id', 'name', 'start_date', 'end_date', 'is_active', 'notes'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Compared as dates, so a stored midnight time cannot shift the boundary. */
    public function scopeCovering($query, string|Carbon $date)
    {
        $date = $date instanceof Carbon ? $date->toDateString() : $date;

        return $query->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date);
    }

    public function days(): int
    {
        return (int) $this->start_date->diffInDays($this->end_date) + 1;
    }

    public function datesText(): string
    {
        return $this->start_date->equalTo($this->end_date)
            ? $this->start_date->format('d M Y')
            : $this->start_date->format('d M') . ' – ' . $this->end_date->format('d M Y');
    }
}
