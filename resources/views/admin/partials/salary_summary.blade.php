{{--
    Salary for the month, worked out from the attendance logged on this same page.

    It lives here rather than behind its own menu entry on purpose: the figures are
    only as good as the clock-in rows above them, and an admin questioning a number
    can scroll up to the day it came from instead of holding two pages in mind.
--}}
@php
    $canEditPay = auth()->user()->isAdmin() || auth()->user()->isFloorManager();
    $currency = '₨';
    $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $offDays = $payroll_rules->weeklyOffDays();
    $monthsBack = collect(range(0, 11))->map(fn ($i) => \Carbon\Carbon::now()->startOfMonth()->subMonths($i));
@endphp

<style>
    .pay-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 15px; flex-wrap: wrap; }
    .pay-tools { display: flex; gap: 8px; align-items: flex-end; flex-wrap: wrap; }
    .pay-tools label { display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); margin-bottom: 5px; }
    .pay-tools select, .pay-tools input { padding: 0.5rem 0.6rem; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-family: inherit; font-size: 0.85rem; height: 38px; }
    .pay-tools .btn { height: 38px; display: inline-flex; align-items: center; gap: 6px; font-weight: 600; text-decoration: none; }

    .pay-totals { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin: 1.2rem 0; }
    .pay-total { border: 1px solid var(--border); border-radius: 12px; padding: 0.85rem 1rem; background: var(--bg-body); }
    .pay-total span { display: block; font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px; }
    .pay-total strong { font-size: 1.25rem; font-weight: 700; color: var(--text-main); }

    .pay-table th, .pay-table td { white-space: nowrap; }
    .pay-row { cursor: pointer; transition: background 0.15s ease; }
    .pay-row:hover { background: rgba(99,102,241,0.05); }
    .pay-row:focus-visible { outline: 2px solid var(--primary); outline-offset: -2px; }
    .pay-caret { color: var(--text-muted); transition: transform 0.2s ease; }
    .pay-row.is-open .pay-caret { transform: rotate(180deg); color: var(--primary); }

    .pay-breakdown { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-bottom: 1.3rem; }
    .pay-breakdown > div { border: 1px solid var(--border); border-radius: 10px; padding: 0.6rem 0.75rem; background: var(--bg-card); }
    .pay-breakdown span { display: block; font-size: 0.71rem; color: var(--text-muted); margin-bottom: 3px; }
    .pay-breakdown b { font-size: 1.05rem; color: var(--text-main); }
    .pay-breakdown small { display: block; font-size: 0.68rem; color: var(--text-muted); margin-top: 3px; }
    .pay-num { text-align: right; font-variant-numeric: tabular-nums; }
    .pay-chip { display: inline-block; min-width: 26px; text-align: center; padding: 0.2rem 0.45rem; border-radius: 7px; font-size: 0.78rem; font-weight: 700; }
    .pay-chip.ok { background: rgba(16,185,129,0.12); color: var(--success); }
    .pay-chip.warn { background: rgba(245,158,11,0.15); color: var(--warning); }
    .pay-chip.bad { background: rgba(239,68,68,0.12); color: var(--danger); }
    .pay-chip.mute { background: rgba(100,116,139,0.12); color: var(--secondary); }
    .pay-net { font-weight: 800; color: var(--success); }

    .pay-salary-form { display: inline-flex; gap: 5px; align-items: center; }
    .pay-salary-form input { width: 105px; padding: 0.35rem 0.5rem; border-radius: 7px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-size: 0.82rem; text-align: right; font-family: inherit; }
    .pay-salary-form button { border: none; background: var(--primary); color: #fff; border-radius: 7px; padding: 0.35rem 0.55rem; cursor: pointer; font-size: 0.75rem; }
    .pay-missing { font-size: 0.72rem; color: var(--danger); font-weight: 600; }

    /* Day-by-day strip inside the expanded row */
    .pay-days { display: grid; grid-template-columns: repeat(auto-fill, minmax(118px, 1fr)); gap: 8px; }
    .pay-day { border: 1px solid var(--border); border-left-width: 4px; border-radius: 9px; padding: 0.5rem 0.6rem; background: var(--bg-card); font-size: 0.74rem; }
    .pay-day b { display: block; font-size: 0.8rem; color: var(--text-main); margin-bottom: 3px; }
    .pay-day small { display: block; color: var(--text-muted); line-height: 1.5; }
    .pay-day.full { border-left-color: var(--success); }
    .pay-day.half { border-left-color: var(--warning); }
    .pay-day.short { border-left-color: var(--warning); }
    .pay-day.absent { border-left-color: var(--danger); }
    .pay-day.leave { border-left-color: var(--info); }
    .pay-day.unpaid_leave { border-left-color: var(--danger); }
    .pay-day.off { border-left-color: var(--secondary); opacity: 0.75; }
    .pay-day.future { border-left-color: var(--border); opacity: 0.55; }
    .pay-day .late { color: var(--danger); font-weight: 700; }

    .pay-legend { font-size: 0.75rem; color: var(--text-muted); line-height: 1.7; margin-top: 1rem; }
    .pay-legend b { color: var(--text-main); }

    .rules-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 1rem; margin-bottom: 1.2rem; }
    .rules-grid label { display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 5px; color: var(--text-main); }
    .rules-grid input, .rules-grid select { width: 100%; padding: 0.6rem; border-radius: 9px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-family: inherit; }
    .rules-grid small { display: block; font-size: 0.72rem; color: var(--text-muted); margin-top: 4px; }
    .rules-days { display: flex; flex-wrap: wrap; gap: 10px; }
    .rules-days label { display: inline-flex; align-items: center; gap: 6px; font-weight: 500; font-size: 0.82rem; margin: 0; cursor: pointer; }
    .rules-days input { width: auto; }

    /* Same expanding-panel language as the Users page: an ordinary block in the
       page flow rather than a floating box, so it cannot land in the wrong place. */
    #salarySection .inline-panel-row > td { padding: 0 !important; border: none !important; }
    #salarySection .inline-panel { background: var(--bg-body); border-top: 3px solid var(--primary); border-bottom: 1px solid var(--border); padding: 1.5rem clamp(1rem, 4vw, 2.5rem); }
    #salarySection .inline-panel-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem; }
    #salarySection .inline-panel-title { font-size: 1.05rem; font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 8px; }
    #salarySection .inline-panel-title i { color: var(--primary); }

    @media (max-width: 720px) {
        .pay-head { flex-direction: column; }
        .pay-tools { width: 100%; }
    }
