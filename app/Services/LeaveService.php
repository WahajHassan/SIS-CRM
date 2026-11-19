<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\HrSetting;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\WorkingDayOverride;
use App\Notifications\SystemAnnouncement;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * Leave, from asking for it to the day showing as leave on the sheet.
 *
 * Two things here are easy to get wrong and expensive when they are: what a
 * leave actually costs somebody (weekends and holidays inside a range are not
 * leave days — nobody spends their annual allowance on a Sunday), and what
 * happens on approval (the balance moves and the days turn into "On Leave" so
 * the salary and the reports follow without anybody marking them by hand).
 */
class LeaveService
{
    private HrSetting $rules;

    public function __construct(?HrSetting $rules = null)
    {
        $this->rules = $rules ?: HrSetting::current();
    }

    /**
     * The days a leave actually costs: working days only.
     *
     * @return array<int, string>  the dates charged, in order
     */
    public function chargeableDates(User $employee, Carbon $start, Carbon $end): array
    {
        $dates = [];

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            if ($this->isWorkingDay($employee, $d)) {
                $dates[] = $d->toDateString();
            }
        }

        return $dates;
    }

    public function isWorkingDay(User $employee, Carbon $date): bool
    {
        $override = WorkingDayOverride::where('user_id', $employee->id)
            ->whereDate('date', $date->toDateString())
            ->first();

        if ($override) {
            return (bool) $override->is_working;
        }

        if (Holiday::active()->covering($date->toDateString())->exists()) {
            return false;
        }

        return !$this->rules->isWeeklyOff($date);
    }

    /**
     * What one person has of one type this year.
     *
     * @return array{allotted: float, used: float, remaining: float, pending: float,
     *               monthly_limit: int, unlimited: bool}
     */
    public function balance(User $employee, LeaveType $type, int $year, ?Carbon $month = null): array
    {
        $quota = $type->quotaFor($employee->employeeProfile?->department_id);

        // A balance row is what HR *granted* this person, which can differ from
        // the department default; without one the default stands.
        $row = LeaveBalance::where('user_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->where('year', $year)
            ->first();

        $allotted = $row ? (float) $row->allotted : (float) $quota['yearly'];

        $taken = LeaveRequest::where('user_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->whereYear('start_date', $year)
            ->get();

        $used = (float) $taken->where('status', LeaveRequest::APPROVED)->sum('days');
        $pending = (float) $taken->whereIn('status', [LeaveRequest::PENDING, LeaveRequest::TL_APPROVED])->sum('days');

        return [
            'allotted' => $allotted,
            'used' => $used,
            'pending' => $pending,
            'remaining' => $allotted > 0 ? max(0, $allotted - $used) : 0.0,
            'monthly_limit' => (int) $quota['monthly'],
            'unlimited' => $allotted <= 0,
        ];
    }

    /**
     * Why this request cannot be granted, or null when it can.
     *
     * Read by both sides: an employee is stopped by it, HR is only warned — HR
     * is the one who decides when an exception is made.
     */
    public function objection(User $employee, LeaveType $type, Carbon $start, float $days): ?string
    {
        $balance = $this->balance($employee, $type, (int) $start->format('Y'));

        if ($balance['monthly_limit'] > 0) {
            $inMonth = (float) LeaveRequest::where('user_id', $employee->id)
                ->where('leave_type_id', $type->id)
                ->whereIn('status', [LeaveRequest::APPROVED, LeaveRequest::PENDING, LeaveRequest::TL_APPROVED])
                ->whereYear('start_date', $start->year)
                ->whereMonth('start_date', $start->month)
                ->sum('days');

            if ($inMonth + $days > $balance['monthly_limit']) {
                return sprintf(
                    '%s allows %s a month, and %s already has %s in %s.',
                    $type->name,
                    $this->dayText($balance['monthly_limit']),
                    $employee->name,
                    $this->dayText($inMonth),
                    $start->format('F')
                );
            }
        }

        if (!$balance['unlimited'] && $balance['used'] + $balance['pending'] + $days > $balance['allotted']) {
            return sprintf(
                '%s has %s of %s left this year, and this asks for %s.',
                $employee->name,
                $this->dayText($balance['remaining'] - $balance['pending']),
                $type->name,
                $this->dayText($days)
            );
        }

        return null;
    }

    /**
     * Where a new request starts: waiting on the team lead, or straight to HR.
     */
    public function initialStatus(): string
    {
        return LeaveRequest::PENDING;
    }

    /** Whether this person may take the first step on this request. */
    public function canTeamLeadDecide(User $actor, LeaveRequest $leave): bool
    {
        if (!$this->rules->requiresTeamLeadApproval() || $leave->status !== LeaveRequest::PENDING) {
            return false;
        }

        $departmentId = $leave->user?->employeeProfile?->department_id;

        return $departmentId
            && $actor->leadsDepartments()->where('id', $departmentId)->exists();
    }

    /**
     * Approve a leave for good: balance spent, days marked, employee told.
     */
    public function approve(LeaveRequest $leave, User $decidedBy, ?string $note = null): LeaveRequest
    {
        $leave->update([
            'status' => LeaveRequest::APPROVED,
            'decided_by' => $decidedBy->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);

        $this->spendBalance($leave);
        $this->markAttendance($leave);
        $this->tell($leave, 'approved', $note);

        return $leave;
    }

    /** The first of two steps: the team lead agrees, HR still has to. */
    public function teamLeadApprove(LeaveRequest $leave, User $lead, ?string $note = null): LeaveRequest
    {
        $leave->update([
            'status' => LeaveRequest::TL_APPROVED,
            'tl_approved_by' => $lead->id,
            'tl_approved_at' => now(),
            'decision_note' => $note,
        ]);

        $this->tell($leave, 'passed to HR by your team lead', $note);

        return $leave;
    }

    public function reject(LeaveRequest $leave, User $decidedBy, ?string $note = null): LeaveRequest
    {
        $wasApproved = $leave->status === LeaveRequest::APPROVED;

        $leave->update([
            'status' => LeaveRequest::REJECTED,
            'decided_by' => $decidedBy->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);

        if ($wasApproved) {
            $this->releaseAttendance($leave);
        }

        $this->tell($leave, 'rejected', $note);

        return $leave;
    }

    public function cancel(LeaveRequest $leave, User $by): LeaveRequest
    {
        $wasApproved = $leave->status === LeaveRequest::APPROVED;

        $leave->update([
            'status' => LeaveRequest::CANCELLED,
            'decided_by' => $by->id,
            'decided_at' => now(),
        ]);

        if ($wasApproved) {
            $this->releaseAttendance($leave);
        }

        return $leave;
    }

    /**
     * Turn the approved days into attendance marked as leave.
     *
     * A day somebody actually worked is left alone — they came in despite the
     * leave, and the clock-in is the truth.
     */
    public function markAttendance(LeaveRequest $leave): int
    {
        $employee = $leave->user;

        if (!$employee || $leave->type?->isHourly()) {
            return 0;                                      // short leave is part of a worked day
        }

        $marked = 0;

        foreach ($this->chargeableDates($employee, $leave->start_date->copy(), $leave->end_date->copy()) as $date) {
            $attendance = Attendance::where('user_id', $employee->id)->whereDate('date', $date)->first();

            if ($attendance && $attendance->clock_in) {
                continue;
            }

            if ($attendance) {
                $attendance->update([
                    'day_status' => Attendance::ON_LEAVE,
                    'leave_id' => $leave->id,
                    'remarks' => $leave->type?->name,
                ]);
            } else {
                Attendance::create([
                    'user_id' => $employee->id,
                    'date' => $date,
                    'shift_id' => $employee->shiftOn($date)?->id,
                    'status' => 'clocked_out',
                    'day_status' => Attendance::ON_LEAVE,
                    'leave_id' => $leave->id,
                    'worked_minutes' => 0,
                    'source' => 'system',
                    'remarks' => $leave->type?->name,
                ]);
            }

            $marked++;
        }

        return $marked;
    }

    /** Undo the marking when an approval is taken back. */
    public function releaseAttendance(LeaveRequest $leave): void
    {
        $calculator = new AttendanceCalculator($this->rules);

        Attendance::where('leave_id', $leave->id)->get()->each(function (Attendance $attendance) use ($calculator) {
            if ($attendance->source === 'system' && !$attendance->clock_in) {
                $attendance->delete();                     // the row only existed for the leave
                return;
            }

            $attendance->leave_id = null;
            $calculator->recalculate($attendance);
        });
    }

    /**
     * Record the spend against the year's balance, creating the row from the
     * department's quota the first time somebody takes this kind of leave.
     */
    public function spendBalance(LeaveRequest $leave): void
    {
        $employee = $leave->user;
        $type = $leave->type;

        if (!$employee || !$type) {
            return;
        }

        $year = (int) $leave->start_date->format('Y');
        $quota = $type->quotaFor($employee->employeeProfile?->department_id);

        $balance = LeaveBalance::firstOrCreate(
            ['user_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => $year],
            ['allotted' => $quota['yearly'], 'used' => 0]
        );

        // Recounted from the requests rather than added to, so an edit or a
        // cancellation cannot leave the figure drifting.
        $balance->update([
            'used' => (float) LeaveRequest::approved()
                ->where('user_id', $employee->id)
                ->where('leave_type_id', $type->id)
                ->whereYear('start_date', $year)
                ->sum('days'),
        ]);
    }

    /** Tell the employee what happened to their request. */
    private function tell(LeaveRequest $leave, string $what, ?string $note = null): void
    {
        if (!$leave->user) {
            return;
        }

        $dates = $leave->start_date->equalTo($leave->end_date)
            ? $leave->start_date->format('d M Y')
            : $leave->start_date->format('d M') . ' – ' . $leave->end_date->format('d M Y');

        Notification::send($leave->user, new SystemAnnouncement([
            'title' => 'Leave ' . $what,
            'message' => trim(sprintf(
                '%s for %s was %s.%s',
                $leave->type?->name ?? 'Leave',
                $dates,
                $what,
                $note ? ' Note: ' . $note : ''
            )),
            'icon' => $what === 'rejected' ? 'fas fa-circle-xmark' : 'fas fa-plane-departure',
            'type' => $what === 'rejected' ? 'error' : 'success',
        ]));
    }

    /** Tell whoever has to act next that a request is waiting. */
    public function tellApprovers(LeaveRequest $leave): void
    {
        $employee = $leave->user;

        if (!$employee) {
            return;
        }

        $recipients = collect();

        if ($this->rules->requiresTeamLeadApproval() && $leave->status === LeaveRequest::PENDING) {
            $lead = $employee->employeeProfile?->departmentRecord?->lead;

            if ($lead && $lead->id !== $employee->id) {
                $recipients->push($lead);
            }
        }

        // HR always hears about it: they are the final word either way.
        $recipients = $recipients->merge(
            User::where('company_id', $employee->company_id)
                ->whereIn('role', ['hr', 'admin', 'superadmin'])
                ->get()
        )->unique('id')->reject(fn (User $u) => $u->id === $employee->id);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new SystemAnnouncement([
            'title' => 'Leave request',
            'message' => sprintf(
                '%s asked for %s from %s.',
                $employee->name,
                $leave->type?->name ?? 'leave',
                $leave->start_date->format('d M Y')
            ),
            'icon' => 'fas fa-plane-departure',
            'type' => 'info',
        ]));
    }

    private function dayText(float $days): string
    {
        $text = rtrim(rtrim(number_format($days, 1), '0'), '.');

        return $text . ' day' . ((float) $days === 1.0 ? '' : 's');
    }

    public function rules(): HrSetting
    {
        return $this->rules;
    }
}
