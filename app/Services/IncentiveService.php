<?php

namespace App\Services;

use App\Models\IncentiveRule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * What an agent has earned this month, and what is still within reach.
 *
 * The sales figure is the same one the targets page counts — won deals in the
 * month with this person on them — so the blocks on their dashboard cannot
 * disagree with the rest of the CRM.
 *
 * The rules themselves are rows HR can edit, not numbers in this file. A
 * company that has never opened that page still gets the agreed set, seeded
 * from config/incentives.php.
 */
class IncentiveService
{
    private PerformanceService $performance;

    public function __construct(?PerformanceService $performance = null)
    {
        $this->performance = $performance ?: new PerformanceService();
    }

    /** Sales closed by this person in a month, in dollars. */
    public function salesIn(User $agent, Carbon $month): float
    {
        return $this->performance->value($agent, 'deals_value', $month->copy());
    }

    /** The rules that apply to somebody, in the order they are shown. */
    public function rulesFor(User $agent): Collection
    {
        // A company upgrading into this feature has no rules yet; the agreed set
        // is put in place the first time somebody looks, rather than leaving an
        // agent with a blank strip until HR opens the settings page.
        if (!IncentiveRule::query()->exists()) {
            self::seedDefaults($agent->company_id);
        }

        return IncentiveRule::active()->ordered()->get()
            ->filter(fn (IncentiveRule $rule) => $rule->appliesTo($agent->role))
            ->values();
    }

    /**
     * Everything one agent's month amounts to: the blocks, in order, each one
     * knowing whether it has been earned and what it would take.
     */
    public function summary(User $agent, ?Carbon $month = null): array
    {
        $month = ($month ?: Carbon::now())->copy()->startOfMonth();

        $rules = $this->rulesFor($agent);
        $sales = $this->salesIn($agent, $month);

        $targetRule = $rules->firstWhere('kind', IncentiveRule::TARGET);
        $target = (float) ($targetRule->threshold ?? 0);
        $targetMet = $target > 0 && $sales >= $target;

        $blocks = [];
        $earned = 0.0;

        foreach ($rules as $rule) {
            $block = match ($rule->kind) {
                IncentiveRule::TARGET => $this->targetBlock($rule, $sales),
                IncentiveRule::BOUNTY => $this->bountyBlock($rule, $sales),
                IncentiveRule::CONSISTENCY => $this->consistencyBlock($rule, $agent, $month, $target),
                IncentiveRule::ALLOWANCE => $this->allowanceBlock($rule, $targetMet, $target, $sales),
                default => null,
            };

            if (!$block) {
                continue;
            }

            if ($block['achieved'] && $rule->kind !== IncentiveRule::TARGET) {
                $earned += (float) $rule->reward_amount;
            }

            $blocks[] = $block;
        }

        return [
            'month' => $month,
            'sales' => $sales,
            'target' => $target,
            'target_met' => $targetMet,
            'progress' => $target > 0 ? (int) min(100, round($sales / $target * 100)) : 0,
            'blocks' => $blocks,
            'earned' => $earned,
        ];
    }

    // ---- the blocks ---------------------------------------------------------

    private function targetBlock(IncentiveRule $rule, float $sales): array
    {
        $target = (float) $rule->threshold;
        $met = $target > 0 && $sales >= $target;

        return [
            'rule' => $rule,
            'kind' => $rule->kind,
            'label' => $rule->label,
            'note' => $rule->note,
            'figure' => '$' . number_format($sales),
            'figure_note' => 'of $' . number_format($target),
            'progress' => $target > 0 ? (int) min(100, round($sales / $target * 100)) : 0,
            'achieved' => $met,
            'icon' => 'fa-bullseye',
            'foot' => $met
                ? 'Target reached'
                : '$' . number_format(max(0, $target - $sales)) . ' to go',
            'foot_strong' => $met,
        ];
    }

    private function bountyBlock(IncentiveRule $rule, float $sales): array
    {
        $needed = (float) $rule->threshold;
        $met = $needed > 0 && $sales >= $needed;

        return [
            'rule' => $rule,
            'kind' => $rule->kind,
            'label' => $rule->label,
            'note' => $rule->note,
            'figure' => $rule->rewardText(),
            'figure_note' => null,
            'progress' => $needed > 0 ? (int) min(100, round($sales / $needed * 100)) : 0,
            'achieved' => $met,
            'icon' => 'fa-sack-dollar',
            'foot' => $met
                ? 'Earned this month'
                : '$' . number_format(max(0, $needed - $sales)) . ' more in sales',
            'foot_strong' => $met,
        ];
    }

