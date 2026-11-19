<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('attendance:auto-checkout', function () {
    \App\Models\Attendance::autoCheckoutExpired();
    $this->info('Expired attendances checked out successfully.');
})->purpose('Automatically checkout active attendances older than 9 hours');

\Illuminate\Support\Facades\Schedule::command('attendance:auto-checkout')->everyTenMinutes();

/*
 * Absences are marked after shifts end rather than at midnight: the office runs
 * through the night, so midnight is the middle of a working day here. Hourly is
 * often enough to catch each shift soon after it closes, and the command itself
 * only judges dates whose shift has already finished.
 */
\Illuminate\Support\Facades\Schedule::command('attendance:mark-absent')->hourly();

// Incoming mail is only pulled once a real mailbox has been configured in the IMAP_*
// settings. Left at the package default there are no credentials to connect with, and
// scheduling it anyway would only fail every ten minutes and fill the log.
$imapMailbox = config('imap.accounts.default.username');

if ($imapMailbox && $imapMailbox !== 'root@example.com') {
    \Illuminate\Support\Facades\Schedule::command('emails:fetch')->everyTenMinutes();
}


/*
 * A copy of everything, nightly.
 *
 * Soft deletes cover the wrong button; they do not cover a disk, a host, or a
 * bad statement run straight against the database. Nothing covered those.
 *
 * Just after 3am because the floor is quiet then — the command does not lock
 * anybody out while it reads, but a quiet moment is still the cheapest one.
 * Two weeks of copies: long enough that a problem noticed on a Monday can be
 * undone from before the weekend, short enough not to fill the disk.
 */
\Illuminate\Support\Facades\Schedule::command('crm:backup --keep=14 --quiet-ok')
    ->dailyAt('03:10')
    ->withoutOverlapping()
    ->onOneServer();

/*
 * The queue, on a cron rather than a daemon.
 *
 * Email is queued so a floor manager announcing something to twenty people does
 * not wait twenty seconds for the page to come back. Queued work needs a worker,
 * and shared hosting has nowhere to keep one running — so the worker is started
 * every minute, drains whatever is waiting, and exits.
 *
 * --stop-when-empty is what makes it exit instead of sitting there; --max-time
 * keeps it from overlapping the next minute's run if a mail server is slow.
 */
\Illuminate\Support\Facades\Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3 --quiet')
    ->everyMinute()
    ->withoutOverlapping();
