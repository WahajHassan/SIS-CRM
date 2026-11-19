<?php

namespace Tests\Feature;

use App\Models\Huddle;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Quick Huddle: who may ask whom, and what cannot be reached round the page.
 *
 * The page draws its buttons from the same rule this tests, but a button is
 * only a suggestion — anybody can post to the route directly. So what is
 * checked here is the server's own answer, which is the one that counts:
 *
 *   · nobody on the floor asks the owner or HR;
 *   · the owner and HR may ask anybody;
 *   · a huddle belongs to the two people in it and to nobody else;
 *   · saying yes to being watched is not saying yes to being clicked;
 *   · and one person is in one huddle at a time.
 */
class HuddleAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $hr;
    private User $agent;
    private User $closer;
    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-09 11:00'));

        $this->owner = $this->person('The Owner', 'superadmin');
        $this->hr = $this->person('Hr Lead', 'hr');
        $this->agent = $this->person('Ali Agent', 'agent');
        $this->closer = $this->person('Zara Closer', 'closer');

        // Somebody at another company, who should be invisible either way.
        $elsewhere = \App\Models\Company::create([
            'name' => 'Another Company',
            'slug' => 'another-company',
        ]);
        $this->outsider = User::create([
            'company_id' => $elsewhere->id,
            'name' => 'Far Away',
            'username' => 'faraway',
            'email' => 'faraway@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'agent',
        ]);
        $this->outsider->forceFill(['last_seen_at' => now()])->save();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function person(string $name, string $role): User
    {
        $handle = strtolower(str_replace(' ', '', $name));

        $person = User::create([
            'company_id' => $this->company->id,
            'name' => $name,
            'username' => $handle,
            'email' => $handle . '@example.com',
            'password' => bcrypt('secret123'),
            'role' => $role,
        ]);

        // Nobody can be asked unless they are at their desk. The column is kept
        // off the fillable list — the app writes it as people browse — so it is
        // set here rather than passed in, where it would be quietly dropped.
        $person->forceFill(['last_seen_at' => now()])->save();

        return $person;
    }

    private function ask(User $asker, User $host)
    {
        return $this->actingAs($asker)->postJson(route('admin.huddle.request'), [
            'host_id' => $host->id,
        ]);
    }

    // ---- who may ask whom ---------------------------------------------------

    public function test_the_floor_cannot_ask_the_owner(): void
    {
        $this->ask($this->agent, $this->owner)
            ->assertStatus(422)
            ->assertJsonValidationErrors('host_id');

        $this->assertDatabaseCount('huddles', 0);
    }

    public function test_the_floor_cannot_ask_hr(): void
    {
        $this->ask($this->closer, $this->hr)
            ->assertStatus(422)
            ->assertJsonValidationErrors('host_id');

        $this->assertDatabaseCount('huddles', 0);
    }

    public function test_the_floor_can_ask_the_floor(): void
    {
        $this->ask($this->agent, $this->closer)->assertOk();

        $this->assertDatabaseHas('huddles', [
            'host_id' => $this->closer->id,
            'guest_id' => $this->agent->id,
            'status' => Huddle::PENDING,
            // Watching is what was asked for; clicking is a separate yes.
            'can_control' => false,
        ]);
    }

    public function test_the_owner_may_ask_anybody(): void
    {
        $this->ask($this->owner, $this->hr)->assertOk();
        $this->assertDatabaseHas('huddles', ['host_id' => $this->hr->id, 'guest_id' => $this->owner->id]);
    }

    public function test_hr_may_ask_anybody(): void
    {
        $this->ask($this->hr, $this->owner)->assertOk();
        $this->assertDatabaseHas('huddles', ['host_id' => $this->owner->id, 'guest_id' => $this->hr->id]);
    }

    public function test_nobody_asks_somebody_at_another_company(): void
    {
        $this->ask($this->agent, $this->outsider)
            ->assertStatus(422)
            ->assertJsonValidationErrors('host_id');
    }

    public function test_nobody_asks_themselves(): void
    {
        $this->ask($this->agent, $this->agent)
            ->assertStatus(422)
            ->assertJsonValidationErrors('host_id');
    }

    public function test_somebody_away_from_their_desk_cannot_be_asked(): void
    {
        $this->closer->forceFill(['last_seen_at' => now()->subHour()])->save();

        $this->ask($this->agent, $this->closer)
            ->assertStatus(422)
            ->assertJsonValidationErrors('host_id');
    }

    // ---- one at a time ------------------------------------------------------

    public function test_one_huddle_at_a_time(): void
    {
        $this->ask($this->agent, $this->closer)->assertOk();

        // The same host, asked by somebody else.
        $second = $this->person('Sana Agent', 'agent');
        $this->ask($second, $this->closer)
            ->assertStatus(422)
            ->assertJsonValidationErrors('host_id');

        // And the same guest, asking somebody else.
        $third = $this->person('Hamza Agent', 'agent');
        $this->ask($this->agent, $third)
            ->assertStatus(422)
            ->assertJsonValidationErrors('host_id');

        $this->assertDatabaseCount('huddles', 1);
    }

    public function test_a_request_nobody_answered_does_not_block_the_next_one(): void
    {
        $this->ask($this->agent, $this->closer)->assertOk();

        // Long enough that it counts as missed rather than waiting.
        Carbon::setTestNow(now()->addSeconds(Huddle::REQUEST_EXPIRES_AFTER_SECONDS + 10));

        $this->ask($this->agent, $this->closer)->assertOk();

        $this->assertSame(1, Huddle::where('status', Huddle::PENDING)->count());
        $this->assertSame(1, Huddle::where('ended_by', 'timeout')->count());
    }

    // ---- answering, and the mouse ------------------------------------------

    public function test_only_the_host_answers(): void
    {
        $huddle = $this->pending();

        // The one who asked cannot say yes on the other's behalf.
        $this->actingAs($this->agent)
            ->postJson(route('admin.huddle.answer', $huddle), ['accept' => true])
            ->assertForbidden();

        $this->assertSame(Huddle::PENDING, $huddle->fresh()->status);
    }

    public function test_saying_yes_to_watching_is_not_saying_yes_to_clicking(): void
    {
        $huddle = $this->pending();

        $this->actingAs($this->closer)
            ->postJson(route('admin.huddle.answer', $huddle), ['accept' => true])
            ->assertOk();

        $huddle->refresh();
        $this->assertSame(Huddle::ACTIVE, $huddle->status);
        $this->assertFalse($huddle->can_control);
    }

    public function test_the_mouse_can_be_handed_over_and_taken_back_by_the_host_only(): void
    {
        $huddle = $this->pending();

        $this->actingAs($this->closer)
            ->postJson(route('admin.huddle.answer', $huddle), ['accept' => true, 'can_control' => true])
            ->assertOk();
        $this->assertTrue($huddle->fresh()->can_control);

        // The guest cannot give themselves the mouse back.
        $this->actingAs($this->agent)
            ->postJson(route('admin.huddle.control', $huddle), ['can_control' => true])
            ->assertForbidden();

        $this->actingAs($this->closer)
            ->postJson(route('admin.huddle.control', $huddle), ['can_control' => false])
            ->assertOk();
        $this->assertFalse($huddle->fresh()->can_control);
    }

    public function test_either_of_them_can_end_it_and_nobody_else_can(): void
    {
        $huddle = $this->pending();
        $stranger = $this->person('Nosey Parker', 'agent');

        $this->actingAs($stranger)
            ->postJson(route('admin.huddle.end', $huddle))
            ->assertForbidden();

        $this->actingAs($this->agent)
            ->postJson(route('admin.huddle.end', $huddle))
            ->assertOk();

        $huddle->refresh();
        $this->assertSame(Huddle::ENDED, $huddle->status);
        $this->assertSame('guest', $huddle->ended_by);
    }

    // ---- the messages the two browsers swap --------------------------------

    public function test_only_the_two_of_them_can_read_or_write_signals(): void
    {
        $huddle = $this->pending();
        $stranger = $this->person('Nosey Parker', 'agent');

        $this->actingAs($stranger)
            ->postJson(route('admin.huddle.signal', $huddle), ['kind' => 'offer', 'payload' => '{}'])
            ->assertForbidden();

        $this->actingAs($stranger)
            ->getJson(route('admin.huddle.signals', $huddle))
            ->assertForbidden();
    }

    public function test_each_side_is_handed_only_what_the_other_one_said(): void
    {
        $huddle = $this->pending();

        $this->actingAs($this->agent)
            ->postJson(route('admin.huddle.signal', $huddle), ['kind' => 'answer', 'payload' => 'from the guest'])
            ->assertOk();

        // The guest does not get their own message back.
        $this->actingAs($this->agent)
            ->getJson(route('admin.huddle.signals', $huddle))
            ->assertOk()
            ->assertJsonCount(0, 'signals');

        $this->actingAs($this->closer)
            ->getJson(route('admin.huddle.signals', $huddle))
            ->assertOk()
            ->assertJsonCount(1, 'signals')
            ->assertJsonPath('signals.0.payload', 'from the guest');
    }

    // ---- the sharing window -------------------------------------------------

    public function test_only_the_host_opens_the_sharing_window(): void
    {
        $huddle = $this->pending();

        $this->actingAs($this->agent)
            ->get(route('admin.huddle.share', $huddle))
            ->assertForbidden();

        $this->actingAs($this->closer)
            ->get(route('admin.huddle.share', $huddle))
            ->assertOk();
    }

    public function test_the_sharing_window_is_gone_once_the_huddle_is_over(): void
    {
        $huddle = $this->pending();
        $huddle->update(['status' => Huddle::ENDED, 'ended_at' => now()]);

        $this->actingAs($this->closer)
            ->get(route('admin.huddle.share', $huddle))
            ->assertStatus(410);
    }

    /** An agent asking a closer, waiting on an answer. */
    private function pending(): Huddle
    {
        $this->ask($this->agent, $this->closer)->assertOk();

        return Huddle::where('guest_id', $this->agent->id)->latest('id')->firstOrFail();
    }
}
