<?php

namespace Tests\Feature;

use App\Models\FreeSignup;
use App\Models\HrSetting;
use App\Models\User;
use App\Services\PerformanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Viewrec signups.
 *
 * Five a day is the whole of it, so what matters is that the count is right,
 * that the five comes from a setting rather than from the code, that a signup
 * nobody can be reached on is refused, and that an agent sees their own work
 * without seeing everybody else's.
 */
class FreeSignupTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;
    private User $otherAgent;
    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-07 15:00'));

        $this->agent = $this->person('Ali Agent', 'agent');
        $this->otherAgent = $this->person('Bilal Agent', 'agent');
        $this->hr = $this->person('Hr Lead', 'hr');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function person(string $name, string $role): User
    {
        return User::create([
            'company_id' => $this->company->id,
            'name' => $name,
            'username' => strtolower(str_replace(' ', '', $name)),
            'email' => strtolower(str_replace(' ', '', $name)) . '@example.com',
            'password' => bcrypt('secret123'),
            'role' => $role,
        ]);
    }

    /**
     * Set what a day asks for.
     *
     * current() hands back an unsaved set of defaults when a company has never
     * saved any, so this saves before changing anything — updating a row that is
     * not there changes nothing and says nothing.
     */
    private function askFor(int $perDay): void
    {
        $rules = HrSetting::current();
        $rules->free_signups_per_day = $perDay;
        $rules->save();
    }

    private function signUp(User $agent, array $extra = [])
    {
        return $this->actingAs($agent)->post(route('admin.viewrec.store'), array_merge([
            'name' => 'Dave Owner',
            'business_name' => 'Tucson Gutter Pros',
            'phone' => '520-555-0101',
        ], $extra));
    }

    // ---- the five a day -----------------------------------------------------

    public function test_the_days_count_is_what_this_person_did_today(): void
    {
        $this->signUp($this->agent);
        $this->signUp($this->agent);

        // Somebody else's work is not theirs.
        $this->signUp($this->otherAgent);

        // Nor is yesterday's.
        Carbon::setTestNow(Carbon::parse('2026-10-06 15:00'));
        $this->signUp($this->agent);
        Carbon::setTestNow(Carbon::parse('2026-10-07 15:00'));

        $today = FreeSignup::todayFor($this->agent->fresh());

        $this->assertSame(2, $today['done']);
        $this->assertSame(5, $today['target'], 'five is the default ask');
        $this->assertSame(3, $today['left']);
    }

    public function test_the_number_asked_for_comes_from_the_settings(): void
    {
        $this->askFor(8);

        $this->signUp($this->agent);

        $today = FreeSignup::todayFor($this->agent->fresh());

        $this->assertSame(8, $today['target'], 'the setting decides, not the code');
        $this->assertSame(7, $today['left']);
    }

    public function test_nothing_is_left_over_once_the_day_is_done(): void
    {
        $this->askFor(2);

        $this->signUp($this->agent);
        $this->signUp($this->agent);
        $this->signUp($this->agent);

        $today = FreeSignup::todayFor($this->agent->fresh());

        $this->assertSame(3, $today['done'], 'more than asked for still counts');
        $this->assertSame(0, $today['left'], 'but there is nothing left to go');
    }

    // ---- what a signup has to say -------------------------------------------

    public function test_a_signup_nobody_can_be_reached_on_is_refused(): void
    {
        $this->signUp($this->agent, ['phone' => null, 'email' => null])
            ->assertSessionHas('error');

        $this->assertSame(0, FreeSignup::count());
    }

    public function test_an_email_on_its_own_is_enough(): void
    {
        $this->signUp($this->agent, ['phone' => null, 'email' => 'dave@example.com'])
            ->assertSessionHas('success');

        $this->assertSame(1, FreeSignup::count());
    }

    public function test_a_signup_remembers_who_took_it(): void
    {
        $this->signUp($this->agent);

        $signup = FreeSignup::first();

        $this->assertSame($this->agent->id, $signup->created_by);
        $this->assertSame('new', $signup->status, 'and starts as new');
    }

    // ---- who sees what ------------------------------------------------------

    public function test_an_agent_sees_their_own_and_hr_sees_everybody(): void
    {
        $this->signUp($this->agent, ['business_name' => 'Theirs']);
        $this->signUp($this->otherAgent, ['business_name' => 'Somebody Elses']);

        $this->actingAs($this->agent)
            ->get(route('admin.viewrec'))
            ->assertOk()
            ->assertSee('Theirs')
            ->assertDontSee('Somebody Elses');

        $this->actingAs($this->hr)
            ->get(route('admin.viewrec'))
            ->assertOk()
            ->assertSee('Theirs')
            ->assertSee('Somebody Elses');
    }

    public function test_an_agent_cannot_remove_a_signup(): void
    {
        $this->signUp($this->agent);
        $signup = FreeSignup::first();

        $this->actingAs($this->agent)
            ->delete(route('admin.viewrec.destroy', $signup->id))
            ->assertForbidden();

        $this->assertSame(1, FreeSignup::count());

        $this->actingAs($this->hr)
            ->delete(route('admin.viewrec.destroy', $signup->id))
            ->assertRedirect();

        $this->assertSame(0, FreeSignup::count());
    }

    public function test_a_status_can_be_moved_by_whoever_took_it_or_by_hr(): void
    {
        $this->signUp($this->agent);
        $signup = FreeSignup::first();

        $this->actingAs($this->otherAgent)
            ->put(route('admin.viewrec.status', $signup->id), ['status' => 'active'])
            ->assertForbidden();

        $this->actingAs($this->agent)
            ->put(route('admin.viewrec.status', $signup->id), ['status' => 'verified'])
            ->assertRedirect();

        $this->assertSame('verified', $signup->fresh()->status);

        $this->actingAs($this->hr)
            ->put(route('admin.viewrec.status', $signup->id), ['status' => 'active'])
            ->assertRedirect();

        $this->assertSame('active', $signup->fresh()->status);
    }

    public function test_a_team_lead_sees_their_own_department_and_not_the_whole_floor(): void
    {
        $sales = \App\Models\Department::create([
            'company_id' => $this->company->id, 'name' => 'Sales', 'code' => 'SAL', 'is_active' => true,
        ]);

        $tech = \App\Models\Department::create([
            'company_id' => $this->company->id, 'name' => 'Tech', 'code' => 'TEC', 'is_active' => true,
        ]);

        $lead = $this->person('Sales Lead', 'team_lead');
        $sales->update(['lead_user_id' => $lead->id]);

        foreach ([[$this->agent, $sales], [$this->otherAgent, $tech]] as [$person, $department]) {
            \App\Models\EmployeeProfile::create([
                'company_id' => $this->company->id,
                'user_id' => $person->id,
                'department_id' => $department->id,
            ]);
        }

        $this->signUp($this->agent, ['business_name' => 'In Sales']);
        $this->signUp($this->otherAgent, ['business_name' => 'In Tech']);

        $this->actingAs($lead)
            ->get(route('admin.viewrec'))
            ->assertOk()
            ->assertSee('In Sales')
            ->assertDontSee('In Tech', false);

        // And HR, who answers for everybody, sees both.
        $this->actingAs($this->hr)
            ->get(route('admin.viewrec'))
            ->assertSee('In Sales')
            ->assertSee('In Tech');
    }

    // ---- and it counts as a KPI ---------------------------------------------

    public function test_the_month_is_what_the_kpi_reads(): void
    {
        $this->signUp($this->agent);
        $this->signUp($this->agent);

        // Last month's work belongs to last month.
        Carbon::setTestNow(Carbon::parse('2026-09-20 15:00'));
        $this->signUp($this->agent);
        Carbon::setTestNow(Carbon::parse('2026-10-07 15:00'));

        $performance = new PerformanceService();

        $this->assertSame(2.0, $performance->value($this->agent->fresh(), 'free_signups', Carbon::parse('2026-10-01')));
        $this->assertSame(1.0, $performance->value($this->agent->fresh(), 'free_signups', Carbon::parse('2026-09-01')));
        $this->assertSame(0.0, $performance->value($this->otherAgent->fresh(), 'free_signups', Carbon::parse('2026-10-01')));
    }

    public function test_viewrec_is_offered_as_a_kpi_source(): void
    {
        $this->assertArrayHasKey('free_signups', PerformanceService::sources());
    }
}
