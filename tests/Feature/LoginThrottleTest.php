<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * A password could be guessed as fast as a script could ask.
 *
 * Nothing counted wrong attempts, so there was no number of tries at which the
 * door closed — a list of common passwords against a known username was simply
 * a matter of waiting.
 *
 * The limit is keyed on the address being tried *and* where it is being tried
 * from, which is the part worth testing: keyed on the address alone, anybody
 * could lock a colleague out by failing their login five times; keyed on the
 * address alone, one machine could also work through every account one try at
 * a time and never hit a limit.
 */
class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    private User $ali;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ali = $this->person('ali', 'ali@example.com');
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('login:ali|127.0.0.1');
        RateLimiter::clear('login:ali@example.com|127.0.0.1');
        RateLimiter::clear('login:bilal|127.0.0.1');

        parent::tearDown();
    }

    private function person(string $handle, string $email): User
    {
        return User::create([
            'company_id' => $this->company->id,
            'name' => ucfirst($handle),
            'username' => $handle,
            'email' => $email,
            'password' => bcrypt('correct-horse'),
            'role' => 'agent',
            'is_active' => true,
            'approval_status' => 'approved',
        ]);
    }

    private function tryIt(string $login, string $password = 'wrong')
    {
        return $this->post(route('login.post'), ['login' => $login, 'password' => $password]);
    }

    public function test_the_right_password_still_works(): void
    {
        $this->tryIt('ali', 'correct-horse')->assertRedirect();

        $this->assertAuthenticatedAs($this->ali);
    }

    public function test_guessing_is_stopped_after_five_tries(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->tryIt('ali')->assertSessionHasErrors('login');
        }

        $this->tryIt('ali')->assertSessionHasErrors('login');

        $this->assertStringContainsString(
            'Too many attempts',
            session('errors')->first('login')
        );

        $this->assertGuest();
    }

    public function test_the_right_password_is_refused_too_once_the_door_is_shut(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->tryIt('ali');
        }

        // Otherwise the limit is no limit at all — the guesser's next try is the
        // one that works.
        $this->tryIt('ali', 'correct-horse');

        $this->assertGuest();
    }

    public function test_one_account_being_attacked_does_not_lock_out_anybody_else(): void
    {
        $this->person('bilal', 'bilal@example.com');

        for ($i = 0; $i < 6; $i++) {
            $this->tryIt('ali');
        }

        $this->tryIt('bilal', 'correct-horse')->assertRedirect();

        $this->assertAuthenticated();
    }

    public function test_signing_in_clears_the_count(): void
    {
        $this->tryIt('ali');
        $this->tryIt('ali');

        $this->tryIt('ali', 'correct-horse');
        $this->assertAuthenticated();

        $this->post(route('logout'));

        // Somebody who fumbles their password twice every morning should never
        // creep up on the limit.
        $this->assertSame(0, RateLimiter::attempts('login:ali|127.0.0.1'));
    }

    public function test_the_wait_is_said_in_words_somebody_can_act_on(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->tryIt('ali');
        }

        $said = session('errors')->first('login');

        $this->assertMatchesRegularExpression('/Try again in \d+ (seconds|minutes)/', $said);
    }
}
