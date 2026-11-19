<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\EmployeeProfile;
use App\Models\Holiday;
use App\Models\HrSetting;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * The month's attendance, shaped for a sheet.
 *
 * Two reports come out of here: everybody's month on one page, a day per column,
 * and one person's month with a row per day. Both are built from the attendance
 * rows the engine already judged — a report never re-decides whether somebody
 * was late — and both are trimmed to the columns the person downloading asked
 * for, so the file holds what they ticked and nothing else.
 */
class AttendanceReportService
{
    private HrSetting $rules;
    private AttendanceCalculator $calculator;

    /** The month's shape, and each person's shift, worked out once per report. */
    private array $dayCache = [];
    private array $shiftCache = [];
    private array $monthLeaves = [];
    private array $monthLateOffs = [];

    /** Everything the download popup can offer, in the order it offers it. */
    public const SECTIONS = [
        'check_times' => 'Check-in / check-out time',
        'working_hours' => 'Working hours',
        'late' => 'Late count & late minutes',
        'early_off' => 'Early Off count & minutes',
        'leaves' => 'Leaves (type-wise breakdown)',
        'absents' => 'Absents',
        'late_offs' => 'Late-deducted offs',
        'weekends' => 'Weekends / Holidays',
        'remarks' => 'Remarks / HR notes',
        'totals' => 'Summary totals row',
    ];

    public function __construct(?HrSetting $rules = null)
    {
        $this->rules = $rules ?: HrSetting::current();
        $this->calculator = new AttendanceCalculator($this->rules);
    }

    /** The codes printed in the day cells, and the colour each one is filled with. */
    public function legend(): array
    {
        $leave = LeaveType::orderBy('name')->get()->map(fn (LeaveType $t) => [
            'code' => $t->code ?: strtoupper(substr($t->name, 0, 2)),
            'label' => $t->name,
            'colour' => $t->colour,
        ])->all();

        return array_merge([
            ['code' => 'P', 'label' => 'Present', 'colour' => '#10b981'],
            ['code' => 'L', 'label' => 'Late', 'colour' => '#f59e0b'],
            ['code' => 'EO', 'label' => 'Early Off', 'colour' => '#f59e0b'],
            ['code' => 'LE', 'label' => 'Late + Early Off', 'colour' => '#f97316'],
            ['code' => 'HD', 'label' => 'Half Day', 'colour' => '#fbbf24'],
            ['code' => 'A', 'label' => 'Absent', 'colour' => '#ef4444'],
            ['code' => 'W', 'label' => 'Weekend', 'colour' => '#cbd5e1'],
            ['code' => 'H', 'label' => 'Holiday', 'colour' => '#94a3b8'],
        ], $leave);
    }

    /**
     * Everybody's month, one row per person and one column per day.
     *
     * @param  array<string, bool>  $sections  which of SECTIONS to include
     */
    public function overview(Carbon $month, array $filters = [], array $sections = []): array
    {
        $staff = $this->staff($filters);
        $days = $this->days($month);
        $cells = $this->cellsFor($staff, $month);

        $rows = $staff->map(function (User $employee) use ($days, $cells, $sections) {
            $own = $cells[$employee->id] ?? [];
            $totals = ['present' => 0, 'late' => 0, 'early_off' => 0, 'half_day' => 0,
                       'absent' => 0, 'leave' => 0, 'off' => 0, 'worked_minutes' => 0,
                       'late_minutes' => 0, 'early_minutes' => 0, 'late_offs' => 0];

            $cellsOut = [];

            foreach ($days as $day) {
                $cell = $own[$day['date']] ?? ['code' => '', 'status' => null, 'colour' => null];

                // Weekends and holidays are only printed when they were asked for;
                // otherwise the cell is left blank so the sheet reads as work days.
                if (in_array($cell['status'], [Attendance::WEEKEND, Attendance::HOLIDAY], true)
                    && !($sections['weekends'] ?? true)) {
                    $cell = ['code' => '', 'status' => null, 'colour' => null];
                }

                $cellsOut[$day['date']] = $cell;

                match ($cell['status']) {
                    Attendance::PRESENT => $totals['present']++,
                    Attendance::LATE => [$totals['present']++, $totals['late']++],
                    Attendance::EARLY_OFF => [$totals['present']++, $totals['early_off']++],
                    Attendance::LATE_EARLY_OFF => [$totals['present']++, $totals['late']++, $totals['early_off']++],
                    Attendance::HALF_DAY => $totals['half_day']++,
                    Attendance::ABSENT => $totals['absent']++,
                    Attendance::ON_LEAVE => $totals['leave']++,
                    Attendance::WEEKEND, Attendance::HOLIDAY => $totals['off']++,
                    default => null,
                };

                $totals['worked_minutes'] += $cell['worked_minutes'] ?? 0;
                $totals['late_minutes'] += $cell['late_minutes'] ?? 0;
                $totals['early_minutes'] += $cell['early_minutes'] ?? 0;
            }

            $totals['late_offs'] = $this->lateOffCount($employee, $cells['__month__']);
            $totals['leave_by_type'] = $this->leaveByType($employee, $cells['__month__']);

            return [
                'user' => $employee,
                'id' => $employee->username ?: $employee->id,
                'department' => $employee->employeeProfile?->departmentName() ?: '—',
                'shift' => $this->shiftNameFor($employee, $cells['__month__']->copy()->endOfMonth()->toDateString()) ?: '—',
                'cells' => $cellsOut,
                'totals' => $totals,
            ];
        });

        return [
            'month' => $month,
            'days' => $days,
            'rows' => $rows,
            'legend' => $this->legend(),
            'sections' => $sections,
            'filters' => $this->filterLabels($filters),
        ];
    }

