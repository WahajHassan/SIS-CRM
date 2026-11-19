<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\HrSetting;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Shift;
use App\Models\User;
use App\Services\AttendanceCalculator;
use App\Services\LateOffService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * The HR dashboard: who is in today, who is not, and what is waiting on somebody.
 *
 * Everything here is read off the attendance rows the engine already judged, so
 * the dashboard cannot tell a different story from the employee's own file or
 * the salary sheet. Days nobody has judged yet — a weekend, a holiday, a day
 * still to come — are worked out on the spot rather than left blank.
 */
class HrDashboardController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeSection();

        $rules = HrSetting::current();
        $calculator = new AttendanceCalculator($rules);

        $date = $this->dateFrom($request->input('date'));
        [$from, $to] = $this->period($request, $date);

        $departments = Department::active()->orderBy('name')->get();
        $shifts = Shift::active()->orderBy('start_time')->get();

        $departmentId = $request->input('department_id');
        $shiftId = $request->input('shift_id');
        $employeeId = $request->input('user_id');

        $staff = $this->staff($departmentId, $employeeId);

        // Today, per person: the row if there is one, otherwise what the day
        // means for them anyway.
        $rows = Attendance::with(['user', 'shift', 'leave.type'])
            ->whereIn('user_id', $staff->pluck('id'))
            ->whereDate('date', $date)
            ->get()
            ->keyBy('user_id');

        $today = $staff->map(function (User $employee) use ($rows, $date, $calculator, $shiftId) {
            $row = $rows->get($employee->id);
            $shift = $row?->shift ?: $employee->shiftOn($date);

            return [
                'user' => $employee,
                'row' => $row,
                'shift' => $shift,
                'status' => $row?->day_status ?: $calculator->emptyDayStatus($employee, $date),
                'department' => $employee->employeeProfile?->departmentName() ?: '—',
            ];
        })->filter(fn ($entry) => !$shiftId || (string) ($entry['shift']->id ?? '') === (string) $shiftId)
          ->values();

        $counts = $this->countByStatus($today);
        $byDepartment = $this->splitByDepartment($today, $departments);
        $byShift = $this->splitByShift($today, $shifts);

        // Leave taken in the period, split by kind.
        $leaveCards = $this->leaveCards($staff, $from, $to);

        // What is waiting on a decision, oldest first — this is the part of the
        // page that is holding somebody up.
        $pending = LeaveRequest::with(['user', 'type', 'teamLead'])
            ->open()
            ->whereIn('user_id', $staff->pluck('id'))
            ->orderBy('start_date')
            ->get();

        [$nearlyLate, $absentees] = $this->alerts($staff, $date, $rules);

        return view('admin.hr.dashboard', compact(
            'date', 'from', 'to', 'departments', 'shifts', 'departmentId', 'shiftId', 'employeeId',
            'staff', 'today', 'counts', 'byDepartment', 'byShift', 'leaveCards', 'pending',
            'nearlyLate', 'absentees', 'rules'
        ));
    }

    /** Staff this dashboard is about, narrowed by the filters. */
    private function staff(?string $departmentId, ?string $employeeId)
    {
        $actor = auth()->user();

        $query = User::with(['employeeProfile.departmentRecord', 'shiftAssignments.shift'])
            ->where('role', '!=', 'superadmin')
            ->orderBy('name');

        // A team lead answers for their own department and sees only that.
        if ($actor->isTeamLead() && !in_array($actor->role, ['hr', 'admin', 'superadmin'], true)) {
            $departmentId = $actor->leadsDepartments()->pluck('id')->first() ?: $departmentId;
        }

        if ($departmentId) {
            $query->whereIn('id', \App\Models\EmployeeProfile::where('department_id', $departmentId)->pluck('user_id'));
        }

        if ($employeeId) {
            $query->where('id', $employeeId);
        }

        return $query->get();
    }

    private function dateFrom(?string $value): string
    {
        try {
            return $value ? Carbon::parse($value)->toDateString() : User::getOfficeDate();
        } catch (\Exception $e) {
            return Carbon::today()->toDateString();
        }
    }

    /** The period the leave figures cover; the current month unless asked otherwise. */
    private function period(Request $request, string $date): array
    {
        try {
            $from = $request->input('from') ? Carbon::parse($request->input('from')) : Carbon::parse($date)->startOfMonth();
            $to = $request->input('to') ? Carbon::parse($request->input('to')) : Carbon::parse($date)->endOfMonth();
        } catch (\Exception $e) {
            $from = Carbon::parse($date)->startOfMonth();
            $to = Carbon::parse($date)->endOfMonth();
        }

        return [$from->toDateString(), $to->toDateString()];
    }

    /** @return array<string, int> */
    private function countByStatus($today): array
    {
        $counts = array_fill_keys(array_keys(Attendance::DAY_STATUS_LABELS), 0);

        foreach ($today as $entry) {
            $counts[$entry['status']] = ($counts[$entry['status']] ?? 0) + 1;
        }

        // Late and early off are each their own card, and the combination counts
        // toward both — somebody who came late and left early did both.
        $counts['late_total'] = $counts[Attendance::LATE] + $counts[Attendance::LATE_EARLY_OFF];
        $counts['early_total'] = $counts[Attendance::EARLY_OFF] + $counts[Attendance::LATE_EARLY_OFF];
        $counts['present_total'] = $counts[Attendance::PRESENT] + $counts['late_total']
            + $counts['early_total'] - $counts[Attendance::LATE_EARLY_OFF] + $counts[Attendance::HALF_DAY];

        return $counts;
    }

    private function splitByDepartment($today, $departments): array
    {
        $names = $departments->pluck('name')->all();
        $split = [];

        foreach (array_merge($names, ['—']) as $name) {
            $people = $today->where('department', $name);

            if ($people->isEmpty()) {
                continue;
            }

            $split[$name] = $this->countByStatus($people->values());
            $split[$name]['headcount'] = $people->count();
        }

        return $split;
    }

    private function splitByShift($today, $shifts): array
    {
        $split = [];

        foreach ($shifts as $shift) {
            $people = $today->filter(fn ($entry) => ($entry['shift']->id ?? null) === $shift->id);

            $split[$shift->name] = $this->countByStatus($people->values()) + [
                'headcount' => $people->count(),
                'times' => $shift->timesText(),
            ];
        }

        $unassigned = $today->filter(fn ($entry) => !$entry['shift']);

        if ($unassigned->count()) {
            $split['No shift assigned'] = $this->countByStatus($unassigned->values()) + [
                'headcount' => $unassigned->count(),
                'times' => '—',
            ];
        }

        return $split;
    }

    /** Leave in the period, one card per kind. */
    private function leaveCards($staff, string $from, string $to): array
    {
        $taken = LeaveRequest::with('type')
            ->approved()
            ->whereIn('user_id', $staff->pluck('id'))
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from)
            ->get()
            ->groupBy('leave_type_id');

        return LeaveType::orderBy('name')->get()->map(function (LeaveType $type) use ($taken) {
            $rows = $taken->get($type->id) ?? collect();

            return [
                'type' => $type,
                'requests' => $rows->count(),
                'days' => (float) $rows->sum('days'),
                'people' => $rows->pluck('user_id')->unique()->count(),
            ];
        })->all();
    }

    /**
     * Who is about to cost the company a day, and who is missing the most.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection}
     */
    private function alerts($staff, string $date, HrSetting $rules): array
    {
        $month = Carbon::parse($date)->startOfMonth();
        $service = new LateOffService($rules);
        $perOff = (int) $rules->lates_per_off;

        $nearlyLate = collect();
        $absentees = collect();

        $absenceCounts = Attendance::whereIn('user_id', $staff->pluck('id'))
            ->whereBetween('date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->where('day_status', Attendance::ABSENT)
            ->selectRaw('user_id, COUNT(*) as days')
            ->groupBy('user_id')
            ->pluck('days', 'user_id');

        foreach ($staff as $employee) {
            if ($perOff > 0) {
                $lates = $service->lateCount($employee, $month);

                // One more late and it costs them a day.
                if ($lates > 0 && $lates % $perOff === $perOff - 1) {
                    $nearlyLate->push(['user' => $employee, 'lates' => $lates, 'of' => $perOff]);
                }
            }

            $absent = (int) ($absenceCounts[$employee->id] ?? 0);

            if ($absent > 0) {
                $absentees->push(['user' => $employee, 'days' => $absent]);
            }
        }

        return [
            $nearlyLate->sortByDesc('lates')->values(),
            $absentees->sortByDesc('days')->take(5)->values(),
        ];
    }

    /**
     * An employee's own month: the same calendar HR sees on their file, without
     * the correction form.
     */
    public function myAttendance(Request $request)
    {
        $employee = auth()->user();
        $month = \App\Services\PayrollService::month($request->input('month'));

        $pay = (new \App\Services\PayrollService())->summary(collect([$employee]), $month)->first();

        $attendanceByDate = Attendance::with('breaks')
            ->where('user_id', $employee->id)
            ->whereBetween('date', [
                $month->copy()->startOfMonth()->toDateString(),
                $month->copy()->endOfMonth()->toDateString(),
            ])
            ->get()
            ->keyBy(fn ($a) => $a->date->toDateString());

        $service = new LateOffService();
        $lates = $service->lateCount($employee, $month);
        $latesPerOff = (int) HrSetting::current()->lates_per_off;
        $deductedOffs = $service->deductedOffs($employee, $month);

        $quota = HrController::leaveQuota($employee, (int) $month->format('Y'), $month->copy());
        $months = collect(range(0, 11))->map(fn ($i) => now()->startOfMonth()->subMonths($i));

        return view('admin.hr.my_attendance', compact(
            'employee', 'month', 'months', 'pay', 'attendanceByDate',
            'lates', 'latesPerOff', 'deductedOffs', 'quota'
        ));
    }
}
