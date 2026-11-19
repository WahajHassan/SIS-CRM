<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\EmployeeDocument;
use App\Models\EmployeeProfile;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\PayrollService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The people side of the CRM: who works here, their papers, and what their
 * month looked like.
 *
 * Employees are the same rows as the logins in User Management — an HR record is
 * extra detail hung off an account, never a second list of staff that has to be
 * kept in step by hand.
 */
class HrController extends Controller
{
    /**
     * The employee this request is about.
     *
     * The owner account is a system login rather than a member of staff, and it
     * is left off every list here — so it must not be reachable by typing its id
     * into the address either. Only the owner may open the owner's file.
     */
    private function employee($userId): User
    {
        $employee = User::findOrFail($userId);

        abort_if($employee->isSuperAdmin() && !auth()->user()->isSuperAdmin(), 404);

        return $employee;
    }

    /** Roles that are staff rather than system accounts. */
    private function staff()
    {
        return User::with('employeeProfile')
            ->where('role', '!=', 'superadmin')
            ->orderBy('name')
            ->get();
    }

    /** The employee directory, with how complete each file is. */
    public function index(Request $request)
    {
        $this->authorizeSection();

        $search = trim((string) $request->input('q'));
        $department = trim((string) $request->input('department'));
        $employmentStatus = trim((string) $request->input('employment_status'));

        $employees = $this->staff()->filter(function (User $user) use ($search, $department, $employmentStatus) {
            $profile = $user->employeeProfile;

            if ($search !== '') {
                $haystack = strtolower(implode(' ', array_filter([
                    $user->name, $user->username, $user->email,
                    $profile?->designation, $profile?->department, $profile?->id_card_no,
                ])));
                if (!str_contains($haystack, strtolower($search))) {
                    return false;
                }
            }

            if ($department !== '' && ($profile?->department ?? '') !== $department) {
                return false;
            }

            if ($employmentStatus !== '' && ($profile?->employment_status ?? 'probation') !== $employmentStatus) {
                return false;
            }

            return true;
        })->values();

        $all = $this->staff();

        $stats = [
            'total' => $all->count(),
            'permanent' => $all->filter(fn ($u) => ($u->employeeProfile?->employment_status ?? 'probation') === 'permanent')->count(),
            'probation' => $all->filter(fn ($u) => ($u->employeeProfile?->employment_status ?? 'probation') !== 'permanent')->count(),
            'incomplete' => $all->filter(fn ($u) => !$u->employeeProfile || $u->employeeProfile->completeness() < 100)->count(),
        ];

        // Whose birthday is coming up in the next month, so HR is not the last to know.
        $birthdays = $all->filter(fn ($u) => $u->employeeProfile?->date_of_birth)
            ->map(fn ($u) => ['user' => $u, 'date' => $u->employeeProfile->nextBirthday()])
            ->filter(fn ($b) => $b['date'] && $b['date']->lte(now()->addDays(30)))
            ->sortBy(fn ($b) => $b['date']->timestamp)
            ->values();

        // Filtering still works off the names people already have, while the
        // list to choose from is the departments table itself.
        $departments = $all->map(fn ($u) => $u->employeeProfile?->departmentName())
            ->filter()->unique()->sort()->values();

        $departmentOptions = Department::active()->orderBy('name')->get();

        return view('admin.hr.employees', compact(
            'employees', 'stats', 'birthdays', 'departments', 'departmentOptions',
            'search', 'department', 'employmentStatus'
        ));
    }