    /** One person's month, a row per day. */
    public function single(User $employee, Carbon $month, array $sections = []): array
    {
        $days = $this->days($month);
        $cells = $this->cellsFor(collect([$employee]), $month);
        $own = $cells[$employee->id] ?? [];

        $rows = [];
        $summary = ['present' => 0, 'late' => 0, 'early_off' => 0, 'half_day' => 0, 'absent' => 0,
                    'leave' => 0, 'off' => 0, 'worked_minutes' => 0, 'late_minutes' => 0, 'early_minutes' => 0];

        foreach ($days as $day) {
            $cell = $own[$day['date']] ?? ['code' => '', 'status' => null];

            $rows[] = [
                'date' => $day['date'],
                'day' => Carbon::parse($day['date'])->format('D'),
                'shift' => $cell['shift'] ?? '—',
                'in' => $cell['in'] ?? '',
                'out' => $cell['out'] ?? '',
                'worked' => PayrollService::hoursText((int) ($cell['worked_minutes'] ?? 0)),
                'status' => $cell['status'] ? (Attendance::DAY_STATUS_LABELS[$cell['status']] ?? '—') : '—',
                'status_key' => $cell['status'],
                'code' => $cell['code'],
                'late_minutes' => (int) ($cell['late_minutes'] ?? 0),
                'early_minutes' => (int) ($cell['early_minutes'] ?? 0),
                'leave_type' => $cell['leave_type'] ?? '',
                'remarks' => $cell['remarks'] ?? '',
                'colour' => $cell['colour'] ?? null,
            ];

            match ($cell['status']) {
                Attendance::PRESENT => $summary['present']++,
                Attendance::LATE => [$summary['present']++, $summary['late']++],
                Attendance::EARLY_OFF => [$summary['present']++, $summary['early_off']++],
                Attendance::LATE_EARLY_OFF => [$summary['present']++, $summary['late']++, $summary['early_off']++],
                Attendance::HALF_DAY => $summary['half_day']++,
                Attendance::ABSENT => $summary['absent']++,
                Attendance::ON_LEAVE => $summary['leave']++,
                Attendance::WEEKEND, Attendance::HOLIDAY => $summary['off']++,
                default => null,
            };

            $summary['worked_minutes'] += $cell['worked_minutes'] ?? 0;
            $summary['late_minutes'] += $cell['late_minutes'] ?? 0;
            $summary['early_minutes'] += $cell['early_minutes'] ?? 0;
        }

        $summary['late_offs'] = $this->lateOffCount($employee, $month);
        $summary['leave_by_type'] = $this->leaveByType($employee, $month);

        return [
            'employee' => $employee,
            'department' => $employee->employeeProfile?->departmentName() ?: '—',
            'shift' => $employee->shiftOn($month->copy()->endOfMonth()->toDateString())?->name ?: '—',
            'month' => $month,
            'rows' => $rows,
            'summary' => $summary,
            'legend' => $this->legend(),
            'sections' => $sections,
        ];
    }

    // ---- the pieces ---------------------------------------------------------

    /** @return Collection<int, User> */
    public function staff(array $filters = []): Collection
    {
        $query = User::with(['employeeProfile.departmentRecord', 'shiftAssignments.shift'])
            ->where('role', '!=', 'superadmin')
            ->orderBy('name');

        if (!empty($filters['department_id'])) {
            $query->whereIn('id', EmployeeProfile::where('department_id', $filters['department_id'])->pluck('user_id'));
        }

        if (!empty($filters['user_id'])) {
            $query->where('id', $filters['user_id']);
        }

        $staff = $query->get();

        if (!empty($filters['shift_id'])) {
            $staff = $staff->filter(function (User $employee) use ($filters) {
                return (string) ($employee->shiftOn(now())->id ?? '') === (string) $filters['shift_id'];
            })->values();
        }

        return $staff;
    }

