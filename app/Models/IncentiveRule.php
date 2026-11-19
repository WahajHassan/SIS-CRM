<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * One thing an agent can earn: the monthly target, a sales bounty, the streak,
 * or an allowance.
 *
 * Every change to one of these is written to the log, because a bounty that
 * quietly changed mid-month is exactly the sort of thing that is argued about
 * later.
 */
class IncentiveRule extends Model
{
    use BelongsToCompany;

    public const TARGET = 'target';
    public const BOUNTY = 'bounty';
    public const CONSISTENCY = 'consistency';
    public const ALLOWANCE = 'allowance';

    public const KINDS = [
        self::TARGET => 'Monthly target',
        self::BOUNTY => 'Sales bounty',
        self::CONSISTENCY => 'Consistency (several months running)',
        self::ALLOWANCE => 'Allowance on hitting target',
    ];

    protected $fillable = [
        'company_id', 'kind', 'label', 'note', 'threshold', 'reward_amount',
        'reward_currency', 'salary_increment', 'months', 'roles', 'position',
        'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'threshold' => 'float',
        'reward_amount' => 'float',
        'salary_increment' => 'float',
        'months' => 'integer',
        'position' => 'integer',
        'is_active' => 'boolean',
    ];

    /** The fields worth keeping a history of. */
    private const LOGGED = [
        'kind', 'label', 'note', 'threshold', 'reward_amount', 'reward_currency',
        'salary_increment', 'months', 'roles', 'is_active',
    ];

    protected static function booted(): void
    {
        static::created(fn (self $rule) => $rule->log('created', collect(self::LOGGED)
            ->mapWithKeys(fn ($f) => [$f => [null, $rule->{$f}]])
            ->all()));

        static::updated(function (self $rule) {
            $changes = [];

            foreach (self::LOGGED as $field) {
                if ($rule->wasChanged($field)) {
                    $changes[$field] = [$rule->getOriginal($field), $rule->{$field}];
                }
            }

            if ($changes) {
                $rule->log('updated', $changes);
            }
        });

        static::deleted(fn (self $rule) => $rule->log('deleted', [
            'label' => [$rule->label, null],
            'reward_amount' => [$rule->reward_amount, null],
        ]));
    }

    public function logs()
    {
        return $this->hasMany(IncentiveRuleLog::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('position')->orderBy('threshold')->orderBy('id');
    }

    /** Whether this applies to somebody in a given role. */
    public function appliesTo(string $role): bool
    {
        $roles = collect(explode(',', (string) $this->roles))
            ->map(fn ($r) => trim($r))
            ->filter()
            ->all();

        return !$roles || in_array($role, $roles, true);
    }

    public function rewardText(): string
    {
        if ($this->reward_amount <= 0) {
            return '—';
        }

        return $this->reward_currency === 'USD'
            ? '$' . number_format($this->reward_amount)
            : 'PKR ' . number_format($this->reward_amount);
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? ucfirst((string) $this->kind);
    }

    private function log(string $action, array $changes): void
    {
        IncentiveRuleLog::create([
            'incentive_rule_id' => $this->id,
            'label' => $this->label,
            'action' => $action,
            'changes' => array_filter($changes, fn ($pair) => ($pair[0] ?? null) != ($pair[1] ?? null)),
            'changed_by' => auth()->id(),
            'company_id' => $this->company_id,
        ]);
    }
}
