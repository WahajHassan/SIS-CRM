<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\EmployeeProfile;
use App\Models\EmployeeShift;
use App\Models\Holiday;
use App\Models\HrSetting;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Shift;
use App\Models\User;
use App\Services\LeaveService;
use Carbon\Carbon;
use Database\Seeders\HrModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4: leave, from asking for it to the day it changes.
 *
 * The rules worth proving: a weekend inside a leave costs nothing, an approval
 * moves the balance and marks the days, taking an approval back unmarks them,
 * and the two-step flow only lets a team lead speak for their own department.
 */
class LeaveFlowTest extends TestCase
{
    use RefreshDatabase;

    private Department $sales;
    private Shift $night;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(HrModuleSeeder::class);
        $this->sales = Department::where('name', 'Sales')->first();
        $this->night = Shift::where('name', 'Night Shift')->first();
    }

    private function employee(string $name = 'Agent', ?Department $department = null): User
    {
        $user = User::create([
            'company_id' => $this->company->id,
            'name' => $name,
            'username' => strtolower(str_replace(' ', '', $name)) . rand(1000, 9999),
            'email' => strtolower(str_replace(' ', '', $name)) . rand(1000, 9999) . '@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'agent',
        ]);

        EmployeeProfile::create([
            'user_id' => $user->id,
            'department_id' => ($department ?: $this->sales)->id,
        ]);

        EmployeeShift::assign($user, $this->night, '2026-01-01');

        return $user;
    }

    private function type(string $code): LeaveType
    {
        return LeaveType::where('code', $code)->first();
    }

    private function request(User $employee, string $code, string $from, string $to, array $extra = []): LeaveRequest
    {
        $service = new LeaveService();
        $days = count($service->chargeableDates($employee, Carbon::parse($from), Carbon::parse($to)));

        return LeaveRequest::create(array_merge([
            'user_id' => $employee->id,
            'leave_type_id' => $this->type($code)->id,
            'start_date' => $from,
            'end_date' => $to,
            'days' => $days,
            'status' => LeaveRequest::PENDING,
        ], $extra));
    }

    // ---- what a leave costs -------------------------------------------------

    public function test_weekends_inside_a_leave_are_not_charged(): void
    {
        $employee = $this->employee('Long Leave');

        // Friday 25 September 2026 to Tuesday the 29th: the 26th and 27th are
        // the weekend, so four calendar days cost three.
        $dates = (new LeaveService())->chargeableDates(
            $employee,
            Carbon::parse('2026-09-25'),
            Carbon::parse('2026-09-29')
        );

        $this->assertEquals(['2026-09-25', '2026-09-28', '2026-09-29'], $dates);
    }

    public function test_public_holidays_inside_a_leave_are_not_charged_either(): void
    {
        $employee = $this->employee('Holiday Leave');

        Holiday::create([
            'company_id' => $this->company->id,
            'name' => 'Eid',
            'start_date' => '2026-09-28',
            'end_date' => '2026-09-29',
        ]);

        $dates = (new LeaveService())->chargeableDates(
            $employee,
            Carbon::parse('2026-09-25'),
            Carbon::parse('2026-09-30')
        );

        $this->assertEquals(['2026-09-25', '2026-09-30'], $dates);
    }

    // ---- approving it -------------------------------------------------------

    public function test_approving_a_leave_marks_the_days_and_spends_the_balance(): void
    {
        $employee = $this->employee('Annual Taker');
        $hr = $this->employee('HR Person');
        $leave = $this->request($employee, 'AL', '2026-09-25', '2026-09-29');

        $this->assertEquals(3, $leave->days, 'the weekend was charged');

        (new LeaveService())->approve($leave, $hr);

        // The working days are marked, the weekend is not touched.
        foreach (['2026-09-25', '2026-09-28', '2026-09-29'] as $date) {
            $row = Attendance::where('user_id', $employee->id)->whereDate('date', $date)->first();
            $this->assertNotNull($row, "$date was not marked");
            $this->assertEquals(Attendance::ON_LEAVE, $row->day_status);
            $this->assertEquals($leave->id, $row->leave_id);
        }

        $this->assertNull(Attendance::where('user_id', $employee->id)->whereDate('date', '2026-09-26')->first());

        $balance = LeaveBalance::where('user_id', $employee->id)
            ->where('leave_type_id', $this->type('AL')->id)
            ->where('year', 2026)
            ->first();

        $this->assertNotNull($balance);
        $this->assertEquals(14, $balance->allotted);
        $this->assertEquals(3, $balance->used);
        $this->assertEquals(11, $balance->remaining());
    }

    public function test_a_day_actually_worked_is_left_alone(): void
    {
        $employee = $this->employee('Came In Anyway');
        $hr = $this->employee('HR Two');

        Attendance::create([
            'user_id' => $employee->id,
            'date' => '2026-09-25',
            'clock_in' => Carbon::parse('2026-09-25 19:00'),
            'clock_out' => Carbon::parse('2026-09-26 04:00'),
            'status' => 'clocked_out',
            'day_status' => Attendance::PRESENT,
            'source' => 'web',
        ]);

        (new LeaveService())->approve($this->request($employee, 'CL', '2026-09-25', '2026-09-25'), $hr);

        $row = Attendance::where('user_id', $employee->id)->whereDate('date', '2026-09-25')->first();
        $this->assertEquals(Attendance::PRESENT, $row->day_status, 'a worked day was overwritten by the leave');
        $this->assertNull($row->leave_id);
    }

    public function test_taking_an_approval_back_unmarks_the_days(): void
    {
        $employee = $this->employee('Changed Mind');
        $hr = $this->employee('HR Three');
        $service = new LeaveService();

        $leave = $this->request($employee, 'CL', '2026-09-28', '2026-09-29');
        $service->approve($leave, $hr);
        $this->assertEquals(2, Attendance::where('leave_id', $leave->id)->count());

        $service->cancel($leave->fresh(['user', 'type']), $hr);

        $this->assertEquals(0, Attendance::where('leave_id', $leave->id)->count());
        $this->assertEquals(0, Attendance::where('user_id', $employee->id)->count(), 'rows made only for the leave should go');
        $this->assertEquals(LeaveRequest::CANCELLED, $leave->fresh()->status);
    }

    // ---- allowances ---------------------------------------------------------

    public function test_asking_for_more_than_the_yearly_allowance_is_objected_to(): void
    {
        $employee = $this->employee('Over Budget');
        $hr = $this->employee('HR Four');
        $service = new LeaveService();
        $casual = $this->type('CL');                       // ten days a year

        // Nine days spent already.
        $spent = $this->request($employee, 'CL', '2026-03-02', '2026-03-12');
        $spent->update(['days' => 9]);
        $service->approve($spent, $hr);

        $this->assertNull($service->objection($employee, $casual, Carbon::parse('2026-05-04'), 1));

        $objection = $service->objection($employee, $casual, Carbon::parse('2026-05-04'), 3);
        $this->assertNotNull($objection);
        $this->assertStringContainsString('1 day', $objection);
    }

    public function test_pending_requests_are_counted_against_the_allowance(): void
    {
        $employee = $this->employee('Double Booker');
        $service = new LeaveService();
        $casual = $this->type('CL');

        $pending = $this->request($employee, 'CL', '2026-04-06', '2026-04-17');
        $pending->update(['days' => 10]);

        $this->assertNotNull(
            $service->objection($employee, $casual, Carbon::parse('2026-06-01'), 1),
            'a request already waiting should count against what is left'
        );
    }

    public function test_an_unlimited_type_never_runs_out(): void
    {
        $employee = $this->employee('Unpaid Taker');
        $service = new LeaveService();

        $this->assertNull($service->objection($employee, $this->type('UL'), Carbon::parse('2026-07-01'), 20));
    }

    public function test_a_department_can_be_given_a_bigger_allowance(): void
    {
        $employee = $this->employee('Sales Person');
        $casual = $this->type('CL');

        $casual->departmentQuotas()->create([
            'department_id' => $this->sales->id,
            'yearly_quota' => 15,
            'monthly_limit' => 0,
        ]);

        $balance = (new LeaveService())->balance($employee, $casual->fresh('departmentQuotas'), 2026);

        $this->assertEquals(15, $balance['allotted']);
    }

    // ---- who approves -------------------------------------------------------

    public function test_a_team_lead_may_only_speak_for_their_own_department(): void
    {
        $tech = Department::where('name', 'Tech')->first();

        $salesLead = $this->employee('Sales Lead');
        $this->sales->update(['lead_user_id' => $salesLead->id]);

        $salesPerson = $this->employee('Sales Member');
        $techPerson = $this->employee('Tech Member', $tech);

        $service = new LeaveService();

        $this->assertTrue($service->canTeamLeadDecide(
            $salesLead->fresh(),
            $this->request($salesPerson, 'CL', '2026-09-28', '2026-09-28')->load('user.employeeProfile')
        ));

        $this->assertFalse($service->canTeamLeadDecide(
            $salesLead->fresh(),
            $this->request($techPerson, 'CL', '2026-09-28', '2026-09-28')->load('user.employeeProfile')
        ));
    }

    public function test_the_team_lead_step_passes_it_on_rather_than_granting_it(): void
    {
        $lead = $this->employee('The Lead');
        $this->sales->update(['lead_user_id' => $lead->id]);

        $employee = $this->employee('Asker');
        $leave = $this->request($employee, 'CL', '2026-09-28', '2026-09-28');

        $service = new LeaveService();
        $service->teamLeadApprove($leave, $lead);

        $leave->refresh();
        $this->assertEquals(LeaveRequest::TL_APPROVED, $leave->status);
        $this->assertEquals($lead->id, $leave->tl_approved_by);

        // Nothing has been spent or marked yet: HR has not decided.
        $this->assertEquals(0, Attendance::where('leave_id', $leave->id)->count());
        $this->assertEquals(0, LeaveBalance::where('user_id', $employee->id)->count());

        $hr = $this->employee('Final Word');
        $service->approve($leave, $hr);

        $this->assertEquals(LeaveRequest::APPROVED, $leave->fresh()->status);
        $this->assertEquals(1, Attendance::where('leave_id', $leave->id)->count());
    }

    public function test_with_single_step_approval_no_team_lead_is_involved(): void
    {
        HrSetting::current()->update(['leave_approval_flow' => HrSetting::FLOW_HR_ONLY]);

        $lead = $this->employee('Ignored Lead');
        $this->sales->update(['lead_user_id' => $lead->id]);
        $employee = $this->employee('Straight To HR');

        $service = new LeaveService();

        $this->assertFalse($service->canTeamLeadDecide(
            $lead->fresh(),
            $this->request($employee, 'CL', '2026-09-28', '2026-09-28')->load('user.employeeProfile')
        ));
    }

    // ---- the employee hears about it ---------------------------------------

    public function test_the_employee_is_notified_of_a_decision(): void
    {
        $employee = $this->employee('Waiting');
        $hr = $this->employee('Decider');

        (new LeaveService())->approve($this->request($employee, 'CL', '2026-09-28', '2026-09-28'), $hr);

        $this->assertEquals(1, $employee->notifications()->count());
        $this->assertStringContainsString('approved', $employee->notifications()->first()->data['message']);

        $other = $this->employee('Rejected One');
        (new LeaveService())->reject($this->request($other, 'CL', '2026-09-28', '2026-09-28'), $hr, 'Too busy that week');

        $this->assertStringContainsString('Too busy that week', $other->notifications()->first()->data['message']);
    }
}
