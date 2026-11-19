<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\PayrollSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns the attendance that is already being recorded into a month's pay.
 *
 * Nothing here is stored: every figure is worked out from the clock-in and
 * clock-out rows on demand, so correcting an attendance row immediately corrects
 * the salary that is shown, and there is no second copy of the truth to go stale.
 */
class PayrollService
{
    public const FULL = 'full';
    public const HALF = 'half';
    public const SHORT = 'short';
    public const ABSENT = 'absent';
    public const OFF = 'off';
    public const FUTURE = 'future';
    public const LEAVE = 'leave';            // approved, and the type is paid
    public const UNPAID = 'unpaid_leave';    // approved, but the type is unpaid

    private PayrollSetting $rules;

    public function __construct(?PayrollSetting $rules = null)
    {
        $this->rules = $rules ?: PayrollSetting::current();
    }

    /**
     * The month a report covers, as a Carbon on its first day. Anything
     * unparseable falls back to the current month rather than breaking the page.
     */
    public static function month(?string $value): Carbon
    {
        try {
            return $value ? Carbon::parse($value . '-01')->startOfMonth() : Carbon::now()->startOfMonth();
        } catch (\Exception $e) {
            return Carbon::now()->startOfMonth();
        }
    }

    /**
     * One row per employee for the given month.
     *
     * @param  Collection<int, User>  $users
     * @return Collection<int, array>
     */
    public function summary(Collection $users, Carbon $month): Collection
    {
        return $this->summaryMonths($users, $month, $month)[$month->format('Y-m')];
    }

    /**
     * The same thing for a run of months, reading the attendance and the leave
     * once for the whole span instead of once per month.
     *
     * A year of history on an employee's file was 24 queries before this; it is
     * two now, and every month still goes through exactly the same counting.
     *
     * @param  Collection<int, User>  $users
     * @return array<string, Collection<int, array>>  keyed 'Y-m', newest last
     */
    public function summaryMonths(Collection $users, Carbon $from, Carbon $to): array
    {
        $spanStart = $from->copy()->startOfMonth();
        $spanEnd = $to->copy()->endOfMonth();

        if ($spanStart->greaterThan($spanEnd)) {
            [$spanStart, $spanEnd] = [$to->copy()->startOfMonth(), $from->copy()->endOfMonth()];
        }

        $attendances = Attendance::with('breaks')
            ->whereIn('user_id', $users->pluck('id'))
            ->whereBetween('date', [$spanStart->toDateString(), $spanEnd->toDateString()])
            ->get()
            ->groupBy(['user_id', fn ($a) => $a->date->toDateString()]);

        $leave = $this->approvedLeave($users, $spanStart, $spanEnd);

        $out = [];
        for ($m = $spanStart->copy(); $m->lte($spanEnd); $m->addMonth()) {
            $start = $m->copy()->startOfMonth();
            $end = $m->copy()->endOfMonth();
            $calendar = $this->calendar($start, $end);

            $out[$m->format('Y-m')] = $users->map(function (User $user) use ($attendances, $calendar, $leave, $start, $end) {
                $byDate = ($attendances->get($user->id) ?? collect())
                    ->filter(fn ($rows, $date) => $date >= $start->toDateString() && $date <= $end->toDateString());

                $userLeave = array_filter(
                    $leave[$user->id] ?? [],
                    fn ($date) => $date >= $start->toDateString() && $date <= $end->toDateString(),
                    ARRAY_FILTER_USE_KEY
                );

                return $this->summaryFor($user, $byDate, $calendar, $userLeave);
            })->values();
        }

        return $out;
    }