    /** @return array<int, array{date: string, day: string, weekend: bool, holiday: bool}> */
    public function days(Carbon $month): array
    {
        // The same month is asked for once per employee, so the answer is kept.
        $key = $month->format('Y-m');

        if (isset($this->dayCache[$key])) {
            return $this->dayCache[$key];
        }

        // One query for the month's holidays rather than one per day.
        $this->calculator->prime([], $month->copy()->startOfMonth()->toDateString(),
            $month->copy()->endOfMonth()->toDateString());

        $days = [];

        for ($d = $month->copy()->startOfMonth(); $d->lte($month->copy()->endOfMonth()); $d->addDay()) {
            $days[] = [
                'date' => $d->toDateString(),
                'number' => $d->day,
                'day' => $d->format('D'),
                'weekend' => $this->rules->isWeeklyOff($d),
                'holiday' => $this->calculator->isHoliday($d->toDateString()),
            ];
        }

        return $this->dayCache[$key] = $days;
    }

    /**
     * Every person's month as cells, read once rather than per day.
     *
     * Days with no attendance row are worked out on the spot — a weekend is a
     * weekend whether or not anybody wrote it down.
     */
    private function cellsFor(Collection $staff, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth()->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();

        $attendances = Attendance::with(['shift', 'leave.type'])
            ->whereIn('user_id', $staff->pluck('id'))
            ->whereBetween('date', [$start, $end])
            ->get()
            ->groupBy('user_id');

        // Holidays, leaves and working-day overrides for everybody at once: a
        // month for fifty people is a few queries, not a few thousand.
        $this->calculator->prime($staff->pluck('id')->all(), $start, $end);
        $this->primeShifts($staff, $start, $end);
        $this->primeMonthLeaves($staff, $month);

        $codes = $this->codeMap();
        $days = $this->days($month);
        $today = now()->toDateString();
        $out = ['__month__' => $month->copy()];

        foreach ($staff as $employee) {
            $rows = ($attendances[$employee->id] ?? collect())->keyBy(fn ($a) => $a->date->toDateString());
            $cells = [];

            foreach ($days as $day) {
                $row = $rows->get($day['date']);
                $status = $row?->day_status ?: $this->calculator->emptyDayStatus($employee, $day['date']);

                // A day still to come is left blank rather than called an absence.
                if (!$row && $day['date'] > $today) {
                    $cells[$day['date']] = ['code' => '', 'status' => null, 'colour' => null];
                    continue;
                }

                $leaveType = $row?->leave?->type;
                $code = $status === Attendance::ON_LEAVE && $leaveType
                    ? ($leaveType->code ?: 'LV')
                    : ($codes[$status]['code'] ?? '');

                $cells[$day['date']] = [
                    'code' => $code,
                    'status' => $status,
                    'colour' => $status === Attendance::ON_LEAVE && $leaveType
                        ? $leaveType->colour
                        : ($codes[$status]['colour'] ?? null),
                    'in' => $row?->clock_in?->format('h:i A'),
                    'out' => $row?->clock_out?->format('h:i A'),
                    'worked_minutes' => (int) ($row?->worked_minutes ?? 0),
                    'late_minutes' => (int) ($row?->late_minutes ?? 0),
                    'early_minutes' => (int) ($row?->early_off_minutes ?? 0),
                    'shift' => $row?->shift?->name ?: ($this->shiftNameFor($employee, $day['date']) ?: '—'),
                    'leave_type' => $leaveType?->name ?? '',
                    'remarks' => $row?->remarks ?? '',
                ];
            }

            $out[$employee->id] = $cells;
        }

        return $out;
    }

    /**
     * Everybody's shift assignments for the month, read in one query. An
     * assignment covers a span of dates, so the one that covers a given day is
     * picked out in PHP rather than asked for again.
     */
    private function primeShifts(Collection $staff, string $from, string $to): void
    {
        $assignments = \App\Models\EmployeeShift::with('shift')
            ->whereIn('user_id', $staff->pluck('id'))
            ->get()
            ->groupBy('user_id');

        $active = \App\Models\Shift::active()->get();
        $only = $active->count() === 1 ? $active->first() : null;

        foreach ($staff as $employee) {
            $this->shiftCache[$employee->id] = [
                'assignments' => ($assignments[$employee->id] ?? collect())
                    ->sortByDesc('effective_from')->values(),
                'fallback' => $only,
            ];
        }
    }

