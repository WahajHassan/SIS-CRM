<?php

namespace Tests\Feature;

use App\Models\EmployeeShift;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkingDayOverride;
use App\Services\AttendanceCalculator;
use App\Services\AttendanceReportService;
use Carbon\Carbon;
use Database\Seeders\HrModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The month sheet has to stay readable as the company grows.
 *
 * A report for fifty people over a month asks the same few questions over and
 * over — is this a holiday, was this person on leave, which shift were they on —
 * so the answers are read once for everybody rather than once per cell. These
 * tests hold that: the figures must not change, and the number of queries must
 * not grow with the number of employees.
 */
class ReportReadingTest extends TestCase
{
    use RefreshDatabase;

    private Shift $night;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(HrModuleSeeder::class);
        $this->night = Shift::where('name', 'Night Shift')->first();
        Carbon::setTestNow(Carbon::parse('2026-09-30 12:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function staff(int $count): void
    {
        $already = User::where('role', 'agent')->count();

        for ($i = $already + 1; $i <= $already + $count; $i++) {
            $user = User::create([
                'company_id' => $this->company->id,
                'name' => "Person {$i}",
                'username' => "person{$i}",
                'email' => "person{$i}@example.com",
                'password' => bcrypt('secret123'),
                'role' => 'agent',
            ]);

            EmployeeShift::assign($user, $this->night, '2026-01-01');
        }
    }

    public function test_the_month_sheet_does_not_ask_more_of_the_database_as_the_team_grows(): void
    {
        $month = Carbon::parse('2026-09-01');

        $this->staff(4);
        DB::enableQueryLog();
        (new AttendanceReportService())->overview($month);
        $small = count(DB::getQueryLog());
        DB::flushQueryLog();

        // Only the reading counts — creating the extra people is not part of it.
        $this->staff(40);
        DB::flushQueryLog();
        (new AttendanceReportService())->overview($month);
        $large = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Ten times the people must not mean ten times the questions. A handful
        // more is fine; a per-person-per-day climb is what this guards against.
        $this->assertLessThan($small * 2, $large,
            "4 people took {$small} queries, 44 took {$large}");
        $this->assertLessThan(60, $large, "a month for 44 people took {$large} queries");
    }

    public function test_reading_everybody_at_once_gives_the_same_answers_as_asking_one_by_one(): void
    {
        $this->staff(3);
        $people = User::where('role', 'agent')->orderBy('id')->get();
        $month = Carbon::parse('2026-09-01');

        // A holiday, a leave and an override — each one changes what an empty day
        // means, and each one has to survive being read in bulk.
        Holiday::create([
            'company_id' => $this->company->id,
            'name' => 'Independence Day',
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-14',
            'is_active' => true,
        ]);

        $casual = LeaveType::where('code', 'CL')->first();
        LeaveRequest::create([
            'company_id' => $this->company->id,
            'user_id' => $people[0]->id,
            'leave_type_id' => $casual->id,
            'start_date' => '2026-09-09',
            'end_date' => '2026-09-10',
            'days' => 2,
            'reason' => 'A couple of days',
            'status' => LeaveRequest::APPROVED,
        ]);

        WorkingDayOverride::create([
            'company_id' => $this->company->id,
            'user_id' => $people[1]->id,
            'date' => '2026-09-26',               // a Saturday
            'is_working' => true,
            'reason' => 'Covering a launch',
        ]);

        $sheet = collect((new AttendanceReportService())->overview($month)['rows'])
            ->keyBy(fn ($row) => $row['user']->id);

        // The same questions, asked one at a time by a calculator that knows
        // nothing in advance.
        $plain = new AttendanceCalculator();

        foreach ($people as $person) {
            foreach (['2026-09-09', '2026-09-14', '2026-09-26', '2026-09-21'] as $date) {
                $this->assertSame(
                    $plain->emptyDayStatus($person, $date),
                    $sheet[$person->id]['cells'][$date]['status'],
                    "{$person->name} on {$date}"
                );
            }
        }
    }

    public function test_a_holiday_added_mid_request_is_noticed(): void
    {
        $this->staff(1);
        $person = User::where('role', 'agent')->first();
        $calculator = new AttendanceCalculator();

        $this->assertFalse($calculator->isHoliday('2026-09-14'));

        Holiday::create([
            'company_id' => $this->company->id,
            'name' => 'Added later',
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-14',
            'is_active' => true,
        ]);

        $this->assertTrue($calculator->isHoliday('2026-09-14'),
            'a holiday written after the question was first asked still counts');
    }

    public function test_a_leave_approved_mid_request_is_noticed(): void
    {
        $this->staff(1);
        $person = User::where('role', 'agent')->first();
        $calculator = new AttendanceCalculator();

        $this->assertNull($calculator->leaveOn($person, '2026-09-09'));

        $leave = LeaveRequest::create([
            'company_id' => $this->company->id,
            'user_id' => $person->id,
            'leave_type_id' => LeaveType::where('code', 'CL')->first()->id,
            'start_date' => '2026-09-09',
            'end_date' => '2026-09-09',
            'days' => 1,
            'reason' => 'One day',
            'status' => LeaveRequest::PENDING,
        ]);

        $this->assertNull($calculator->leaveOn($person, '2026-09-09'), 'pending is not approved');

        $leave->update(['status' => LeaveRequest::APPROVED]);

        $this->assertNotNull($calculator->leaveOn($person, '2026-09-09'),
            'an approval granted after the question was first asked is seen');
    }
}
