<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The wallet has no figures of its own.
 *
 * That is the whole design: the salary comes from the same service the payroll
 * sheet reads, so the two cannot drift. A wallet that quietly disagreed with
 * payroll would be worse than no wallet, because somebody would believe it.
 *
 * So what is tested here is agreement, and that one person's wallet is their
 * own.
 */
class WalletTest extends TestCase
{
    use RefreshDatabase;

    private User $ali;
    private User $bilal;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-20 15:00'));

        $this->ali = $this->person('Ali Agent', 75000);
        $this->bilal = $this->person('Bilal Agent', 60000);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function person(string $name, int $salary): User
    {
        $handle = strtolower(str_replace(' ', '', $name));

        return User::create([
            'company_id' => $this->company->id,
            'name' => $name,
            'username' => $handle,
            'email' => $handle . '@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'agent',
            'monthly_salary' => $salary,
        ]);
    }

    /** A day on the clock, worked in full. */
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

    public function test_the_wallet_says_the_same_as_the_payroll_sheet(): void
    {
        $this->worked($this->ali, '2026-10-01');
        $this->worked($this->ali, '2026-10-02');
        $this->worked($this->ali, '2026-10-05');

        $fromPayroll = app(PayrollService::class)
            ->summary(collect([$this->ali]), Carbon::parse('2026-10-01'))
            ->first();

        $this->actingAs($this->ali)
            ->get(route('admin.wallet'))
            ->assertOk()
            // The take-home figure, written the way the page writes it.
            ->assertSee('Rs' . number_format($fromPayroll['net_salary']), false)
            ->assertSee('Rs' . number_format($fromPayroll['monthly_salary']), false);
    }

    public function test_it_shows_what_was_held_back_and_why(): void
    {
        // One day in, the rest of the month's working days missed.
        $this->worked($this->ali, '2026-10-01');

        $sheet = app(PayrollService::class)
            ->summary(collect([$this->ali]), Carbon::parse('2026-10-01'))
            ->first();

        $this->assertGreaterThan(0, $sheet['absent_deduction'], 'the month should have absences to dock');

        $this->actingAs($this->ali)
            ->get(route('admin.wallet'))
            ->assertOk()
            ->assertSee('Absent')
            ->assertSee('Rs' . number_format($sheet['absent_deduction']), false);
    }

    public function test_a_wallet_is_their_own_and_nobody_elses(): void
    {
        $this->worked($this->ali, '2026-10-01');
        $this->worked($this->bilal, '2026-10-01');

        // Ali's salary is on Ali's wallet; Bilal's is not.
        $this->actingAs($this->ali)
            ->get(route('admin.wallet'))
            ->assertOk()
            ->assertSee('Rs75,000', false)
            ->assertDontSee('Rs60,000', false);

        $this->actingAs($this->bilal)
            ->get(route('admin.wallet'))
            ->assertOk()
            ->assertSee('Rs60,000', false)
            ->assertDontSee('Rs75,000', false);
    }

    public function test_an_older_month_can_be_looked_at(): void
    {
        $this->worked($this->ali, '2026-09-02');

        $this->actingAs($this->ali)
            ->get(route('admin.wallet', ['month' => '2026-09']))
            ->assertOk()
            ->assertSee('September');
    }

    public function test_a_month_that_has_not_happened_falls_back_to_this_one(): void
    {
        $this->worked($this->ali, '2026-10-01');

        $this->actingAs($this->ali)
            ->get(route('admin.wallet', ['month' => '2027-05']))
            ->assertOk()
            ->assertSee('October');
    }

    public function test_nonsense_in_the_month_does_not_break_it(): void
    {
        $this->actingAs($this->ali)
            ->get(route('admin.wallet', ['month' => 'not-a-month']))
            ->assertOk();
    }

    public function test_somebody_without_the_section_cannot_reach_it(): void
    {
        $this->ali->update(['permissions' => ['overview']]);

        $this->actingAs($this->ali)
            ->get(route('admin.wallet'))
            ->assertForbidden();
    }
}
