<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Conversation;
use App\Models\Department;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Every department gets a channel, with its people in it.
 *
 * Sales and Tech talk to each other all day; without this somebody has to make
 * the channel and then remember to add each new hire to it. Run again after
 * people move around and the membership catches up — it only adds, so anybody
 * invited into a team channel from outside that department stays where they were
 * put.
 */
class SyncTeamChannels extends Command
{
    protected $signature = 'chat:team-channels {--prune : also remove people who have left the department}';

    protected $description = 'Give every department a chat channel and keep its members in it';

    public function handle(): int
    {
        $companies = Company::query()->get();

        if ($companies->isEmpty()) {
            $this->warn('No companies yet — nothing to do.');
            return self::SUCCESS;
        }

        foreach ($companies as $company) {
            foreach (Department::withoutGlobalScope('company')->where('company_id', $company->id)->get() as $department) {
                $this->syncOne($company->id, $department);
            }
        }

        return self::SUCCESS;
    }

    private function syncOne(int $companyId, Department $department): void
    {
        $memberIds = User::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->whereIn('id', EmployeeProfile::withoutGlobalScope('company')
                ->where('department_id', $department->id)
                ->pluck('user_id'))
            ->pluck('id');

        if ($memberIds->isEmpty()) {
            // Nobody is in it because nobody has been put in it: a department is
            // set per person on their HR record, and until that is done there is
            // no team to make a channel for.
            $this->warn("{$department->name}: no staff assigned to this department yet — "
                . 'set it on each person under HR, Employee Records, then run this again.');

            return;
        }

        $name = $department->name;

        $channel = Conversation::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->where('type', 'group')
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->first();

        if (!$channel) {
            $channel = Conversation::withoutGlobalScope('company')->create([
                'company_id' => $companyId,
                'type' => 'group',
                'name' => $name,
                // The department's lead owns it where there is one; otherwise the
                // first person in it, so the channel is never left ownerless.
                'created_by' => $department->lead_user_id ?: $memberIds->first(),
            ]);

            $this->info("{$name}: channel created.");
        }

        $already = $channel->users()->pluck('users.id');
        $missing = $memberIds->diff($already);

        if ($missing->isNotEmpty()) {
            $channel->users()->attach($missing->all());
            $this->line("{$name}: added {$missing->count()}.");
        }

        if ($this->option('prune')) {
            $gone = $already->diff($memberIds)->reject(fn ($id) => $id === $channel->created_by);

            if ($gone->isNotEmpty()) {
                $channel->users()->detach($gone->all());
                $this->line("{$name}: removed {$gone->count()} who have left it.");
            }
        }
    }
}