    /**
     * One employee's file: their details, documents, this month's attendance and
     * pay, their leave, and what they are measured on — all on one page, because
     * that is how anyone actually looks at a person.
     */
    public function show(Request $request, $userId)
    {
        $this->authorizeSection();

        $employee = $this->employee($userId)->load(['employeeProfile', 'employeeDocuments', 'jobDescription']);
        $profile = $employee->employeeProfile ?: new EmployeeProfile(['user_id' => $employee->id]);

        $month = PayrollService::month($request->input('month'));
        $service = new PayrollService();
        $pay = $service->summary(collect([$employee]), $month)->first();

        // The month's clock-ins with their breaks, keyed by date, so the calendar
        // can show every break and the minute it ended without another query.
        $attendanceByDate = Attendance::with('breaks')
            ->where('user_id', $employee->id)
            ->whereBetween('date', [
                $month->copy()->startOfMonth()->toDateString(),
                $month->copy()->endOfMonth()->toDateString(),
            ])
            ->get()
            ->keyBy(fn ($a) => $a->date->toDateString());

        $leaves = LeaveRequest::with('type')
            ->where('user_id', $employee->id)
            ->orderByDesc('start_date')
            ->get();

        $quota = $this->leaveQuota($employee, (int) $month->format("Y"), $month->copy());

        $kpis = (new \App\Services\PerformanceService())->refresh(
            $employee->kpiTargets()->with('user')->where('period', $month->toDateString())->orderBy('metric')->get()
        );

        // Every hand-made change to this month, so a corrected day carries its
        // own explanation rather than leaving the next reader guessing.
        $auditByDate = \App\Models\AttendanceAuditLog::with('editor')
            ->where('user_id', $employee->id)
            ->whereBetween('attendance_date', [
                $month->copy()->startOfMonth()->toDateString(),
                $month->copy()->endOfMonth()->toDateString(),
            ])
            ->orderByDesc('created_at')
            ->get()
            ->groupBy(fn ($entry) => $entry->attendance_date?->toDateString());

        // Twelve months of attendance at a glance, so a year's record is one
        // page rather than twelve visits to the filter.
        $history = $this->monthlyHistory($employee, $month);

        $departmentOptions = Department::active()->orderBy('name')->get();

        return view('admin.hr.profile', compact(
            'employee', 'profile', 'pay', 'month', 'leaves', 'quota', 'kpis',
            'attendanceByDate', 'history', 'auditByDate', 'departmentOptions'
        ));
    }

    /**
     * A short row per month: how many days were worked, missed and paid.
     *
     * @return \Illuminate\Support\Collection<int, array>
     */
    private function monthlyHistory(User $employee, \Carbon\Carbon $upTo)
    {
        $months = (new PayrollService())->summaryMonths(
            collect([$employee]),
            $upTo->copy()->subMonths(11),
            $upTo->copy()
        );

        return collect(range(0, 11))
            ->map(fn ($i) => $upTo->copy()->subMonths($i))
            ->map(function ($month) use ($months) {
                $row = $months[$month->format('Y-m')]->first();
                return [
                    'month' => $month,
                    'present' => $row['present_days'],
                    'half' => $row['half_days'],
                    'short' => $row['short_days'],
                    'absent' => $row['absent_days'],
                    'leave' => ($row['leave_days'] ?? 0) + ($row['unpaid_leave_days'] ?? 0),
                    'late' => $row['late_days'],
                    'hours' => $row['worked_minutes'],
                    'net' => $row['net_salary'],
                ];
            });
    }

    /**
     * How much of each leave type this person has used this year, against quota.
     *
     * @return array<int, array{type: \App\Models\LeaveType, used: float, left: float}>
     */
    public static function leaveQuota(User $user, int $year, ?\Carbon\Carbon $month = null): array
    {
        $types = \App\Models\LeaveType::where('is_active', true)->orderBy('name')->get();
        $month = $month ?: \Carbon\Carbon::create($year, (int) now()->format('n'), 1);

        $taken = LeaveRequest::approved()
            ->where('user_id', $user->id)
            ->whereYear('start_date', $year)
            ->get();

        $used = $taken->groupBy('leave_type_id')->map(fn ($rows) => (float) $rows->sum('days'));
        $usedInMonth = $taken
            ->filter(fn ($r) => $r->start_date->month === $month->month)
            ->groupBy('leave_type_id')
            ->map(fn ($rows) => (float) $rows->sum('days'));

        return $types->map(fn ($type) => [
            'type' => $type,
            'used' => $used[$type->id] ?? 0.0,
            'left' => max(0, $type->annual_quota - ($used[$type->id] ?? 0.0)),
            'month' => $month,
            'month_used' => $usedInMonth[$type->id] ?? 0.0,
            'month_left' => $type->monthly_quota > 0
                ? max(0, $type->monthly_quota - ($usedInMonth[$type->id] ?? 0.0))
                : null,
        ])->all();
    }

