<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The tick beside a name.
 *
 * It is a claim somebody made about somebody else, so the two things worth
 * testing are who is allowed to make it and whether the record says who did.
 * A tick anybody could award themselves would mean nothing, and a tick with no
 * name behind it could not be questioned later.
 */
class VerificationTest extends TestCase
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

    /* ---- the record --------------------------------------------------------- */

    public function test_nobody_starts_verified(): void
    {
        $this->assertFalse($this->person('agent')->isVerified());
    }

    public function test_verifying_records_who_said_so(): void
    {
        $hr = $this->person('hr', ['dashboard', 'users']);
        $agent = $this->person('agent');

        $hr->verify($agent, 'Passport and address checked');

        $agent->refresh();

        $this->assertTrue($agent->isVerified());
        $this->assertSame($hr->id, $agent->verified_by);
        $this->assertSame('Passport and address checked', $agent->verified_note);
        $this->assertSame($hr->id, $agent->verifier->id);
        $this->assertNotNull($agent->verified_at);
    }

    public function test_taking_it_off_leaves_nothing_behind(): void
    {
        $hr = $this->person('hr', ['dashboard', 'users']);
        $agent = $this->person('agent');

        $hr->verify($agent, 'Checked');
        $hr->unverify($agent->refresh());

        $agent->refresh();

        $this->assertFalse($agent->isVerified());
        $this->assertNull($agent->verified_by);
        $this->assertNull($agent->verified_note);
    }

    /* ---- who may award it --------------------------------------------------- */

    public function test_user_management_is_the_gate_not_the_role(): void
    {
        $this->assertTrue($this->person('agent', ['dashboard', 'users'])->mayVerify($this->person('closer')));
        $this->assertFalse($this->person('agent', ['dashboard'])->mayVerify($this->person('closer')));
    }

    public function test_nobody_reaches_above_themselves(): void
    {
        $owner = $this->person('superadmin');
        $admin = $this->person('admin');

        $this->assertFalse($this->person('hr', ['users'])->mayVerify($owner));
        $this->assertFalse($this->person('hr', ['users'])->mayVerify($admin));
        $this->assertTrue($this->person('superadmin')->mayVerify($admin));
    }

    public function test_nobody_verifies_themselves(): void
    {
        $hr = $this->person('hr', ['dashboard', 'users']);

        $this->assertFalse($hr->mayVerify($hr));
    }

    /* ---- the route ---------------------------------------------------------- */

    public function test_the_route_puts_it_on_and_takes_it_off(): void
    {
        $hr = $this->person('hr', ['dashboard', 'users']);
        $agent = $this->person('agent');

        $this->actingAs($hr)
            ->from(route('admin.users'))
            ->post(route('admin.users.verify', $agent->id))
            ->assertRedirect(route('admin.users'));

        $this->assertTrue($agent->refresh()->isVerified());

        $this->actingAs($hr)
            ->from(route('admin.users'))
            ->post(route('admin.users.verify', $agent->id))
            ->assertRedirect(route('admin.users'));

        $this->assertFalse($agent->refresh()->isVerified());
    }

    public function test_the_route_refuses_a_reach_the_page_would_not_offer(): void
    {
        $hr = $this->person('hr', ['dashboard', 'users']);
        $owner = $this->person('superadmin');

        $this->actingAs($hr)
            ->post(route('admin.users.verify', $owner->id))
            ->assertForbidden();

        $this->assertFalse($owner->refresh()->isVerified());
    }

    public function test_the_route_is_closed_without_the_section(): void
    {
        $agent = $this->person('agent', ['dashboard']);
        $other = $this->person('closer');

        $this->actingAs($agent)
            ->post(route('admin.users.verify', $other->id))
            ->assertForbidden();

        $this->assertFalse($other->refresh()->isVerified());
    }

    /* ---- one picture per person --------------------------------------------- */

    public function test_a_person_with_no_picture_has_a_letter_and_a_colour(): void
    {
        $agent = $this->person('agent');

        $this->assertNull($agent->avatar_url);
        $this->assertSame('A', $agent->initial);
        $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $agent->tone);
    }

    public function test_their_colour_does_not_change_between_screens(): void
    {
        $agent = $this->person('agent');

        $this->assertSame($agent->tone, $agent->fresh()->tone);
    }
}