    /** The shift this person was on that day, as a name. */
    private function shiftNameFor(User $employee, string $date): ?string
    {
        $cached = $this->shiftCache[$employee->id] ?? null;

        if (!$cached) {
            return $employee->shiftOn($date)?->name;
        }

        foreach ($cached['assignments'] as $assignment) {
            $start = $assignment->effective_from ? Carbon::parse($assignment->effective_from)->toDateString() : null;
            $end = $assignment->effective_to ? Carbon::parse($assignment->effective_to)->toDateString() : null;

            if (($start === null || $start <= $date) && ($end === null || $end >= $date)) {
                return $assignment->shift?->name;
            }
        }

        return $cached['fallback']?->name;
    }

    private function codeMap(): array
    {
        $map = [];

        foreach ($this->legend() as $entry) {
            // Leave types have codes of their own and no day status to map to.
            $status = $this->statusForCode($entry['code']);

            if ($status !== null) {
                $map[$status] = $entry;
            }
        }

        return $map;
    }

    private function statusForCode(string $code): ?string
    {
        return [
            'P' => Attendance::PRESENT,
            'L' => Attendance::LATE,
            'EO' => Attendance::EARLY_OFF,
            'LE' => Attendance::LATE_EARLY_OFF,
            'HD' => Attendance::HALF_DAY,
            'A' => Attendance::ABSENT,
            'W' => Attendance::WEEKEND,
            'H' => Attendance::HOLIDAY,
        ][$code] ?? null;
    }

    private function lateOffCount(User $employee, Carbon $month): int
    {
        $key = $month->format('Y-m');

        if (isset($this->monthLateOffs[$key])) {
            return count($this->monthLateOffs[$key][$employee->id] ?? []);
        }

        return (new LateOffService($this->rules))->deductedOffs($employee, $month)->count();
    }

    /**
     * The month's approved leaves for everybody on the sheet, in two queries
     * instead of two per person: what they took, and which of those were offs
     * deducted for being late.
     */
    private function primeMonthLeaves(Collection $staff, Carbon $month): void
    {
        $key = $month->format('Y-m');
        $start = $month->copy()->startOfMonth()->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();
        $ids = $staff->pluck('id');

        $this->monthLeaves[$key] = LeaveRequest::with('type')
            ->approved()
            ->whereIn('user_id', $ids)
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->get()
            ->groupBy('user_id')
            ->all();

        $deductedType = LeaveType::where('code', LeaveType::LATE_DEDUCTED_OFF)->first()
            ?: LeaveType::where('is_system', true)->first();

        $this->monthLateOffs[$key] = $deductedType
            ? LeaveRequest::whereIn('user_id', $ids)
                ->where('leave_type_id', $deductedType->id)
                ->whereBetween('start_date', [$start, $end])
                ->get()
                ->groupBy('user_id')
                ->all()
            : [];
    }

    /** @return array<string, float> leave type name => days */
    private function leaveByType(User $employee, Carbon $month): array
    {
        $key = $month->format('Y-m');

        $leaves = isset($this->monthLeaves[$key])
            ? collect($this->monthLeaves[$key][$employee->id] ?? [])
            : LeaveRequest::with('type')
                ->approved()
                ->where('user_id', $employee->id)
                ->whereDate('start_date', '<=', $month->copy()->endOfMonth()->toDateString())
                ->whereDate('end_date', '>=', $month->copy()->startOfMonth()->toDateString())
                ->get();

        return $leaves
            ->groupBy(fn ($l) => $l->type?->name ?? 'Leave')
            ->map(fn ($rows) => (float) $rows->sum('days'))
            ->all();
    }

    /** What the filters say, for the sheet's header and the file name. */
    public function filterLabels(array $filters): array
    {
        $department = !empty($filters['department_id'])
            ? Department::find($filters['department_id'])?->name
            : null;

        $shift = !empty($filters['shift_id'])
            ? \App\Models\Shift::find($filters['shift_id'])?->name
            : null;

        return [
            'department' => $department ?: 'All departments',
            'department_slug' => $department ? str_replace(' ', '-', $department) : 'All',
            'shift' => $shift ?: 'All shifts',
        ];
    }

    /** Which sections were asked for, with everything on by default. */
    public static function normaliseSections(?array $chosen): array
    {
        if ($chosen === null) {
            return array_fill_keys(array_keys(self::SECTIONS), true);
        }

        $sections = [];

        foreach (array_keys(self::SECTIONS) as $key) {
            $sections[$key] = in_array($key, $chosen, true);
        }

        return $sections;
    }
}
