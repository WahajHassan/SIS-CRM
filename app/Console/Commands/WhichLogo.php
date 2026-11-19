<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;

/**
 * Which logo will the login page draw, and why that one?
 *
 * The page tries four things in order and falls back to the company's name as
 * text when none of them is there. From the browser all four failures look
 * identical, so this says which step was reached and what stopped it — rather
 * than leaving somebody to guess between "the file is missing", "the database
 * says nothing" and "the new code is not on this server yet".
 */
class WhichLogo extends Command
{
    protected $signature = 'crm:logo';

    protected $description = 'Say which logo the login page will show, and why';

    public function handle(): int
    {
        $company = Company::withoutGlobalScopes()->first();

        $this->newLine();
        $this->line('<options=bold>Company</>');
        $this->line('  name      : ' . ($company->name ?? '(no company row)'));
        $this->line('  logo      : ' . ($company?->logo ?: '(nothing recorded)'));
        $this->line('  dark_logo : ' . ($company?->dark_logo ?: '(nothing recorded)'));

        $this->newLine();
        $this->line('<options=bold>What the login page tries, in order</>');

        $legacy = glob(public_path('uploads/company_logos/*dark_logo*'));

        $steps = [
            ['the company\'s dark logo', $company?->dark_logo],
            ['a legacy *dark_logo* file', !empty($legacy) ? 'uploads/company_logos/' . basename($legacy[0]) : null],
            ['the company\'s logo', $company?->logo],
            ['the mark shipped with the CRM', 'uploads/company_logos/whitelogo.png'],
        ];

        $chosen = null;

        foreach ($steps as [$what, $path]) {
            if (!$path) {
                $this->line(sprintf('  <fg=gray>—</> %-32s nothing recorded', $what));
                continue;
            }

            $full = public_path($path);
            $there = file_exists($full);

            $this->line(sprintf(
                '  %s %-32s %s',
                $there ? '<fg=green>✓</>' : '<fg=red>✗</>',
                $what,
                $there ? $path : "{$path}  <fg=red>(no such file)</>"
            ));

            if ($there && !$chosen) {
                $chosen = $path;
            }
        }

        $this->newLine();

        if ($chosen) {
            $this->info('The login page will show: ' . $chosen);
            $this->line('  served from: ' . url('file/' . $chosen));
            $this->newLine();
            $this->line('If the browser still shows the company name as text, the new');
            $this->line('login page is not on this server yet, or the view cache is stale:');
            $this->line('  <options=bold>php artisan view:clear</>');

            return self::SUCCESS;
        }

        $this->error('Nothing was found, so the page falls back to the company name as text.');
        $this->newLine();
        $this->line('  Either upload a logo in Settings > Branding,');
        $this->line('  or put a file at ' . public_path('uploads/company_logos/whitelogo.png'));

        return self::FAILURE;
    }
}
