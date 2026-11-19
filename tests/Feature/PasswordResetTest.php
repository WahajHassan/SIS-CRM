<?php

namespace Tests\Feature;

use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Getting back in.
 *
 * Somebody locked out could only be rescued by an administrator resetting their
 * password by hand — awake, available, and knowing the new password afterwards.
 *
 * Two things here are easy to get wrong and expensive to get wrong. The form
 * must not reveal whether an address has an account, or it becomes a way to
 * find out who works here. And a reset link must not work for an account
 * somebody deliberately closed.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private User $ali;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('reset:127.0.0.1');

        $this->ali = User::create([
            'company_id' => $this->company->id,
            'name' => 'Ali Agent',
            'username' => 'ali',
            'email' => 'ali@example.com',
            'password' => bcrypt('theoldone'),
            'role' => 'agent',
            'is_active' => true,
            'approval_status' => 'approved',
        ]);
    }

    /* ---- asking for a link ---------------------------------------------------- */

    public function test_the_page_is_there_and_linked_from_the_login(): void
    {
        $this->get(route('password.request'))->assertOk()->assertSee('Forgotten your password?');

        $this->get(route('login'))->assertOk()->assertSee(route('password.request'));
    }

    public function test_a_link_is_sent_to_somebody_on_file(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'ali@example.com'])
            ->assertSessionHas('status');

        Notification::assertSentTo($this->ali, ResetPassword::class);
    }

    public function test_the_answer_is_the_same_for_an_address_nobody_has(): void
    {
        Notification::fake();

        $said = $this->post(route('password.email'), ['email' => 'nobody@example.com']);

        // Word for word the same, or the form becomes a way to find out who
        // works here.
        $said->assertSessionHas('status');
        Notification::assertNothingSent();

        $this->assertSame(
            session('status'),
            $this->post(route('password.email'), ['email' => 'ali@example.com'])->getSession()->get('status')
        );
    }

    public function test_a_disabled_account_gets_no_way_back_in(): void
    {
        Notification::fake();

        $this->ali->update(['is_active' => false]);

        $this->post(route('password.email'), ['email' => 'ali@example.com']);

        // A door somebody deliberately closed.
        Notification::assertNothingSent();
    }

    public function test_a_removed_account_gets_no_way_back_in(): void
    {
        Notification::fake();

        $this->ali->delete();

        $this->post(route('password.email'), ['email' => 'ali@example.com']);

        Notification::assertNothingSent();
    }

    public function test_asking_over_and_over_is_stopped(): void
    {
        Notification::fake();

        for ($i = 0; $i < 4; $i++) {
            $this->post(route('password.email'), ['email' => 'ali@example.com']);
        }

        // A form that emails whatever address is typed into it is a way to send
        // mail on somebody else's behalf.
        $this->post(route('password.email'), ['email' => 'ali@example.com'])
            ->assertSessionHasErrors('email');
    }

    /* ---- using the link -------------------------------------------------------- */

    /** The token the emailed link carries. */
    private function tokenFor(User $user): string
    {
        $token = null;

        Notification::fake();
        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        return $token;
    }

    public function test_the_link_opens_a_page_that_sets_a_new_password(): void
    {
        $token = $this->tokenFor($this->ali);

        $this->get(route('password.reset', ['token' => $token, 'email' => $this->ali->email]))
            ->assertOk()
            ->assertSee('Set a new password');
    }

    public function test_a_new_password_actually_works(): void
    {
        $token = $this->tokenFor($this->ali);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $this->ali->email,
            'password' => 'a-brand-new-one',
            'password_confirmation' => 'a-brand-new-one',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('a-brand-new-one', $this->ali->fresh()->password));

        // And the old one stops working.
        $this->assertFalse(Hash::check('theoldone', $this->ali->fresh()->password));
    }

    public function test_the_same_link_cannot_be_used_twice(): void
    {
        $token = $this->tokenFor($this->ali);

        $use = fn () => $this->post(route('password.update'), [
            'token' => $token,
            'email' => $this->ali->email,
            'password' => 'another-new-one',
            'password_confirmation' => 'another-new-one',
        ]);

        $use()->assertRedirect(route('login'));
        $use()->assertSessionHasErrors('email');
    }

    public function test_a_made_up_token_is_refused(): void
    {
        $this->post(route('password.update'), [
            'token' => 'not-a-real-token',
            'email' => $this->ali->email,
            'password' => 'something-else',
            'password_confirmation' => 'something-else',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('theoldone', $this->ali->fresh()->password));
    }

    public function test_a_short_password_is_refused(): void
    {
        $token = $this->tokenFor($this->ali);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $this->ali->email,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }

    public function test_the_two_boxes_have_to_match(): void
    {
        $token = $this->tokenFor($this->ali);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $this->ali->email,
            'password' => 'one-password',
            'password_confirmation' => 'a-different-one',
        ])->assertSessionHasErrors('password');
    }

    /* ---- and it is written down ------------------------------------------------ */

    public function test_both_halves_are_written_down(): void
    {
        $token = $this->tokenFor($this->ali);

        $this->assertTrue(SystemLog::where('action_type', 'Password Reset Asked')->exists());

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $this->ali->email,
            'password' => 'a-brand-new-one',
            'password_confirmation' => 'a-brand-new-one',
        ]);

        $this->assertTrue(SystemLog::where('action_type', 'Password Reset')->exists());
    }

    /* ---- somebody already signed in -------------------------------------------- */

    public function test_these_pages_are_for_people_who_are_locked_out(): void
    {
        $this->actingAs($this->ali)->get(route('password.request'))->assertRedirect();
    }
}
