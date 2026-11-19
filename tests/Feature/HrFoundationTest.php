<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\EmployeeShift;
use App\Models\Holiday;
use App\Models\HrSetting;
use App\Models\LeaveType;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\HrModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1: the ground the HR panel stands on.
 *
 * The rules themselves are tested in later phases; what is checked here is that
 * the shapes are right — a night shift knows it runs into tomorrow, a shift
 * history answers "which shift was this person on in March", and the defaults
 * come out as agreed.
 */
class HrFoundationTest extends TestCase
{
    use RefreshDatabase;

    /* The base TestCase already creates the company and sets it as the tenant,
       so all this needs to add is the HR defaults. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(HrModuleSeeder::class);
    }

    private function employee(string $name = 'Employee'): User
    {
        return User::create([
            'company_id' => $this->company->id,
            'name' => $name,
            'username' => strtolower(str_replace(' ', '', $name)) . rand(100, 999),
            'email' => strtolower(str_replace(' ', '', $name)) . rand(100, 999) . '@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'agent',
        ]);
    }

    public function test_it_seeds_the_departments_and_the_shifts_this_office_runs(): void
    {
        $this->assertEquals(['Sales', 'Tech'], Department::orderBy('name')->pluck('name')->all());

        $night = Shift::where('name', 'Night Shift')->first();
        $tech = Shift::where('name', 'Tech Shift')->first();
        $midnight = Shift::where('name', 'Midnight Shift')->first();

        $this->assertNotNull($night);
        $this->assertEquals('19:00', substr($night->start_time, 0, 5));
        $this->assertEquals('04:00', substr($night->end_time, 0, 5));
        $this->assertEquals(10, $night->grace_minutes);
        $this->assertEquals(10, $midnight->grace_minutes);

        // The tech team start earlier and finish earlier.
        $this->assertNotNull($tech);
        $this->assertEquals('16:00', substr($tech->start_time, 0, 5));
        $this->assertEquals('01:00', substr($tech->end_time, 0, 5));
        $this->assertTrue($tech->crosses_midnight);
    }

    public function test_every_shift_carries_an_hour_of_break(): void
    {
        foreach (Shift::all() as $shift) {
            $this->assertEquals(60, $shift->break_minutes, $shift->name . ' has no break');
        }

        // Nine hours on the clock with an hour's break is an eight-hour day.
        $night = Shift::where('name', 'Night Shift')->first();
        $this->assertEquals(9 * 60, $night->lengthMinutes('2026-09-25'));
        $this->assertEquals(8 * 60, $night->workingMinutes('2026-09-25'));
        $this->assertEquals('1h break', $night->breakText());
    }

    public function test_a_night_shift_knows_it_runs_into_the_next_day(): void
    {
        $night = Shift::where('name', 'Night Shift')->first();

        $this->assertTrue($night->crosses_midnight);

        // Friday 25 September 2026: 7pm Friday to 4am Saturday.
        $this->assertEquals('2026-09-25 19:00', $night->startOn('2026-09-25')->format('Y-m-d H:i'));
        $this->assertEquals('2026-09-26 04:00', $night->endOn('2026-09-25')->format('Y-m-d H:i'));
        $this->assertEquals(9 * 60, $night->lengthMinutes('2026-09-25'));
        $this->assertEquals('2026-09-25 19:10', $night->graceEndsOn('2026-09-25')->format('Y-m-d H:i'));
    }

    public function test_a_midnight_shift_stays_inside_one_day(): void
    {
        $midnight = Shift::where('name', 'Midnight Shift')->first();

        $this->assertFalse($midnight->crosses_midnight);
        $this->assertEquals('2026-09-25 00:00', $midnight->startOn('2026-09-25')->format('Y-m-d H:i'));
        $this->assertEquals('2026-09-25 09:00', $midnight->endOn('2026-09-25')->format('Y-m-d H:i'));
    }

    public function test_crossing_midnight_is_worked_out_from_the_times_not_asked_for(): void
    {
        $shift = Shift::create([
            'company_id' => $this->company->id,
            'name' => 'Evening', 'start_time' => '22:00', 'end_time' => '06:00',
            'crosses_midnight' => false,                      // deliberately wrong
        ]);

        $this->assertTrue($shift->fresh()->crosses_midnight);
    }

    public function test_shift_history_answers_which_shift_someone_was_on(): void
    {
        $employee = $this->employee('Shift Mover');
        $night = Shift::where('name', 'Night Shift')->first();
        $midnight = Shift::where('name', 'Midnight Shift')->first();

        EmployeeShift::assign($employee, $night, '2026-01-01');
        EmployeeShift::assign($employee, $midnight, '2026-06-01');

        $this->assertEquals($night->id, $employee->shiftOn('2026-03-15')->id);
        $this->assertEquals($midnight->id, $employee->shiftOn('2026-06-01')->id);
        $this->assertEquals($midnight->id, $employee->shiftOn('2026-09-20')->id);

        // The first assignment was closed the day before the second began: no
        // overlap, no gap.
        $first = EmployeeShift::where('user_id', $employee->id)->orderBy('effective_from')->first();
        $this->assertEquals('2026-05-31', $first->effective_to->toDateString());
    }

    public function test_moving_somebody_twice_from_the_same_date_replaces_the_assignment(): void
    {
        $employee = $this->employee('Twice Moved');
        $night = Shift::where('name', 'Night Shift')->first();
        $midnight = Shift::where('name', 'Midnight Shift')->first();

        EmployeeShift::assign($employee, $night, '2026-02-01');
        EmployeeShift::assign($employee, $midnight, '2026-02-01');

        $this->assertEquals(1, EmployeeShift::where('user_id', $employee->id)->count());
        $this->assertEquals($midnight->id, $employee->shiftOn('2026-02-10')->id);
    }

    public function test_the_default_rules_are_the_agreed_ones(): void
    {
        $rules = HrSetting::current();

        $this->assertEquals([6, 0], $rules->weeklyOffDays());           // Saturday, Sunday
        $this->assertEquals('Saturday, Sunday', $rules->weeklyOffsText());
        $this->assertEquals(120, $rules->early_checkin_minutes);
        $this->assertEquals(15, $rules->early_off_minutes);
        $this->assertTrue($rules->half_day_enabled);
        $this->assertEquals(240, $rules->half_day_minutes);
        $this->assertEquals(3, $rules->lates_per_off);
        $this->assertEquals(HrSetting::DEDUCT_CASUAL_THEN_UNPAID, $rules->late_deduction_order);
        $this->assertFalse($rules->early_off_counts_toward_lates);
        $this->assertTrue($rules->requiresTeamLeadApproval());

        $this->assertTrue($rules->isWeeklyOff(Carbon::parse('2026-09-26')));   // Saturday
        $this->assertFalse($rules->isWeeklyOff(Carbon::parse('2026-09-25')));  // Friday
    }

    public function test_the_six_leave_types_are_seeded_with_their_codes(): void
    {
        $types = LeaveType::orderBy('id')->get()->keyBy('code');

        $this->assertEquals(
            ['CL', 'SL', 'AL', 'UL', 'SHL', 'LDO'],
            $types->keys()->all()
        );

        $this->assertEquals(10, $types['CL']->annual_quota);
        $this->assertEquals(8, $types['SL']->annual_quota);
        $this->assertEquals(14, $types['AL']->annual_quota);
        $this->assertFalse($types['UL']->is_paid);

        $this->assertTrue($types['SHL']->isHourly());
        $this->assertEquals(2, $types['SHL']->monthly_quota);
        $this->assertEquals(3.0, $types['SHL']->max_hours);

        // The software owns late-deducted offs; nobody applies for one.
        $this->assertTrue($types['LDO']->is_system);
        $this->assertFalse(LeaveType::applicable()->pluck('code')->contains('LDO'));
    }

    public function test_a_department_can_be_given_its_own_quota(): void
    {
        $sales = Department::where('name', 'Sales')->first();
        $casual = LeaveType::where('code', 'CL')->first();

        $casual->departmentQuotas()->create([
            'department_id' => $sales->id,
            'yearly_quota' => 15,
            'monthly_limit' => 2,
        ]);

        $casual->load('departmentQuotas');

        $this->assertEquals(['yearly' => 15, 'monthly' => 2], $casual->quotaFor($sales->id));
        $this->assertEquals(['yearly' => 10, 'monthly' => 0], $casual->quotaFor(null));
    }

    public function test_holidays_cover_a_range_of_dates(): void
    {
        Holiday::create([
            'company_id' => $this->company->id,
            'name' => 'Eid',
            'start_date' => '2026-03-20',
            'end_date' => '2026-03-23',
        ]);

        $this->assertTrue(Holiday::covering('2026-03-21')->exists());
        $this->assertTrue(Holiday::covering('2026-03-23')->exists());
        $this->assertFalse(Holiday::covering('2026-03-24')->exists());
        $this->assertEquals(4, Holiday::first()->days());
    }

    public function test_seeding_twice_does_not_duplicate_anything(): void
    {
        $this->seed(HrModuleSeeder::class);

        $this->assertEquals(2, Department::count());
        $this->assertEquals(3, Shift::count());     // night, tech, midnight
        $this->assertEquals(6, LeaveType::count());
        $this->assertEquals(1, HrSetting::count());
    }

    public function test_attendance_keeps_its_existing_columns_and_gains_the_new_ones(): void
    {
        $columns = \Schema::getColumnListing('attendances');

        // Nothing the rest of the CRM relies on was taken away.
        foreach (['user_id', 'date', 'clock_in', 'clock_out', 'status'] as $existing) {
            $this->assertContains($existing, $columns, "attendances lost $existing");
        }

        foreach (['shift_id', 'worked_minutes', 'late_minutes', 'early_off_minutes',
                  'day_status', 'leave_id', 'source', 'remarks', 'edited_by'] as $added) {
            $this->assertContains($added, $columns, "attendances is missing $added");
        }
    }
}
