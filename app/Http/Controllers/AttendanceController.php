<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\User;
use App\Models\BreakRequest;
use App\Models\PayrollSetting;
use App\Services\PayrollService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * Filter dates arrive straight from the query string. Anything unparseable is
     * replaced with today, because the value is handed to Carbon further down and
     * in the report views, where a bad string would break the whole page.
     */
    private function filterDate($value)
    {
        if (!$value) {
            return Carbon::today()->toDateString();
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Exception $e) {
            return Carbon::today()->toDateString();
        }
    }

    /**
     * Answer a clock action: JSON for the dashboard's own script, a redirect for
     * anything that posted the form the old way.
     *
     * The JSON carries the state of the card so the page never has to guess what
     * the button should say next.
     */
    private function clockResponse(Request $request, string $message, bool $ok = true)
    {
        if (!$request->expectsJson()) {
            return redirect()->back()->with($ok ? 'success' : 'error', $message);
        }

        $user = auth()->user();
        $attendance = $user->todayAttendance();
        $shift = $user->shiftOn(now());
        $lateService = new \App\Services\LateOffService();

        return response()->json([
            'success' => $ok,
            'message' => $message,
            'state' => [
                'status' => $attendance?->status ?? 'none',
                'clock_in' => $attendance?->clock_in?->format('h:i A'),
                'clock_out' => $attendance?->clock_out?->format('h:i A'),
                'breaks' => $attendance?->breaks()->count() ?? 0,
                'worked' => $attendance ? \App\Services\PayrollService::hoursText((int) $attendance->totalWorkedMinutes()) : null,
                'day_status' => $attendance?->dayStatusLabel(),
                'shift' => $shift?->name,
                'lates' => $lateService->lateCount($user, now()),
            ],
        ], $ok ? 200 : 422);
    }

    /**
     * Everyone who is paid a salary.
     *
     * This used to be the four sales and support roles, which quietly left the
     * rest of the staff — HR, editors, QA, floor managers — off the salary sheet
     * even though they clock in like everybody else. The owner account is a
     * system login rather than an employee, so it is the only one left out.
     */
    private function payableStaff()
    {
        return User::where('role', '!=', 'superadmin')->orderBy('name')->get();
    }

    /**
     * Admin view of employee attendances with filters.
     */
    public function index(Request $request)
    {
        $this->authorizeSection();

        Attendance::autoCheckoutExpired();

        $selected_user_id = $request->input('user_id');
        $start_date = $this->filterDate($request->input('start_date'));
        $end_date = $this->filterDate($request->input('end_date'));
        
        $query = Attendance::with(['user', 'breaks'])
            ->whereBetween('date', [$start_date, $end_date]);

        if ($selected_user_id) {
            $query->where('user_id', $selected_user_id);
        }

        // The four tiles above the table count the whole range, so they are
        // asked for separately — a page of twenty-five would otherwise report
        // "25 records" however long the month was. One grouped query rather
        // than four, because this runs on every load.
        $tally = (clone $query)
            ->toBase()
            ->selectRaw('status, COUNT(*) as how_many')
            ->groupBy('status')
            ->pluck('how_many', 'status');

        $counts = [
            'all' => (int) $tally->sum(),
            'clocked_in' => (int) ($tally['clocked_in'] ?? 0),
            'on_break' => (int) ($tally['on_break'] ?? 0),
            'clocked_out' => (int) ($tally['clocked_out'] ?? 0),
        ];

        // Attendance is the one table that grows every day for every person and
        // never stops — forty staff over a year is ten thousand rows, and the
        // page used to load every one of them before drawing anything.
        $attendances = $query->orderBy('date', 'desc')
            ->paginate(50)
            ->withQueryString();

        $users = $this->payableStaff();

        // Salary sits on the same page as the attendance it is worked out from, so
        // an admin can check a figure against the day it came from without leaving.
        [$payroll, $payroll_month, $payroll_rules, $payroll_totals] = $this->payroll($request, $users);

        return view('admin.attendance', compact(
            'attendances', 'counts', 'start_date', 'end_date', 'selected_user_id', 'users',
            'payroll', 'payroll_month', 'payroll_rules', 'payroll_totals'
        ));
    }

    /**
     * The month's pay for everyone the filter covers.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: \Carbon\Carbon, 2: \App\Models\PayrollSetting, 3: array}
     */
    private function payroll(Request $request, $users): array
    {
        $month = PayrollService::month($request->input('payroll_month'));
        $service = new PayrollService();

        $selected = $request->input('user_id');
        $subjects = $selected ? $users->where('id', (int) $selected)->values() : $users;

        $payroll = $service->summary($subjects, $month);

        $totals = [
            'salary' => $payroll->sum('monthly_salary'),
            'earned' => $payroll->sum('earned'),
            'late_deduction' => $payroll->sum('late_deduction'),
            'absent_deduction' => $payroll->sum('absent_deduction'),
            'net' => $payroll->sum('net_salary'),
            'unset_salaries' => $payroll->where('monthly_salary', 0.0)->count(),
        ];

        return [$payroll, $month, $service->rules(), $totals];
    }

    /**
     * Save the company's salary rules. Posted from the attendance page itself.
     */
    public function updatePayrollSettings(Request $request)
    {
        $this->authorizeSection();
        abort_if(!auth()->user()->managesStaff() && !auth()->user()->isFloorManager(), 403, 'Unauthorized action.');

        $data = $request->validate([
            'shift_start' => 'required|date_format:H:i',
            'grace_minutes' => 'required|integer|min:0|max:240',
            'required_minutes' => 'required|integer|min:60|max:960',
            'half_day_minutes' => 'required|integer|min:15|max:960',
            'lates_per_deduction' => 'required|integer|min:0|max:31',
            'weekly_offs' => 'nullable|array',
            'weekly_offs.*' => 'integer|min:0|max:6',
        ]);

        if ($data['half_day_minutes'] > $data['required_minutes']) {
            return back()->withErrors(['half_day_minutes' => 'Half day hours cannot be more than a full day.'])
                         ->withInput();
        }

        $data['weekly_offs'] = implode(',', $request->input('weekly_offs', []));

        $settings = PayrollSetting::query()->first();
        if ($settings) {
            $settings->update($data);
        } else {
            PayrollSetting::create($data + ['company_id' => auth()->user()->company_id]);
        }

        return back()->with('success', 'Salary rules saved.');
    }

    /**
     * Save one employee's monthly salary, straight from the salary table.
     */
    public function updateSalary(Request $request, $userId)
    {
        $this->authorizeSection();
        abort_if(!auth()->user()->managesStaff() && !auth()->user()->isFloorManager(), 403, 'Unauthorized action.');

        $data = $request->validate([
            'monthly_salary' => 'required|numeric|min:0|max:999999999',
        ]);

        $user = User::findOrFail($userId);
        $user->update(['monthly_salary' => $data['monthly_salary']]);

        return back()->with('success', $user->display_name . "'s salary updated.");
    }

    /**
     * The salary table as a CSV, for whoever actually pays the wages.
     */
    public function exportPayroll(Request $request)
    {
        $this->authorizeSection();

        $users = $this->payableStaff();
        [$payroll, $month] = $this->payroll($request, $users);

        $filename = 'salary_' . $month->format('Y_m') . '.csv';
        $columns = ['Employee', 'Role', 'Monthly Salary', 'Working Days', 'Present', 'Half Days',
                    'Short Days', 'Absent', 'Paid Leave', 'Unpaid Leave', 'Weekly Offs', 'Late Days', 'Late Deduction Days',
                    'Hours Worked', 'Hours Required', 'Earned', 'Absent Deduction', 'Late Deduction', 'Net Salary'];

        $callback = function () use ($payroll, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            foreach ($payroll as $row) {
                fputcsv($file, [
                    $row['user']->display_name,
                    strtoupper($row['user']->role),
                    number_format($row['monthly_salary'], 2, '.', ''),
                    $row['working_days'],
                    $row['present_days'],
                    $row['half_days'],
                    $row['short_days'],
                    $row['absent_days'],
                    $row['leave_days'] ?? 0,
                    $row['unpaid_leave_days'] ?? 0,
                    $row['off_days'],
                    $row['late_days'],
                    $row['late_deducted_days'],
                    round($row['worked_minutes'] / 60, 2),
                    round($row['required_minutes'] / 60, 2),
                    number_format($row['earned'], 2, '.', ''),
                    number_format($row['absent_deduction'], 2, '.', ''),
                    number_format($row['late_deduction'], 2, '.', ''),
                    number_format($row['net_salary'], 2, '.', ''),
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$filename",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);
    }

    /**
     * Export attendance data as CSV.
     */
    public function exportCsv(Request $request)
    {
        $this->authorizeSection();

        Attendance::autoCheckoutExpired();

        $selected_user_id = $request->input('user_id');
        $start_date = $this->filterDate($request->input('start_date'));
        $end_date = $this->filterDate($request->input('end_date'));

        $query = Attendance::with(['user', 'breaks'])
            ->whereBetween('date', [$start_date, $end_date]);

        if ($selected_user_id) {
            $query->where('user_id', $selected_user_id);
        }

        $attendances = $query->orderBy('date', 'asc')->get();

        $filename = "attendance_report_" . now()->format('Y-m-d') . ".csv";

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = ['Employee Name', 'Role', 'Date', 'Clock In', 'Clock Out', 'Break Count', 'Total Break Time', 'Net Work Hours', 'Status'];

        $callback = function() use($attendances, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($attendances as $att) {
                $breakText = $att->totalBreakDurationText();
                
                fputcsv($file, [
                    $att->user->name,
                    strtoupper($att->user->role),
                    $att->date->format('Y-m-d'),
                    $att->clock_in ? $att->clock_in->format('h:i A') : '--',
                    $att->clock_out ? $att->clock_out->format('h:i A') : '--',
                    $att->breaks->count(),
                    $breakText,
                    $att->totalWorkedHoursText(),
                    strtoupper($att->status)
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Printable view of attendance data.
     */
    public function printReport(Request $request)
    {
        $this->authorizeSection();

        Attendance::autoCheckoutExpired();

        $selected_user_id = $request->input('user_id');
        $start_date = $this->filterDate($request->input('start_date'));
        $end_date = $this->filterDate($request->input('end_date'));

        $query = Attendance::with(['user', 'breaks'])
            ->whereBetween('date', [$start_date, $end_date]);

        if ($selected_user_id) {
            $query->where('user_id', $selected_user_id);
        }

        $attendances = $query->orderBy('date', 'asc')->get();
        $target_user = $selected_user_id ? User::find($selected_user_id) : null;

        return view('admin.attendance_print', compact('attendances', 'start_date', 'end_date', 'target_user'));
    }

    /**
     * Clock In for the current day.
     */
    /**
     * Is there a fingerprint check standing between this person and the clock?
     *
     * Returns the reason when there is, and null when the way is clear. Three
     * things have to hold: HR asks for it, this person has set a device up, and
     * they passed a check within the last couple of minutes.
     *
     * Somebody with nothing set up is let through rather than locked out — the
     * alternative is a floor that cannot clock in on the morning the setting is
     * switched on.
     */
    private function biometricStandsInTheWay(Request $request, $user): ?string
    {
        if (!\App\Models\HrSetting::current()->require_biometric_clock) {
            return null;
        }

        if (!\App\Models\DeviceKey::registeredFor($user->id)) {
            return null;
        }

        $passedAt = (int) $request->session()->get('device_check_passed_at', 0);

        // Long enough to cover the moment between the check and the press,
        // short enough that it cannot be done once in the morning and leaned on
        // all day.
        if ($passedAt && (now()->timestamp - $passedAt) <= 120) {
            $request->session()->forget('device_check_passed_at');
            return null;
        }

        return 'Use your fingerprint or face to clock in.';
    }

    public function clockIn(Request $request)
    {
        $user = auth()->user();

        // Everybody on the staff clocks in — HR and team leads work shifts too.
        // Only the owner account, which is a system login, does not.
        abort_if($user->isSuperAdmin(), 403, 'The owner account does not clock in.');

        // When HR asks for it, the clock wants a fingerprint or a face first.
        // The check itself happened a moment ago in the browser and was verified
        // on the way in; what is read here is that it passed, and recently.
        if ($why = $this->biometricStandsInTheWay($request, $user)) {
            return $request->expectsJson()
                ? response()->json(['message' => $why, 'needs_biometric' => true], 422)
                : back()->with('error', $why);
        }

        $attendance = $user->todayAttendance();

        if (!$attendance) {
            // Which shift this moment belongs to, and which date that shift counts
            // as: a 7 PM start running to 4 AM is one day, dated to the evening it
            // began.
            $resolved = (new \App\Services\ShiftResolver())->resolve($user, Carbon::now());

            $attendance = Attendance::create([
                'user_id' => $user->id,
                'date' => $resolved['date'],
                'shift_id' => $resolved['shift']?->id,
                'clock_in' => Carbon::now(),
                'status' => 'clocked_in',
                'source' => 'web',
            ]);

            (new \App\Services\AttendanceCalculator())->recalculate($attendance);

            // Auto-start global break if currently active
            if (\Illuminate\Support\Facades\Cache::get("global_break_state_half_1_{$user->company_id}") === 'started') {
                $attendance->update(['status' => 'on_break']);
                \App\Models\AttendanceBreak::create([
                    'attendance_id' => $attendance->id,
                    'type' => 'half_1',
                    'break_start' => Carbon::now(),
                ]);
            } elseif (\Illuminate\Support\Facades\Cache::get("global_break_state_half_2_{$user->company_id}") === 'started') {
                $attendance->update(['status' => 'on_break']);
                \App\Models\AttendanceBreak::create([
                    'attendance_id' => $attendance->id,
                    'type' => 'half_2',
                    'break_start' => Carbon::now(),
                ]);
            }

            return $this->clockResponse($request, 'Clocked in successfully!');
        }

        return $this->clockResponse($request, 'You are already clocked in for today.', false);
    }

    /**
     * Start break session.
     */
    public function startBreak(Request $request)
    {
        $user = auth()->user();
        abort_if(!$user->isAgentOrCloser() && !$user->isSupport(), 403, 'Only agents/closers and support staff can start breaks.');

        $attendance = $user->todayAttendance();
        if (!$attendance || $attendance->status !== 'clocked_in') {
            return redirect()->back()->with('error', 'Cannot start break. Ensure you are clocked in and not already on break.');
        }

        $nextType = $attendance->getNextBreakType();
        if (!$nextType) {
            return redirect()->back()->with('error', 'All allowed breaks for today have been completed.');
        }

        $requestedType = $request->input('type');
        
        // Validate requested type against the sequence
        $isValid = false;
        if ($nextType === 'short_1_or_half_1') {
            $isValid = in_array($requestedType, ['short_1', 'half_1']);
        } elseif ($nextType === 'short_2_or_half_2') {
            $isValid = in_array($requestedType, ['short_2', 'half_2']);
        } else {
            $isValid = ($requestedType === $nextType);
        }

        if (!$isValid) {
            return redirect()->back()->with('error', 'Invalid break sequence requested.');
        }

        // Special check for all short breaks - requires approved appeal request
        if (in_array($requestedType, ['short_1', 'short_2', 'short_3'])) {
            $approvedRequest = BreakRequest::where('user_id', $user->id)
                ->where('type', $requestedType)
                ->where('status', 'approved')
                ->whereBetween('created_at', \App\Models\User::getOfficeDayRange())
                ->exists();

            if (!$approvedRequest) {
                return redirect()->back()->with('error', 'This Short Break requires approval from Closer or Floor Manager.');
            }
        }

        // Create the break
        $attendance->update(['status' => 'on_break']);
        AttendanceBreak::create([
            'attendance_id' => $attendance->id,
            'type' => $requestedType,
            'break_start' => Carbon::now(),
        ]);

        return redirect()->back()->with('success', 'Break started successfully!');
    }

    /**
     * Returns the HTML for the global breaks control partial.
     * This is used by the frontend to auto-refresh the buttons.
     */
    public function getGlobalBreaksControlHtml()
    {
        $admin = auth()->user();
        abort_if(!$admin || (!$admin->managesStaff() && !$admin->isFloorManager()), 403);
        return view('admin.partials.global_breaks_control')->render();
    }

    /**
     * Start a global main break (Admin/FM only)
     */
    public function startGlobalBreak(Request $request)
    {
        $admin = auth()->user();
        abort_if(!$admin->managesStaff() && !$admin->isFloorManager(), 403, 'Only Admins and Floor Managers can trigger global breaks.');

        $request->validate([
            'type' => 'required|in:half_1,half_2'
        ]);
        
        $type = $request->input('type');
        $this->executeGlobalBreak($type, $admin->company_id);
        
        // Update cycle state to started
        \Illuminate\Support\Facades\Cache::put("global_break_state_{$type}_{$admin->company_id}", 'started', \Carbon\Carbon::now()->endOfDay());
        
        $company = $admin->company;
        $breakName = $type === 'half_1' ? ($company->break_1_name ?? '1st Half Break') : ($company->break_2_name ?? '2nd Half Break');
        
        return redirect()->back()->with('success', "Global $breakName started successfully!");
    }

    /**
     * Executes the global break logic for a given type.
     */
    public function executeGlobalBreak($type, $companyId = null, $overrideTime = null)
    {
        // Find all clocked in agents and closers
        $attendances = Attendance::whereHas('user', function($q) use ($companyId) {
                $q->whereIn('role', ['agent', 'closer']);
                if ($companyId) {
                    $q->where('company_id', $companyId);
                }
            })
            ->whereBetween('clock_in', \App\Models\User::getOfficeDayRange())
            ->whereIn('status', ['clocked_in', 'on_break'])
            ->get();

        foreach ($attendances as $attendance) {
            // End any currently active break first
            if ($attendance->status === 'on_break') {
                $activeBreak = $attendance->breaks()->whereNull('break_end')->first();
                if ($activeBreak) {
                    $activeBreak->update(['break_end' => Carbon::now()]);
                }
            }

            // Start the new main break
            $attendance->update(['status' => 'on_break']);
            AttendanceBreak::create([
                'attendance_id' => $attendance->id,
                'type' => $type,
                'break_start' => $overrideTime ?? Carbon::now(),
            ]);
        }
    }

    /**
     * Checks if it's time for automated global breaks and triggers them if needed.
     */
    public static function checkAndTriggerAutomatedBreaks()
    {
        $now = Carbon::now('Asia/Karachi');
        $today = \App\Models\User::getOfficeDate($now);
        
        $companies = \App\Models\Company::where('is_active', 1)->get();
        
        foreach ($companies as $company) {
            $companyId = $company->id;
            
            foreach (['half_1' => 1, 'half_2' => 2] as $type => $breakNum) {
                if ($breakNum === 2 && $company->number_of_breaks < 2) continue;
                
                $startField = "break_{$breakNum}_start";
                $endField = "break_{$breakNum}_end";
                
                if ($company->$startField && $company->$endField) {
                    $startTime = Carbon::parse($company->$startField, 'Asia/Karachi');
                    $endTime = Carbon::parse($company->$endField, 'Asia/Karachi');
                    
                    $startTime->setDate($now->year, $now->month, $now->day);
                    $endTime->setDate($now->year, $now->month, $now->day);
                    
                    if ($startTime->hour < 12 && $now->hour >= 12) {
                        $startTime->addDay();
                    } elseif ($startTime->hour >= 12 && $now->hour < 12) {
                        $startTime->subDay();
                    }
                    
                    if ($endTime->hour < 12 && $now->hour >= 12) {
                        $endTime->addDay();
                    } elseif ($endTime->hour >= 12 && $now->hour < 12) {
                        $endTime->subDay();
                    }
                    
                    if ($now->between($startTime, $endTime)) {
                        $startCacheKey = "auto_break_{$type}_date_{$companyId}";
                        if (\Illuminate\Support\Facades\Cache::get($startCacheKey) !== $today) {
                            (new self)->executeGlobalBreak($type, $companyId, clone $startTime);
                            \Illuminate\Support\Facades\Cache::put($startCacheKey, $today, \Carbon\Carbon::now()->endOfDay());
                            \Illuminate\Support\Facades\Cache::put("global_break_state_{$type}_{$companyId}", 'started', \Carbon\Carbon::now()->endOfDay());
                        }
                    }

                    if ($now->greaterThanOrEqualTo($endTime)) {
                        $endCacheKey = "auto_break_{$type}_end_date_{$companyId}";
                        if (\Illuminate\Support\Facades\Cache::get($endCacheKey) !== $today) {
                            $startCacheKey = "auto_break_{$type}_date_{$companyId}";
                            if (\Illuminate\Support\Facades\Cache::get($startCacheKey) === $today) {
                                (new self)->executeEndGlobalBreak($type, $companyId, clone $endTime);
                                \Illuminate\Support\Facades\Cache::put($endCacheKey, $today, \Carbon\Carbon::now()->endOfDay());
                                \Illuminate\Support\Facades\Cache::put("global_break_state_{$type}_{$companyId}", 'ended', \Carbon\Carbon::now()->endOfDay());
                            } else {
                                // If it never started, just mark it as ended to prevent future triggers today
                                \Illuminate\Support\Facades\Cache::put($endCacheKey, $today, \Carbon\Carbon::now()->endOfDay());
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Executes the end global break logic.
     */
    public function executeEndGlobalBreak($type, $companyId = null, $overrideTime = null)
    {
        // Find all clocked in agents and closers currently on THIS break type
        $attendances = Attendance::whereHas('user', function($q) use ($companyId) {
                $q->whereIn('role', ['agent', 'closer']);
                if ($companyId) {
                    $q->where('company_id', $companyId);
                }
            })
            ->whereBetween('clock_in', \App\Models\User::getOfficeDayRange())
            ->where('status', 'on_break')
            ->whereHas('breaks', function($q) use ($type) {
                $q->where('type', $type)->whereNull('break_end');
            })
            ->get();

        foreach ($attendances as $attendance) {
            $activeBreak = $attendance->breaks()->where('type', $type)->whereNull('break_end')->first();
            if ($activeBreak) {
                $activeBreak->update(['break_end' => $overrideTime ?? Carbon::now()]);
            }
            $attendance->update(['status' => 'clocked_in']);
        }
    }

    /**
     * Pause a global main break (Admin/FM only)
     * Pausing effectively ends the current break block, allowing it to be resumed later.
     */
    public function pauseGlobalBreak(Request $request)
    {
        $admin = auth()->user();
        abort_if(!$admin->managesStaff() && !$admin->isFloorManager(), 403, 'Unauthorized.');

        $request->validate(['type' => 'required|in:half_1,half_2']);
        $type = $request->input('type');

        // Pausing is the same as ending it early
        (new self)->executeEndGlobalBreak($type, $admin->company_id);
        
        // Update cycle state to paused
        \Illuminate\Support\Facades\Cache::put("global_break_state_{$type}_{$admin->company_id}", 'paused', \Carbon\Carbon::now()->endOfDay());

        $company = $admin->company;
        $breakName = $type === 'half_1' ? ($company->break_1_name ?? '1st Half Break') : ($company->break_2_name ?? '2nd Half Break');
        return redirect()->back()->with('success', "Global $breakName paused successfully. Agents can now resume work.");
    }

    /**
     * Resume a paused global main break.
     */
    public function resumeGlobalBreak(Request $request)
    {
        $admin = auth()->user();
        abort_if(!$admin->managesStaff() && !$admin->isFloorManager(), 403, 'Unauthorized.');

        $request->validate(['type' => 'required|in:half_1,half_2']);
        $type = $request->input('type');

        // Resuming is the same as starting a new block of the same global break
        $this->executeGlobalBreak($type, $admin->company_id);
        
        // Update cycle state to resumed
        \Illuminate\Support\Facades\Cache::put("global_break_state_{$type}_{$admin->company_id}", 'resumed', \Carbon\Carbon::now()->endOfDay());

        $company = $admin->company;
        $breakName = $type === 'half_1' ? ($company->break_1_name ?? '1st Half Break') : ($company->break_2_name ?? '2nd Half Break');
        return redirect()->back()->with('success', "Global $breakName resumed successfully.");
    }

    /**
     * End a global main break (Admin/FM only)
     */
    public function endGlobalBreak(Request $request)
    {
        $admin = auth()->user();
        abort_if(!$admin->managesStaff() && !$admin->isFloorManager(), 403, 'Only Admins and Floor Managers can end global breaks.');

        $request->validate([
            'type' => 'required|in:half_1,half_2'
        ]);
        
        $type = $request->input('type');

        $this->executeEndGlobalBreak($type, $admin->company_id);

        // Register the break as fully ended for today so it disables controls
        \Illuminate\Support\Facades\Cache::put("auto_break_{$type}_end_date_{$admin->company_id}", \App\Models\User::getOfficeDate(), \Carbon\Carbon::now()->endOfDay());
        \Illuminate\Support\Facades\Cache::put("global_break_state_{$type}_{$admin->company_id}", 'ended', \Carbon\Carbon::now()->endOfDay());

        $company = $admin->company;
        $breakName = $type === 'half_1' ? ($company->break_1_name ?? '1st Half Break') : ($company->break_2_name ?? '2nd Half Break');
        return redirect()->back()->with('success', "Global $breakName ended successfully.");
    }

    /**
     * Reset a global main break (Admin/FM only)
     * This allows the break controls to be enabled again if ended accidentally.
     */
    public function resetGlobalBreak(Request $request)
    {
        $admin = auth()->user();
        abort_if(!$admin->managesStaff() && !$admin->isFloorManager(), 403, 'Unauthorized.');

        $request->validate([
            'type' => 'required|in:half_1,half_2'
        ]);
        
        $type = $request->input('type');

        // Forget the cache keys
        \Illuminate\Support\Facades\Cache::forget("auto_break_{$type}_end_date_{$admin->company_id}");
        \Illuminate\Support\Facades\Cache::forget("global_break_state_{$type}_{$admin->company_id}");

        $company = $admin->company;
        $breakName = $type === 'half_1' ? ($company->break_1_name ?? '1st Half Break') : ($company->break_2_name ?? '2nd Half Break');
        return redirect()->back()->with('success', "Global $breakName has been reset. Controls are now active again.");
    }

    /**
     * Force end an active break for a specific user.
     */
    public function forceEndBreak($userId)
    {
        $admin = auth()->user();
        abort_if(!$admin->managesStaff() && !$admin->isFloorManager(), 403, 'Unauthorized.');

        $targetUser = \App\Models\User::findOrFail($userId);
        $attendance = $targetUser->todayAttendance();

        if ($attendance && $attendance->status === 'on_break') {
            $activeBreak = $attendance->breaks()->whereNull('break_end')->first();
            if ($activeBreak) {
                // We no longer delete half_1 or half_2, we just end them so the time is preserved.
                $activeBreak->update(['break_end' => Carbon::now()]);
            }
            $attendance->update(['status' => 'clocked_in']);
            return redirect()->back()->with('success', "Successfully stopped break for {$targetUser->display_name}. They can take it later.");
        }

        return redirect()->back()->with('error', "User is not currently on a break.");
    }

    /**
     * Force start a main break for a specific user.
     */
    public function forceStartMainBreak($userId)
    {
        $admin = auth()->user();
        abort_if(!$admin->managesStaff() && !$admin->isFloorManager(), 403, 'Unauthorized.');

        $targetUser = \App\Models\User::findOrFail($userId);
        $attendance = $targetUser->todayAttendance();

        if ($attendance && $attendance->status === 'clocked_in') {
            $nextBreakType = $attendance->getNextBreakType();
            $typeToStart = null;
            
            if (in_array($nextBreakType, ['half_1', 'half_2'])) {
                $typeToStart = $nextBreakType;
            } else {
                if (\Illuminate\Support\Facades\Cache::get("global_break_state_half_1_{$targetUser->company_id}") === 'started') {
                    $typeToStart = 'half_1';
                } elseif (\Illuminate\Support\Facades\Cache::get("global_break_state_half_2_{$targetUser->company_id}") === 'started') {
                    $typeToStart = 'half_2';
                }
            }

            if ($typeToStart) {
                $attendance->update(['status' => 'on_break']);
                \App\Models\AttendanceBreak::create([
                    'attendance_id' => $attendance->id,
                    'type' => $typeToStart,
                    'break_start' => Carbon::now(),
                ]);
                return redirect()->back()->with('success', "Successfully started $typeToStart break for {$targetUser->display_name}.");
            } else {
                return redirect()->back()->with('error', "User is not currently waiting for a Main Break and no global break is active.");
            }
        }

        return redirect()->back()->with('error', "User is not currently clocked in or already on break.");
    }

    /**
     * End break session. and return to active work.
     */
    public function endBreak()
    {
        $user = auth()->user();
        abort_if(!$user->isAgentOrCloser() && !$user->isSupport(), 403, 'Only agents/closers and support staff can end breaks.');

        $attendance = $user->todayAttendance();
        if ($attendance && $attendance->status === 'on_break') {
            $activeBreak = $attendance->breaks()->whereNull('break_end')->first();
            if ($activeBreak) {
                $activeBreak->update(['break_end' => Carbon::now()]);
            }
            $attendance->update(['status' => 'clocked_in']);
            return redirect()->back()->with('success', 'Returned to work from break.');
        }

        return redirect()->back()->with('error', 'Cannot end break. You are not currently on break.');
    }

    /**
     * Clock Out for the current day.
     */
    public function clockOut(Request $request)
    {
        $user = auth()->user();
        abort_if($user->isSuperAdmin(), 403, 'The owner account does not clock in.');

        $attendance = $user->todayAttendance();
        if ($attendance && $attendance->status !== 'clocked_out') {
            // End active break session if currently on break
            if ($attendance->status === 'on_break') {
                $activeBreak = $attendance->breaks()->whereNull('break_end')->first();
                if ($activeBreak) {
                    $activeBreak->update(['break_end' => Carbon::now()]);
                }
            }
            $attendance->update([
                'clock_out' => Carbon::now(),
                'status' => 'clocked_out',
            ]);

            // The day is judged the moment it closes, and the month's lates are
            // turned into a deducted off if they now add up to one.
            (new \App\Services\AttendanceCalculator())->recalculate($attendance->fresh('breaks'));
            (new \App\Services\LateOffService())->sync($user, $attendance->date);

            return $this->clockResponse($request, 'Clocked out successfully! Have a great evening!');
        }

        return $this->clockResponse($request, 'Cannot clock out. Ensure you are clocked in first.', false);
    }

    /**
     * Agent requests a 3rd temporary break.
     */
    public function appealBreak(\Illuminate\Http\Request $request)
    {
        $user = auth()->user();
        abort_if(!$user->isAgentOrCloser() && !$user->isSupport(), 403, 'Only agents/closers and support staff can request breaks.');

        $attendance = $user->todayAttendance();
        if (!$attendance) {
            return response()->json(['error' => 'You must clock in first.'], 400);
        }

        $nextType = $attendance->getNextBreakType();
        $requestedType = $request->input('type');

        if (!$requestedType || !in_array($requestedType, ['short_1', 'short_2', 'short_3'])) {
            return response()->json(['error' => 'Invalid break type requested for approval.'], 400);
        }

        // Validate requested type against the sequence
        $isValid = false;
        if ($nextType === 'short_1_or_half_1') {
            $isValid = ($requestedType === 'short_1');
        } elseif ($nextType === 'short_2_or_half_2') {
            $isValid = ($requestedType === 'short_2');
        } else {
            $isValid = ($requestedType === $nextType);
        }

        if (!$isValid) {
            return response()->json(['error' => 'Invalid break sequence requested.'], 400);
        }

        // Check if there is already a pending or approved request today for THIS type
        $existing = BreakRequest::where('user_id', $user->id)
            ->whereBetween('created_at', \App\Models\User::getOfficeDayRange())
            ->where('type', $requestedType)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'status' => $existing->status,
                'message' => 'Request already exists: ' . $existing->status
            ]);
        }

        // Create new request
        $breakReq = BreakRequest::create([
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            'type' => $requestedType,
            'status' => 'pending',
        ]);

        $typeLabels = ['short_1' => '1st Short Break', 'short_2' => '2nd Short Break', 'short_3' => '3rd Temporary Break'];
        \App\Support\AdminNotifier::notifyCompanyAdmins($user->company_id, [
            'title' => 'Break Request',
            'message' => $user->display_name . ' requested a ' . ($typeLabels[$requestedType] ?? 'break') . '.',
            'icon' => 'fas fa-mug-hot',
            'type' => 'info',
        ], $user->role === 'agent' ? ['closer'] : [], $user->id);

        return response()->json([
            'success' => true,
            'status' => 'pending',
            'message' => 'Break request submitted successfully.'
        ]);
    }

    /**
     * Check active appeal status for the current user.
     */
    public function appealStatus()
    {
        $user = auth()->user();
        $request = BreakRequest::where('user_id', $user->id)
            ->whereBetween('created_at', \App\Models\User::getOfficeDayRange())
            ->latest()
            ->first();

        return response()->json([
            'status' => $request ? $request->status : 'none'
        ]);
    }

    /**
     * Get pending appeals for Closer or Floor Manager.
     * - Closers see ONLY agent appeals (not other closers' appeals)
     * - Admin/FM see closer appeals ALWAYS, and agent appeals only if no closer is clocked in
     */
    public function getPendingAppeals()
    {
        $user = auth()->user();
        
        $isCloser = $user->isCloser();
        $isFM = $user->managesStaff() || $user->isEditor() || $user->isFloorManager();

        if (!$isCloser && !$isFM) {
            return response()->json(['requests' => []]);
        }

        $query = BreakRequest::with('user.performer')
            ->where('company_id', $user->company_id)
            ->where('status', 'pending')
            ->whereBetween('created_at', \App\Models\User::getOfficeDayRange());

        if ($isCloser) {
            // Closers only see appeals from AGENTS (not from other closers)
            $query->whereHas('user', function($q) {
                $q->where('role', 'agent');
            });
        } elseif ($isFM) {
            // Admin/FM: always see closer appeals
            // Also see agent appeals ONLY if no active closer is clocked in today
            $activeClosers = User::where('company_id', $user->company_id)
                ->where('role', 'closer')
                ->whereHas('attendances', function($q) {
                    $q->whereBetween('clock_in', \App\Models\User::getOfficeDayRange())
                      ->where('status', '!=', 'clocked_out');
                })
                ->exists();

            if ($activeClosers) {
                // Closers are active, so FM only sees CLOSER appeals (agents go to closers)
                $query->whereHas('user', function($q) {
                    $q->where('role', 'closer');
                });
            }
            // If no active closers, FM sees ALL appeals (both agent + closer)
        }

        $requests = $query->get()
            ->map(function($r) {
                $typeLabel = 'Break';
                if ($r->type === 'short_1') $typeLabel = '1st Short Break';
                elseif ($r->type === 'short_2') $typeLabel = '2nd Short Break';
                elseif ($r->type === 'short_3') $typeLabel = '3rd Temporary Break';

                return [
                    'id' => $r->id,
                    'agent_name' => $r->user->display_name,
                    'role' => strtoupper($r->user->role),
                    'type' => $r->type,
                    'type_label' => $typeLabel,
                    'created_at' => $r->created_at->toIso8601String()
                ];
            });

        return response()->json(['requests' => $requests]);
    }

    /**
     * Approve appeal.
     */
    public function approveAppeal($id)
    {
        $user = auth()->user();
        $isCloser = $user->isCloser();
        $isFM = $user->managesStaff() || $user->isEditor() || $user->isFloorManager();
        abort_if(!$isCloser && !$isFM, 403, 'Unauthorized.');

        $request = BreakRequest::where('company_id', $user->company_id)->findOrFail($id);
        $request->update([
            'status' => 'approved',
            'processed_by' => $user->id,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Decline appeal.
     */
    public function declineAppeal($id)
    {
        $user = auth()->user();
        $isCloser = $user->isCloser();
        $isFM = $user->managesStaff() || $user->isEditor() || $user->isFloorManager();
        abort_if(!$isCloser && !$isFM, 403, 'Unauthorized.');

        $request = BreakRequest::where('company_id', $user->company_id)->findOrFail($id);
        $request->update([
            'status' => 'declined',
            'processed_by' => $user->id,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Get active overtime breaks for alarm/notification.
     */
    public function getActiveOvertimes()
    {
        $user = auth()->user();
        if (!$user->managesStaff() && !$user->isFloorManager()) {
            return response()->json(['success' => false, 'overtimes' => []]);
        }

        $overtimeLimit = Carbon::now()->subMinutes(5);

        $overtimes = \App\Models\AttendanceBreak::whereIn('type', ['short_1', 'short_2', 'short_3'])
            ->whereNull('break_end')
            ->where('break_start', '<', $overtimeLimit)
            ->with(['attendance.user'])
            ->get()
            ->map(function ($break) {
                return [
                    'id' => $break->id,
                    'agent_name' => $break->attendance->user->name ?? 'Unknown',
                    'type' => str_replace('_', ' ', strtoupper($break->type)),
                    'overtime_mins' => Carbon::now()->diffInMinutes($break->break_start) - 5
                ];
            });

        return response()->json(['success' => true, 'overtimes' => $overtimes]);
    }
}
