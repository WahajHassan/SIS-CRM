<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A payslip somebody can keep.
 *
 * It has no figures of its own: every number is read back out of the same
 * service the payroll screen and the wallet read. So the thing worth testing is
 * agreement — a payslip that quietly disagreed with payroll would be worse than
 * none, because somebody would believe it and take it to a bank.
 *
 * The other half is who may open one. Pay is the one thing a colleague has no
 * business seeing just because they are senior.
 */
class PayslipTest extends TestCase
{
    use RefreshDatabase;

    private User $ali;
    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-20 15:00'));

        $this->ali = $this->person('Ali Agent', 'ali', 'agent', 75000);
        $this->hr = $this->person('HR Manager', 'hr', 'hr', 90000);
        $this->hr->update(['permissions' => ['dashboard', 'hr_employees']]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function person(string $name, string $handle, string $role, int $salary): User
    {
        return User::create([
            'company_id' => $this->company->id,
            'name' => $name,
            'username' => $handle,
            'email' => $handle . '@example.com',
            'password' => bcrypt('secret123'),
            'role' => $role,
            'monthly_salary' => $salary,
            'is_active' => true,
            'approval_status' => 'approved',
        ]);
    }

    private function worked(User $who, string $date): void
    {
        Attendance::create([
            'company_id' => $this->company->id,
            'user_id' => $who->id,
            'date' => $date,
            'clock_in' => Carbon::parse($date . ' 09:00'),
            'clock_out' => Carbon::parse($date . ' 18:00'),
            'status' => 'clocked_out',
        ]);
    }

    private function slip(User $viewer, User $about, string $month = '2026-10')
    {
        return $this->actingAs($viewer)->get(route('admin.payslip', ['user' => $about->id, 'month' => $month]));
    }

    /* ---- it says the same as payroll ---------------------------------------- */

    public function test_the_payslip_says_what_payroll_says(): void
    {
        $this->worked($this->ali, '2026-10-01');
        $this->worked($this->ali, '2026-10-02');

        $month = PayrollService::month('2026-10');
        $row = (new PayrollService())->summary(collect([$this->ali]), $month)->first();

        $response = $this->slip($this->ali, $this->ali);
        $response->assertOk();

        // The one number anybody actually reads.
        $response->assertSee(number_format((float) $row['net_salary'], 2), false);
        $response->assertSee(number_format((float) $row['monthly_salary'], 2), false);
        $response->assertSee((string) $row['credited_days'], false);
    }

    public function test_it_names_the_person_the_month_and_the_company(): void
    {
        $this->worked($this->ali, '2026-10-01');

        $this->slip($this->ali, $this->ali)
            ->assertOk()
            ->assertSee('Ali Agent')
            ->assertSee('October 2026')
            ->assertSee($this->company->name);
    }

    public function test_a_month_still_running_says_so(): void
    {
        $this->worked($this->ali, '2026-10-01');

        // Somebody reading an October payslip on the 20th of October should not
        // think that is the whole month.
        $this->slip($this->ali, $this->ali)
            ->assertOk()
            ->assertSee('not finished', false);
    }

    public function test_a_finished_month_does_not(): void
    {
        $this->worked($this->ali, '2026-09-01');

        $this->slip($this->ali, $this->ali, '2026-09')
            ->assertOk()
            ->assertDontSee('not finished', false);
    }

    public function test_the_numbers_on_it_add_up(): void
    {
        // Four days worked out of a month, so most of it is absence — the case
        // where the arithmetic used to look wrong on the page.
        foreach (['2026-10-01', '2026-10-02', '2026-10-05', '2026-10-06'] as $day) {
            $this->worked($this->ali, $day);
        }

        $month = PayrollService::month('2026-10');
        $row = (new PayrollService())->summary(collect([$this->ali]), $month)->first();

        // What the service actually does: absence never entered the earned
        // figure, and only lateness is taken off it.
        $this->assertEqualsWithDelta(
            (float) $row['earned'] - (float) $row['late_deduction'],
            (float) $row['net_salary'],
            0.01,
            'net salary is earned minus lateness, and nothing else'
        );

        $response = $this->slip($this->ali, $this->ali);
        $response->assertOk();

        // So absence must not be printed with a minus sign beside it.
        $response->assertSee('Not earned', false);
        $response->assertDontSee('Less absence', false);
    }

    /* ---- who may open one ---------------------------------------------------- */

    public function test_anybody_can_open_their_own(): void
    {
        $this->slip($this->ali, $this->ali)->assertOk();
    }

    public function test_hr_can_open_somebody_elses(): void
    {
        $this->slip($this->hr, $this->ali)->assertOk();
    }

    public function test_a_colleague_cannot(): void
    {
        $bilal = $this->person('Bilal Agent', 'bilal', 'agent', 60000);
        $bilal->update(['permissions' => ['dashboard', 'leads']]);

        // Being on the same floor is not a reason to see somebody's pay.
        $this->slip($bilal, $this->ali)->assertForbidden();
    }

    public function test_the_owner_can(): void
    {
        $owner = $this->person('Owner', 'owner', 'superadmin', 0);

        $this->slip($owner, $this->ali)->assertOk();
    }

    /* ---- somebody who has left ------------------------------------------------ */

    public function test_somebody_who_has_left_still_has_last_months_payslip(): void
    {
        $this->worked($this->ali, '2026-09-10');
        $this->ali->delete();

        // Refusing it is exactly when they need it most.
        $this->slip($this->hr, $this->ali, '2026-09')
            ->assertOk()
            ->assertSee('Ali Agent');
    }

    /* ---- and it is written down when it is not your own ----------------------- */

    public function test_opening_somebody_elses_is_written_down(): void
    {
        $this->slip($this->hr, $this->ali);

        $this->assertTrue(SystemLog::where('action_type', 'Viewed Payslip')->exists());
    }

    public function test_opening_your_own_is_not(): void
    {
        $this->slip($this->ali, $this->ali);

        $this->assertFalse(SystemLog::where('action_type', 'Viewed Payslip')->exists());
    }
}
