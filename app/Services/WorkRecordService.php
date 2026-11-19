<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Deal;
use App\Models\EmployeeProfile;
use App\Models\SupportTicket;
use App\Models\TransferredLead;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * What each person actually did: transfers given, transfers taken, deals won,
 * the money behind them, tickets closed, and the days they were in.
 *
 * Everything is counted from the rows the CRM already keeps — transferred
 * leads, deals, tickets, attendance — so this page is a different view of the
 * same truth rather than a second set of numbers to maintain. Read for a date
 * range, per person and per day, in a handful of queries rather than one per
 * employee.
 */
class WorkRecordService
{
    /** The columns this report is made of, in the order they are shown. */
    public const METRICS = [
        'transfers_given' => ['label' => 'Transfers given', 'help' => 'Leads this agent handed to a closer', 'colour' => '#6366f1'],
        'transfers_taken' => ['label' => 'Transfers taken', 'help' => 'Leads handed to this closer', 'colour' => '#0ea5e9'],
        'transfers_converted' => ['label' => 'Converted', 'help' => 'Of the transfers they gave, how many turned into business', 'colour' => '#10b981'],
        'deals_won' => ['label' => 'Deals won', 'help' => 'Deals marked WON with them on it', 'colour' => '#22c55e'],
        'revenue' => ['label' => 'Revenue', 'help' => 'The value of those deals', 'colour' => '#f59e0b', 'money' => true],
        'tickets_resolved' => ['label' => 'Tickets resolved', 'help' => 'Support tickets they closed', 'colour' => '#a855f7'],
        'days_present' => ['label' => 'Days in', 'help' => 'Days they were at work', 'colour' => '#64748b'],
        'hours' => ['label' => 'Hours worked', 'help' => 'Time on the clock, breaks taken off', 'colour' => '#94a3b8', 'hours' => true],
    ];

    /**
     * One row per person for a date range, plus the team's totals and a day by
     * day series for the chart.
     *
     * @return array{rows: Collection, totals: array, days: array, people: Collection}
     */
    public function report(Carbon $from, Carbon $to, array $filters = []): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $staff = $this->staff($filters);
        $ids = $staff->pluck('id');

        $given = $this->transfersGiven($ids, $from, $to);
        $taken = $this->transfersTaken($ids, $from, $to);
        $converted = $this->transfersConverted($ids, $from, $to);
        $deals = $this->deals($staff, $from, $to);
        $tickets = $this->tickets($ids, $from, $to);
        $attendance = $this->attendance($ids, $from, $to);

        $rows = $staff->map(function (User $person) use ($given, $taken, $converted, $deals, $tickets, $attendance) {
            $own = $deals[$person->id] ?? ['count' => 0, 'value' => 0.0];

            return [
                'user' => $person,
                'department' => $person->employeeProfile?->departmentName() ?: '—',
                'role' => $person->role,
                'transfers_given' => (int) ($given['totals'][$person->id] ?? 0),
                'transfers_taken' => (int) ($taken['totals'][$person->id] ?? 0),
                'transfers_converted' => (int) ($converted[$person->id] ?? 0),
                'deals_won' => (int) $own['count'],
                'revenue' => (float) $own['value'],
                'tickets_resolved' => (int) ($tickets[$person->id] ?? 0),
                'days_present' => (int) ($attendance['days'][$person->id] ?? 0),
                'hours' => round(($attendance['minutes'][$person->id] ?? 0) / 60, 1),
            ];
        })->sortByDesc(fn ($row) => $row['transfers_given'] + $row['deals_won'])->values();

