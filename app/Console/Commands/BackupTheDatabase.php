<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * A copy of everything, taken every night.
 *
 * The Recycle Bin covers somebody pressing the wrong button. It does not cover
 * a disk failing, a bad migration, a host losing a server, or somebody with
 * database access running the wrong statement — and there was nothing at all
 * covering those.
 *
 * mysqldump where it exists, because it is the fastest and most faithful copy
 * and the one any host can restore from. Where it does not — plenty of shared
 * hosts leave it out — the fallback writes the same SQL by reading the tables
 * through the connection the app already has. Slower, but a backup that only
 * works on some servers is not a backup.
 *
 * Old copies are thrown away on a schedule, because a backup that fills the
 * disk takes the site down, which is the thing it was meant to prevent.
 */
class BackupTheDatabase extends Command
{
    protected $signature = 'crm:backup
                            {--keep=14 : How many days of copies to keep}
                            {--path= : Where to write them}
                            {--quiet-ok : Say nothing unless something goes wrong}';

    protected $description = 'Write a copy of the database, and tidy away old ones';

    public function handle(): int
    {
        $folder = $this->option('path') ?: storage_path('app/backups');

        if (!is_dir($folder) && !@mkdir($folder, 0750, true)) {
            $this->error("Could not create {$folder}");

            return self::FAILURE;
        }

        $this->keepItOutOfTheWeb($folder);

        $database = config('database.connections.' . config('database.default') . '.database');
        $file = $folder . DIRECTORY_SEPARATOR . 'crm-' . now()->format('Y-m-d-His') . '.sql';

        $wrote = $this->withMysqldump($file) ?: $this->byHand($file);

        if (!$wrote) {
            $this->error('The backup could not be written.');

            return self::FAILURE;
        }

        $size = filesize($file);

        // A file that exists but holds nothing is worse than no file, because
        // it looks like a backup until the day somebody needs it.
        if ($size < 1024) {
            @unlink($file);
            $this->error('The backup came out empty and was thrown away. Nothing has been backed up.');

            return self::FAILURE;
        }

        $removed = $this->tidyAway($folder, (int) $this->option('keep'));

        if (!$this->option('quiet-ok')) {
            $this->info(sprintf(
                'Backed up %s — %s (%s MB)%s',
                $database,
                basename($file),
                number_format($size / 1048576, 2),
                $removed ? ", {$removed} old copy(ies) removed" : ''
            ));
        }

        return self::SUCCESS;
    }

    /* ---- the fast way --------------------------------------------------- */

    private function withMysqldump(string $file): bool
    {
        $c = config('database.connections.' . config('database.default'));

        if (($c['driver'] ?? null) !== 'mysql') {
            return false;
        }

        $dump = $this->findMysqldump();

        if (!$dump) {
            return false;
        }

        // The password goes in a file rather than on the command line, where
        // every other user on a shared host can read it out of the process
        // list.
        $credentials = tempnam(sys_get_temp_dir(), 'crmdb');
        file_put_contents(
            $credentials,
            "[client]\nuser=\"{$c['username']}\"\npassword=\"{$c['password']}\"\nhost=\"{$c['host']}\"\nport=\"{$c['port']}\"\n"
        );
        @chmod($credentials, 0600);

        try {
            $process = new Process([
                $dump,
                '--defaults-extra-file=' . $credentials,
                '--single-transaction',       // a consistent copy without locking the floor out
                '--quick',
                '--skip-lock-tables',
                '--default-character-set=utf8mb4',
                $c['database'],
            ]);

            $process->setTimeout(1800);

            $out = fopen($file, 'w');
            $process->run(function ($type, $chunk) use ($out) {
                if ($type === Process::OUT) {
                    fwrite($out, $chunk);
                }
            });
            fclose($out);

            if (!$process->isSuccessful()) {
                $this->warn('mysqldump did not finish: ' . trim($process->getErrorOutput()));
                @unlink($file);

                return false;
            }

            return true;
        } finally {
            @unlink($credentials);
        }
    }

    private function findMysqldump(): ?string
    {
        foreach (['mysqldump', '/usr/bin/mysqldump', '/usr/local/bin/mysqldump', '/opt/alt/mysql/usr/bin/mysqldump'] as $where) {
            $p = new Process([$where, '--version']);
            $p->run();

            if ($p->isSuccessful()) {
                return $where;
            }
        }

        return null;
    }

    /* ---- the way that works anywhere ------------------------------------- */

    /**
     * The same SQL, written through the app's own connection.
     *
     * For hosts with no mysqldump. Every table is read in chunks so a large one
     * cannot exhaust memory, and the result is an ordinary .sql file that can be
     * fed back in exactly the same way.
     */
    private function byHand(string $file): bool
    {
        $handle = fopen($file, 'w');

        if (!$handle) {
            return false;
        }

        fwrite($handle, "-- CRM backup, written without mysqldump\n");
        fwrite($handle, '-- ' . now()->toDateTimeString() . "\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

        foreach ($this->tables() as $table) {
            $create = DB::selectOne("SHOW CREATE TABLE `{$table}`");
            $sql = $create->{'Create Table'} ?? ($create->{'Create View'} ?? null);

            if (!$sql) {
                continue;
            }

            fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n{$sql};\n\n");

            DB::table($table)->orderBy(DB::raw('1'))->chunk(500, function ($rows) use ($handle, $table) {
                foreach ($rows as $row) {
                    $values = collect((array) $row)
                        ->map(fn ($v) => $v === null ? 'NULL' : DB::getPdo()->quote((string) $v))
                        ->implode(', ');

                    fwrite($handle, "INSERT INTO `{$table}` VALUES ({$values});\n");
                }
            });

            fwrite($handle, "\n");
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);

        return true;
    }

    private function tables(): array
    {
        return array_map(
            fn ($row) => array_values((array) $row)[0],
            DB::select('SHOW TABLES')
        );
    }

    /* ---- housekeeping ----------------------------------------------------- */

    /**
     * Backups hold every password hash and every customer's details, so they
     * must not be reachable over the web. storage/ is already outside the
     * document root on a normal install, but plenty of shared hosts are not
     * normal installs.
     */
    private function keepItOutOfTheWeb(string $folder): void
    {
        $guard = $folder . DIRECTORY_SEPARATOR . '.htaccess';

        if (!file_exists($guard)) {
            @file_put_contents($guard, "Require all denied\nDeny from all\n");
        }

        $index = $folder . DIRECTORY_SEPARATOR . 'index.html';

        if (!file_exists($index)) {
            @file_put_contents($index, '');
        }
    }

    private function tidyAway(string $folder, int $keepDays): int
    {
        if ($keepDays < 1) {
            return 0;
        }

        $cutoff = now()->subDays($keepDays)->getTimestamp();
        $removed = 0;

        foreach (glob($folder . DIRECTORY_SEPARATOR . 'crm-*.sql') ?: [] as $old) {
            if (filemtime($old) < $cutoff && @unlink($old)) {
                $removed++;
            }
        }

        return $removed;
    }
}
