<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
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

    protected $fillable = [
        'user_id', 'company_id', 'leave_type_id', 'start_date', 'end_date', 'days',
        'half_day', 'reason', 'status', 'decided_by', 'decided_at', 'decision_note',
        // HR panel: short leave slices, proof, and the first approval step.
        'start_time', 'end_time', 'hours', 'attachment_path', 'tl_approved_by', 'tl_approved_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'days' => 'float',
        'hours' => 'float',
        'half_day' => 'boolean',
        'decided_at' => 'datetime',
        'tl_approved_at' => 'datetime',
    ];

    public const PENDING = 'pending';
    public const TL_APPROVED = 'tl_approved';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::PENDING => 'Pending',
        self::TL_APPROVED => 'Approved by TL',
        self::APPROVED => 'Approved',
        self::REJECTED => 'Rejected',
        self::CANCELLED => 'Cancelled',
    ];

    public function teamLead()
    {
        return $this->belongsTo(User::class, 'tl_approved_by')->withTrashed();
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'leave_id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    /** Still to be decided, at either step. */
    public function isOpen(): bool
    {
        return in_array($this->status, [self::PENDING, self::TL_APPROVED], true);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function type()
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by')->withTrashed();
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::APPROVED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::PENDING);
    }

    /** Waiting on somebody — either step of the approval flow. */
    public function scopeOpen($query)
    {
        return $query->whereIn('status', [self::PENDING, self::TL_APPROVED]);
    }

    /** Every calendar day this leave covers, as Y-m-d strings. */
    public function dates(): array
    {
        $dates = [];
        for ($d = $this->start_date->copy(); $d->lte($this->end_date); $d->addDay()) {
            $dates[] = $d->toDateString();
        }

        return $dates;
    }

    public function statusColour(): string
    {
        return [
            self::APPROVED => '#10b981',
            self::TL_APPROVED => '#3b82f6',
            self::REJECTED => '#ef4444',
            self::CANCELLED => '#64748b',
        ][$this->status] ?? '#f59e0b';
    }

    /**
     * Working days between two dates, skipping the company's weekly offs — what
     * a leave actually costs someone out of their quota.
     */
    public static function workingDaysBetween(Carbon $start, Carbon $end, PayrollSetting $rules): float
    {
        $days = 0;
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            if (!$rules->isWeeklyOff($d)) {
                $days++;
            }
        }

        return (float) $days;
    }
}