        return [
            'rows' => $rows,
            'totals' => $this->totals($rows),
            'days' => $this->byDay($from, $to, $given['by_day'], $taken['by_day'], $deals['__by_day__'] ?? []),
            'people' => $staff,
        ];
    }

    /** One person's own record, day by day, for the detail view. */
    public function forEmployee(User $person, Carbon $from, Carbon $to): array
    {
        $report = $this->report($from, $to, ['user_id' => $person->id]);

        return [
            'row' => $report['rows']->first(),
            'days' => $report['days'],
        ];
    }

    // ---- the pieces ---------------------------------------------------------

    public function staff(array $filters = []): Collection
    {
        $actor = auth()->user();

        $query = User::with(['employeeProfile.departmentRecord', 'performer'])
            ->where('role', '!=', 'superadmin')
            ->orderBy('name');

        $departmentId = $filters['department_id'] ?? null;

        // A team lead answers for their own department and sees only that.
        if ($actor && $actor->isTeamLead() && !in_array($actor->role, ['hr', 'admin', 'superadmin'], true)) {
            $departmentId = $actor->leadsDepartments()->pluck('id')->first() ?: $departmentId;
        }

        if ($departmentId) {
            $query->whereIn('id', EmployeeProfile::where('department_id', $departmentId)->pluck('user_id'));
        }

        if (!empty($filters['user_id'])) {
            $query->where('id', $filters['user_id']);
        }

        return $query->get();
    }

    /** @return array{totals: array, by_day: array} */
    private function transfersGiven($ids, Carbon $from, Carbon $to): array
    {
        $rows = TransferredLead::whereIn('agent_id', $ids)
            ->whereBetween('created_at', [$from, $to])
            ->get(['agent_id', 'created_at']);

        return [
            'totals' => $rows->groupBy('agent_id')->map->count()->all(),
            'by_day' => $rows->groupBy(fn ($r) => $r->created_at->toDateString())->map->count()->all(),
        ];
    }

    /** @return array{totals: array, by_day: array} */
    private function transfersTaken($ids, Carbon $from, Carbon $to): array
    {
        $rows = TransferredLead::whereIn('closer_id', $ids)
            ->whereBetween('created_at', [$from, $to])
            ->get(['closer_id', 'created_at']);

        return [
            'totals' => $rows->groupBy('closer_id')->map->count()->all(),
            'by_day' => $rows->groupBy(fn ($r) => $r->created_at->toDateString())->map->count()->all(),
        ];
    }

    private function transfersConverted($ids, Carbon $from, Carbon $to): array
    {
        return TransferredLead::whereIn('agent_id', $ids)
            ->where('is_converted', true)
            ->whereBetween('created_at', [$from, $to])
            ->get(['agent_id'])
            ->groupBy('agent_id')
            ->map->count()
            ->all();
    }

    /**
     * Deals are linked to a sales performer by name, and a login points at that
     * performer — so somebody with no performer linked has no deals of their own.
     */
    private function deals(Collection $staff, Carbon $from, Carbon $to): array
    {
        $byName = $staff->filter(fn (User $u) => $u->performer)
            ->mapWithKeys(fn (User $u) => [strtolower($u->performer->name) => $u->id]);

        if ($byName->isEmpty()) {
            return ['__by_day__' => []];
        }

        $deals = Deal::where('status', 'WON')
            ->whereBetween('created_at', [$from, $to])
            ->get(['closed_by', 'closer_by', 'value', 'created_at']);

        $out = ['__by_day__' => []];

        foreach ($deals as $deal) {
            $owners = collect([$deal->closed_by, $deal->closer_by])
                ->filter()
                ->map(fn ($name) => $byName[strtolower($name)] ?? null)
                ->filter()
                ->unique();

            foreach ($owners as $userId) {
                $out[$userId] ??= ['count' => 0, 'value' => 0.0];
                $out[$userId]['count']++;
                // A deal shared by an agent and a closer counts once for each of
                // them: both did the work it represents.
                $out[$userId]['value'] += (float) $deal->value;
            }

            if ($owners->isNotEmpty()) {
                $date = $deal->created_at->toDateString();
                $out['__by_day__'][$date] = ($out['__by_day__'][$date] ?? 0) + 1;
            }
        }

        return $out;
    }

    private function tickets($ids, Carbon $from, Carbon $to): array
    {
        return SupportTicket::whereIn('assigned_to', $ids)
            ->where('status', 'resolved')
            ->whereBetween('updated_at', [$from, $to])
            ->get(['assigned_to'])
            ->groupBy('assigned_to')
            ->map->count()
            ->all();
    }

    /** @return array{days: array, minutes: array} */
    private function attendance($ids, Carbon $from, Carbon $to): array
    {
        $rows = Attendance::whereIn('user_id', $ids)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereNotNull('clock_in')
            ->get(['user_id', 'worked_minutes']);

        return [
            'days' => $rows->groupBy('user_id')->map->count()->all(),
            'minutes' => $rows->groupBy('user_id')->map->sum('worked_minutes')->all(),
        ];
    }

    private function totals(Collection $rows): array
    {
        $totals = [];

        foreach (array_keys(self::METRICS) as $metric) {
            $totals[$metric] = $rows->sum($metric);
        }

        return $totals;
    }

    /**
     * The team's work day by day, for the bars.
     *
     * @return array<int, array{date: string, label: string, transfers: int, taken: int, deals: int}>
     */
    private function byDay(Carbon $from, Carbon $to, array $given, array $taken, array $deals): array
    {
        $days = [];

        for ($d = $from->copy()->startOfDay(); $d->lte($to); $d->addDay()) {
            $date = $d->toDateString();

            $days[] = [
                'date' => $date,
                'label' => $d->format('d M'),
                'short' => $d->format('j'),
                'weekday' => $d->format('D'),
                'transfers' => (int) ($given[$date] ?? 0),
                'taken' => (int) ($taken[$date] ?? 0),
                'deals' => (int) ($deals[$date] ?? 0),
            ];
        }

        return $days;
    }

    /** How a figure should read on screen. */
    public static function format(string $metric, $value): string
    {
        $meta = self::METRICS[$metric] ?? [];

        if (!empty($meta['money'])) {
            return '$' . number_format((float) $value);
        }

        if (!empty($meta['hours'])) {
            return rtrim(rtrim(number_format((float) $value, 1), '0'), '.') . 'h';
        }

        return number_format((float) $value);
    }
}
