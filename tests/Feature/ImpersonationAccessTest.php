<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who may step into whose dashboard.
 *
 * The gate used to be the role, which meant somebody handed User Management
 * could edit a colleague's role and reset their password but not look at what
 * that colleague sees — the one harmless thing on the page was the one thing
 * refused. The gate is now the section itself.
 *
 * What the role still decides is reach. Nobody enters an account that outranks
 * their own, and that has to hold at the route as well as on the page: a button
 * that is not drawn is still a URL somebody can type.
 */
class ImpersonationAccessTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $role, ?array $sections = null): User
    {
        $handle = $role . bin2hex(random_bytes(3));

        return User::create([
            'company_id' => $this->company->id,
            'name' => ucfirst($role),
            'username' => $handle,
            'email' => $handle . '@example.com',
            'password' => bcrypt('secret123'),
            'role' => $role,
            'permissions' => $sections,
        ]);
    }

    /* ---- the gate is the section ------------------------------------------ */

    public function test_an_agent_trusted_with_user_management_may_step_in(): void
    {
        $agent = $this->person('agent', ['dashboard', 'users']);

        $this->assertTrue($agent->mayImpersonate($this->person('closer')));
    }

    public function test_an_agent_without_user_management_may_not(): void
    {
        $agent = $this->person('agent', ['dashboard']);

        $this->assertFalse($agent->mayImpersonate($this->person('closer')));
    }

    public function test_hr_trusted_with_user_management_may_step_in(): void
    {
        $hr = $this->person('hr', ['dashboard', 'users']);

        $this->assertTrue($hr->mayImpersonate($this->person('agent')));
    }

    /* ---- reach ------------------------------------------------------------- */

    public function test_nobody_but_an_owner_enters_an_owner_account(): void
    {
        $owner = $this->person('superadmin');

        $this->assertFalse($this->person('admin')->mayImpersonate($owner));
        $this->assertFalse($this->person('hr', ['users'])->mayImpersonate($owner));
        $this->assertTrue($this->person('superadmin')->mayImpersonate($owner));
    }

    public function test_an_admin_account_is_only_entered_from_above(): void
    {
        $admin = $this->person('admin');

        $this->assertFalse($this->person('hr', ['users'])->mayImpersonate($admin));
        $this->assertFalse($this->person('agent', ['users'])->mayImpersonate($admin));
        $this->assertTrue($this->person('admin')->mayImpersonate($admin));
        $this->assertTrue($this->person('superadmin')->mayImpersonate($admin));
    }

    public function test_a_floor_manager_reaches_only_their_floor(): void
    {
        $fm = $this->person('floor_manager', ['dashboard', 'users']);

        $this->assertTrue($fm->mayImpersonate($this->person('agent')));
        $this->assertTrue($fm->mayImpersonate($this->person('closer')));
        $this->assertFalse($fm->mayImpersonate($this->person('hr')));
    }

    public function test_nobody_steps_into_their_own_account(): void
    {
        $admin = $this->person('admin');

        $this->assertFalse($admin->mayImpersonate($admin));
    }

    /* ---- the route agrees with the page ------------------------------------ */

    public function test_the_route_lets_an_allowed_agent_through(): void
    {
        $agent = $this->person('agent', ['dashboard', 'users']);
        $target = $this->person('closer');

        $this->actingAs($agent)
            ->get(route('admin.impersonate', $target->id))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertSame($target->id, (int) session(Auth::getName()));
        $this->assertSame($agent->id, session('impersonate_original_id'));
    }

    public function test_the_route_refuses_what_the_page_would_not_offer(): void
    {
        $hr = $this->person('hr', ['dashboard', 'users']);
        $owner = $this->person('superadmin');

        $this->actingAs($hr)
            ->from(route('admin.users'))
            ->get(route('admin.impersonate', $owner->id))
            ->assertRedirect(route('admin.users'))
            ->assertSessionHas('error');

        $this->assertSame($hr->id, auth()->id());
        $this->assertFalse(session()->has('impersonate_original_id'));
        $this->assertNotSame($owner->id, (int) session(Auth::getName()));
    }

    public function test_the_route_is_closed_to_anybody_without_the_section(): void
    {
        $agent = $this->person('agent', ['dashboard']);

        $this->actingAs($agent)
            ->get(route('admin.impersonate', $this->person('closer')->id))
            ->assertForbidden();
    }

    public function test_leaving_puts_them_back_where_they_started(): void
    {
        $agent = $this->person('agent', ['dashboard', 'users']);
        $target = $this->person('closer');

        $this->actingAs($agent)->get(route('admin.impersonate', $target->id));
        $this->assertSame($target->id, (int) session(Auth::getName()));

        $this->get(route('admin.impersonate.leave'))->assertRedirect(route('admin.users'));

        $this->assertSame($agent->id, (int) session(Auth::getName()));
        $this->assertFalse(session()->has('impersonate_original_id'));
    }

    /* ---- and the button itself --------------------------------------------- */

    public function test_the_button_is_on_the_page_for_an_allowed_agent(): void
    {
        $agent = $this->person('agent', ['dashboard', 'users']);
        $target = $this->person('closer');

        $this->actingAs($agent)
            ->get(route('admin.users'))
            ->assertOk()
            ->assertSee(route('admin.impersonate', $target->id));
    }
}