</style>

<div class="card" id="salarySection">
    <div class="pay-head" style="border-bottom: 1px solid var(--border); padding-bottom: 1rem; margin-bottom: 0.5rem;">
        <div>
            <div class="card-title" style="margin-bottom: 5px; font-size: 1.2rem; font-weight: 700;">
                <i class="fas fa-wallet" style="color: var(--primary);"></i> Salary for {{ $payroll_month->format('F Y') }}
            </div>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">
                Days worked, days off and lateness taken from the attendance above, and the pay that adds up to.
            </p>
        </div>

        <form method="GET" action="{{ route('admin.attendance') }}" class="pay-tools">
            {{-- The date filter above stays put while the salary month changes. --}}
            <input type="hidden" name="user_id" value="{{ $selected_user_id }}">
            <input type="hidden" name="start_date" value="{{ $start_date }}">
            <input type="hidden" name="end_date" value="{{ $end_date }}">
            <div>
                <label for="payrollMonth">Month</label>
                <select name="payroll_month" id="payrollMonth" onchange="this.form.submit()">
                    @foreach($monthsBack as $m)
                        <option value="{{ $m->format('Y-m') }}" {{ $payroll_month->format('Y-m') === $m->format('Y-m') ? 'selected' : '' }}>
                            {{ $m->format('F Y') }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="padding: 0 1.1rem; border-radius: 8px; cursor: pointer;">
                <i class="fas fa-rotate"></i> Show
            </button>
            <a href="{{ route('admin.attendance.salary.export', array_merge(request()->all(), ['payroll_month' => $payroll_month->format('Y-m')])) }}"
               class="btn btn-ghost" style="border: 1px solid var(--border); background: var(--bg-body); color: var(--text-main); padding: 0 1.1rem; border-radius: 8px;">
                <i class="fas fa-file-excel" style="color: #10b981;"></i> Export Salary CSV
            </a>
            @if($canEditPay)
            <button type="button" class="btn btn-ghost" onclick="toggleSalaryRules()"
                    style="border: 1px solid var(--border); background: var(--bg-body); color: var(--text-main); padding: 0 1.1rem; border-radius: 8px; cursor: pointer;">
                <i class="fas fa-sliders"></i> Salary Rules
            </button>
            @endif
        </form>
    </div>

    @if($canEditPay)
    <div id="salaryRules" class="inline-panel" style="display: none; border-radius: 12px; margin-bottom: 1.2rem; border-top: 3px solid var(--primary);">
        <div class="inline-panel-head">
            <div class="inline-panel-title"><i class="fas fa-sliders"></i> How salary is counted</div>
            <button type="button" class="btn btn-ghost" onclick="toggleSalaryRules()" style="cursor: pointer;">&times;</button>
        </div>
        <form method="POST" action="{{ route('admin.attendance.salary-rules') }}">
            @csrf
            <div class="rules-grid">
                <div>
                    <label for="shiftStart">Shift starts at</label>
                    <input type="time" id="shiftStart" name="shift_start" value="{{ $payroll_rules->shift_start }}" required>
                    <small>Arriving after this counts as late.</small>
                </div>
                <div>
                    <label for="graceMinutes">Grace period (minutes)</label>
                    <input type="number" id="graceMinutes" name="grace_minutes" value="{{ $payroll_rules->grace_minutes }}" min="0" max="240" required>
                    <small>Lateness is forgiven up to this.</small>
                </div>
                <div>
                    <label for="requiredMinutes">Full day (minutes)</label>
                    <input type="number" id="requiredMinutes" name="required_minutes" value="{{ $payroll_rules->required_minutes }}" min="60" max="960" required>
                    <small>{{ \App\Services\PayrollService::hoursText($payroll_rules->required_minutes) }} of work, breaks not counted.</small>
                </div>
                <div>
                    <label for="halfDayMinutes">Half day from (minutes)</label>
                    <input type="number" id="halfDayMinutes" name="half_day_minutes" value="{{ $payroll_rules->half_day_minutes }}" min="15" max="960" required>
                    <small>Less than this is not paid for that day.</small>
                </div>
                <div>
                    <label for="latesPerDeduction">Lates that cost one day</label>
                    <input type="number" id="latesPerDeduction" name="lates_per_deduction" value="{{ $payroll_rules->lates_per_deduction }}" min="0" max="31" required>
                    <small>0 turns late deductions off.</small>
                </div>
            </div>
            <div style="margin-bottom: 1.2rem;">
                <label style="display: block; font-size: 0.82rem; font-weight: 600; margin-bottom: 8px; color: var(--text-main);">Weekly offs</label>
                <div class="rules-days">
                    @foreach($dayNames as $num => $name)
                        <label>
                            <input type="checkbox" name="weekly_offs[]" value="{{ $num }}" {{ in_array($num, $offDays, true) ? 'checked' : '' }}>
                            {{ $name }}
                        </label>
                    @endforeach
                </div>
                <small style="display: block; font-size: 0.72rem; color: var(--text-muted); margin-top: 6px;">
                    These days are not counted as working days, so nobody is marked absent on them.
                </small>
            </div>
            @error('half_day_minutes')
                <p style="color: var(--danger); font-size: 0.8rem; margin-bottom: 1rem;">{{ $message }}</p>
            @enderror
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary" style="cursor: pointer;">Save Rules</button>
                <button type="button" class="btn btn-ghost" onclick="toggleSalaryRules()" style="cursor: pointer;">Cancel</button>
            </div>
        </form>
    </div>
    @endif

    <div class="pay-totals">
        <div class="pay-total">
            <span>Employees</span>
            <strong>{{ $payroll->count() }}</strong>
        </div>
        <div class="pay-total">
            <span>Total monthly salary</span>
            <strong>{{ $currency }}{{ number_format($payroll_totals['salary']) }}</strong>
        </div>
        <div class="pay-total">
            <span>Not payable (absent + late)</span>
            <strong style="color: var(--danger);">{{ $currency }}{{ number_format($payroll_totals['absent_deduction'] + $payroll_totals['late_deduction']) }}</strong>
        </div>
        <div class="pay-total">
            <span>Payable this month</span>
            <strong style="color: var(--success);">{{ $currency }}{{ number_format($payroll_totals['net']) }}</strong>
        </div>
    </div>

    @if($payroll_totals['unset_salaries'] > 0)
        <p style="font-size: 0.82rem; color: var(--warning); margin: 0 0 1rem; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-triangle-exclamation"></i>
            {{ $payroll_totals['unset_salaries'] }} {{ \Illuminate\Support\Str::plural('employee', $payroll_totals['unset_salaries']) }}
            {{ $payroll_totals['unset_salaries'] === 1 ? 'has' : 'have' }} no salary set yet
            @if($canEditPay) — type it in the Salary column below and press save. @endif
        </p>
    @endif

    <div style="overflow-x: auto;">
        {{--
            Six columns, not fourteen. What anyone looks for on a salary sheet is
            who, how much, and whether the month was clean; everything the figure is
            built from opens under the row when they want to check it — which is
            where the day-by-day strip already lived.
        --}}
        <table class="pay-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Monthly Salary</th>
                    <th class="pay-num">Present</th>
                    <th class="pay-num">Absent</th>
                    <th class="pay-num">Late</th>
                    <th class="pay-num">Net Salary</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($payroll as $row)
                @php $u = $row['user']; @endphp
                <tr class="pay-row" onclick="toggleSalaryDays({{ $u->id }}, this)" tabindex="0"
                    onkeydown="if (event.key === 'Enter') { event.preventDefault(); toggleSalaryDays({{ $u->id }}, this); }"
                    aria-expanded="false" aria-controls="salaryDays_{{ $u->id }}">
                    <td style="font-weight: 600; color: var(--text-main);">
                        {{ $u->display_name }}
                        <div style="font-size: 0.72rem; color: var(--text-muted); font-weight: 500; text-transform: uppercase;">{{ $u->role }}</div>
                    </td>
                    {{-- The salary box is a form inside a clickable row, so its own
                         clicks must not also open the panel underneath. --}}
                    <td onclick="event.stopPropagation()">
                        @if($canEditPay)
                            <form class="pay-salary-form" method="POST" action="{{ route('admin.attendance.salary.update', $u->id) }}">
                                @csrf
                                <input type="number" name="monthly_salary" step="0.01" min="0" value="{{ rtrim(rtrim(number_format($row['monthly_salary'], 2, '.', ''), '0'), '.') ?: '0' }}" aria-label="Monthly salary for {{ $u->display_name }}">
                                <button type="submit" title="Save salary"><i class="fas fa-check"></i></button>
                            </form>
                            @if($row['monthly_salary'] <= 0)<div class="pay-missing">not set</div>@endif
                        @else
                            {{ $currency }}{{ number_format($row['monthly_salary']) }}
                        @endif
                        <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 3px;">
                            {{ $currency }}{{ number_format($row['per_day'], 0) }} / day &middot; {{ $row['working_days'] }} working days
                        </div>
                    </td>
                    <td class="pay-num">
                        <span class="pay-chip ok">{{ $row['present_days'] }}</span>
                        @if($row['half_days'] + $row['short_days'] > 0)
                            <div style="font-size: 0.7rem; color: var(--warning); margin-top: 3px;">+{{ $row['half_days'] + $row['short_days'] }} part day{{ $row['half_days'] + $row['short_days'] > 1 ? 's' : '' }}</div>
                        @endif
                    </td>
                    <td class="pay-num">
                        <span class="pay-chip {{ $row['absent_days'] ? 'bad' : 'mute' }}">{{ $row['absent_days'] }}</span>
                        @if(($row['leave_days'] ?? 0) + ($row['unpaid_leave_days'] ?? 0) > 0)
                            <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 3px;">{{ ($row['leave_days'] ?? 0) + ($row['unpaid_leave_days'] ?? 0) }} on leave</div>
                        @endif
                    </td>
                    <td class="pay-num">
                        <span class="pay-chip {{ $row['late_days'] ? 'bad' : 'mute' }}">{{ $row['late_days'] }}</span>
                        @if($row['late_minutes'] > 0)
                            <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 3px;">{{ \App\Services\PayrollService::hoursText($row['late_minutes']) }}</div>
                        @endif
                    </td>
                    <td class="pay-num pay-net">
                        {{ $currency }}{{ number_format($row['net_salary']) }}
                        @if($row['absent_deduction'] + $row['late_deduction'] > 0)
                            <div style="font-size: 0.7rem; color: var(--danger); font-weight: 600;">&minus;{{ $currency }}{{ number_format($row['absent_deduction'] + $row['late_deduction']) }} held back</div>
                        @endif
                    </td>
                    <td class="pay-num" onclick="event.stopPropagation();">
                        {{-- stopPropagation: the row itself opens the breakdown,
                             and a link inside it must not do both. --}}
                        <a href="{{ route('admin.payslip', ['user' => $u->id, 'month' => $payroll_month->format('Y-m')]) }}"
                           target="_blank" rel="noopener" class="btn btn-ghost btn-sm"
                           title="Payslip for {{ $u->display_name }}">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </a>
                        <i class="fas fa-chevron-down pay-caret" id="payCaret_{{ $u->id }}" aria-hidden="true"></i>
                    </td>
                </tr>
                <tr id="salaryDays_{{ $u->id }}" class="inline-panel-row" style="display: none;">
                    <td colspan="7">
                        <div class="inline-panel">
                            <div class="inline-panel-head">
                                <div class="inline-panel-title"><i class="fas fa-calendar-days"></i> {{ $u->display_name }} &mdash; {{ $payroll_month->format('F Y') }}</div>
                                <button type="button" class="btn btn-ghost" style="cursor: pointer;" onclick="toggleSalaryDays({{ $u->id }})">&times;</button>
                            </div>

                            {{-- The columns that used to crowd the table. --}}
                            <div class="pay-breakdown">
                                <div><span>Half days</span><b>{{ $row['half_days'] }}</b></div>
                                <div><span>Short days</span><b>{{ $row['short_days'] }}</b></div>
                                <div><span>Paid leave</span><b>{{ $row['leave_days'] ?? 0 }}</b></div>
                                <div><span>Unpaid leave</span><b>{{ $row['unpaid_leave_days'] ?? 0 }}</b></div>
                                <div><span>Weekly offs</span><b>{{ $row['off_days'] }}</b></div>
                                <div>
                                    <span>Hours done</span>
                                    <b style="color: {{ $row['worked_minutes'] >= $row['required_minutes'] ? 'var(--success)' : 'var(--text-main)' }};">
                                        {{ \App\Services\PayrollService::hoursText($row['worked_minutes']) }}
                                    </b>
                                    @php $shortfall = $row['required_minutes'] - $row['worked_minutes']; @endphp
                                    <small>of {{ \App\Services\PayrollService::hoursText($row['required_minutes']) }} required
                                        @if($shortfall > 0) &middot; {{ \App\Services\PayrollService::hoursText($shortfall) }} short
                                        @elseif($shortfall < 0) &middot; {{ \App\Services\PayrollService::hoursText(abs($shortfall)) }} extra @endif
                                    </small>
                                </div>
                                <div>
                                    <span>Earned</span><b>{{ $currency }}{{ number_format($row['earned']) }}</b>
                                    <small>{{ rtrim(rtrim(number_format($row['credited_days'], 1), '0'), '.') }} days paid</small>
                                </div>
                                <div>
                                    <span>Days not paid</span>
                                    <b style="color: {{ $row['absent_deduction'] > 0 ? 'var(--danger)' : 'inherit' }};">
                                        {{ $row['absent_deduction'] > 0 ? '−' . $currency . number_format($row['absent_deduction']) : '—' }}
                                    </b>
                                    <small>already off the earned figure</small>
                                </div>
                                <div>
                                    <span>Late penalty</span>
                                    <b style="color: {{ $row['late_deduction'] > 0 ? 'var(--danger)' : 'inherit' }};">
                                        {{ $row['late_deduction'] > 0 ? '−' . $currency . number_format($row['late_deduction']) : '—' }}
                                    </b>
                                    <small>{{ $row['late_deducted_days'] }} day{{ $row['late_deducted_days'] == 1 ? '' : 's' }} for {{ $row['late_days'] }} lates</small>
                                </div>
                                <div>
                                    <span>Net salary</span>
                                    <b style="color: var(--success);">{{ $currency }}{{ number_format($row['net_salary']) }}</b>
                                </div>
                            </div>

                            <div class="pay-days">
                                @foreach($row['days'] as $day)
                                    @php $d = \Carbon\Carbon::parse($day['date']); @endphp
                                    <div class="pay-day {{ $day['status'] }}">
                                        <b>{{ $d->format('d M') }} <span style="font-weight: 500; color: var(--text-muted);">{{ $d->format('D') }}</span></b>
                                        <small>
                                            @switch($day['status'])
                                                @case('full') Full day @break
                                                @case('half') Half day @break
                                                @case('short') Short day @break
                                                @case('absent') <span style="color: var(--danger); font-weight: 700;">Absent</span> @break
                                                @case('leave') <span style="color: var(--info); font-weight: 700;">{{ $day['leave_name'] ?? 'Leave' }}</span> @break
                                                @case('unpaid_leave') <span style="color: var(--danger); font-weight: 700;">{{ $day['leave_name'] ?? 'Leave' }} (unpaid)</span> @break
                                                @case('off') Weekly off @break
                                                @default Upcoming
                                            @endswitch
                                        </small>
                                        @if($day['clock_in'])
                                            <small>In {{ $day['clock_in'] }} &middot; Out {{ $day['clock_out'] ?: '—' }}</small>
                                            <small>{{ \App\Services\PayrollService::hoursText($day['worked_minutes']) }} worked</small>
                                        @endif
                                        @if($day['late_minutes'] > 0)
                                            <small class="late">{{ \App\Services\PayrollService::hoursText($day['late_minutes']) }} late</small>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-muted); ">
                        <i class="fas fa-wallet" style="font-size: 2rem; color: var(--border); display: block; margin-bottom: 1rem;"></i>
                        No employees to pay for this month.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <p class="pay-legend">
        <b>How this is counted.</b>
        A working day is any day that is not a weekly off ({{ count($offDays) ? implode(', ', array_map(fn ($n) => $dayNames[$n], $offDays)) : 'none set' }}).
        A day pays in full at {{ \App\Services\PayrollService::hoursText($payroll_rules->required_minutes) }} of work,
        half from {{ \App\Services\PayrollService::hoursText($payroll_rules->half_day_minutes) }},
        and nothing below that — breaks are already taken off.
        Arriving after {{ \Carbon\Carbon::parse($payroll_rules->shift_start)->format('h:i A') }}
        plus {{ $payroll_rules->grace_minutes }} minutes is late,
        @if($payroll_rules->lates_per_deduction > 0)
            and every {{ $payroll_rules->lates_per_deduction }} lates cost one day's pay.
        @else
            and lateness is shown but not deducted.
        @endif
        Approved leave of a paid type is counted as a working day and is not a deduction;
        unpaid leave is treated like an absence.
        Days still to come in this month are not counted for or against anyone.
    </p>
</div>

@push('scripts')
<script>
    function toggleSalaryRules() {
        var el = document.getElementById('salaryRules');
        if (!el) return;
        el.style.display = el.style.display === 'none' || el.style.display === '' ? 'block' : 'none';
    }

    // Clicking the row opens the detail under it; clicking it again closes it.
    // The caret on the right is the only thing that has to be kept in step.
    function toggleSalaryDays(id, row) {
        var panel = document.getElementById('salaryDays_' + id);
        if (!panel) return;

        var open = !(panel.style.display === 'none' || panel.style.display === '');
        panel.style.display = open ? 'none' : 'table-row';

        var trigger = row && row.classList && row.classList.contains('pay-row')
            ? row
            : document.querySelector('.pay-row[aria-controls="salaryDays_' + id + '"]');

        if (trigger) {
            trigger.setAttribute('aria-expanded', open ? 'false' : 'true');
            trigger.classList.toggle('is-open', !open);
        }
    }
</script>
@endpush
