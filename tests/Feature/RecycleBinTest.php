<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\SystemLog;
use App\Models\TransferredLead;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Deleting somebody must not destroy their record.
 *
 * The database is wired so that a real DELETE on a user takes twenty-four other
 * tables with it: every day they clocked, the breaks inside those days, their
 * leave, their employee file, and every lead they handed over. That is what the
 * red button used to do, behind one browser confirm box, with no backup and no
 * log.
 *
 * So the test is not "does the row disappear" — it is "does everything else
 * survive". If somebody ever swaps the trait out or calls forceDelete from a
 * screen, this is what fails.
 */
class RecycleBinTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $victim;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-13 10:00'));

        $this->owner = $this->person('superadmin', 'theowner');
        $this->victim = $this->person('agent', 'leaver');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function person(string $role, string $handle): User
    {
        return User::create([
            'company_id' => $this->company->id,
            'name' => ucfirst($handle),
            'username' => $handle,
            'email' => $handle . '@example.com',
            'password' => bcrypt('secret123'),
            'role' => $role,
            'is_active' => true,
            'approval_status' => 'approved',
        ]);
    }

    /** A day on the clock, with a break inside it. */
    private function aDayOfWork(User $who): Attendance
    {
        $day = Attendance::create([
            'company_id' => $this->company->id,
            'user_id' => $who->id,
            'date' => '2026-10-01',
            'clock_in' => Carbon::parse('2026-10-01 09:00'),
            'clock_out' => Carbon::parse('2026-10-01 18:00'),
            'status' => 'clocked_out',
        ]);

        AttendanceBreak::create([
            'attendance_id' => $day->id,
            'break_start' => Carbon::parse('2026-10-01 13:00'),
            'break_end' => Carbon::parse('2026-10-01 13:30'),
        ]);

        return $day;
    }

    private function aHandover(User $agent): TransferredLead
    {
        return TransferredLead::create([
            'company_id' => $this->company->id,
            'agent_id' => $agent->id,
            'customer_name' => 'Gutter Co',
            'business_name' => 'Gutter Co',
            'stage' => 'pool',
            'status' => 'Active',
            'scheduled_at' => Carbon::parse('2026-10-14 11:00'),
        ]);
    }

    /* ---- the thing that must not happen again -------------------------------- */

    public function test_deleting_somebody_leaves_every_day_they_ever_worked(): void
    {
        $day = $this->aDayOfWork($this->victim);
        $handover = $this->aHandover($this->victim);

        $this->victim->delete();

        // The row is marked, not removed.
        $this->assertSoftDeleted('users', ['id' => $this->victim->id]);
        $this->assertDatabaseHas('users', ['id' => $this->victim->id]);

        // And nothing cascaded.
        $this->assertDatabaseHas('attendances', ['id' => $day->id]);
        $this->assertSame(1, DB::table('attendance_breaks')->where('attendance_id', $day->id)->count());
        $this->assertDatabaseHas('transferred_leads', ['id' => $handover->id]);
    }

    public function test_the_record_still_says_whose_it_was(): void
    {
        $day = $this->aDayOfWork($this->victim);

        $this->victim->delete();

        // The attendance sheet has to be able to print a name, or the page that
        // shows it dies reaching through a null.
        $this->assertNotNull($day->fresh()->user);
        $this->assertSame('Leaver', $day->fresh()->user->name);
    }

    public function test_they_are_gone_from_the_lists_they_should_be_gone_from(): void
    {
        $this->victim->delete();

        $this->assertNull(User::find($this->victim->id));
        $this->assertFalse(User::where('username', 'leaver')->exists());
        $this->assertTrue(User::withTrashed()->where('username', 'leaver')->exists());
    }

    public function test_a_removed_account_cannot_sign_in(): void
    {
        $this->victim->delete();

        $this->post(route('login.post'), ['login' => 'leaver', 'password' => 'secret123'])
            ->assertSessionHasErrors();

        $this->assertGuest();
    }

    /* ---- putting them back ---------------------------------------------------- */

    public function test_the_bin_gives_them_back_whole(): void
    {
        $day = $this->aDayOfWork($this->victim);
        $this->victim->delete();

        $this->actingAs($this->owner)
            ->from(route('admin.bin'))
            ->post(route('admin.bin.restore', ['users', $this->victim->id]))
            ->assertRedirect(route('admin.bin'));

        $back = User::find($this->victim->id);

        $this->assertNotNull($back);
        $this->assertNull($back->deleted_at);
        $this->assertSame('agent', $back->role);
        $this->assertDatabaseHas('attendances', ['id' => $day->id, 'user_id' => $back->id]);
    }

    public function test_the_bin_lists_what_was_removed(): void
    {
        $this->victim->delete();

        $this->actingAs($this->owner)
            ->get(route('admin.bin'))
            ->assertOk()
            ->assertSee('Leaver')
            ->assertSee('Recycle Bin');
    }

    public function test_an_empty_bin_says_so(): void
    {
        $this->actingAs($this->owner)
            ->get(route('admin.bin'))
            ->assertOk()
            ->assertSee('The bin is empty');
    }

    /* ---- who may open it ------------------------------------------------------ */

    public function test_the_bin_is_closed_without_user_management(): void
    {
        $agent = $this->person('agent', 'nobody');
        $agent->update(['permissions' => ['dashboard']]);

        $this->actingAs($agent)->get(route('admin.bin'))->assertForbidden();
    }

    public function test_only_the_owner_may_destroy_something_for_good(): void
    {
        $hr = $this->person('hr', 'thehr');
        $hr->update(['permissions' => ['dashboard', 'users']]);

        $this->victim->delete();

        $this->actingAs($hr)
            ->post(route('admin.bin.purge', ['users', $this->victim->id]))
            ->assertForbidden();

        // Still there, still recoverable.
        $this->assertDatabaseHas('users', ['id' => $this->victim->id]);
    }

    public function test_the_owner_can_destroy_it_and_it_is_written_down(): void
    {
        $this->victim->delete();

        $this->actingAs($this->owner)
            ->from(route('admin.bin'))
            ->post(route('admin.bin.purge', ['users', $this->victim->id]))
            ->assertRedirect(route('admin.bin'));

        $this->assertDatabaseMissing('users', ['id' => $this->victim->id]);
        $this->assertTrue(
            SystemLog::where('action_type', 'like', 'Destroyed%')->exists(),
            'destroying something for good must be written down'
        );
    }

    /* ---- the other things that used to vanish --------------------------------- */

    public function test_a_lead_a_deal_and_a_handover_can_all_be_undone(): void
    {
        $lead = Lead::create([
            'company_id' => $this->company->id,
            'name' => 'Roof Co',
            'source' => 'Cold call',
            'created_by' => $this->owner->id,
        ]);

        $deal = Deal::create([
            'company_id' => $this->company->id,
            'name' => 'Roof Co',
            'value' => 1200,
            'status' => 'PENDING',
        ]);

        $handover = $this->aHandover($this->victim);

        $lead->delete();
        $deal->delete();
        $handover->delete();

        $this->assertSoftDeleted('leads', ['id' => $lead->id]);
        $this->assertSoftDeleted('deals', ['id' => $deal->id]);
        $this->assertSoftDeleted('transferred_leads', ['id' => $handover->id]);

        foreach ([['leads', $lead->id], ['deals', $deal->id], ['transfers', $handover->id]] as [$kind, $id]) {
            $this->actingAs($this->owner)->post(route('admin.bin.restore', [$kind, $id]));
        }

        $this->assertNull(Lead::find($lead->id)?->deleted_at);
        $this->assertNull(Deal::find($deal->id)?->deleted_at);
        $this->assertNull(TransferredLead::find($handover->id)?->deleted_at);
    }

    /* ---- and it is written down ----------------------------------------------- */

    public function test_removing_somebody_is_written_down(): void
    {
        $this->actingAs($this->owner)
            ->delete(route('admin.users.delete', $this->victim->id));

        $this->assertTrue(
            SystemLog::where('action_type', 'Deleted User')->exists(),
            'a deletion nobody recorded is a deletion nobody can ask about'
        );
    }
}
