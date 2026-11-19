<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * The way back in.
 *
 * An empty database has no company and no login, and every screen in the CRM
 * is behind one. `migrate:fresh` on a live server therefore locks everybody
 * out of it, including the person who ran it — there is no migration and no
 * seeder that puts the first owner back.
 *
 * So this exists to be run immediately after a wipe. It makes one company and
 * one owner account, which is the smallest thing the CRM can be opened with;
 * everything else is then made from inside it.
 */
class CreateFirstOwner extends Command
{
    protected $signature = 'crm:first-owner
                            {--company= : The company name}
                            {--username= : The login}
                            {--email= : Their email}
                            {--password= : Their password}';

    protected $description = 'Create the first company and owner account on an empty database';

    public function handle(): int
    {
        if (User::withoutGlobalScopes()->where('role', 'superadmin')->exists()) {
            $this->error('There is already an owner account. This command is only for an empty database.');
            $this->line('To add another owner, use User Management inside the CRM.');

            return self::FAILURE;
        }

        $companyName = $this->option('company') ?: $this->ask('Company name');
        $username = $this->option('username') ?: $this->ask('Owner login', 'owner');
        $email = $this->option('email') ?: $this->ask('Owner email');
        $password = $this->option('password') ?: $this->secret('Owner password');

        if (!$companyName || !$username || !$email || !$password) {
            $this->error('All four are needed: company, username, email, password.');

            return self::FAILURE;
        }

        if (strlen($password) < 8) {
            $this->error('The password needs at least 8 characters.');

            return self::FAILURE;
        }

        // The migration that creates the companies table puts one row in it, so
        // on a freshly migrated database there is already a company sitting
        // there with a name somebody else chose. Rename that one rather than
        // adding a second: two companies on a one-company install is a mess
        // nothing in the CRM would help them out of.
        $company = Company::withoutGlobalScopes()->first();

        if ($company) {
            $company->update([
                'name' => $companyName,
                'slug' => Str::slug($companyName),
                'is_active' => true,
            ]);
        } else {
            $company = Company::create([
                'name' => $companyName,
                'slug' => Str::slug($companyName),
                'is_active' => true,
            ]);
        }

        $owner = User::create([
            'company_id' => $company->id,
            'name' => 'Owner',
            'username' => $username,
            'email' => $email,
            'password' => bcrypt($password),
            'role' => 'superadmin',
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        $this->newLine();
        $this->info('Done. The CRM can be opened now.');
        $this->table(
            ['', ''],
            [
                ['Company', $company->name . '  (id ' . $company->id . ')'],
                ['Login', $owner->username],
                ['Email', $owner->email],
            ]
        );
        $this->warn('Sign in and change this password before anybody else is given an account.');

        return self::SUCCESS;
    }
}
