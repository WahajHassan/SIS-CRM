<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\EmployeeShift;
use App\Models\Holiday;
use App\Models\HrSetting;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkingDayOverride;
use App\Services\AttendanceCalculator;
use App\Services\LateOffService;
use App\Services\ShiftResolver;
use Carbon\Carbon;
use Database\Seeders\HrModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3: the rules themselves.
 *
 * These are the things that are expensive to get wrong and impossible to check
 * by looking at a screen — a night that belongs to the evening it started, ten
 * minutes of grace, three lates costing a day, and a correction putting all of
 * it right again.
 */
class AttendanceRulesTest extends TestCase
{
    use RefreshDatabase;

    private Shift $night;
    private Shift $midnight;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(HrModuleSeeder::class);

        $this->night = Shift::where('name', 'Night Shift')->first();          // 19:00 – 04:00
        $this->midnight = Shift::where('name', 'Midnight Shift')->first();    // 00:00 – 09:00
    }

    private function employee(string $name = 'Agent', ?Shift $shift = null, string $from = '2026-01-01'): User
    {
        $user = User::create([
            'company_id' => $this->company->id,
            'name' => $name,
            'username' => strtolower(str_replace(' ', '', $name)) . rand(1000, 9999),
            'email' => strtolower(str_replace(' ', '', $name)) . rand(1000, 9999) . '@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'agent',
        ]);

        EmployeeShift::assign($user, $shift ?: $this->night, $from);

        return $user;
    }

    /** A day on the clock, judged by the rules. */
    private function day(User $employee, string $date, ?string $in, ?string $out = null): Attendance
    {
        $attendance = Attendance::create([
            'user_id' => $employee->id,
            'date' => $date,
            'clock_in' => $in ? Carbon::parse($in) : null,
            'clock_out' => $out ? Carbon::parse($out) : null,
            'status' => $out ? 'clocked_out' : 'clocked_in',
            'source' => 'web',
        ]);

        return (new AttendanceCalculator())->recalculate($attendance->fresh('breaks'));
    }

    // ---- which day does a night belong to? ---------------------------------

    public function test_a_night_shift_belongs_to_the_evening_it_started(): void
    {
        $employee = $this->employee('Night Worker');
        $resolver = new ShiftResolver();

        // Friday 25 September 2026, 7 PM — the shift runs to 4 AM Saturday.
        $this->assertEquals('2026-09-25', $resolver->dateFor($employee, Carbon::parse('2026-09-25 19:00')));
        $this->assertEquals('2026-09-25', $resolver->dateFor($employee, Carbon::parse('2026-09-25 23:59')));

        // One in the morning on Saturday is still Friday's night.
        $this->assertEquals('2026-09-25', $resolver->dateFor($employee, Carbon::parse('2026-09-26 01:00')));
        $this->assertEquals('2026-09-25', $resolver->dateFor($employee, Carbon::parse('2026-09-26 03:59')));
    }

    public function test_arriving_early_still_counts_as_that_shift(): void
    {
        $employee = $this->employee('Early Bird');
        $resolver = new ShiftResolver();

        // Two hours is the default window: 5:30 PM for a 7 PM start.
        $this->assertEquals('2026-09-25', $resolver->dateFor($employee, Carbon::parse('2026-09-25 17:30')));

        $resolved = $resolver->resolve($employee, Carbon::parse('2026-09-25 17:30'));
        $this->assertEquals($this->night->id, $resolved['shift']->id);
    }

    public function test_the_early_window_is_a_setting_not_a_number_in_the_code(): void
    {
        $employee = $this->employee('Window Test');

        HrSetting::current()->update(['early_checkin_minutes' => 15]);

        // With only fifteen minutes allowed, 5:30 PM is no longer part of the
        // evening shift — and the resolver must not simply invent a date.
        $resolved = (new ShiftResolver())->resolve($employee, Carbon::parse('2026-09-25 17:30'));
        $this->assertEquals('2026-09-25', $resolved['date']);

        $this->assertEquals(
            '2026-09-25',
            (new ShiftResolver())->dateFor($employee, Carbon::parse('2026-09-25 18:50'))
        );
    }

    public function test_a_midnight_shift_belongs_to_the_date_it_starts(): void
    {
        $employee = $this->employee('Midnight Worker', $this->midnight);
        $resolver = new ShiftResolver();

        $this->assertEquals('2026-09-26', $resolver->dateFor($employee, Carbon::parse('2026-09-26 00:05')));
        $this->assertEquals('2026-09-26', $resolver->dateFor($employee, Carbon::parse('2026-09-26 08:30')));

        // Half past eleven the night before is within the early window, so it is
        // already the next day's shift.
        $this->assertEquals('2026-09-26', $resolver->dateFor($employee, Carbon::parse('2026-09-25 23:30')));
    }

    // ---- what the day amounted to -------------------------------------------

    public function test_a_full_night_is_simply_present(): void
    {
        $employee = $this->employee('Punctual');
        $day = $this->day($employee, '2026-09-25', '2026-09-25 19:00', '2026-09-26 04:00');

        $this->assertEquals(Attendance::PRESENT, $day->day_status);
        $this->assertEquals(9 * 60, $day->worked_minutes);
        $this->assertEquals(0, $day->late_minutes);
        $this->assertEquals(0, $day->early_off_minutes);
    }

    public function test_grace_forgives_lateness_but_does_not_subtract_from_it(): void
    {
        $employee = $this->employee('Grace');

        // Ten minutes of grace: 7:09 PM is not late at all.
        $within = $this->day($employee, '2026-09-25', '2026-09-25 19:09', '2026-09-26 04:00');
        $this->assertEquals(Attendance::PRESENT, $within->day_status);
        $this->assertEquals(0, $within->late_minutes);

        // 7:25 PM is late by twenty-five minutes, counted from the shift start.
        $late = $this->day($employee, '2026-09-28', '2026-09-28 19:25', '2026-09-29 04:00');
        $this->assertEquals(Attendance::LATE, $late->day_status);
        $this->assertEquals(25, $late->late_minutes);
    }

    public function test_leaving_early_is_recorded_and_can_combine_with_being_late(): void
    {
        $employee = $this->employee('Early Off');

        $early = $this->day($employee, '2026-09-25', '2026-09-25 19:00', '2026-09-26 03:30');
        $this->assertEquals(Attendance::EARLY_OFF, $early->day_status);
        $this->assertEquals(30, $early->early_off_minutes);

        $both = $this->day($employee, '2026-09-28', '2026-09-28 19:40', '2026-09-29 03:00');
        $this->assertEquals(Attendance::LATE_EARLY_OFF, $both->day_status);
        $this->assertEquals(40, $both->late_minutes);
        $this->assertEquals(60, $both->early_off_minutes);
    }

    public function test_leaving_a_few_minutes_early_is_within_tolerance(): void
    {
        $employee = $this->employee('Nearly');

        // The setting is fifteen minutes; ten is not an early off.
        $day = $this->day($employee, '2026-09-25', '2026-09-25 19:00', '2026-09-26 03:50');

        $this->assertEquals(Attendance::PRESENT, $day->day_status);
        $this->assertEquals(0, $day->early_off_minutes);
    }

    public function test_breaks_come_off_the_hours_worked(): void
    {
        $employee = $this->employee('Break Taker');
        $day = $this->day($employee, '2026-09-25', '2026-09-25 19:00', '2026-09-26 04:00');

        AttendanceBreak::create([
            'attendance_id' => $day->id,
            'type' => 'half_1',
            'break_start' => Carbon::parse('2026-09-25 22:00'),
            'break_end' => Carbon::parse('2026-09-25 22:45'),
        ]);

        (new AttendanceCalculator())->recalculate($day->fresh('breaks'));

        $this->assertEquals(9 * 60 - 45, $day->fresh()->worked_minutes);
    }

    public function test_a_short_day_is_a_half_day_and_the_threshold_is_a_setting(): void
    {
        $employee = $this->employee('Short Day');

        $day = $this->day($employee, '2026-09-25', '2026-09-25 19:00', '2026-09-25 22:00');
        $this->assertEquals(Attendance::HALF_DAY, $day->day_status);

        // Turned off, the same three hours is simply a present (and early) day.
        HrSetting::current()->update(['half_day_enabled' => false]);
        $again = (new AttendanceCalculator())->recalculate($day->fresh('breaks'));
        $this->assertEquals(Attendance::EARLY_OFF, $again->day_status);
    }

    // ---- days nobody worked -------------------------------------------------

    public function test_an_empty_day_is_only_an_absence_when_it_was_a_working_day(): void
    {
        $employee = $this->employee('Absentee');
        $calculator = new AttendanceCalculator();

        $this->assertEquals(Attendance::ABSENT, $calculator->emptyDayStatus($employee, '2026-09-25'));   // Friday
        $this->assertEquals(Attendance::WEEKEND, $calculator->emptyDayStatus($employee, '2026-09-26'));  // Saturday

        Holiday::create([
            'company_id' => $this->company->id,
            'name' => 'Independence Day',
            'start_date' => '2026-08-14',
            'end_date' => '2026-08-14',
        ]);
        $this->assertEquals(Attendance::HOLIDAY, $calculator->emptyDayStatus($employee, '2026-08-14'));

        LeaveRequest::create([
            'user_id' => $employee->id,
            'leave_type_id' => LeaveType::where('code', 'CL')->first()->id,
            'start_date' => '2026-09-28',
            'end_date' => '2026-09-28',
            'days' => 1,
            'status' => LeaveRequest::APPROVED,
        ]);
        $this->assertEquals(Attendance::ON_LEAVE, $calculator->emptyDayStatus($employee, '2026-09-28'));
    }

    public function test_one_person_can_be_made_to_work_a_normally_off_day(): void
    {
        $employee = $this->employee('Saturday Worker');
        $calculator = new AttendanceCalculator();

        $this->assertFalse($calculator->isWorkingDay($employee, '2026-09-26'));   // Saturday

        WorkingDayOverride::create([
            'company_id' => $this->company->id,
            'user_id' => $employee->id,
            'date' => '2026-09-26',
            'is_working' => true,
            'reason' => 'Covering a launch',
        ]);

        $this->assertTrue($calculator->isWorkingDay($employee, '2026-09-26'));
        $this->assertEquals(Attendance::ABSENT, $calculator->emptyDayStatus($employee, '2026-09-26'));
    }

    public function test_the_sweep_marks_absent_only_after_the_shift_has_finished(): void
    {
        $employee = $this->employee('Sweep Target');

        // Friday's night shift ends at 4 AM Saturday. At 9 PM on Friday it is
        // still running, so nobody can be called absent for it yet.
        Carbon::setTestNow(Carbon::parse('2026-09-25 21:00'));
        $this->artisan('attendance:mark-absent')->assertExitCode(0);
        $this->assertDatabaseMissing('attendances', ['user_id' => $employee->id, 'date' => '2026-09-25']);

        // By Saturday morning it is over and unworked.
        Carbon::setTestNow(Carbon::parse('2026-09-26 06:00'));
        $this->artisan('attendance:mark-absent')->assertExitCode(0);

        $row = Attendance::where('user_id', $employee->id)->whereDate('date', '2026-09-25')->first();
        $this->assertNotNull($row, 'Friday was never judged');
        $this->assertEquals(Attendance::ABSENT, $row->day_status);
        $this->assertEquals('system', $row->source);

        Carbon::setTestNow();
    }

    public function test_the_sweep_leaves_leave_weekends_and_worked_days_alone(): void
    {
        $onLeave = $this->employee('On Leave');
        $worked = $this->employee('Worked');

        LeaveRequest::create([
            'user_id' => $onLeave->id,
            'leave_type_id' => LeaveType::where('code', 'AL')->first()->id,
            'start_date' => '2026-09-25',
            'end_date' => '2026-09-25',
            'days' => 1,
            'status' => LeaveRequest::APPROVED,
        ]);

        $this->day($worked, '2026-09-25', '2026-09-25 19:00', '2026-09-26 04:00');

        Carbon::setTestNow(Carbon::parse('2026-09-26 06:00'));
        $this->artisan('attendance:mark-absent')->assertExitCode(0);
        Carbon::setTestNow();

        $this->assertDatabaseMissing('attendances', [
            'user_id' => $onLeave->id, 'date' => '2026-09-25', 'day_status' => Attendance::ABSENT,
        ]);
        $this->assertEquals(
            Attendance::PRESENT,
            Attendance::where('user_id', $worked->id)->first()->day_status
        );
    }

    // ---- three lates cost a day ---------------------------------------------

    public function test_every_third_late_in_a_month_costs_a_day(): void
    {
        $employee = $this->employee('Three Lates');
        $service = new LateOffService();

        $this->day($employee, '2026-09-01', '2026-09-01 19:30', '2026-09-02 04:00');
        $this->day($employee, '2026-09-02', '2026-09-02 19:30', '2026-09-03 04:00');
        $this->assertEquals(2, $service->lateCount($employee, '2026-09-01'));
        $this->assertEquals(1, $service->latesUntilNextOff($employee, '2026-09-01'));

        $sync = $service->sync($employee, '2026-09-01');
        $this->assertEquals(0, $sync['owed'], 'two lates is not yet an off');

        $this->day($employee, '2026-09-03', '2026-09-03 19:30', '2026-09-04 04:00');
        $sync = $service->sync($employee, '2026-09-01');

        $this->assertEquals(1, $sync['owed']);
        $this->assertEquals(1, $sync['created']);

        $off = LeaveRequest::where('user_id', $employee->id)
            ->whereHas('type', fn ($q) => $q->where('code', 'LDO'))
            ->first();

        $this->assertNotNull($off, 'no deducted off was recorded');
        $this->assertEquals(LeaveRequest::APPROVED, $off->status);
        $this->assertEquals('2026-09-03', $off->start_date->toDateString(), 'dated to the late that earned it');
        $this->assertStringContainsString('Casual', $off->reason);
    }

    public function test_the_count_resets_each_month(): void
    {
        $employee = $this->employee('Monthly Reset');
        $service = new LateOffService();

        foreach (['2026-09-01', '2026-09-02', '2026-09-03'] as $date) {
            $this->day($employee, $date, $date . ' 19:30', $date . ' 23:59');
        }
        foreach (['2026-10-01', '2026-10-02'] as $date) {
            $this->day($employee, $date, $date . ' 19:30', $date . ' 23:59');
        }

        $this->assertEquals(3, $service->lateCount($employee, '2026-09-01'));
        $this->assertEquals(2, $service->lateCount($employee, '2026-10-01'));

        $service->sync($employee, '2026-09-01');
        $service->sync($employee, '2026-10-01');

        $this->assertEquals(1, $service->deductedOffs($employee, '2026-09-01')->count());
        $this->assertEquals(0, $service->deductedOffs($employee, '2026-10-01')->count());
    }

    public function test_how_many_lates_cost_a_day_is_a_setting(): void
    {
        $employee = $this->employee('Configurable');
        HrSetting::current()->update(['lates_per_off' => 2]);

        $service = new LateOffService();
        $this->day($employee, '2026-09-01', '2026-09-01 19:30', '2026-09-02 04:00');
        $this->day($employee, '2026-09-02', '2026-09-02 19:30', '2026-09-03 04:00');

        $this->assertEquals(1, $service->sync($employee, '2026-09-01')['owed']);

        // Zero switches the whole rule off.
        HrSetting::current()->update(['lates_per_off' => 0]);
        $this->assertEquals(0, (new LateOffService())->sync($employee, '2026-09-01')['owed']);
        $this->assertNull((new LateOffService())->latesUntilNextOff($employee, '2026-09-01'));
    }

    public function test_correcting_a_late_takes_the_deducted_off_back(): void
    {
        $employee = $this->employee('Corrected');
        $service = new LateOffService();

        $days = [];
        foreach (['2026-09-01', '2026-09-02', '2026-09-03'] as $date) {
            $days[$date] = $this->day($employee, $date, $date . ' 19:30', $date . ' 23:59');
        }

        $service->sync($employee, '2026-09-01');
        $this->assertEquals(1, $service->deductedOffs($employee, '2026-09-01')->count());

        // The clock was wrong on the third day: they were actually on time.
        $fixed = $days['2026-09-03'];
        $fixed->update(['clock_in' => Carbon::parse('2026-09-03 19:00')]);
        (new AttendanceCalculator())->recalculate($fixed->fresh('breaks'));

        $after = $service->sync($employee, '2026-09-01');

        $this->assertEquals(2, $service->lateCount($employee, '2026-09-01'));
        $this->assertEquals(0, $after['owed']);
        $this->assertEquals(1, $after['removed']);
        $this->assertEquals(0, $service->deductedOffs($employee, '2026-09-01')->count());
    }

    public function test_early_offs_only_count_toward_lates_when_that_is_switched_on(): void
    {
        $employee = $this->employee('Early Counter');

        $this->day($employee, '2026-09-01', '2026-09-01 19:30', '2026-09-02 04:00');   // late
        $this->day($employee, '2026-09-02', '2026-09-02 19:00', '2026-09-03 03:00');   // early off
        $this->day($employee, '2026-09-03', '2026-09-03 19:00', '2026-09-04 03:00');   // early off

        $this->assertEquals(1, (new LateOffService())->lateCount($employee, '2026-09-01'));

        HrSetting::current()->update(['early_off_counts_toward_lates' => true]);

        $service = new LateOffService();
        $this->assertEquals(3, $service->lateCount($employee, '2026-09-01'));
        $this->assertEquals(1, $service->sync($employee, '2026-09-01')['owed']);
    }
}
