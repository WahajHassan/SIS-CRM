<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\SystemAnnouncement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Is this server actually ready to be used by customers?
 *
 * Every item here is something that is invisible until the day it matters: a
 * debug flag that prints the database password onto an error page, a backup
 * that has never run, a queue nobody is draining so no email ever leaves. None
 * of them show up in the application — the CRM looks perfectly fine with all
 * three wrong.
 *
 * So this asks the questions out loud and says which answers are a problem.
 * Read-only: it changes nothing, and it is safe to run on a live server.
 */
class CheckBeforeLaunch extends Command
{
    protected $signature = 'crm:check';

    protected $description = 'Check this server over before customers use it';

    private array $bad = [];
    private array $warn = [];

    public function handle(): int
    {
        $this->newLine();
        $this->line('<options=bold>Checking this server over</>');
        $this->newLine();

        $this->theDebugFlag();
        $this->theEnvironment();
        $this->theAppKey();
        $this->theDatabase();
        $this->theBackups();
        $this->theScheduler();
        $this->theMail();
        $this->theHttps();
        $this->theOwner();
        $this->theStorageLink();

        $this->newLine();

        if ($this->bad) {
            $this->error(count($this->bad) . ' thing(s) to fix before customers use this:');
            foreach ($this->bad as $line) {
                $this->line('  <fg=red>✗</> ' . $line);
            }
        }

        if ($this->warn) {
            $this->newLine();
            $this->warn(count($this->warn) . ' thing(s) worth looking at:');
            foreach ($this->warn as $line) {
                $this->line('  <fg=yellow>!</> ' . $line);
            }
        }

        if (!$this->bad && !$this->warn) {
            $this->info('Everything checked out.');
        }

        $this->newLine();

        return $this->bad ? self::FAILURE : self::SUCCESS;
    }

    /* ---- the checks ---------------------------------------------------------- */

    private function theDebugFlag(): void
    {
        $on = config('app.debug');

        $this->say('Debug mode', $on ? 'ON' : 'off', !$on);

        if ($on) {
            $this->bad[] = 'APP_DEBUG=true — every error page will print the database password and the code behind it. Set APP_DEBUG=false.';
        }
    }

    private function theEnvironment(): void
    {
        $env = config('app.env');

        $this->say('Environment', $env, $env === 'production');

        if ($env !== 'production') {
            $this->warn[] = "APP_ENV is \"{$env}\". On a live server it should be production.";
        }
    }

    private function theAppKey(): void
    {
        $key = config('app.key');

        $this->say('App key', $key ? 'set' : 'MISSING', (bool) $key);

        if (!$key) {
            $this->bad[] = 'APP_KEY is empty — every encrypted field (card details, card numbers) is unreadable. Run php artisan key:generate.';
        }
    }

    private function theDatabase(): void
    {
        try {
            DB::connection()->getPdo();
            $behind = $this->migrationsBehind();

            $this->say('Database', $behind === 0 ? 'connected, up to date' : "connected, {$behind} migration(s) behind", $behind === 0);

            if ($behind > 0) {
                $this->bad[] = "{$behind} migration(s) have not been run. Run php artisan migrate --force.";
            }
        } catch (\Throwable $e) {
            $this->say('Database', 'NOT CONNECTED', false);
            $this->bad[] = 'The database could not be reached: ' . $e->getMessage();
        }
    }

    private function migrationsBehind(): int
    {
        if (!Schema::hasTable('migrations')) {
            return 0;
        }

        $ran = DB::table('migrations')->pluck('migration')->flip();
        $behind = 0;

        foreach (glob(database_path('migrations/*.php')) ?: [] as $file) {
            if (!$ran->has(basename($file, '.php'))) {
                $behind++;
            }
        }

        return $behind;
    }

    private function theBackups(): void
    {
        $folder = storage_path('app/backups');
        $copies = glob($folder . DIRECTORY_SEPARATOR . 'crm-*.sql') ?: [];

        if (!$copies) {
            $this->say('Backups', 'NONE', false);
            $this->bad[] = 'No backup has ever been written. Run php artisan crm:backup once by hand to prove it works.';

            return;
        }

        $newest = max(array_map('filemtime', $copies));
        $age = (int) round((time() - $newest) / 3600);

        $this->say('Backups', count($copies) . ' copies, newest ' . $age . 'h old', $age < 48);

        if ($age >= 48) {
            $this->bad[] = "The newest backup is {$age} hours old — the nightly one is not running. Check the cron.";
        }
    }

    private function theScheduler(): void
    {
        // Every scheduled job writes nothing of its own, but the backup folder
        // and the queue both depend on schedule:run being called by a real cron.
        $queued = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;

        $this->say('Queue', $queued === 0 ? 'empty' : $queued . ' job(s) waiting', $queued < 50);

        if ($queued >= 50) {
            $this->bad[] = "{$queued} jobs are stuck in the queue — nothing is draining it, so no email is going out. Check that the cron runs schedule:run every minute.";
        }
    }

    private function theMail(): void
    {
        $ready = SystemAnnouncement::mailIsSetUp();

        $this->say('Email', $ready ? 'configured' : 'not configured', $ready);

        if (!$ready) {
            $this->warn[] = 'No mail server is set up, so nothing the CRM says will reach anybody by email. Run php artisan crm:mail-test to see why.';
        }
    }

    private function theHttps(): void
    {
        $url = (string) config('app.url');
        $secure = str_starts_with($url, 'https://');

        $this->say('APP_URL', $url ?: 'not set', $secure);

        if (!$secure) {
            $this->bad[] = "APP_URL is \"{$url}\" — it must be the real https:// address, or password reset emails will carry a link that sends the new password in the clear.";
        }
    }

    private function theOwner(): void
    {
        $owners = User::withoutGlobalScopes()->where('role', 'superadmin')->count();

        $this->say('Owner accounts', (string) $owners, $owners > 0);

        if ($owners === 0) {
            $this->bad[] = 'There is no owner account — nobody can administer this CRM. Run php artisan crm:first-owner.';
        }
    }

    /**
     * Can an uploaded file actually be fetched?
     *
     * Not simply "is there a symlink". This application also carries a
     * /storage/{path} route that reads straight out of storage/app/public, and
     * the subfolder deployment relies on it precisely because a symlink cannot
     * be made there. Telling somebody on that layout to run storage:link would
     * be telling them to break it.
     */
    private function theStorageLink(): void
    {
        $linked = file_exists(public_path('storage'));
        $routed = \Illuminate\Support\Facades\Route::has('storage.fallback');

        $ok = $linked || $routed;

        $this->say('Uploads', $linked ? 'served by the link' : ($routed ? 'served by the fallback route' : 'NOT SERVABLE'), $ok);

        if (!$ok) {
            $this->bad[] = 'Uploaded pictures and documents cannot be fetched: there is neither a public/storage link nor the /storage fallback route.';
        }
    }

    /* ---- saying it ------------------------------------------------------------ */

    private function say(string $what, string $found, bool $good): void
    {
        $this->line(sprintf(
            '  %s %s %s',
            $good ? '<fg=green>✓</>' : '<fg=red>✗</>',
            str_pad($what, 18, '.'),
            $good ? $found : "<fg=red>{$found}</>"
        ));
    }
}