    /**
     * Approved leave that touches the month, flattened to one entry per date so a
     * day can be answered with a single lookup.
     *
     * @return array<int, array<string, array{name: string, paid: bool}>>
     */
    private function approvedLeave(Collection $users, Carbon $start, Carbon $end): array
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('leave_requests')) {
            return [];   // the HR tables have not been migrated yet
        }

        $requests = \App\Models\LeaveRequest::with('type')
            ->approved()
            ->whereIn('user_id', $users->pluck('id'))
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->get();

        $map = [];
        foreach ($requests as $request) {
            foreach ($request->dates() as $date) {
                if ($date < $start->toDateString() || $date > $end->toDateString()) {
                    continue;
                }
                $map[$request->user_id][$date] = [
                    'name' => $request->type->name ?? 'Leave',
                    'paid' => (bool) ($request->type->is_paid ?? true),
                ];
            }
        }

        return $map;
    }

    /** Every date in the month, tagged as a working day or a weekly off. */
    private function calendar(Carbon $start, Carbon $end): array
    {
        // Only days that have actually happened can be counted against someone.
        // The office day rolls over at noon, so before noon "today" is still
        // yesterday's shift and today is not yet judged.
        $lastCounted = User::getOfficeDate();

        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $date = $d->toDateString();
            $days[$date] = [
                'date' => $date,
                'weekly_off' => $this->rules->isWeeklyOff($d),
                'elapsed' => $date <= $lastCounted,
            ];
        }
        return $days;
    }

    private function summaryFor(User $user, Collection $byDate, array $calendar, array $leave = []): array
    {
        $days = [];
        $counts = [
            self::FULL => 0, self::HALF => 0, self::SHORT => 0, self::ABSENT => 0,
            self::OFF => 0, self::FUTURE => 0, self::LEAVE => 0, self::UNPAID => 0,
        ];
        $lateDays = 0;
        $lateMinutes = 0;
        $workedMinutes = 0;
        $requiredMinutes = 0;
        $creditedDays = 0.0;

        // Pay is spread over every working day of the whole month, so the daily
        // rate does not change as the month goes on.
        $workingDaysInMonth = collect($calendar)->reject(fn ($d) => $d['weekly_off'])->count();

        foreach ($calendar as $date => $day) {
            $attendance = optional($byDate->get($date))->first();
            $worked = $attendance ? $attendance->totalWorkedMinutes() : 0;
            /*
             * Lateness comes from the attendance row when the HR engine has
             * judged it: that verdict was made against the shift the person was
             * actually on and the grace period in force, which this service
             * knows nothing about. Older rows, recorded before shifts existed,
             * are still worked out here from the company-wide shift start.
             */
            $late = $attendance && $attendance->clock_in
                ? ($attendance->shift_id && $attendance->late_minutes !== null
                    ? (int) $attendance->late_minutes
                    : $this->lateMinutes($date, $attendance->clock_in))
                : 0;
            $onLeave = $leave[$date] ?? null;

            if ($day['weekly_off'] && !$attendance) {
                $status = self::OFF;
            } elseif ($day['weekly_off']) {
                $status = self::OFF;                       // came in on their off day
            } elseif (!$day['elapsed']) {
                $status = self::FUTURE;
            } elseif ($onLeave && (!$attendance || !$attendance->clock_in)) {
                // Approved leave is not absence: a paid type still earns the day.
                //
                // Approving a leave also writes an attendance row for each of its
                // days, so the day was being read as a row with no hours on it —
                // a short day — and the pay for an approved paid leave was being
                // docked. What matters is whether anybody actually clocked in,
                // not whether a row exists.
                $status = $onLeave['paid'] ? self::LEAVE : self::UNPAID;
            } elseif (!$attendance || !$attendance->clock_in) {
                // A row with no clock-in is the absent sweep's work, not a short
                // day: nobody came in at all.
                $status = self::ABSENT;
            } elseif ($worked >= $this->rules->required_minutes) {
                $status = self::FULL;
            } elseif ($worked >= $this->rules->half_day_minutes) {
                $status = self::HALF;
            } else {
                $status = self::SHORT;
            }

            // A weekly off still to come is nobody's day off yet, so it is not
            // counted in the month-to-date tally even though the strip labels it.
            $counts[$status === self::OFF && !$day['elapsed'] ? self::FUTURE : $status]++;

            if ($status === self::FULL || $status === self::LEAVE) {
                $creditedDays += 1;
            } elseif ($status === self::HALF) {
                $creditedDays += 0.5;
            }

            if (!$day['weekly_off'] && $day['elapsed']) {
                $requiredMinutes += $this->rules->required_minutes;
            }

            $workedMinutes += $worked;

            if ($late > 0 && in_array($status, [self::FULL, self::HALF, self::SHORT], true)) {
                $lateDays++;
                $lateMinutes += $late;
            }

            $days[] = [
                'date' => $date,
                'status' => $status,
                'weekly_off' => $day['weekly_off'],
                'clock_in' => $attendance && $attendance->clock_in ? $attendance->clock_in->format('h:i A') : null,
                'clock_out' => $attendance && $attendance->clock_out ? $attendance->clock_out->format('h:i A') : null,
                'worked_minutes' => $worked,
                'late_minutes' => $late,
                'leave_name' => $onLeave['name'] ?? null,
            ];
        }

        $salary = (float) $user->monthly_salary;
        $perDay = $workingDaysInMonth > 0 ? $salary / $workingDaysInMonth : 0.0;

        $absentDeduction = round($perDay * ($counts[self::ABSENT] + $counts[self::UNPAID] + $counts[self::SHORT] + ($counts[self::HALF] * 0.5)), 2);

        $lateUnit = max(0, (int) $this->rules->lates_per_deduction);
        $lateDeductedDays = $lateUnit > 0 ? intdiv($lateDays, $lateUnit) : 0;
        $lateDeduction = round($perDay * $lateDeductedDays, 2);

        $earned = round($perDay * $creditedDays, 2);
        $net = max(0, round($earned - $lateDeduction, 2));

        return [
            'user' => $user,
            'monthly_salary' => $salary,
            'per_day' => round($perDay, 2),
            'working_days' => $workingDaysInMonth,
            'present_days' => $counts[self::FULL],
            'half_days' => $counts[self::HALF],
            'short_days' => $counts[self::SHORT],
            'absent_days' => $counts[self::ABSENT],
            'leave_days' => $counts[self::LEAVE],
            'unpaid_leave_days' => $counts[self::UNPAID],
            'off_days' => $counts[self::OFF],
            'upcoming_days' => $counts[self::FUTURE],
            'credited_days' => $creditedDays,
            'late_days' => $lateDays,
            'late_minutes' => $lateMinutes,
            'late_deducted_days' => $lateDeductedDays,
            'worked_minutes' => $workedMinutes,
            'required_minutes' => $requiredMinutes,
            'earned' => $earned,
            'absent_deduction' => $absentDeduction,
            'late_deduction' => $lateDeduction,
            'net_salary' => $net,
            'days' => $days,
        ];
    }

    /**
     * How many minutes past the allowed start someone arrived.
     *
     * The office day runs from noon to noon, so a shift that begins before midday
     * belongs to the calendar day after the attendance date.
     */
    private function lateMinutes(string $date, Carbon $clockIn): int
    {
        $expected = $this->shiftStartFor($date);
        $allowed = $expected->copy()->addMinutes($this->rules->grace_minutes);

        return $clockIn->greaterThan($allowed) ? (int) $expected->diffInMinutes($clockIn) : 0;
    }

    public function shiftStartFor(string $date): Carbon
    {
        [$h, $m] = array_pad(explode(':', $this->rules->shift_start), 2, '0');
        $start = Carbon::parse($date)->setTime((int) $h, (int) $m, 0);

        if ((int) $h < 12) {
            $start->addDay();
        }

        return $start;
    }

    public function rules(): PayrollSetting
    {
        return $this->rules;
    }

    public static function hoursText(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0h';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return $h > 0 ? ($m > 0 ? "{$h}h {$m}m" : "{$h}h") : "{$m}m";
    }
}
