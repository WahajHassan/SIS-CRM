<?php

namespace App\Console\Commands;

use App\Notifications\SystemAnnouncement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Does the mail actually go out?
 *
 * Setting up SMTP is the step where everything silently does nothing: a wrong
 * port, an unverified sender, a password with a quote in it. All of those fail
 * the same way from inside the CRM — the notification appears in the bell and
 * no email arrives, with the reason buried in a log nobody opens.
 *
 * So this says what the server is configured to do, tries it once for real, and
 * when it fails, explains the failure in the terms the person can act on rather
 * than handing back a transport exception.
 */
class TestTheMail extends Command
{
    protected $signature = 'crm:mail-test {to? : Where to send it}';

    protected $description = 'Send one test email and say plainly what happened';

    public function handle(): int
    {
        $mailer = config('mail.default');

        $this->newLine();
        $this->line('<options=bold>What this server is set up to do</>');

        $this->table(['', ''], [
            ['Driver', $mailer],
            ['Host', config("mail.mailers.{$mailer}.host") ?: '—'],
            ['Port', config("mail.mailers.{$mailer}.port") ?: '—'],
            ['Scheme', config("mail.mailers.{$mailer}.scheme") ?: 'decided by the port'],
            ['Username', config("mail.mailers.{$mailer}.username") ?: '—'],
            ['Password', config("mail.mailers.{$mailer}.password") ? 'set (' . strlen((string) config("mail.mailers.{$mailer}.password")) . ' characters)' : 'NOT SET'],
            ['From', config('mail.from.address') . ' (' . config('mail.from.name') . ')'],
        ]);

        // The same question the CRM itself asks before it bothers queueing one.
        if (!SystemAnnouncement::mailIsSetUp()) {
            $this->error('The CRM considers mail NOT set up, so it will not send anything.');
            $this->newLine();

            foreach ($this->whyNot($mailer) as $reason) {
                $this->line('  • ' . $reason);
            }

            $this->newLine();
            $this->line('Fix the .env, then run <options=bold>php artisan config:clear</> and try again.');

            return self::FAILURE;
        }

        // The commonest misconfiguration of the lot, and it fails at the very
        // last step with a 550 that reads like a problem with the recipient.
        //
        // A mail server will not send on behalf of an address it has not
        // authenticated. Signing in as info@ and then claiming to be crm@ is
        // forgery as far as the server is concerned, and it says no.
        $username = (string) config("mail.mailers.{$mailer}.username");
        $from = (string) config('mail.from.address');

        if (str_contains($username, '@') && strcasecmp($username, $from) !== 0) {
            $this->newLine();
            $this->warn('MAIL_USERNAME and MAIL_FROM_ADDRESS are different addresses.');
            $this->line("  signing in as : {$username}");
            $this->line("  sending as    : {$from}");
            $this->newLine();
            $this->line('  Most mail servers refuse that — you may only send as the mailbox you signed into.');
            $this->line("  Unless {$from} is a real alias of {$username}, set MAIL_FROM_ADDRESS to {$username}.");
            $this->newLine();
        }

        $to = $this->argument('to') ?: $this->ask('Send the test to which address?');

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error('That is not an email address.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->line("Sending to {$to}…");

        try {
            Mail::raw(
                "This is a test from your CRM.\n\n"
                . "If you are reading this, the CRM can send email: leave approvals, announcements, "
                . "and leads handed to somebody will now reach them.\n\n"
                . 'Sent ' . now()->format('j M Y, g:i a') . '.',
                function ($message) use ($to) {
                    $message->to($to)->subject('Your CRM can send email');
                }
            );
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('It did not go.');
            $this->newLine();
            $this->line('<options=bold>What the mail server said</>');
            $this->line('  ' . $e->getMessage());
            $this->newLine();

            foreach ($this->adviceFor($e->getMessage()) as $hint) {
                $this->line('  • ' . $hint);
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Sent. Check {$to} — including the spam folder, which is where a new sender usually lands first.");
        $this->line('If it is in spam, add an SPF record for your domain and it will settle down.');

        return self::SUCCESS;
    }

    /** @return array<string> */
    private function whyNot(string $mailer): array
    {
        $why = [];

        if (in_array($mailer, ['log', 'array', null], true)) {
            $why[] = "MAIL_MAILER is \"{$mailer}\" — that writes to a file instead of sending. Set MAIL_MAILER=smtp.";
        }

        $host = config("mail.mailers.{$mailer}.host");

        if (!$host || in_array($host, ['127.0.0.1', 'localhost'], true)) {
            $why[] = 'MAIL_HOST is not a real mail server. For a Hostinger mailbox it is smtp.hostinger.com.';
        }

        if (config('mail.from.address') === 'hello@example.com' || !config('mail.from.address')) {
            $why[] = 'MAIL_FROM_ADDRESS is still the Laravel default. Set it to a real mailbox on your own domain.';
        }

        if (!config("mail.mailers.{$mailer}.username")) {
            $why[] = 'MAIL_USERNAME is empty. It is the full email address, not just the part before the @.';
        }

        return $why ?: ['Something in the mail configuration is incomplete.'];
    }

    /**
     * The handful of failures that account for almost all of them, said in
     * terms of what to change rather than what the library threw.
     *
     * @return array<string>
     */
    private function adviceFor(string $said): array
    {
        $said = strtolower($said);

        return match (true) {
            str_contains($said, 'authentication') || str_contains($said, '535') => [
                'The username or password was refused.',
                'MAIL_USERNAME must be the FULL address — crm@systematicitsolutions.com, not crm.',
                'If the password has a # or a space in it, wrap it in quotes in the .env.',
            ],

            str_contains($said, 'connection could not be established')
            || str_contains($said, 'closed unexpectedly')     // what a refused port says
            || str_contains($said, 'connection refused')
            || str_contains($said, 'timed out')
            || str_contains($said, 'getaddrinfo') => [
                'Nothing usable answered at that host and port.',
                'Check MAIL_HOST is spelled right.',
                'Port 2525 is the Laravel default and Hostinger stopped accepting it years ago — use 587 or 465.',
                'On 587 leave MAIL_SCHEME out; on 465 set MAIL_SCHEME=smtps.',
                'If one port is blocked by the host, the other usually is not.',
            ],

            str_contains($said, 'ssl') || str_contains($said, 'tls') || str_contains($said, 'certificate') => [
                'The encryption did not match the port.',
                'Port 465 needs MAIL_SCHEME=smtps.',
                'Port 587 needs MAIL_SCHEME=smtp (or leave MAIL_SCHEME out entirely).',
            ],

            str_contains($said, 'sender') || str_contains($said, '550') || str_contains($said, 'relay') => [
                'The server would not send on behalf of that address.',
                'MAIL_FROM_ADDRESS must be a mailbox that really exists on this domain,',
                'and it usually has to be the same address as MAIL_USERNAME.',
            ],

            default => [
                'Run this again after `php artisan config:clear` — a cached config is the commonest cause of a change not taking.',
            ],
        };
    }
}
