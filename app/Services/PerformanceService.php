<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Deal;
use App\Models\KpiTarget;
use App\Models\SupportTicket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Targets that keep their own score.
 *
 * A target measured from the CRM reads its achieved figure out of the work that
 * is already recorded — deals, tickets, attendance — so nobody has to total
 * anything up by hand at month end, and the number cannot drift from the source
 * it came from.
 */
class PerformanceService
{
    /**
     * The figures the CRM can work out on its own.
     *
     * @return array<string, array{label: string, unit: string, help: string}>
     */
    public static function sources(): array
    {
        return [
            'manual' => [
                'label' => 'Typed in by hand',
                'unit' => 'number',
                'help' => 'You enter what was achieved yourself.',
            ],
            'deals_closed' => [
                'label' => 'Deals closed (from Deals)',
                'unit' => 'number',
                'help' => 'Deals marked WON in the month with this person as the agent or closer.',
            ],
            'deals_value' => [
                'label' => 'Revenue closed (from Deals)',
                'unit' => 'amount',
                'help' => 'The value of those same won deals.',
            ],
            'free_signups' => [
                'label' => 'Viewrec signups (from Viewrec)',
                'unit' => 'number',
                'help' => 'Free signups this person took in the month. Five a working day is the usual ask.',
            ],
            'tickets_resolved' => [
                'label' => 'Tickets resolved (from Support)',
                'unit' => 'number',
                'help' => 'Support tickets assigned to them that reached resolved in the month.',
            ],
            'attendance_days' => [
                'label' => 'Days attended (from Attendance)',
                'unit' => 'number',
                'help' => 'Full days worked in the month, half days counting as half.',
            ],
            'hours_worked' => [
                'label' => 'Hours worked (from Attendance)',
                'unit' => 'number',
                'help' => 'Net hours on the clock, breaks already taken off.',
            ],
            'punctuality' => [
                'label' => 'On-time arrivals (from Attendance)',
                'unit' => 'percent',
                'help' => 'The share of days worked where they arrived within the grace period.',
            ],
        ];
    }

    public static function isAuto(?string $source): bool
    {
        return $source !== null && $source !== '' && $source !== 'manual';
    }

    public static function label(?string $source): string
    {
        return self::sources()[$source ?? 'manual']['label'] ?? 'Typed in by hand';
    }

    /**
     * What this person actually achieved against a system-measured target.
     */
    public function value(User $user, string $source, Carbon $month): float
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        return match ($source) {
            'deals_closed' => (float) $this->dealsQuery($user, $start, $end)->count(),
            'deals_value' => (float) $this->dealsQuery($user, $start, $end)->sum('value'),
            'free_signups' => (float) \App\Models\FreeSignup::where('created_by', $user->id)
                ->whereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
                ->count(),
            'tickets_resolved' => (float) SupportTicket::where('assigned_to', $user->id)
                ->where('status', 'resolved')
                ->whereBetween('updated_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
                ->count(),
            'attendance_days' => (float) $this->payrollRow($user, $month)['credited_days'],
            'hours_worked' => round($this->payrollRow($user, $month)['worked_minutes'] / 60, 1),
            'punctuality' => $this->punctuality($user, $month),
            default => 0.0,
        };
    }

    /**
     * The won deals that belong to somebody.
     *
     * A deal records who made it two ways: the ids written when a transfer is
     * converted, and the sales performer's name, which is how it worked before
     * those ids existed. Both count — otherwise somebody with no performer
     * linked, or whose name was typed differently on the deal, closes business
     * and shows a month of nothing.
     */
    private function dealsQuery(User $user, Carbon $start, Carbon $end)
    {
        $name = $user->performer?->name;

        return Deal::query()
            ->where('status', 'WON')
            ->whereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->where(function ($q) use ($user, $name) {
                $q->where('agent_id', $user->id)
                  ->orWhere('closer_user_id', $user->id);

                if ($name) {
                    $q->orWhere('closed_by', $name)->orWhere('closer_by', $name);
                }
            });
    }

    /** One month of pay/attendance for this person, worked out once per request. */
    private function payrollRow(User $user, Carbon $month): array
    {
        static $cache = [];
        $key = $user->id . '|' . $month->format('Y-m');

        return $cache[$key] ??= (new PayrollService())->summary(collect([$user]), $month)->first();
    }

    private function punctuality(User $user, Carbon $month): float
    {
        $row = $this->payrollRow($user, $month);
        $worked = $row['present_days'] + $row['half_days'] + $row['short_days'];

        if ($worked <= 0) {
            return 0.0;
        }

        return round(($worked - $row['late_days']) / $worked * 100, 1);
    }

    /**
     * Refresh every system-measured target in the set, so what is shown and what
     * is stored (and later exported) are the same number.
     *
     * @param  Collection<int, KpiTarget>  $targets
     */
    public function refresh(Collection $targets): Collection
    {
        foreach ($targets as $target) {
            if (!self::isAuto($target->source) || !$target->user) {
                continue;
            }

            $value = $this->value($target->user, $target->source, $target->period->copy());

            if (abs($value - (float) $target->achieved_value) > 0.001) {
                $target->achieved_value = $value;
                $target->saveQuietly();
            }
        }

        return $targets;
    }

    /**
     * Targets a new starter gets automatically, so the first month is measured
     * without anyone having to set it up. Sales roles get numbers, support gets
     * tickets, and everyone is measured on turning up.
     *
     * @return array<int, array{metric: string, source: string, unit: string, target_value: float}>
     */
    public static function startingTargets(string $role): array
    {
        $attendance = [
            ['metric' => 'Days attended', 'source' => 'attendance_days', 'unit' => 'number', 'target_value' => 26],
            ['metric' => 'On-time arrivals', 'source' => 'punctuality', 'unit' => 'percent', 'target_value' => 90],
        ];

        return match ($role) {
            'agent', 'closer' => array_merge([
                ['metric' => 'Deals closed', 'source' => 'deals_closed', 'unit' => 'number', 'target_value' => 10],
                ['metric' => 'Revenue closed', 'source' => 'deals_value', 'unit' => 'amount', 'target_value' => 10000],
            ], $attendance),
            'support_agent', 'support_manager' => array_merge([
                ['metric' => 'Tickets resolved', 'source' => 'tickets_resolved', 'unit' => 'number', 'target_value' => 40],
            ], $attendance),
            default => $attendance,
        };
    }
}
