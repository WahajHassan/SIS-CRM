<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Leave: the kinds of it, who asked for it, and who is entitled to how much.
 *
 * An approved leave day is not an absent day on the salary sheet — PayrollService
 * reads these same rows — which is the whole point of recording it here rather
 * than in a spreadsheet.
 */
class LeaveController extends Controller
{
    /** The leave desk: pending first, then everything else, plus the quota board. */
    public function index(Request $request)
    {
        $this->authorizeSection();

        LeaveType::seedDefaults();

        $year = (int) ($request->input('year') ?: now()->year);
        $status = $request->input('status', '');
        $userId = $request->input('user_id', '');

        $query = LeaveRequest::with(['user', 'type', 'decider', 'teamLead'])->whereYear('start_date', $year);

        /*
         * A team lead answers for their own department and nobody else's, so the
         * desk shows them only their own people. HR and admins see everyone.
         */
        $actor = auth()->user();
        $leadOnly = $actor->isTeamLead() && !in_array($actor->role, ['hr', 'admin', 'superadmin'], true);

        if ($leadOnly) {
            $departmentIds = $actor->leadsDepartments()->pluck('id');
            $query->whereIn('user_id', \App\Models\EmployeeProfile::whereIn('department_id', $departmentIds)->pluck('user_id'));
        }

        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($userId !== '') {
            $query->where('user_id', $userId);
        }

        $requests = $query->orderByRaw("CASE WHEN status IN ('pending', 'tl_approved') THEN 0 ELSE 1 END")
            ->orderByDesc('start_date')
            ->get();

        // Anything still waiting on somebody, at either step.
        $pendingCount = LeaveRequest::open()->count();
        $types = LeaveType::orderBy('name')->get();
        $users = User::where('role', '!=', 'superadmin')->orderBy('name')->get();

        // Quota board: one row per employee, one column per leave type.
        $usedByUser = LeaveRequest::approved()
            ->whereYear('start_date', $year)
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->groupBy('leave_type_id')->map(fn ($r) => (float) $r->sum('days')));

        // The month within that year the board reports on, so "how much is left
        // this month" is answerable rather than only "left this year".
        $boardMonth = \App\Services\PayrollService::month($request->input('board_month'));
        if ((int) $boardMonth->format('Y') !== (int) $year) {
            $boardMonth = \Carbon\Carbon::create((int) $year, min(12, max(1, (int) now()->format('n'))), 1);
        }

