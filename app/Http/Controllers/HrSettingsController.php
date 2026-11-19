<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\EmployeeShift;
use App\Models\Holiday;
use App\Models\HrSetting;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkingDayOverride;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Everything the HR panel judges a day by, in one place.
 *
 * No rule in the attendance or leave code is written into the code itself; it is
 * read from here. That is the whole point of this screen — the office changes
 * its mind about a grace period without anybody editing a file.
 */
class HrSettingsController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeSection();

        $rules = HrSetting::current();
        $shifts = Shift::orderBy('start_time')->get();
        $departments = Department::with('lead')->orderBy('name')->get();

        $holidays = Holiday::orderByDesc('start_date')->get();
        $year = (int) ($request->input('holiday_year') ?: now()->year);

        $staff = User::with(['employeeProfile.departmentRecord', 'shiftAssignments.shift'])
            ->where('role', '!=', 'superadmin')
            ->orderBy('name')
            ->get();

        // Who is on what today, so the assignment table reads as a roster.
        $today = Carbon::today();
        $roster = $staff->map(fn (User $user) => [
            'user' => $user,
            'shift' => $user->shiftOn($today),
            'since' => $user->shiftAssignments->sortByDesc('effective_from')->first()?->effective_from,
        ]);

        $overrides = WorkingDayOverride::with('user')
            ->whereDate('date', '>=', $today->copy()->subMonth()->toDateString())
            ->orderBy('date')
            ->get();

        // Bonuses and achievements: what an agent is working towards, and every
        // change anybody has made to it.
        \App\Services\IncentiveService::seedDefaults();
        $incentives = \App\Models\IncentiveRule::ordered()->get();
        $incentiveLogs = \App\Models\IncentiveRuleLog::with('editor')
            ->orderByDesc('created_at')
            ->limit(40)
            ->get();

        $tab = $request->input('tab', 'rules');

        return view('admin.hr.settings', compact(
            'rules', 'shifts', 'departments', 'holidays', 'year', 'roster', 'overrides', 'staff', 'tab',
            'incentives', 'incentiveLogs'
        ));
    }

    // ---- the rules ----------------------------------------------------------

    public function updateRules(Request $request)
    {
        $this->authorizeSection();

        $data = $request->validate([
            'weekly_offs' => 'nullable|array',
            'weekly_offs.*' => 'integer|min:0|max:6',
            'early_checkin_minutes' => 'required|integer|min:0|max:720',
            'early_off_minutes' => 'required|integer|min:0|max:240',
            'half_day_minutes' => 'required|integer|min:15|max:960',
            'lates_per_off' => 'required|integer|min:0|max:31',
            'free_signups_per_day' => 'required|integer|min:0|max:100',
            'late_deduction_order' => 'required|in:casual_then_unpaid,unpaid',
            'leave_approval_flow' => 'required|in:tl_then_hr,hr_only',
        ]);

        // Unticked switches are simply absent from the post, so each one is read
        // explicitly rather than taken from the validated set.
        $values = array_merge($data, [
            'weekly_offs' => implode(',', $request->input('weekly_offs', [])),
            'half_day_enabled' => $request->boolean('half_day_enabled'),
            'early_off_counts_toward_lates' => $request->boolean('early_off_counts_toward_lates'),
            'auto_absent_enabled' => $request->boolean('auto_absent_enabled'),
            'sick_leave_requires_attachment' => $request->boolean('sick_leave_requires_attachment'),
            'require_biometric_clock' => $request->boolean('require_biometric_clock'),
        ]);

        $rules = HrSetting::query()->first();

        if ($rules) {
            $rules->update($values);
        } else {
            HrSetting::create($values + ['company_id' => auth()->user()->company_id]);
        }

        return back()->with('success', 'Attendance rules saved.');
    }

    // ---- shifts -------------------------------------------------------------

    private function shiftRules(): array
    {
        return [
            'name' => 'required|string|max:60',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'grace_minutes' => 'required|integer|min:0|max:240',
            'break_minutes' => 'required|integer|min:0|max:240',
        ];
    }

    public function storeShift(Request $request)
    {
        $this->authorizeSection();

        $data = $request->validate($this->shiftRules());

        // crosses_midnight is worked out by the model from the times themselves.
        Shift::create($data + ['is_active' => true]);

        return back()->with('success', 'Shift added.');
    }

    public function updateShift(Request $request, $id)
    {
        $this->authorizeSection();

        $data = $request->validate($this->shiftRules());

        Shift::findOrFail($id)->update($data + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Shift updated.');
    }

    public function destroyShift($id)
    {
        $this->authorizeSection();

        $shift = Shift::findOrFail($id);

        // A shift somebody has worked keeps its rows: past attendance is judged
        // against it, so it is retired rather than deleted.
        if ($shift->assignments()->exists()) {
            $shift->update(['is_active' => false]);

            return back()->with('success', 'That shift has been worked, so it was switched off instead of deleted.');
        }

        $shift->delete();

        return back()->with('success', 'Shift deleted.');
    }

    /** Put somebody on a shift from a date, keeping the history straight. */
    public function assignShift(Request $request)
    {
        $this->authorizeSection();

        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'shift_id' => 'required|exists:shifts,id',
            'effective_from' => 'required|date',
        ]);

        $employee = User::findOrFail($data['user_id']);
        $shift = Shift::findOrFail($data['shift_id']);

        EmployeeShift::assign($employee, $shift, $data['effective_from']);

        return back()->with('success', sprintf(
            '%s is on %s from %s.',
            $employee->name,
            $shift->name,
            Carbon::parse($data['effective_from'])->format('d M Y')
        ));
    }

    // ---- departments --------------------------------------------------------

    public function storeDepartment(Request $request)
    {
        $this->authorizeSection();

        $data = $request->validate([
            'name' => 'required|string|max:60',
            'code' => 'nullable|string|max:20',
            'lead_user_id' => 'nullable|exists:users,id',
        ]);

        Department::create($data + ['is_active' => true]);

        return back()->with('success', 'Department added.');
    }

    public function updateDepartment(Request $request, $id)
    {
        $this->authorizeSection();

        $data = $request->validate([
            'name' => 'required|string|max:60',
            'code' => 'nullable|string|max:20',
            'lead_user_id' => 'nullable|exists:users,id',
        ]);

        Department::findOrFail($id)->update($data + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Department updated.');
    }

    public function destroyDepartment($id)
    {
        $this->authorizeSection();

        $department = Department::findOrFail($id);

        if ($department->profiles()->exists()) {
            $department->update(['is_active' => false]);

            return back()->with('success', 'People still belong to that department, so it was switched off instead of deleted.');
        }

        $department->delete();

        return back()->with('success', 'Department deleted.');
    }

    // ---- holidays -----------------------------------------------------------

    public function storeHoliday(Request $request)
    {
        $this->authorizeSection();

        $data = $request->validate([
            'name' => 'required|string|max:80',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'notes' => 'nullable|string|max:300',
        ]);

        // A single day is a range whose ends are the same date.
        $data['end_date'] = $data['end_date'] ?: $data['start_date'];

        Holiday::create($data + ['is_active' => true]);

        return back()->with('success', 'Holiday added.');
    }

    public function updateHoliday(Request $request, $id)
    {
        $this->authorizeSection();

        $data = $request->validate([
            'name' => 'required|string|max:80',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $data['end_date'] = $data['end_date'] ?: $data['start_date'];

        Holiday::findOrFail($id)->update($data + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Holiday updated.');
    }

    public function destroyHoliday($id)
    {
        $this->authorizeSection();

        Holiday::findOrFail($id)->delete();

        return back()->with('success', 'Holiday removed.');
    }

    // ---- bonuses and achievements ------------------------------------------

    private function incentiveRules(): array
    {
        return [
            'kind' => 'required|in:target,bounty,consistency,allowance',
            'label' => 'required|string|max:80',
            'note' => 'nullable|string|max:120',
            'threshold' => 'nullable|numeric|min:0|max:99999999',
            'reward_amount' => 'nullable|numeric|min:0|max:99999999',
            'reward_currency' => 'required|in:PKR,USD',
            'salary_increment' => 'nullable|numeric|min:0|max:99999999',
            'months' => 'nullable|integer|min:1|max:24',
            'roles' => 'nullable|string|max:120',
            'position' => 'nullable|integer|min:0|max:999',
        ];
    }

    public function storeIncentive(Request $request)
    {
        $this->authorizeSection();

        $data = $request->validate($this->incentiveRules());

        // Only one monthly target makes sense; a second would leave every other
        // block measuring against a different number.
        if ($data['kind'] === \App\Models\IncentiveRule::TARGET
            && \App\Models\IncentiveRule::where('kind', \App\Models\IncentiveRule::TARGET)->exists()) {
            return back()->with('error', 'There is already a monthly target — edit that one instead.');
        }

        \App\Models\IncentiveRule::create($data + [
            'roles' => $data['roles'] ?: 'agent',
            'is_active' => true,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Added “' . $data['label'] . '”.');
    }

    public function updateIncentive(Request $request, $id)
    {
        $this->authorizeSection();

        $data = $request->validate($this->incentiveRules());

        \App\Models\IncentiveRule::findOrFail($id)->update($data + [
            'roles' => $data['roles'] ?: 'agent',
            'is_active' => $request->boolean('is_active'),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Saved. The change is in the record below.');
    }

    public function destroyIncentive($id)
    {
        $this->authorizeSection();

        $rule = \App\Models\IncentiveRule::findOrFail($id);
        $label = $rule->label;
        $rule->delete();

        return back()->with('success', '“' . $label . '” removed. The record of it stays below.');
    }

    // ---- importing a device's export ---------------------------------------

    /**
     * Take a CSV of clock-ins and turn it into attendance.
     *
     * Columns: employee, date, check in, check out — where "employee" is a
     * username, an email or an exact name, and the times may be full timestamps
     * or just a time of day. Every row is judged by the same rules as a web
     * check-in and written with its source set to `import`, so an imported day is
     * never mistaken for one the employee clocked themselves.
     *
     * The whole file is read before anything is written: a file with a bad row is
     * reported rather than half-applied.
     */
    public function importAttendance(Request $request)
    {
        $this->authorizeSection();

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
            'reason' => 'required|string|max:200',
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');

        if (!$handle) {
            return back()->with('error', 'That file could not be read.');
        }

        $header = fgetcsv($handle);

        if (!$header) {
            fclose($handle);
            return back()->with('error', 'That file is empty.');
        }

        $columns = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $index = function (array $names) use ($columns) {
            foreach ($names as $name) {
                $at = array_search($name, $columns, true);
                if ($at !== false) {
                    return $at;
                }
            }
            return null;
        };

        $employeeAt = $index(['employee', 'username', 'user', 'name', 'email', 'employee_id']);
        $dateAt = $index(['date', 'attendance_date', 'shift_date']);
        $inAt = $index(['check in', 'check_in', 'clock in', 'clock_in', 'in']);
        $outAt = $index(['check out', 'check_out', 'clock out', 'clock_out', 'out']);

        if ($employeeAt === null || $dateAt === null || $inAt === null) {
            fclose($handle);
            return back()->with('error', 'The file needs at least the columns: employee, date, check in.');
        }

        $staff = User::where('role', '!=', 'superadmin')->get();
        $rows = [];
        $problems = [];
        $line = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $line++;

            if (!array_filter($row, fn ($cell) => trim((string) $cell) !== '')) {
                continue;                                   // a blank line
            }

            $who = trim((string) ($row[$employeeAt] ?? ''));
            $employee = $staff->first(fn (User $u) => strcasecmp($u->username ?? '', $who) === 0
                || strcasecmp($u->email ?? '', $who) === 0
                || strcasecmp($u->name, $who) === 0
                || (string) $u->id === $who);

            if (!$employee) {
                $problems[] = "line {$line}: no employee called \"{$who}\"";
                continue;
            }

            try {
                $date = Carbon::parse(trim((string) $row[$dateAt]))->toDateString();
                $in = $this->momentFrom($date, trim((string) ($row[$inAt] ?? '')));
                $out = $outAt === null ? null : $this->momentFrom($date, trim((string) ($row[$outAt] ?? '')));
            } catch (\Exception $e) {
                $problems[] = "line {$line}: the date or time could not be read";
                continue;
            }

            if (!$in) {
                $problems[] = "line {$line}: no check-in time";
                continue;
            }

            if ($out && $out->lessThan($in)) {
                $out->addDay();                              // an overnight shift
            }

            $rows[] = compact('employee', 'date', 'in', 'out');
        }

        fclose($handle);

        if ($problems) {
            return back()->with('error', 'Nothing was imported. ' . implode('; ', array_slice($problems, 0, 5))
                . (count($problems) > 5 ? ' (and ' . (count($problems) - 5) . ' more)' : ''));
        }

        $calculator = new \App\Services\AttendanceCalculator();
        $lateOffs = new \App\Services\LateOffService();
        $imported = 0;
        $touchedMonths = [];

        foreach ($rows as $row) {
            $attendance = \App\Models\Attendance::where('user_id', $row['employee']->id)
                ->whereDate('date', $row['date'])
                ->first();

            $before = [
                'clock_in' => $attendance?->clock_in?->format('Y-m-d H:i'),
                'clock_out' => $attendance?->clock_out?->format('Y-m-d H:i'),
            ];

            $values = [
                'clock_in' => $row['in'],
                'clock_out' => $row['out'],
                'status' => $row['out'] ? 'clocked_out' : 'clocked_in',
                'source' => 'import',
                'edited_by' => auth()->id(),
            ];

            if ($attendance) {
                $attendance->update($values);
            } else {
                $attendance = \App\Models\Attendance::create($values + [
                    'user_id' => $row['employee']->id,
                    'date' => $row['date'],
                ]);
            }

            $calculator->recalculate($attendance->fresh('breaks'));

            \App\Models\AttendanceAuditLog::record(
                'imported',
                $row['employee'],
                $attendance,
                [
                    'clock_in' => [$before['clock_in'], $row['in']->format('Y-m-d H:i')],
                    'clock_out' => [$before['clock_out'], $row['out']?->format('Y-m-d H:i')],
                ],
                $request->input('reason'),
                $row['date']
            );

            $touchedMonths[$row['employee']->id . '|' . substr($row['date'], 0, 7)] = [$row['employee'], $row['date']];
            $imported++;
        }

        // Imported lates count like any other, so the month is settled up once
        // per employee rather than once per row.
        foreach ($touchedMonths as $touched) {
            $lateOffs->sync($touched[0], Carbon::parse($touched[1]));
        }

        return back()->with('success', "Imported {$imported} " . str('day')->plural($imported) . ' of attendance.');
    }

    /**
     * A cell that may be a full timestamp or just a time of day, read against
     * the row's date.
     */
    private function momentFrom(string $date, string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        // A bare time — "19:05" or "7:05 PM" — belongs to the row's own date.
        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?\s*(am|pm)?$/i', $value)) {
            return Carbon::parse($date . ' ' . $value);
        }

        return Carbon::parse($value);
    }

    // ---- one-off working days ----------------------------------------------

    public function storeOverride(Request $request)
    {
        $this->authorizeSection();

        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'is_working' => 'required|in:0,1',
            'reason' => 'nullable|string|max:200',
        ]);

        WorkingDayOverride::updateOrCreate(
            ['user_id' => $data['user_id'], 'date' => $data['date']],
            [
                'is_working' => (bool) $data['is_working'],
                'reason' => $data['reason'] ?? null,
                'created_by' => auth()->id(),
            ]
        );

        return back()->with('success', 'Exception saved.');
    }

    public function destroyOverride($id)
    {
        $this->authorizeSection();

        WorkingDayOverride::findOrFail($id)->delete();

        return back()->with('success', 'Exception removed.');
    }
}
