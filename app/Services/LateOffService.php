<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceAuditLog;
use App\Models\HrSetting;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\Carbon;

/**
 * Lates turning into a deducted off.
 *
 * Every third late in a calendar month costs a day. The day is recorded as a
 * leave of the system's own type so it lands on the sheet, in the balance and in
 * the salary through the same path as any other leave — there is no second,
 * parallel idea of "a deducted day" to keep in step.
 *
 * The count is worked out from the month's attendance each time rather than kept
 * as a running total, so correcting one late in the middle of a month puts
 * everything after it right on its own.
 */
class LateOffService
{
    private HrSetting $rules;

    public function __construct(?HrSetting $rules = null)
    {
        $this->rules = $rules ?: HrSetting::current();
    }

    /**
     * Bring a month's deducted offs in line with its lates.
     *
     * Adds what is owed and takes back what no longer is, and answers with what
     * it did so the caller can tell somebody.
     *
     * @return array{owed: int, existing: int, created: int, removed: int}
     */
    public function sync(User $employee, string|Carbon $month): array
    {
        $month = ($month instanceof Carbon ? $month->copy() : Carbon::parse($month))->startOfMonth();

        $perOff = (int) $this->rules->lates_per_off;
        $lates = $this->lateCount($employee, $month);
        $owed = $perOff > 0 ? intdiv($lates, $perOff) : 0;

        $existing = $this->deductedOffs($employee, $month);
        $created = 0;
        $removed = 0;

        // Too many: the newest ones go first, because the earlier lates are the
        // ones that earned the offs that remain.
        while ($existing->count() > $owed) {
            $leave = $existing->pop();
            $this->releaseLeave($leave, $employee, $month);
            $removed++;
        }

        while ($existing->count() < $owed) {
            $existing->push($this->createDeductedOff($employee, $month, $existing->count() + 1, $lates));
            $created++;
        }

        return ['owed' => $owed, 'existing' => $existing->count(), 'created' => $created, 'removed' => $removed];
    }

    /** Lates in a month, counting the ones that are recorded as such. */
    public function lateCount(User $employee, string|Carbon $month): int
    {
        $month = ($month instanceof Carbon ? $month->copy() : Carbon::parse($month))->startOfMonth();

        $statuses = [Attendance::LATE, Attendance::LATE_EARLY_OFF];

        if ($this->rules->early_off_counts_toward_lates) {
            $statuses[] = Attendance::EARLY_OFF;
        }

        return (int) Attendance::where('user_id', $employee->id)
            ->whereBetween('date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->whereIn('day_status', $statuses)
            ->count();
    }

    /** How many more lates until the next one costs a day. */
    public function latesUntilNextOff(User $employee, string|Carbon $month): ?int
    {
        $perOff = (int) $this->rules->lates_per_off;

        if ($perOff <= 0) {
            return null;                                   // the rule is switched off
        }

        return $perOff - ($this->lateCount($employee, $month) % $perOff);
    }

    /**
     * The offs already deducted for a month, oldest first.
     *
     * @return \Illuminate\Support\Collection<int, LeaveRequest>
     */
    public function deductedOffs(User $employee, string|Carbon $month)
    {
        $month = ($month instanceof Carbon ? $month->copy() : Carbon::parse($month))->startOfMonth();
        $type = $this->deductedOffType();

        if (!$type) {
            return collect();
        }

        return LeaveRequest::where('user_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->whereBetween('start_date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Record one deducted off.
     *
     * Where it comes from is a setting: out of the Casual balance while there is
     * any left, otherwise unpaid. Either way it is dated to the day the late that
     * triggered it happened, so the record reads in order.
     */
    private function createDeductedOff(User $employee, Carbon $month, int $number, int $lates): LeaveRequest
    {
        $type = $this->deductedOffType();
        $date = $this->dateOfLate($employee, $month, $number * (int) $this->rules->lates_per_off)
            ?: $month->copy()->endOfMonth()->toDateString();

        $paidFromCasual = $this->rules->late_deduction_order === HrSetting::DEDUCT_CASUAL_THEN_UNPAID
            && $this->casualBalanceLeft($employee, $month->year) >= 1;

        $source = $paidFromCasual ? $this->casualType() : null;

        $leave = LeaveRequest::create([
            'user_id' => $employee->id,
            'leave_type_id' => $type->id,
            'start_date' => $date,
            'end_date' => $date,
            'days' => 1,
            'status' => LeaveRequest::APPROVED,
            'decided_at' => now(),
            'reason' => sprintf(
                '%d lates in %s. Deducted %s.',
                $lates,
                $month->format('F Y'),
                $paidFromCasual ? 'from ' . ($source->name ?? 'Casual Leave') : 'as unpaid'
            ),
            'decision_note' => $paidFromCasual ? 'casual' : 'unpaid',
        ]);

        AttendanceAuditLog::record(
            'late_off_added',
            $employee,
            null,
            ['deducted_off' => [null, $date]],
            $leave->reason,
            $date
        );

        return $leave;
    }

    /** Take back an off that the lates no longer justify. */
    private function releaseLeave(LeaveRequest $leave, User $employee, Carbon $month): void
    {
        $date = $leave->start_date->toDateString();

        // An attendance row that was marked as this leave goes back to being an
        // ordinary day, and is re-judged from its own clock-ins.
        Attendance::where('leave_id', $leave->id)->get()->each(function (Attendance $attendance) {
            $attendance->leave_id = null;
            (new AttendanceCalculator($this->rules))->recalculate($attendance);
        });

        $leave->delete();

        AttendanceAuditLog::record(
            'late_off_removed',
            $employee,
            null,
            ['deducted_off' => [$date, null]],
            'Lates in ' . $month->format('F Y') . ' no longer add up to this off.',
            $date
        );
    }

    /** The date of the Nth late in the month, which is what earned the off. */
    private function dateOfLate(User $employee, Carbon $month, int $n): ?string
    {
        $statuses = [Attendance::LATE, Attendance::LATE_EARLY_OFF];

        if ($this->rules->early_off_counts_toward_lates) {
            $statuses[] = Attendance::EARLY_OFF;
        }

        $row = Attendance::where('user_id', $employee->id)
            ->whereBetween('date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->whereIn('day_status', $statuses)
            ->orderBy('date')
            ->skip(max(0, $n - 1))
            ->take(1)
            ->first();

        return $row?->date?->toDateString();
    }

    private function casualBalanceLeft(User $employee, int $year): float
    {
        $casual = $this->casualType();

        if (!$casual) {
            return 0;
        }

        $used = (float) LeaveRequest::approved()
            ->where('user_id', $employee->id)
            ->where('leave_type_id', $casual->id)
            ->whereYear('start_date', $year)
            ->sum('days');

        $quota = (int) $casual->quotaFor($employee->employeeProfile?->department_id)['yearly'];

        return max(0, $quota - $used);
    }

    private function deductedOffType(): ?LeaveType
    {
        return LeaveType::where('code', LeaveType::LATE_DEDUCTED_OFF)->first()
            ?: LeaveType::where('is_system', true)->first();
    }

    private function casualType(): ?LeaveType
    {
        return LeaveType::where('code', LeaveType::CASUAL)->first();
    }
}