        $usedThisMonth = LeaveRequest::approved()
            ->whereYear('start_date', $boardMonth->year)
            ->whereMonth('start_date', $boardMonth->month)
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->groupBy('leave_type_id')->map(fn ($r) => (float) $r->sum('days')));

        $quotaBoard = $users->map(function (User $user) use ($types, $usedByUser, $usedThisMonth) {
            $used = $usedByUser[$user->id] ?? collect();
            $month = $usedThisMonth[$user->id] ?? collect();

            return [
                'user' => $user,
                'cells' => $types->map(fn ($type) => [
                    'type' => $type,
                    'used' => (float) ($used[$type->id] ?? 0),
                    'left' => max(0, $type->annual_quota - (float) ($used[$type->id] ?? 0)),
                    'month_used' => (float) ($month[$type->id] ?? 0),
                    'month_left' => $type->monthly_quota > 0
                        ? max(0, $type->monthly_quota - (float) ($month[$type->id] ?? 0))
                        : null,
                ])->all(),
                'total_used' => (float) $used->sum(),
            ];
        });

        $years = range(now()->year + 1, now()->year - 3);

        $flow = \App\Models\HrSetting::current();

        return view('admin.hr.leaves', compact(
            'requests', 'types', 'users', 'quotaBoard', 'year', 'years', 'status', 'userId',
            'pendingCount', 'boardMonth', 'leadOnly', 'flow'
        ));
    }

    /** Record a leave. HR files it for someone; an employee files their own. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'half_day' => 'nullable|boolean',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'reason' => 'nullable|string|max:1000',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp,doc,docx|max:8192',
        ]);

        $forSelf = (int) $data['user_id'] === auth()->id();
        $canManage = auth()->user()->canAccess('hr_leaves');

        abort_unless($forSelf || $canManage, 403, 'Unauthorized action.');

        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        $halfDay = (bool) ($data['half_day'] ?? false);

        $employee = \App\Models\User::findOrFail($data['user_id']);
        $type = LeaveType::findOrFail($data['leave_type_id']);
        $service = new \App\Services\LeaveService();

        // A system type is the software's own record of something it decided;
        // nobody applies for one.
        if ($type->is_system) {
            return back()->with('error', $type->name . ' is recorded by the system and cannot be applied for.');
        }

        // Weekly offs and public holidays inside a leave are not charged to
        // anybody's allowance — nobody spends annual leave on a Sunday.
        $chargeable = $service->chargeableDates($employee, $start, $end);
        $days = (float) count($chargeable);
        $hours = null;

        if ($type->isHourly()) {
            // Short leave is a slice of one day, counted in hours.
            $from = Carbon::parse($data['start_date'] . ' ' . ($data['start_time'] ?? '00:00'));
            $to = Carbon::parse($data['start_date'] . ' ' . ($data['end_time'] ?? '00:00'));
            $hours = round(max(0, $from->diffInMinutes($to, false)) / 60, 1);

            if ($hours <= 0) {
                return back()->with('error', 'Short leave needs a start and end time.');
            }

            if ($type->max_hours && $hours > (float) $type->max_hours) {
                return back()->with('error', sprintf(
                    '%s is up to %s hours at a time; this asks for %s.',
                    $type->name,
                    rtrim(rtrim(number_format($type->max_hours, 1), '0'), '.'),
                    rtrim(rtrim(number_format($hours, 1), '0'), '.')
                ));
            }

            $end = $start->copy();
            $days = 1;                                     // one occasion out of the monthly allowance
        } elseif ($halfDay) {
            $days = 0.5;   // a half day is one day's absence, only half of it
        }

        if ($days <= 0 && !$type->isHourly()) {
            return back()->with('error', 'Those dates are all weekly offs or holidays, so there is no leave to take.');
        }

        // Proof, when this type asks for it or the setting demands it for sick leave.
        $needsProof = $type->requires_attachment
            || ($type->code === 'SL' && $service->rules()->sick_leave_requires_attachment);

        if ($needsProof && !$request->hasFile('attachment')) {
            return back()->with('error', $type->name . ' needs a document attached.');
        }

        $overlap = LeaveRequest::where('user_id', $data['user_id'])
            ->whereIn('status', ['pending', 'approved'])
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->exists();

        if ($overlap) {
            return back()->with('error', 'There is already a leave recorded across those dates.');
        }

        /*
         * Allowances are checked against what is already taken. An employee asking
         * for more than they have is stopped here; HR filing it is only warned,
         * because HR is the one who decides when an exception is made.
         */
        $over = $service->objection($employee, $type, $start, $days);

        if ($over && !$canManage) {
            return back()->with('error', $over);
        }

        $leave = LeaveRequest::create([
            'user_id' => $employee->id,
            'leave_type_id' => $type->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days' => $days,
            'hours' => $hours,
            'start_time' => $type->isHourly() ? ($data['start_time'] ?? null) : null,
            'end_time' => $type->isHourly() ? ($data['end_time'] ?? null) : null,
            'half_day' => $halfDay,
            'reason' => $data['reason'] ?? null,
            'attachment_path' => $request->hasFile('attachment')
                ? $request->file('attachment')->store('hr/leave', 'public')
                : null,
            'status' => LeaveRequest::PENDING,
        ]);

        // HR filing a leave for someone has already made the decision.
        if ($canManage && !$forSelf && $request->boolean('approve_now')) {
            $service->approve($leave->fresh(['user', 'type']), auth()->user());
        } else {
            $service->tellApprovers($leave->fresh(['user', 'type']));
        }

        $leave->refresh();

        $message = 'Leave recorded' . ($leave->status === LeaveRequest::APPROVED
            ? ' and approved.'
            : ' and sent for approval.');

        if (!$type->isHourly() && count($chargeable) && $leave->days != ($end->diffInDays($start) + 1)) {
            $message .= ' Weekly offs and holidays in those dates are not counted.';
        }

        return back()->with($over ? 'error' : 'success', $over ? ($message . ' Note: ' . $over) : $message);
    }

    /**
     * Whether this request goes past the type's monthly or yearly allowance, as a
     * sentence to show, or null when it is within both.
     *
     * Only approved and pending leave counts against an allowance — a rejected or
     * withdrawn request was never taken.
     */
    private function overAllowance(LeaveType $type, int $userId, Carbon $start, float $days): ?string
    {
        $counted = fn ($query) => $query->where('user_id', $userId)
            ->where('leave_type_id', $type->id)
            ->whereIn('status', ['approved', 'pending'])
            ->sum('days');

        if ($type->monthly_quota > 0) {
            $inMonth = (float) $counted(LeaveRequest::query()
                ->whereYear('start_date', $start->year)
                ->whereMonth('start_date', $start->month));

            if ($inMonth + $days > $type->monthly_quota) {
                return sprintf(
                    '%s allows %s a month, and %s already has %s in %s.',
                    $type->name,
                    $this->dayText($type->monthly_quota),
                    \App\Models\User::find($userId)?->name ?? 'this employee',
                    $this->dayText($inMonth),
                    $start->format('F')
                );
            }
        }

        if ($type->annual_quota > 0) {
            $inYear = (float) $counted(LeaveRequest::query()->whereYear('start_date', $start->year));

            if ($inYear + $days > $type->annual_quota) {
                return sprintf(
                    '%s allows %s a year, and %s already has %s in %d.',
                    $type->name,
                    $this->dayText($type->annual_quota),
                    \App\Models\User::find($userId)?->name ?? 'this employee',
                    $this->dayText($inYear),
                    $start->year
                );
            }
        }

        return null;
    }

    private function dayText(float $days): string
    {
        $text = rtrim(rtrim(number_format($days, 1), '0'), '.');

        return $text . ' day' . ($days == 1 ? '' : 's');
    }

    /**
     * Approve or reject.
     *
     * Two steps when the rules say so: the team lead passes it up, HR decides.
     * A team lead may only do that for their own department, which is why this
     * is not simply the section permission.
     */
    public function decide(Request $request, $id)
    {
        $data = $request->validate([
            'status' => 'required|in:approved,rejected',
            'decision_note' => 'nullable|string|max:500',
        ]);

        $leave = LeaveRequest::with(['user.employeeProfile', 'type'])->findOrFail($id);
        $service = new \App\Services\LeaveService();
        $actor = auth()->user();

        $isHr = $actor->canAccess('hr_leaves') && !$actor->isTeamLead();
        $canDecideAsHr = $actor->canAccess('hr_leaves')
            && (!$service->rules()->requiresTeamLeadApproval()
                || $leave->status === LeaveRequest::TL_APPROVED
                || $isHr);

        $asTeamLead = $service->canTeamLeadDecide($actor, $leave);

        abort_unless($canDecideAsHr || $asTeamLead, 403, 'This leave is not yours to decide.');

        if ($data['status'] === 'rejected') {
            $service->reject($leave, $actor, $data['decision_note'] ?? null);

            return back()->with('success', 'Leave rejected.');
        }

        // A team lead's approval is the first step; HR's is the last.
        if ($asTeamLead && !$canDecideAsHr) {
            $service->teamLeadApprove($leave, $actor, $data['decision_note'] ?? null);

            return back()->with('success', 'Passed to HR for final approval.');
        }

        $service->approve($leave, $actor, $data['decision_note'] ?? null);
        $marked = $leave->fresh()->attendances()->count();

        return back()->with('success', 'Leave approved.'
            . ($marked ? ' ' . $marked . ' ' . str('day')->plural($marked) . ' marked as leave on the attendance.' : ''));
    }

    /** Withdraw a leave: the person who asked, or HR, while it still matters. */
    public function cancel($id)
    {
        $leave = LeaveRequest::findOrFail($id);

        abort_unless(
            $leave->user_id === auth()->id() || auth()->user()->canAccess('hr_leaves'),
            403,
            'Unauthorized action.'
        );

        (new \App\Services\LeaveService())->cancel($leave, auth()->user());

        return back()->with('success', 'Leave cancelled.');
    }

    public function destroy($id)
    {
        $this->authorizeSection();

        LeaveRequest::findOrFail($id)->delete();

        return back()->with('success', 'Leave record deleted.');
    }

    // ---- leave types ---------------------------------------------------------

    public function storeType(Request $request)
    {
        $this->authorizeSection();

        $data = $request->validate([
            'name' => 'required|string|max:80',
            'code' => 'nullable|string|max:10',
            'annual_quota' => 'required|integer|min:0|max:365',
            'monthly_quota' => 'required|integer|min:0|max:31',
            'is_paid' => 'nullable|boolean',
            'requires_attachment' => 'nullable|boolean',
            'colour' => 'nullable|string|max:20',
        ]);

        LeaveType::create($data + [
            'code' => strtoupper((string) ($data['code'] ?? '')) ?: null,
            'is_paid' => $request->boolean('is_paid'),
            'requires_attachment' => $request->boolean('requires_attachment'),
        ]);

        return back()->with('success', 'Leave type added.');
    }

    public function updateType(Request $request, $id)
    {
        $this->authorizeSection();

        $data = $request->validate([
            'name' => 'required|string|max:80',
            'code' => 'nullable|string|max:10',
            'annual_quota' => 'required|integer|min:0|max:365',
            'monthly_quota' => 'required|integer|min:0|max:31',
            'is_paid' => 'nullable|boolean',
            'requires_attachment' => 'nullable|boolean',
            'colour' => 'nullable|string|max:20',
            'is_active' => 'nullable|boolean',
        ]);

        LeaveType::findOrFail($id)->update($data + [
            'code' => strtoupper((string) ($data['code'] ?? '')) ?: null,
            'is_paid' => $request->boolean('is_paid'),
            'requires_attachment' => $request->boolean('requires_attachment'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Leave type updated.');
    }

    public function destroyType($id)
    {
        $this->authorizeSection();

        $type = LeaveType::findOrFail($id);

        if ($type->is_system) {
            return back()->with('error', $type->name . ' is created by the system and cannot be deleted.');
        }

        if ($type->requests()->exists()) {
            // Leave already taken keeps its name, so the type is retired instead.
            $type->update(['is_active' => false]);
            return back()->with('success', 'Leave type is in use, so it was switched off instead of deleted.');
        }

        $type->delete();

        return back()->with('success', 'Leave type deleted.');
    }

    /** An employee's own leave: what they have taken and what is left. */
    public function mine()
    {
        $user = auth()->user();

        LeaveType::seedDefaults();

        $leaves = LeaveRequest::with(['type', 'decider'])
            ->where('user_id', $user->id)
            ->orderByDesc('start_date')
            ->get();

        $quota = HrController::leaveQuota($user, now()->year);
        // System types are the software's own record; nobody applies for one.
        $types = LeaveType::applicable()->orderBy('name')->get();
        $rules = \App\Models\HrSetting::current();

        return view('admin.hr.my_leaves', compact('leaves', 'quota', 'types', 'rules'));
    }
}
