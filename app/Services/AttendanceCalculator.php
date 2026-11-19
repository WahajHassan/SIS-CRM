<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\HrSetting;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkingDayOverride;
use Carbon\Carbon;

/**
 * What a day amounted to.
 *
 * One place decides whether somebody was present, late, half a day or absent,
 * and it is read from the rules rather than written into the code. Everything
 * else — the dashboard, the sheet, the export, the salary — reads the answer off
 * the attendance row instead of working it out again, so the figures cannot
 * disagree with each other.
 */
class AttendanceCalculator
{
    private HrSetting $rules;
    private ShiftResolver $resolver;

    /**
     * Answers already looked up, so a report covering fifty people over a month
     * asks the database for holidays, leaves and working-day overrides once each
     * instead of once per cell. Read through prime() for a whole range, or
     * filled a key at a time by the lookups below.
     */
    private array $holidays = [];      // date => bool
    private array $leaves = [];        // "user|date" => LeaveRequest|null
    private array $overrides = [];     // "user|date" => bool|null

    /**
     * Bumped whenever a holiday, a leave or a working-day override is written,
     * which throws away what any calculator remembered. Without it a long-lived
     * instance would keep answering from before the change — a leave approved and
     * then acted on in the same request has to be seen.
     */
    public static int $generation = 0;
    private int $seen = 0;

    public function __construct(?HrSetting $rules = null)
    {
        $this->rules = $rules ?: HrSetting::current();
        $this->resolver = new ShiftResolver($this->rules);
    }

    /**
     * Work out the figures for one attendance row and save them.
     *
     * Safe to call again at any time: it is the same answer from the same
     * inputs, which is what makes correcting a clock-in enough to correct
     * everything that follows from it.
     */
    public function recalculate(Attendance $attendance, bool $save = true): Attendance
    {
        $employee = $attendance->user;
        $date = $attendance->date instanceof Carbon
            ? $attendance->date->toDateString()
            : (string) $attendance->date;

        $shift = $attendance->shift ?: ($employee ? $employee->shiftOn($date) : null);
        $attendance->shift_id = $shift?->id;

        $worked = $this->workedMinutes($attendance);
        $attendance->worked_minutes = $worked;
        $attendance->late_minutes = $shift ? $this->lateMinutes($attendance, $shift, $date) : 0;
        $attendance->early_off_minutes = $shift ? $this->earlyOffMinutes($attendance, $shift, $date) : 0;
        $attendance->day_status = $this->dayStatus($attendance, $employee, $date);

        if ($save) {
            $attendance->save();
        }

        return $attendance;
    }

    /** Time on the clock with breaks taken off. */
    public function workedMinutes(Attendance $attendance): int
    {
        if (!$attendance->clock_in) {
            return 0;
        }

        $end = $attendance->clock_out ?: Carbon::now();
        $seconds = $attendance->clock_in->diffInSeconds($end);

        foreach ($attendance->breaks as $break) {
            if (!$break->break_start) {
                continue;
            }
            $breakEnd = $break->break_end ?: Carbon::now();
            $seconds -= $break->break_start->diffInSeconds($breakEnd);
        }

        return (int) max(0, round($seconds / 60));
    }

    /**
     * How many minutes past the shift start they arrived, once the grace period
     * has been spent. Within grace is not late at all.
     */
    public function lateMinutes(Attendance $attendance, Shift $shift, string $date): int
    {
        if (!$attendance->clock_in) {
            return 0;
        }

        $graceEnds = $shift->graceEndsOn($date);

        if ($attendance->clock_in->lessThanOrEqualTo($graceEnds)) {
            return 0;
        }

        // Counted from the shift start, not from the end of grace: ten minutes of
        // grace forgives lateness, it does not subtract from it.
        return (int) $shift->startOn($date)->diffInMinutes($attendance->clock_in);
    }

    /** How early they left, if that is far enough before the end to count. */
    public function earlyOffMinutes(Attendance $attendance, Shift $shift, string $date): int
    {
        if (!$attendance->clock_out) {
            return 0;
        }

        $ends = $shift->endOn($date);
        $minutes = (int) $attendance->clock_out->diffInMinutes($ends, false);

        return $minutes >= (int) $this->rules->early_off_minutes ? $minutes : 0;
    }

    /**
     * The name for the day.
     *
     * A day with no clock-in is not automatically an absence — it may be leave,
     * a holiday, or simply not a working day for that person.
     */
    public function dayStatus(Attendance $attendance, ?User $employee, string $date): string
    {
        if (!$attendance->clock_in) {
            if ($attendance->leave_id || ($employee && $this->leaveOn($employee, $date))) {
                return Attendance::ON_LEAVE;
            }
            if ($this->isHoliday($date)) {
                return Attendance::HOLIDAY;
            }
            if ($employee && !$this->isWorkingDay($employee, $date)) {
                return Attendance::WEEKEND;
            }

            return Attendance::ABSENT;
        }

        $late = $attendance->late_minutes > 0;
        $early = $attendance->early_off_minutes > 0;

        // A day too short to count is a half day whatever else happened in it.
        if ($this->rules->half_day_enabled
            && $attendance->clock_out
            && $attendance->worked_minutes < (int) $this->rules->half_day_minutes) {
            return Attendance::HALF_DAY;
        }

        return match (true) {
            $late && $early => Attendance::LATE_EARLY_OFF,
            $late => Attendance::LATE,
            $early => Attendance::EARLY_OFF,
            default => Attendance::PRESENT,
        };
    }