    private function consistencyBlock(IncentiveRule $rule, User $agent, Carbon $month, float $target): array
    {
        $needed = max(1, (int) ($rule->months ?: 3));
        $months = [];
        $streak = 0;

        for ($i = $needed - 1; $i >= 0; $i--) {
            $each = $month->copy()->subMonths($i);
            $sales = $this->salesIn($agent, $each);
            $met = $target > 0 && $sales >= $target;

            $months[] = ['month' => $each, 'sales' => $sales, 'met' => $met];
            $streak = $met ? $streak + 1 : 0;
        }

        $achieved = $streak >= $needed;
        $left = max(0, $needed - $streak);

        return [
            'rule' => $rule,
            'kind' => $rule->kind,
            'label' => $rule->label,
            'note' => $rule->note,
            'figure' => $streak,
            'figure_note' => '/ ' . $needed . ' months',
            'progress' => (int) round($streak / $needed * 100),
            'achieved' => $achieved,
            'icon' => 'fa-arrow-trend-up',
            'months' => $months,
            'foot' => $achieved
                ? 'Earned — ' . ($rule->salary_increment > 0 ? '$' . number_format($rule->salary_increment) . ' increment + ' : '') . $rule->rewardText()
                : 'Hit the target ' . $left . ' more ' . str('month')->plural($left) . ' running',
            'foot_strong' => $achieved,
        ];
    }

    private function allowanceBlock(IncentiveRule $rule, bool $targetMet, float $target, float $sales): array
    {
        return [
            'rule' => $rule,
            'kind' => $rule->kind,
            'label' => $rule->label,
            'note' => $rule->note,
            'figure' => $rule->rewardText(),
            'figure_note' => null,
            'progress' => $target > 0 ? (int) min(100, round($sales / $target * 100)) : 0,
            'achieved' => $targetMet,
            'icon' => 'fa-gas-pump',
            'foot' => $targetMet ? 'Earned this month' : 'Paid once this month’s target is reached',
            'foot_strong' => $targetMet,
        ];
    }

    /**
     * Put the agreed set in place for a company that has none.
     *
     * Read from config/incentives.php, which is where these numbers lived
     * before HR could edit them.
     */
    public static function seedDefaults(?int $companyId = null): void
    {
        $companyId = $companyId ?: (auth()->check() ? auth()->user()->company_id : null);

        $exists = IncentiveRule::withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->exists();

        if ($exists) {
            return;
        }

        $rules = [[
            'kind' => IncentiveRule::TARGET,
            'label' => 'Monthly target',
            'note' => 'At least $' . number_format((float) config('incentives.monthly_target', 500)) . ' in sales',
            'threshold' => (float) config('incentives.monthly_target', 500),
            'reward_amount' => 0,
            'position' => 0,
        ]];

        foreach (config('incentives.bounties', []) as $i => $bounty) {
            $rules[] = [
                'kind' => IncentiveRule::BOUNTY,
                'label' => $bounty['label'],
                'note' => $bounty['note'],
                'threshold' => (float) $bounty['above'],
                'reward_amount' => (float) $bounty['reward'],
                'position' => $i + 1,
            ];
        }

        $consistency = config('incentives.consistency', []);
        $rules[] = [
            'kind' => IncentiveRule::CONSISTENCY,
            'label' => $consistency['label'] ?? 'Target 3 months running',
            'note' => $consistency['note'] ?? null,
            'months' => $consistency['months'] ?? 3,
            'salary_increment' => $consistency['increment'] ?? null,
            'reward_amount' => (float) ($consistency['reward'] ?? 0),
            'position' => 10,
        ];

        $fuel = config('incentives.fuel_allowance', []);
        $rules[] = [
            'kind' => IncentiveRule::ALLOWANCE,
            'label' => $fuel['label'] ?? 'Fuel allowance',
            'note' => $fuel['note'] ?? null,
            'reward_amount' => (float) ($fuel['reward'] ?? 0),
            'position' => 11,
        ];

        foreach ($rules as $rule) {
            IncentiveRule::withoutGlobalScope('company')->create($rule + [
                'company_id' => $companyId,
                'reward_currency' => 'PKR',
                'roles' => 'agent',
                'is_active' => true,
            ]);
        }
    }
}