    /**
     * Hire somebody: their login and their personnel file in one step.
     *
     * HR should not have to go to User Management to add a starter and then come
     * back here to fill their file in — one form creates both, and the starting
     * targets for their role, so the new person is measurable from day one.
     */
    public function storeEmployee(Request $request)
    {
        $this->authorizeSection();

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'username' => 'required|string|max:60|unique:users,username',
            'email' => 'required|email|max:150|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'required|in:agent,closer,support_agent,support_manager,editor,qa,floor_manager,hr,team_lead',
            'designation' => 'nullable|string|max:120',
            'department_id' => 'nullable|exists:departments,id',
            'contact_no' => 'nullable|string|max:40',
            'joining_date' => 'nullable|date',
            'employment_status' => 'required|in:probation,permanent',
            'monthly_salary' => 'nullable|numeric|min:0|max:999999999',
        ]);

        $employee = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
            'role' => $data['role'],
            'monthly_salary' => $data['monthly_salary'] ?? 0,
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        $department = !empty($data['department_id']) ? Department::find($data['department_id']) : null;

        EmployeeProfile::create([
            'user_id' => $employee->id,
            'designation' => $data['designation'] ?? null,
            'department_id' => $department?->id,
            'department' => $department?->name,
            'contact_no' => $data['contact_no'] ?? null,
            'joining_date' => $data['joining_date'] ?? now()->toDateString(),
            'employment_status' => $data['employment_status'],
        ]);

        foreach (\App\Services\PerformanceService::startingTargets($employee->role) as $target) {
            \App\Models\KpiTarget::create($target + [
                'user_id' => $employee->id,
                'period' => now()->startOfMonth()->toDateString(),
                'achieved_value' => 0,
                'updated_by' => auth()->id(),
            ]);
        }

        return redirect()
            ->route('admin.hr.employee', $employee->id)
            ->with('success', $employee->name . ' is on board. Finish their file below.');
    }

    /**
     * Correct one day's attendance.
     *
     * Clocks get missed — a laptop dies, somebody forgets on the way out — and
     * every salary figure is worked out from these rows, so HR has to be able to
     * put a day right rather than argue with it at month end.
     */
    public function saveAttendance(Request $request, $userId)
    {
        $this->authorizeSection();

        $employee = $this->employee($userId);

        $data = $request->validate([
            'date' => 'required|date',
            'clock_in' => 'nullable|date_format:H:i',
            'clock_out' => 'nullable|date_format:H:i',
            'day_status' => 'nullable|in:present,late,early_off,late_early_off,half_day,absent,on_leave,weekend,holiday',
            'remarks' => 'nullable|string|max:500',
            // Attendance decides pay, so a correction has to say why.
            'reason' => 'required|string|max:500',
        ]);

        $date = \Carbon\Carbon::parse($data['date'])->toDateString();
        $attendance = Attendance::with('breaks')->where('user_id', $employee->id)->whereDate('date', $date)->first();
        $before = $this->snapshot($attendance);

        if (!$data['clock_in']) {
            // Clearing the start of the day is how a wrongly recorded day is removed.
            if ($attendance) {
                $attendance->breaks()->delete();
                $attendance->delete();

                \App\Models\AttendanceAuditLog::record(
                    'deleted', $employee, null, $this->changesBetween($before, []), $data['reason'], $date
                );

                (new \App\Services\LateOffService())->sync($employee, \Carbon\Carbon::parse($date));
            }

            return back()->with('success', 'The day was cleared.');
        }

        $in = \Carbon\Carbon::parse($date . ' ' . $data['clock_in']);
        $out = $data['clock_out'] ? \Carbon\Carbon::parse($date . ' ' . $data['clock_out']) : null;

        // Shifts here run overnight, so an end time earlier than the start belongs
        // to the next calendar day rather than being a mistake.
        if ($out && $out->lessThan($in)) {
            $out->addDay();
        }

        $values = [
            'clock_in' => $in,
            'clock_out' => $out,
            'status' => $out ? 'clocked_out' : 'clocked_in',
            'remarks' => $data['remarks'] ?? ($attendance->remarks ?? null),
            'source' => 'manual',
            'edited_by' => auth()->id(),
        ];

        $created = false;

        if ($attendance) {
            $attendance->update($values);
        } else {
            $attendance = Attendance::create($values + ['user_id' => $employee->id, 'date' => $date]);
            $created = true;
        }

        // Same rules as a real clock-in, so a corrected day is judged exactly as
        // it would have been had the clock worked.
        $calculator = new \App\Services\AttendanceCalculator();
        $calculator->recalculate($attendance->fresh('breaks'));
        $attendance->refresh();

        // HR may overrule the verdict itself — a day the rules call absent that
        // was really a site visit, say. Their word is kept as given.
        if (!empty($data['day_status']) && $data['day_status'] !== $attendance->day_status) {
            $attendance->update(['day_status' => $data['day_status']]);
        }

        \App\Models\AttendanceAuditLog::record(
            $created ? 'created' : 'updated',
            $employee,
            $attendance,
            $this->changesBetween($before, $this->snapshot($attendance)),
            $data['reason'],
            $date
        );

        // Removing a late (or adding one) changes what the month's lates add up
        // to, so any deducted off is granted or taken back straight away.
        $sync = (new \App\Services\LateOffService())->sync($employee, \Carbon\Carbon::parse($date));

        $message = 'Attendance for ' . \Carbon\Carbon::parse($date)->format('d M Y') . ' saved.';

        if ($sync['created']) {
            $message .= ' That makes enough lates for a deducted off, which has been recorded.';
        } elseif ($sync['removed']) {
            $message .= ' A late-deducted off is no longer owed and has been taken back.';
        }

        return back()->with('success', $message);
    }

    /** The fields worth logging a change to, as they stand now. */
    private function snapshot(?Attendance $attendance): array
    {
        if (!$attendance) {
            return [];
        }

        return [
            'clock_in' => $attendance->clock_in?->format('Y-m-d H:i'),
            'clock_out' => $attendance->clock_out?->format('Y-m-d H:i'),
            'day_status' => $attendance->day_status,
            'worked_minutes' => $attendance->worked_minutes,
            'late_minutes' => $attendance->late_minutes,
            'early_off_minutes' => $attendance->early_off_minutes,
            'remarks' => $attendance->remarks,
        ];
    }

    /** field => [old, new], for every field that actually moved. */
    private function changesBetween(array $before, array $after): array
    {
        $fields = array_unique(array_merge(array_keys($before), array_keys($after)));
        $changes = [];

        foreach ($fields as $field) {
            $changes[$field] = [$before[$field] ?? null, $after[$field] ?? null];
        }

        return $changes;
    }

    /** Remove a break that was started by mistake or never closed properly. */
    public function deleteBreak($userId, $breakId)
    {
        $this->authorizeSection();

        $employee = $this->employee($userId);
        $break = \App\Models\AttendanceBreak::with('attendance')->findOrFail($breakId);
        abort_unless($break->attendance && (int) $break->attendance->user_id === $employee->id, 404);

        $break->delete();

        return back()->with('success', 'Break removed.');
    }

    /** Create or update the personnel record. One form, one save. */
    public function saveProfile(Request $request, $userId)
    {
        $this->authorizeSection();

        $employee = $this->employee($userId);

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'father_name' => 'nullable|string|max:120',
            'personal_email' => 'nullable|email|max:150',
            'contact_no' => 'nullable|string|max:40',
            'designation' => 'nullable|string|max:120',
            'department_id' => 'nullable|exists:departments,id',
            'date_of_birth' => 'nullable|date',
            'emergency_contact_name' => 'nullable|string|max:120',
            'emergency_contact_no' => 'nullable|string|max:40',
            'family_contact_no' => 'nullable|string|max:40',
            'id_card_no' => 'nullable|string|max:40',
            'address' => 'nullable|string|max:500',
            'employment_status' => 'required|in:probation,permanent',
            'joining_date' => 'nullable|date',
            'probation_end_date' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
            'cnic' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:8192',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'cv' => 'nullable|file|mimes:pdf,doc,docx|max:8192',
        ]);

        // The name lives on the login, not on the HR record, so there is one name.
        $employee->update(['name' => $data['name']]);
        unset($data['name']);

        // The department is a row; the old text column is kept in step so every
        // screen that still prints a plain name carries on working.
        $data['department'] = $data['department_id']
            ? (Department::find($data['department_id'])?->name)
            : null;

        $profile = EmployeeProfile::firstOrNew(['user_id' => $employee->id]);

        foreach (['cnic' => 'cnic_path', 'photo' => 'photo_path', 'cv' => 'cv_path'] as $field => $column) {
            if ($request->hasFile($field)) {
                $this->deleteFile($profile->{$column});
                $profile->{$column} = $request->file($field)->store('hr/' . $field, 'public');
            }
            unset($data[$field]);
        }

        $profile->fill($data);
        $profile->user_id = $employee->id;
        $profile->save();

        return back()->with('success', $employee->name . "'s profile saved.");
    }

    /** Attach a degree, certificate or any other paper. */
    public function uploadDocument(Request $request, $userId)
    {
        $this->authorizeSection();

        $employee = $this->employee($userId);

        $data = $request->validate([
            'title' => 'required|string|max:150',
            'category' => 'required|in:education,other',
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png,webp,doc,docx|max:10240',
        ]);

        $file = $request->file('document');

        EmployeeDocument::create([
            'user_id' => $employee->id,
            'category' => $data['category'],
            'title' => $data['title'],
            'path' => $file->store('hr/documents', 'public'),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => auth()->id(),
        ]);

        return back()->with('success', 'Document added.');
    }

    public function deleteDocument($userId, $documentId)
    {
        $this->authorizeSection();

        $employee = $this->employee($userId);
        $document = EmployeeDocument::where('user_id', $employee->id)->findOrFail($documentId);
        $this->deleteFile($document->path);
        $document->delete();

        return back()->with('success', 'Document removed.');
    }

    /** Remove one of the single files (CNIC, photo, CV) from a profile. */
    public function deleteProfileFile(Request $request, $userId)
    {
        $this->authorizeSection();

        $field = $request->input('field');
        $columns = ['cnic' => 'cnic_path', 'photo' => 'photo_path', 'cv' => 'cv_path'];

        abort_unless(isset($columns[$field]), 404);

        $profile = EmployeeProfile::where('user_id', $this->employee($userId)->id)->firstOrFail();
        $this->deleteFile($profile->{$columns[$field]});
        $profile->update([$columns[$field] => null]);

        return back()->with('success', 'File removed.');
    }

    private function deleteFile(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /** The directory as a CSV, for whoever asks HR for "the employee list". */
    public function exportCsv()
    {
        $this->authorizeSection();

        $employees = $this->staff();
        $columns = ['Name', 'Father Name', 'Username', 'Email', 'Contact', 'Designation',
                    'Department', 'Date of Birth', 'ID Card No', 'Emergency Contact',
                    'Family Contact', 'Status', 'Joining Date', 'Monthly Salary'];

        $callback = function () use ($employees, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            foreach ($employees as $user) {
                $p = $user->employeeProfile;
                fputcsv($file, [
                    $user->name,
                    $p?->father_name,
                    $user->username,
                    $user->email,
                    $p?->contact_no,
                    $p?->designation,
                    $p?->department,
                    $p?->date_of_birth?->format('Y-m-d'),
                    $p?->id_card_no,
                    trim(($p?->emergency_contact_name ?? '') . ' ' . ($p?->emergency_contact_no ?? '')),
                    $p?->family_contact_no,
                    ($p?->employment_status ?? 'probation') === 'permanent' ? 'Permanent' : 'Probation',
                    $p?->joining_date?->format('Y-m-d'),
                    number_format((float) $user->monthly_salary, 2, '.', ''),
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=employees_' . now()->format('Y_m_d') . '.csv',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);
    }
}
