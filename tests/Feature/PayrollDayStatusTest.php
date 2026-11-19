<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Shift;
use App\Models\User;
use App\Services\LeaveService;
use App\Services\PayrollService;
use Carbon\Carbon;
use Database\Seeders\HrModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a day counts as when the pay is worked out.
 *
 * Approving a leave writes an attendance row for each of its days, and the
 * absent sweep writes one for every day nobody came in. Both rows have no hours
 * on them, and the payroll was reading them as short days — so an approved paid
 * leave cost somebody money, and an absence was labelled as a day half worked.
 * What decides it is whether anybody clocked in, not whether a row exists.
 */
class PayrollDayStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;
    private Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(HrModuleSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-09-30 23:00'));

        $this->shift = Shift::where('name', 'Night Shift')->first();

        // The pay runs on its own copy of the week. Saying so here means the
        // month below is the same month the payroll thinks it is.
        \App\Models\PayrollSetting::current()->update(['weekly_offs' => '0,6']);

        $this->employee = User::create([
            'company_id' => $this->company->id,
            'name' => 'Paid Properly',
            'username' => 'paidproperly',
            'email' => 'paid@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'agent',
            'monthly_salary' => 60000,
        ]);

        \App\Models\EmployeeShift::assign($this->employee, $this->shift, '2026-01-01');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function worked(string $date): void
    {
        Attendance::create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'date' => $date,
            'shift_id' => $this->shift->id,
            'clock_in' => Carbon::parse($date . ' 19:00'),
            'clock_out' => Carbon::parse($date . ' 19:00')->addHours(9),
            'status' => 'clocked_out',
            'source' => 'manual',
        ]);
    }

    private function row(): array
    {
        return (new PayrollService())
            ->summary(collect([$this->employee]), Carbon::parse('2026-09-01'))
            ->first();
    }

    /** What the payroll made of one particular day. */
    private function statusOn(string $date): ?string
    {
        foreach ($this->row()['days'] as $day) {
            if ($day['date'] === $date) {
                return $day['status'];
            }
        }

        return null;
    }

    public function test_an_approved_paid_leave_is_not_docked(): void
    {
        $type = LeaveType::where('code', 'CL')->first();

        $leave = LeaveRequest::create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'leave_type_id' => $type->id,
            'start_date' => '2026-09-16',
            'end_date' => '2026-09-17',
            'days' => 2,
            'reason' => 'Family matter',
            'status' => LeaveRequest::APPROVED,
        ]);

        // This is what approving one does, and it is what used to confuse the pay.
        (new LeaveService())->markAttendance($leave);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->employee->id,
            'date' => '2026-09-16 00:00:00',
            'leave_id' => $leave->id,
        ]);

        $this->assertSame('leave', $this->statusOn('2026-09-16'), 'the day reads as leave');
        $this->assertSame('leave', $this->statusOn('2026-09-17'), 'and so does the second one');
        $this->assertSame(2, $this->row()['leave_days'], 'both are counted as leave');
    }

    public function test_a_day_nobody_clocked_in_on_is_an_absence(): void
    {
        // What the absent sweep writes: a row, no clock-in.
        Attendance::create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'date' => '2026-09-21',
            'status' => 'clocked_out',
            'source' => 'system',
            'day_status' => Attendance::ABSENT,
            'worked_minutes' => 0,
        ]);

        $this->assertSame('absent', $this->statusOn('2026-09-21'), 'it is an absence, not a short day');
    }

    public function test_a_day_actually_worked_short_still_counts_as_short(): void
    {
        Attendance::create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'date' => '2026-09-22',
            'shift_id' => $this->shift->id,
            'clock_in' => Carbon::parse('2026-09-22 19:00'),
            'clock_out' => Carbon::parse('2026-09-22 21:30'),
            'status' => 'clocked_out',
            'source' => 'manual',
        ]);

        $this->assertSame('short', $this->statusOn('2026-09-22'),
            'somebody who came in and left early is still short');
    }

    public function test_a_paid_leave_day_earns_what_a_worked_day_earns(): void
    {
        $type = LeaveType::where('code', 'CL')->first();

        $leave = LeaveRequest::create([
            'company_id' => $this->company->id,
            'user_id' => $this->employee->id,
            'leave_type_id' => $type->id,
            'start_date' => '2026-09-16',
            'end_date' => '2026-09-16',
            'days' => 1,
            'reason' => 'One day',
            'status' => LeaveRequest::APPROVED,
        ]);

        (new LeaveService())->markAttendance($leave);

        $withLeave = $this->row();
        $this->assertSame('leave', $this->statusOn('2026-09-16'));

        // Take the leave away and the same day is simply missed. The difference
        // between the two is exactly one day's pay, which is what being paid for
        // an approved leave means.
        Attendance::where('user_id', $this->employee->id)->whereDate('date', '2026-09-16')->delete();
        $leave->delete();

        $withoutLeave = $this->row();

        $this->assertSame('absent', $this->statusOn('2026-09-16'), 'without it the day is missed');
        $this->assertSame(
            round($withLeave['credited_days'] - $withoutLeave['credited_days'], 2),
            1.0,
            'and the leave is worth a full day'
        );
        $this->assertSame(
            round($withLeave['earned'] - $withoutLeave['earned'], 2),
            round($withLeave['per_day'], 2),
            'worth exactly one day of pay'
        );
    }
}
