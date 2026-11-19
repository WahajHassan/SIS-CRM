<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\SystemAnnouncement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Where does the email stop?
 *
 * crm:mail-test sends straight down the wire and proves the mail server works.
 * Real notifications do not go that way — they are queued, so that announcing
 * something to twenty people does not make the page wait twenty seconds. Which
 * means "the test works but nothing arrives" is not a contradiction: it is the
 * single most likely thing to be wrong, and it points at the cron rather than
 * at the mail server.
 *
 * So this walks the real road instead of the short one. It sends a genuine
 * notification to a real account, watches whether it reaches the queue, drains
 * the queue by hand, and says which of the three places it stopped: the
 * decision to send, the queue, or the mail server.
 */
class WhyNoMail extends Command
{
    protected $signature = 'crm:mail-why {username? : Whose account to use for the test}';

    protected $description = 'Work out why notification emails are not arriving';

    public function handle(): int
    {
        $this->newLine();

        /* ---- 1. the backlog, which is usually the answer ------------------- */

        $waiting = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
        $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;

        $this->line('<options=bold>The queue</>');
        $this->line("  waiting : {$waiting}");
        $this->line("  failed  : {$failed}");

        if ($failed > 0) {
            $last = DB::table('failed_jobs')->latest('failed_at')->first();

            $this->newLine();
            $this->error('Something has been failing. The most recent one said:');
            $this->line('  ' . trim(explode("\n", (string) ($last->exception ?? ''))[0]));
            $this->newLine();
            $this->line('  Clear them once the cause is fixed: <options=bold>php artisan queue:flush</>');
        }

        if ($waiting > 0) {
            $this->newLine();
            $this->warn("{$waiting} job(s) are sitting in the queue, so nothing is draining it.");
            $this->line('  That is the cron. It should run every minute:');
            $this->line('  <options=bold>* * * * * cd ' . base_path() . ' && php artisan schedule:run >> /dev/null 2>&1</>');
        }

        /* ---- 2. would the CRM even try? ------------------------------------ */

        $this->newLine();
        $this->line('<options=bold>Would the CRM send one at all?</>');

        $ready = SystemAnnouncement::mailIsSetUp();
        $this->line('  mail configured : ' . ($ready ? 'yes' : 'NO'));

        if (!$ready) {
            $this->newLine();
            $this->error('The CRM thinks this server cannot send, so it never queues an email.');
            $this->line('  Run <options=bold>php artisan crm:mail-test</> — it says exactly which setting is wrong.');

            return self::FAILURE;
        }

        $who = $this->argument('username');

        $person = $who
            ? User::withoutGlobalScopes()->where('username', $who)->first()
            : User::withoutGlobalScopes()->whereNotNull('email')->where('email', '!=', '')->first();

        if (!$person) {
            $this->error($who ? "No account called \"{$who}\"." : 'No account on this system has an email address.');

            return self::FAILURE;
        }

        $this->line('  testing with    : ' . $person->username . ' <' . ($person->email ?: 'no address') . '>');
        $this->line('  their switch    : ' . ($person->email_notifications ? 'emails on' : 'EMAILS TURNED OFF in My Profile'));

        if (!$person->email_notifications) {
            $this->newLine();
            $this->warn('That person has turned their emails off, so they get the bell and nothing else.');
            $this->line('  My Profile > "Email me when something needs me".');

            return self::SUCCESS;
        }

        /* ---- 3. send a real one and watch it ------------------------------- */

        $this->newLine();
        $this->line('<options=bold>Sending a real notification…</>');

        $before = DB::table('jobs')->count();

        $person->notify(new SystemAnnouncement([
            'title' => 'Checking the email road',
            'message' => 'This was sent by crm:mail-why to find out where notifications stop.',
            'icon' => 'fas fa-envelope',
            'url' => route('admin.dashboard'),
        ]));

        $queued = DB::table('jobs')->count() - $before;

        $this->line('  bell written    : yes');
        $this->line('  queued for mail : ' . ($queued > 0 ? 'yes' : 'NO'));

        if ($queued < 1) {
            $this->newLine();
            $this->error('It was not queued, so the mail channel decided not to send.');
            $this->line('  That is either the address, their switch, or the mail configuration.');

            return self::FAILURE;
        }

        /* ---- 4. drain it by hand -------------------------------------------- */

        $this->newLine();
        $this->line('Draining the queue by hand, the way the cron would…');
        $this->newLine();

        $this->call('queue:work', [
            '--stop-when-empty' => true,
            '--tries' => 1,
            '--max-time' => 60,
        ]);

        $left = DB::table('jobs')->count();
        $nowFailed = DB::table('failed_jobs')->count() - $failed;

        $this->newLine();

        if ($nowFailed > 0) {
            $last = DB::table('failed_jobs')->latest('failed_at')->first();

            $this->error('It reached the mail server and was refused:');
            $this->line('  ' . trim(explode("\n", (string) ($last->exception ?? ''))[0]));
            $this->newLine();
            $this->line('  Run <options=bold>php artisan crm:mail-test</> for what to change.');

            return self::FAILURE;
        }

        if ($left > $waiting) {
            $this->error('The job is still sitting there. The queue worker could not run it.');

            return self::FAILURE;
        }

        $this->info('It went. ' . $person->email . ' should have it — check the spam folder too.');
        $this->newLine();
        $this->line('So the road itself works. If real notifications still do not arrive, it is');
        $this->line('the cron that is not draining the queue — nothing else is left.');

        return self::SUCCESS;
    }
}
