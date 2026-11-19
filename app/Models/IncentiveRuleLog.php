<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** Who changed an incentive, when, and from what to what. */
class IncentiveRuleLog extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'incentive_rule_id', 'label', 'action', 'changes', 'changed_by'];

    protected $casts = ['changes' => 'array'];

    public function rule()
    {
        return $this->belongsTo(IncentiveRule::class, 'incentive_rule_id');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'changed_by')->withTrashed();
    }

    /** The change list as short, readable lines. */
    public function lines(): array
    {
        $names = [
            'reward_amount' => 'reward',
            'salary_increment' => 'salary increment',
            'threshold' => 'sales needed',
            'is_active' => 'active',
            'reward_currency' => 'currency',
        ];

        /*
         * getAttribute, not $this->changes: Eloquent itself has a protected
         * $changes property (the attributes altered by the last save), and
         * inside the model that wins over the column of the same name — which
         * silently returned an empty list every time.
         */
        return collect($this->getAttribute('changes') ?? [])
            ->map(function ($pair, $field) use ($names) {
                $format = fn ($v) => match (true) {
                    $v === null || $v === '' => '—',
                    is_bool($v) => $v ? 'yes' : 'no',
                    is_numeric($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.'),
                    default => (string) $v,
                };

                return sprintf('%s: %s → %s', $names[$field] ?? str_replace('_', ' ', $field), $format($pair[0] ?? null), $format($pair[1] ?? null));
            })
            ->values()
            ->all();
    }
}
