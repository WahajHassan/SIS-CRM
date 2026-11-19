<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\IncentiveService;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * What somebody has earned, in one place.
 *
 * Everything here already existed and was spread across three screens: the
 * salary came out of the attendance on the payroll sheet, the incentive out of
 * the deals on the overview, and what had been docked for turning up late was
 * on a fourth. Nobody could answer "what am I getting this month" without
 * opening all of them and doing the sum themselves.
 *
 * So nothing new is recorded. The wallet reads the same services the payroll
 * sheet reads, which is the point: if it ever disagreed with the payroll sheet,
 * one of them would be lying, and this one has no numbers of its own to lie
 * with.
 */
class WalletController extends Controller
{
    /** How far back the wallet looks. A year is as much as anybody asks for. */
    private const MONTHS_BACK = 11;

    public function index(Request $request, PayrollService $payroll, IncentiveService $incentives)
    {
        $me = auth()->user();

        $month = $this->monthFrom($request);
        $earliest = $month->copy()->subMonths(self::MONTHS_BACK)->startOfMonth();

        $mine = collect([$me]);

        // One read for the whole run of months rather than one per month.
        $months = $payroll->summaryMonths($mine, $earliest, $month);

        $thisMonth = $this->payFor($months, $month, $me);

        // Incentives are an agent's, and are counted off their own sales.
        $bonus = $me->role === 'agent' ? $incentives->summary($me, $month) : null;

        return view('admin.wallet.index', [
            'me' => $me,
            'month' => $month,
            'pay' => $thisMonth,
            'bonus' => $bonus,
            'history' => $this->history($months, $me, $incentives),
            'months' => $this->pickableMonths($month),
        ]);
    }

    // ---- the plumbing -------------------------------------------------------

    private function monthFrom(Request $request): Carbon
    {
        $asked = $request->query('month');

        try {
            $month = $asked ? Carbon::parse($asked . '-01')->startOfMonth() : now()->startOfMonth();
        } catch (\Throwable $e) {
            $month = now()->startOfMonth();
        }

        // Nothing before the CRM existed, and nothing after this month.
        return $month->greaterThan(now()->startOfMonth()) ? now()->startOfMonth() : $month;
    }

    private function payFor(array $months, Carbon $month, User $me): ?array
    {
        $rows = $months[$month->format('Y-m')] ?? collect();

        return $rows->firstWhere('user.id', $me->id) ?: $rows->first();
    }

    /**
     * The months behind this one, newest first.
     *
     * A month with no salary set and nothing earned is a month before somebody
     * started, so it is left out rather than shown as a row of zeroes.
     */
    private function history(array $months, User $me, IncentiveService $incentives): array
    {
        $out = [];

        foreach (array_reverse($months, true) as $key => $rows) {
            $row = $rows->firstWhere('user.id', $me->id) ?: $rows->first();
            if (!$row) {
                continue;
            }

            $when = Carbon::parse($key . '-01');

            $earnedBonus = $me->role === 'agent'
                ? (float) ($incentives->summary($me, $when)['earned'] ?? 0)
                : 0.0;

            $net = (float) ($row['net_salary'] ?? 0);

            if ($net <= 0 && $earnedBonus <= 0) {
                continue;
            }

            $out[] = [
                'key' => $key,
                'label' => $when->format('F Y'),
                'net' => $net,
                'bonus' => $earnedBonus,
                'total' => $net + $earnedBonus,
                'absent_days' => (int) ($row['absent_days'] ?? 0),
                'late_days' => (int) ($row['late_days'] ?? 0),
                'present_days' => (int) ($row['present_days'] ?? 0),
            ];
        }

        return $out;
    }

    private function pickableMonths(Carbon $current): array
    {
        $out = [];
        $month = now()->startOfMonth();

        for ($i = 0; $i <= self::MONTHS_BACK; $i++) {
            $out[] = [
                'key' => $month->format('Y-m'),
                'label' => $month->format('F Y'),
                'current' => $month->format('Y-m') === $current->format('Y-m'),
            ];
            $month = $month->copy()->subMonth();
        }

        return $out;
    }
}
