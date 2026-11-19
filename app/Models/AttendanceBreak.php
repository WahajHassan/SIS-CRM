<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Traits\BelongsToCompany;

class AttendanceBreak extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'attendance_id',
        'type',
        'break_start',
        'break_end',
    ];

    protected $casts = [
        'break_start' => 'datetime',
        'break_end' => 'datetime',
    ];

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    /**
     * Get break duration in minutes.
     */
    public function durationInMinutes()
    {
        if (!$this->break_start) {
            return 0;
        }

        $end = $this->break_end ?: \Carbon\Carbon::now();
        return round($this->break_start->diffInMinutes($end));
    }

    /**
     * Get break duration in seconds.
     */
    public function durationInSeconds()
    {
        if (!$this->break_start) {
            return 0;
        }

        $end = $this->break_end ?: \Carbon\Carbon::now();
        return max(0, $this->break_start->diffInSeconds($end));
    }

    /**
     * Get break duration text (e.g., 5m 12s or 32s).
     */
    public function durationText()
    {
        $seconds = $this->durationInSeconds();
        if ($seconds <= 0) {
            return '0s';
        }

        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;

        if ($minutes > 0) {
            return "{$minutes}m {$secs}s";
        }
        return "{$secs}s";
    }
}
