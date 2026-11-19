<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Mail;

/**
 * Everything the CRM tells somebody.
 *
 * It used to go one place only: the bell at the top of the page. Which meant a
 * leave approval, a lead handed to you, an announcement from the floor manager
 * — none of it reached anybody who was not already looking at the CRM. People
 * found out the next morning, or when somebody walked over and told them.
 *
 * Two channels now, and they are deliberately not sent the same way:
 *
 *   - the bell is instant, because somebody watching the page should see it
 *     appear; it is written straight to the database on the way past.
 *   - the email is queued, because sending one takes a second against a mail
 *     server, and a floor manager announcing something to twenty closers must
 *     not wait twenty seconds for their page to come back.
 *
 * viaConnections() is what keeps those apart.
 */
class SystemAnnouncement extends Notification implements ShouldQueue
{
    use Queueable;

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Where it goes.
     *
     * The bell always. Email only when there is somewhere to send it, somebody
     * has not turned it off, and this server can actually send — a CRM that
     * silently piles up failed mail jobs is worse than one that never tried.
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($this->worthEmailing($notifiable)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * The bell is written now; the email waits for the worker.
     *
     * Without this the whole notification would queue, and the bell — which
     * people watch while they work — would not light up until the next minute.
     */
    public function viaConnections(): array
    {
        return [
            'database' => 'sync',
            'mail' => config('queue.default'),
        ];
    }

    /* ---- the email ---------------------------------------------------------- */

    public function toMail(object $notifiable): MailMessage
    {
        $title = $this->data['title'] ?? 'From your CRM';
        $body = $this->data['message'] ?? '';
        $url = $this->data['url'] ?? route('admin.dashboard');

        $mail = (new MailMessage)
            ->subject($title)
            ->greeting('Hello ' . ($notifiable->display_name ?? $notifiable->name) . ',')
            ->line($body);

        // A link only earns its place when it goes somewhere in particular.
        if ($url) {
            $mail->action('Open it in the CRM', $url);
        }

        return $mail->line('You are getting this because it needs you. You can turn these emails off in My Profile.');
    }

    /* ---- the bell ----------------------------------------------------------- */

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->data['title'] ?? 'System Announcement',
            'message' => $this->data['message'] ?? '',
            'icon' => $this->data['icon'] ?? 'fas fa-info-circle',
            'type' => $this->data['type'] ?? 'info',
            // Carried through at last. Without it every notification was a dead
            // end: it told somebody something needed them and gave them no way
            // to get to it.
            'url' => $this->data['url'] ?? null,
        ];
    }

    /* ---- whether an email is a good idea ------------------------------------ */

    private function worthEmailing(object $notifiable): bool
    {
        if (empty($notifiable->email)) {
            return false;
        }

        // Their own switch, in My Profile.
        if (property_exists($notifiable, 'email_notifications')
            || array_key_exists('email_notifications', $notifiable->getAttributes())) {
            if (!$notifiable->email_notifications) {
                return false;
            }
        }

        return self::mailIsSetUp();
    }

    /**
     * Can this server actually send?
     *
     * `log` and `array` are the drivers that pretend. Beyond that an SMTP
     * mailer with no host configured will fail on every single job, so it is
     * treated as not set up rather than left to fill the failed_jobs table.
     */
    public static function mailIsSetUp(): bool
    {
        $mailer = config('mail.default');

        if (in_array($mailer, ['log', 'array', null], true)) {
            return false;
        }

        $transport = config("mail.mailers.{$mailer}.transport");

        if ($transport === 'smtp') {
            $host = config("mail.mailers.{$mailer}.host");

            if (!$host || $host === '127.0.0.1' || $host === 'localhost') {
                return false;
            }
        }

        $from = config('mail.from.address');

        return $from && $from !== 'hello@example.com';
    }
}
