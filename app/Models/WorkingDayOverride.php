<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * One person, one date, against the usual pattern: working on an off day, or off
 * on a working one.
 */
class WorkingDayOverride extends Model
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

    protected $fillable = ['company_id', 'user_id', 'date', 'is_working', 'reason', 'created_by'];

    protected $casts = [
        'date' => 'date',
        'is_working' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
