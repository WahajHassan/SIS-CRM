<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SystemAnnouncement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Telling somebody something, and it actually reaching them.
 *
 * Everything the CRM had to say went to the bell at the top of the page and
 * nowhere else, so anybody not already looking at the CRM found out the next
 * morning. What is checked here is that both roads work and that neither gets
 * in the other's way: the bell is instant, the email waits for the worker, and
 * nothing is sent to somebody who asked not to be or to a server that cannot
 * send.
 */
class NotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $handle, array $extra = []): User
    {
        return User::create(array_merge([
            'company_id' => $this->company->id,
            'name' => ucfirst($handle),
            'username' => $handle,
            'email' => $handle . '@example.com',
            'password' => bcrypt('secret123'),
            'role' => 'agent',
            'is_active' => true,
            'approval_status' => 'approved',
        ], $extra));
    }

    /** A mail server that looks real enough for the readiness check. */
    private function withAMailServer(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example.com',
            'mail.from.address' => 'crm@systematicitsolutions.com',
        ]);
    }

    private function announcement(): SystemAnnouncement
    {
        return new SystemAnnouncement([
            'title' => 'A lead for you',
            'message' => 'Ali handed you Gutter Co.',
            'icon' => 'fas fa-user-tie',
            'url' => 'https://crm.example.com/admin/transferred-leads',
        ]);
    }

    /* ---- the bell ----------------------------------------------------------- */

    public function test_the_bell_always_gets_it(): void
    {
        $this->withAMailServer();
        // The mail channel is real in this one — the point is that the bell is
        // written whatever the mail server does — so the transport is held back
        // rather than left to dial a hostname that does not exist.
        Mail::fake();

        $person = $this->person('ali');

        $person->notify($this->announcement());

        $this->assertSame(1, $person->notifications()->count());
    }

    public function test_the_bell_now_knows_where_to_send_them(): void
    {
        $person = $this->person('ali');

        $person->notify($this->announcement());

        $data = $person->notifications()->first()->data;

        // Every notification already knew where it came from and threw it away,
        // so the bell could say something needed you and not say where.
        $this->assertSame('https://crm.example.com/admin/transferred-leads', $data['url']);
        $this->assertSame('A lead for you', $data['title']);
    }

    public function test_a_notification_with_nowhere_to_go_still_works(): void
    {
        $person = $this->person('ali');

        $person->notify(new SystemAnnouncement(['title' => 'Just so you know', 'message' => 'Floor closes early.']));

        $this->assertNull($person->notifications()->first()->data['url']);
    }

    /* ---- the email ---------------------------------------------------------- */

    public function test_an_email_goes_out_as_well(): void
    {
        $this->withAMailServer();
        Notification::fake();

        $person = $this->person('ali');
        $person->notify($this->announcement());

        Notification::assertSentTo($person, SystemAnnouncement::class, function ($notification, $channels) use ($person) {
            return in_array('mail', $channels, true) && in_array('database', $channels, true);
        });
    }

    public function test_the_email_says_what_happened_and_where_to_go(): void
    {
        $this->withAMailServer();

        $person = $this->person('ali');
        $mail = $this->announcement()->toMail($person);
        $rendered = $mail->render();

        $this->assertStringContainsString('Ali handed you Gutter Co.', $rendered);
        $this->assertStringContainsString('crm.example.com/admin/transferred-leads', $rendered);
        $this->assertSame('A lead for you', $mail->subject);
    }

    public function test_the_bell_is_instant_and_the_email_waits(): void
    {
        $this->withAMailServer();
        config(['queue.default' => 'database']);

        $connections = (new SystemAnnouncement([]))->viaConnections();

        // A floor manager announcing something to twenty people must not wait
        // twenty seconds, and somebody watching the page must not wait a minute.
        $this->assertSame('sync', $connections['database']);
        $this->assertSame('database', $connections['mail']);
    }

    /* ---- and when it should not be sent -------------------------------------- */

    public function test_nothing_is_emailed_to_somebody_who_turned_it_off(): void
    {
        $this->withAMailServer();
        Notification::fake();

        $person = $this->person('ali', ['email_notifications' => false]);
        $person->notify($this->announcement());

        Notification::assertSentTo($person, SystemAnnouncement::class, function ($notification, $channels) {
            return $channels === ['database'];
        });
    }

    public function test_nothing_is_emailed_when_the_server_cannot_send(): void
    {
        config(['mail.default' => 'log']);
        Notification::fake();

        $person = $this->person('ali');
        $person->notify($this->announcement());

        // Trying anyway would fill failed_jobs on every announcement.
        Notification::assertSentTo($person, SystemAnnouncement::class, function ($notification, $channels) {
            return $channels === ['database'];
        });
    }

    public function test_an_unconfigured_from_address_counts_as_not_set_up(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example.com',
            'mail.from.address' => 'hello@example.com',   // the Laravel default
        ]);

        $this->assertFalse(SystemAnnouncement::mailIsSetUp());
    }

    public function test_a_local_smtp_host_counts_as_not_set_up(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.from.address' => 'crm@systematicitsolutions.com',
        ]);

        $this->assertFalse(SystemAnnouncement::mailIsSetUp());
    }

    public function test_somebody_with_no_email_is_not_emailed(): void
    {
        $this->withAMailServer();
        Notification::fake();

        $person = $this->person('ali');
        // The column will not take a null, so the honest version of "no email
        // on file" here is an empty one.
        $person->forceFill(['email' => ''])->save();

        $person->notify($this->announcement());

        Notification::assertSentTo($person, SystemAnnouncement::class, function ($notification, $channels) {
            return $channels === ['database'];
        });
    }

    /* ---- the switch ----------------------------------------------------------- */

    public function test_everybody_starts_with_the_emails_on(): void
    {
        $this->assertTrue($this->person('ali')->email_notifications);
    }

    public function test_the_switch_is_on_their_profile_and_saves(): void
    {
        $person = $this->person('ali');

        $this->actingAs($person)
            ->get(route('admin.my_profile'))
            ->assertOk()
            ->assertSee('Email me when something needs me');

        $this->actingAs($person)->put(route('admin.my_profile.update'), [
            'name' => $person->name,
            'email' => $person->email,
            // the box left unticked sends nothing at all
        ]);

        $this->assertFalse($person->fresh()->email_notifications);

        $this->actingAs($person)->put(route('admin.my_profile.update'), [
            'name' => $person->name,
            'email' => $person->email,
            'email_notifications' => '1',
        ]);

        $this->assertTrue($person->fresh()->email_notifications);
    }
}