    // ---- the calendar -------------------------------------------------------

    /**
     * Whether this person is expected in on this date: the weekly pattern, with
     * their own exceptions on top, and never on a public holiday.
     */
    public function isWorkingDay(User $employee, string $date): bool
    {
        $this->fresh();
        $key = "{$employee->id}|{$date}";

        if (!array_key_exists($key, $this->overrides)) {
            $row = WorkingDayOverride::where('user_id', $employee->id)
                ->whereDate('date', $date)
                ->first();

            $this->overrides[$key] = $row ? (bool) $row->is_working : null;
        }

        if ($this->overrides[$key] !== null) {
            return $this->overrides[$key];
        }

        if ($this->isHoliday($date)) {
            return false;
        }

        return !$this->rules->isWeeklyOff(Carbon::parse($date));
    }

    public function isHoliday(string $date): bool
    {
        $this->fresh();

        return $this->holidays[$date] ??= Holiday::active()->covering($date)->exists();
    }

    /** The approved leave covering a date for this person, if there is one. */
    public function leaveOn(User $employee, string $date): ?LeaveRequest
    {
        $this->fresh();
        $key = "{$employee->id}|{$date}";

        if (array_key_exists($key, $this->leaves)) {
            return $this->leaves[$key];
        }

        return $this->leaves[$key] = LeaveRequest::with('type')
            ->approved()
            ->where('user_id', $employee->id)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();
    }

    /**
     * The day as it stands when nobody has clocked in — used by the sheet and by
     * the absent sweep, which both need to know what an empty day means.
     */
    public function emptyDayStatus(User $employee, string $date): string
    {
        if ($this->leaveOn($employee, $date)) {
            return Attendance::ON_LEAVE;
        }
        if ($this->isHoliday($date)) {
            return Attendance::HOLIDAY;
        }
        if (!$this->isWorkingDay($employee, $date)) {
            return Attendance::WEEKEND;
        }

        return Attendance::ABSENT;
    }

    /**
     * Load the holidays, approved leaves and working-day overrides for a group of
     * people over a date range in three queries, so the per-day questions that
     * follow are answered from memory.
     *
     * Entirely optional: every lookup still works on its own. It is worth calling
     * before walking a month, which is what the sheet and the absent sweep do.
     */
    public function prime(array $userIds, string $from, string $to): void
    {
        $this->fresh();

        $dates = [];
        for ($d = Carbon::parse($from); $d->lte(Carbon::parse($to)); $d->addDay()) {
            $dates[] = $d->toDateString();
        }

        // Holidays: a holiday is a range, so it is expanded onto the days it covers.
        foreach ($dates as $date) {
            $this->holidays[$date] ??= false;
        }

        foreach (Holiday::active()->get() as $holiday) {
            $start = Carbon::parse($holiday->start_date)->toDateString();
            $end = Carbon::parse($holiday->end_date ?: $holiday->start_date)->toDateString();

            foreach ($dates as $date) {
                if ($date >= $start && $date <= $end) {
                    $this->holidays[$date] = true;
                }
            }
        }

        // Leaves: null for every day nobody is on leave, so a miss is an answer
        // rather than another query.
        foreach ($userIds as $userId) {
            foreach ($dates as $date) {
                $this->leaves["{$userId}|{$date}"] ??= null;
                $this->overrides["{$userId}|{$date}"] ??= null;
            }
        }

        $leaves = LeaveRequest::with('type')->approved()
            ->whereIn('user_id', $userIds)
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from)
            ->get();

        foreach ($leaves as $leave) {
            $start = Carbon::parse($leave->start_date)->toDateString();
            $end = Carbon::parse($leave->end_date)->toDateString();

            foreach ($dates as $date) {
                if ($date >= $start && $date <= $end) {
                    $this->leaves["{$leave->user_id}|{$date}"] = $leave;
                }
            }
        }

        $overrides = WorkingDayOverride::whereIn('user_id', $userIds)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->get();

        foreach ($overrides as $override) {
            $date = Carbon::parse($override->date)->toDateString();
            $this->overrides["{$override->user_id}|{$date}"] = (bool) $override->is_working;
        }
    }

    /** Drop what was remembered if anything it was based on has been written. */
    private function fresh(): void
    {
        if ($this->seen !== self::$generation) {
            $this->holidays = [];
            $this->leaves = [];
            $this->overrides = [];
            $this->seen = self::$generation;
        }
    }

    public function rules(): HrSetting
    {
        return $this->rules;
    }

    public function resolver(): ShiftResolver
    {
        return $this->resolver;
    }
}
