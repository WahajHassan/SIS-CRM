<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

use App\Traits\BelongsToCompany;

class Attendance extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
        'status', // clocked_in, on_break, clocked_out — the live clock state
        'company_id',
        // HR panel: what the day amounted to, and how it was recorded.
        'shift_id',
        'worked_minutes',
        'late_minutes',
        'early_off_minutes',
        'day_status',
        'leave_id',
        'source',
        'remarks',
        'edited_by',
    ];

    protected $casts = [
        'date' => 'date',
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
        'worked_minutes' => 'integer',
        'late_minutes' => 'integer',
        'early_off_minutes' => 'integer',
    ];

    /* The day statuses the HR panel works in. `status` above is a different
       thing — whether the person is on the clock right now. */
    public const PRESENT = 'present';
    public const LATE = 'late';
    public const EARLY_OFF = 'early_off';
    public const LATE_EARLY_OFF = 'late_early_off';
    public const HALF_DAY = 'half_day';
    public const ABSENT = 'absent';
    public const ON_LEAVE = 'on_leave';
    public const WEEKEND = 'weekend';
    public const HOLIDAY = 'holiday';

    /** Short codes for the monthly sheet. */
    public const DAY_STATUS_CODES = [
        self::PRESENT => 'P',
        self::LATE => 'L',
        self::EARLY_OFF => 'EO',
        self::LATE_EARLY_OFF => 'LE',
        self::HALF_DAY => 'HD',
        self::ABSENT => 'A',
        self::ON_LEAVE => 'LV',
        self::WEEKEND => 'W',
        self::HOLIDAY => 'H',
    ];

    public const DAY_STATUS_LABELS = [
        self::PRESENT => 'Present',
        self::LATE => 'Late',
        self::EARLY_OFF => 'Early Off',
        self::LATE_EARLY_OFF => 'Late + Early Off',
        self::HALF_DAY => 'Half Day',
        self::ABSENT => 'Absent',
        self::ON_LEAVE => 'On Leave',
        self::WEEKEND => 'Weekend',
        self::HOLIDAY => 'Holiday',
    ];

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function leave()
    {
        return $this->belongsTo(LeaveRequest::class, 'leave_id');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'edited_by')->withTrashed();
    }

    public function auditLogs()
    {
        return $this->hasMany(AttendanceAuditLog::class);
    }

    public function dayStatusLabel(): string
    {
        return self::DAY_STATUS_LABELS[$this->day_status] ?? '—';
    }

    public function dayStatusCode(): string
    {
        return self::DAY_STATUS_CODES[$this->day_status] ?? '';
    }

    /** Whether this row was made by a person rather than clocked by the employee. */
    public function isManual(): bool
    {
        return in_array($this->source, ['manual', 'import'], true);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function breaks()
    {
        return $this->hasMany(AttendanceBreak::class);
    }

    /**
     * Calculate total net minutes worked (excluding breaks).
     */
    public function totalWorkedMinutes()
    {
        if (!$this->clock_in) {
            return 0;
        }

        $end = $this->clock_out ?: Carbon::now();
        $totalSeconds = $this->clock_in->diffInSeconds($end);

        // Deduct break durations
        $breakSeconds = 0;
        foreach ($this->breaks as $break) {
            $breakEnd = $break->break_end ?: Carbon::now();
            $breakSeconds += $break->break_start->diffInSeconds($breakEnd);
        }

        $netSeconds = max(0, $totalSeconds - $breakSeconds);
        return round($netSeconds / 60);
    }

    /**
     * Get formatted worked hours string.
     */
    public function totalWorkedHoursText()
    {
        $minutes = $this->totalWorkedMinutes();
        if ($minutes <= 0) {
            return '0m';
        }

        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        if ($hours > 0) {
            return "{$hours}h {$mins}m";
        }
        return "{$mins}m";
    }

    /**
     * Automatically checkout any active attendances older than 9 hours.
     */
    public static function autoCheckoutExpired()
    {
        // This runs from the scheduler as well, where there is no logged in user and
        // therefore no tenant. The company scope would resolve to "match nothing" and
        // silently check nobody out, so it is dropped: the sweep covers every company.
        $expiredAttendances = self::withoutGlobalScope('company')
            ->where('status', '!=', 'clocked_out')
            ->where('clock_in', '<=', Carbon::now()->subHours(9))
            ->get();

        foreach ($expiredAttendances as $attendance) {
            $checkoutTime = $attendance->clock_in->copy()->addHours(9);
            
            // If on break, close open breaks. The scope is dropped here for the same
            // reason as above: from the scheduler there is no tenant to match against.
            if ($attendance->status === 'on_break') {
                $openBreaks = $attendance->breaks()
                    ->withoutGlobalScope('company')
                    ->whereNull('break_end')
                    ->get();
                foreach ($openBreaks as $break) {
                    $breakEnd = $checkoutTime->greaterThan($break->break_start) ? $checkoutTime : $break->break_start;
                    $break->update(['break_end' => $breakEnd]);
                }
            }
            
            $attendance->update([
                'clock_out' => $checkoutTime,
                'status' => 'clocked_out',
            ]);
        }
    }

    public function hasTakenBreak(string $type): bool
    {
        return $this->breaks()->where('type', $type)->exists();
    }

    public function getNextBreakType(): ?string
    {
        $hasShort1 = $this->hasTakenBreak('short_1');
        $hasHalf1 = $this->hasTakenBreak('half_1');
        $hasShort2 = $this->hasTakenBreak('short_2');
        $hasHalf2 = $this->hasTakenBreak('half_2');
        $hasShort3 = $this->hasTakenBreak('short_3');

        if (!$hasHalf1) {
            if (!$hasShort1) {
                return 'short_1_or_half_1';
            }
            return 'half_1';
        }

        if (!$hasHalf2) {
            if (!$hasShort2) {
                return 'short_2_or_half_2';
            }
            return 'half_2';
        }

        if (!$hasShort3) {
            return 'short_3';
        }

        return null;
    }

    public function totalBreakDurationText()
    {
        $totalSeconds = 0;
        foreach ($this->breaks as $break) {
            $totalSeconds += $break->durationInSeconds();
        }

        if ($totalSeconds <= 0) {
            return '0s';
        }

        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;

        $parts = [];
        if ($hours > 0) {
            $parts[] = "{$hours}h";
        }
        if ($minutes > 0 || $hours > 0) {
            $parts[] = "{$minutes}m";
        }
        $parts[] = "{$seconds}s";

        return implode(' ', $parts);
    }
}

