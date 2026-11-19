@extends('layouts.admin')

@section('title', $employee->name . ' — Employee File')

@section('content')
{{--
    One person, one page: their details, their papers, the month they have just
    worked, the leave they have taken and what they are measured on. The
    attendance and salary figures are the same ones the Attendance page shows —
    both read PayrollService, so they cannot disagree.
--}}
@php
    $currency = '₨';
    $months = collect(range(0, 11))->map(fn ($i) => \Carbon\Carbon::now()->startOfMonth()->subMonths($i));
    $files = [
        'cnic' => ['label' => 'CNIC', 'icon' => 'fa-id-card', 'path' => $profile->cnic_path],
        'photo' => ['label' => 'Profile photo', 'icon' => 'fa-camera', 'path' => $profile->photo_path],
        'cv' => ['label' => 'CV', 'icon' => 'fa-file-lines', 'path' => $profile->cv_path],
    ];
@endphp

<style>
    .ep-top { display: flex; gap: 18px; align-items: center; flex-wrap: wrap; }
    .ep-photo { width: 76px; height: 76px; border-radius: 18px; object-fit: cover; border: 1px solid var(--border); background: var(--bg-body); display: flex; align-items: center; justify-content: center; font-size: 1.8rem; font-weight: 700; color: var(--primary); }
    .ep-title { font-size: 1.35rem; font-weight: 700; color: var(--text-main); }
    .ep-sub { font-size: 0.85rem; color: var(--text-muted); margin-top: 3px; }
    .ep-tags { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
    .ep-tag { padding: 0.24rem 0.7rem; border-radius: 999px; font-size: 0.74rem; font-weight: 700; background: rgba(99,102,241,0.12); color: var(--primary); }
    .ep-tag.green { background: rgba(16,185,129,0.12); color: var(--success); }
    .ep-tag.amber { background: rgba(245,158,11,0.15); color: var(--warning); }

    .ep-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1rem; }
    .ep-grid label, .ep-full label { display: block; font-size: 0.78rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; }
    .ep-grid input, .ep-grid select, .ep-full textarea, .ep-full input { width: 100%; padding: 0.6rem 0.7rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-family: inherit; font-size: 0.88rem; }
    .ep-full { margin-top: 1rem; }
    .ep-full textarea { min-height: 80px; resize: vertical; }
    .ep-section-title { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); margin: 1.6rem 0 0.9rem; padding-top: 1.1rem; border-top: 1px solid var(--border); }
    .ep-section-title:first-of-type { border-top: none; padding-top: 0; margin-top: 0; }

    .ep-files { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 1rem; }
    .ep-file { border: 1px solid var(--border); border-radius: 12px; padding: 0.9rem; background: var(--bg-body); }
    .ep-file b { display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: var(--text-main); margin-bottom: 8px; }
    .ep-file .have { font-size: 0.78rem; display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 8px; }
    .ep-file input[type=file] { width: 100%; font-size: 0.78rem; color: var(--text-muted); }

    .ep-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; }
    .ep-card { border: 1px solid var(--border); border-radius: 12px; padding: 0.85rem 1rem; background: var(--bg-body); }
    .ep-card span { display: block; font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px; }
    .ep-card strong { font-size: 1.3rem; font-weight: 700; color: var(--text-main); }

    .ep-quota { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; }
    .ep-quota-item { border: 1px solid var(--border); border-left-width: 4px; border-radius: 12px; padding: 0.8rem 0.95rem; background: var(--bg-card); }
    .ep-quota-item b { display: block; color: var(--text-main); font-size: 0.88rem; }
    .ep-quota-item small { color: var(--text-muted); font-size: 0.76rem; }
    .ep-bar { height: 6px; border-radius: 999px; background: var(--bg-body); border: 1px solid var(--border); overflow: hidden; margin-top: 8px; }
    .ep-bar i { display: block; height: 100%; }

    .ep-doc { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 0.7rem 0; border-bottom: 1px solid var(--border); font-size: 0.86rem; }
    .ep-doc:last-child { border-bottom: none; }
    .ep-doc small { color: var(--text-muted); font-size: 0.74rem; }

    .ep-actions { display: flex; gap: 10px; margin-top: 1.4rem; flex-wrap: wrap; }
    .ep-chip { display: inline-block; padding: 0.2rem 0.55rem; border-radius: 7px; font-size: 0.74rem; font-weight: 700; }

    /* ---- the month calendar ---- */
    .cal-legend { display: flex; gap: 12px; flex-wrap: wrap; font-size: 0.75rem; color: var(--text-muted); }
    .cal-legend span { display: inline-flex; align-items: center; gap: 5px; }
    .cal-legend i { width: 9px; height: 9px; border-radius: 3px; display: inline-block; }

    .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 7px; }
    .cal-head { text-align: center; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: var(--text-muted); padding-bottom: 3px; }
    .cal-blank { min-height: 10px; }
    .cal-day {
        position: relative; text-align: left; font-family: inherit; cursor: pointer;
        border: 1px solid var(--border); border-left-width: 4px; border-radius: 11px;
        background: var(--bg-card); color: var(--text-main); padding: 0.5rem 0.55rem; min-height: 74px;
        display: flex; flex-direction: column; gap: 2px; transition: border-color 0.15s, box-shadow 0.15s;
    }
    .cal-day:hover { box-shadow: 0 3px 10px rgba(0,0,0,0.08); }
    .cal-day.is-open { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(99,102,241,0.25); }
    .cal-day b { font-size: 0.95rem; font-weight: 700; line-height: 1.1; }
    .cal-day small { font-size: 0.68rem; color: var(--text-muted); line-height: 1.3; }
    .cal-day .cal-time { color: var(--text-main); font-weight: 600; margin-top: auto; }
    .cal-day.full { border-left-color: var(--success); }
    .cal-day.half, .cal-day.short { border-left-color: var(--warning); }
    .cal-day.absent { border-left-color: var(--danger); background: rgba(239,68,68,0.07); }
    .cal-day.leave { border-left-color: var(--info); background: rgba(59,130,246,0.07); }
    .cal-day.unpaid_leave { border-left-color: var(--danger); background: rgba(59,130,246,0.07); }
    .cal-day.off { border-left-color: var(--secondary); opacity: 0.7; }
    .cal-day.future { border-left-color: var(--border); opacity: 0.5; }

    /* The attendance engine's own verdicts. */
    .cal-day.st-present { border-left-color: var(--success); }
    .cal-day.st-late, .cal-day.st-early_off, .cal-day.st-late_early_off, .cal-day.st-half_day { border-left-color: var(--warning); }
    .cal-day.st-absent { border-left-color: var(--danger); background: rgba(239,68,68,0.07); }
    .cal-day.st-on_leave { border-left-color: var(--info); background: rgba(59,130,246,0.07); }
    .cal-day.st-weekend, .cal-day.st-holiday { border-left-color: var(--secondary); opacity: 0.7; }

    .cal-pill.st-present { background: rgba(16,185,129,0.14); color: var(--success); }
    .cal-pill.st-late, .cal-pill.st-early_off, .cal-pill.st-late_early_off, .cal-pill.st-half_day { background: rgba(245,158,11,0.16); color: var(--warning); }
    .cal-pill.st-absent { background: rgba(239,68,68,0.14); color: var(--danger); }
    .cal-pill.st-on_leave { background: rgba(59,130,246,0.14); color: var(--info); }
    .cal-late { position: absolute; top: 5px; right: 6px; width: 16px; height: 16px; border-radius: 50%; background: rgba(239,68,68,0.15); color: var(--danger); font-size: 0.62rem; font-weight: 800; display: flex; align-items: center; justify-content: center; }

    .cal-detail { margin-top: 1.2rem; border: 1px solid var(--border); border-top: 3px solid var(--primary); border-radius: 12px; background: var(--bg-body); padding: 1.1rem 1.2rem; }
    .cal-detail-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 1rem; }
    .cal-detail-head b { color: var(--text-main); font-size: 1rem; margin-right: 10px; }
    .cal-pill { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700; background: rgba(100,116,139,0.14); color: var(--secondary); }
    .cal-pill.full { background: rgba(16,185,129,0.14); color: var(--success); }
    .cal-pill.half, .cal-pill.short { background: rgba(245,158,11,0.16); color: var(--warning); }
    .cal-pill.absent, .cal-pill.unpaid_leave { background: rgba(239,68,68,0.14); color: var(--danger); }
    .cal-pill.leave { background: rgba(59,130,246,0.14); color: var(--info); }

    .cal-facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; margin-bottom: 1.1rem; }
    .cal-facts div { border: 1px solid var(--border); border-radius: 10px; padding: 0.55rem 0.7rem; background: var(--bg-card); }
    .cal-facts span { display: block; font-size: 0.71rem; color: var(--text-muted); margin-bottom: 3px; }
    .cal-facts b { font-size: 0.95rem; color: var(--text-main); }

    .cal-breaks { width: 100%; font-size: 0.83rem; }
    .cal-breaks th { font-size: 0.72rem; }
    .cal-x { border: 1px solid var(--border); background: var(--bg-card); color: var(--danger); border-radius: 7px; padding: 0.22rem 0.5rem; font-size: 0.72rem; cursor: pointer; }
    .cal-none { color: var(--text-muted); font-size: 0.84rem; margin: 0 0 1rem; }

    .cal-fix { display: flex; gap: 10px; align-items: end; flex-wrap: wrap; margin-top: 1.1rem; padding-top: 1rem; border-top: 1px solid var(--border); }
    .cal-fix label { display: block; font-size: 0.74rem; font-weight: 600; color: var(--text-muted); margin-bottom: 5px; }
    .cal-fix input { padding: 0.45rem 0.6rem; border-radius: 9px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-family: inherit; }
    .cal-help { font-size: 0.73rem; color: var(--text-muted); flex: 1 1 220px; }

    .cal-log { margin-top: 1rem; padding-top: 0.9rem; border-top: 1px dashed var(--border); }
    .cal-log > b { display: block; font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 8px; }
    .cal-log-row { font-size: 0.78rem; color: var(--text-main); padding: 0.45rem 0; border-bottom: 1px solid var(--border); }
    .cal-log-row:last-child { border-bottom: none; }
    .cal-log-row span { display: block; }
    .cal-log-reason { color: var(--text-muted); font-style: italic; margin-top: 2px; }
    .cal-log-changes { color: var(--text-muted); font-size: 0.73rem; margin-top: 2px; }

    @media (max-width: 720px) {
        .cal-grid { gap: 4px; }
        .cal-day { min-height: 62px; padding: 0.4rem; border-left-width: 3px; }
        .cal-day small { font-size: 0.62rem; }
    }
</style>

<div style="display: flex; flex-direction: column; gap: 1.5rem;">

    <div class="card" style="margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 15px; flex-wrap: wrap;">
            <div class="ep-top">
                @if($profile->photo_path)
                    <img class="ep-photo" src="{{ asset('storage/' . $profile->photo_path) }}" alt="{{ $employee->name }}">
                @else
                    <div class="ep-photo">{{ strtoupper(substr($employee->name, 0, 1)) }}</div>
                @endif
                <div>
                    <div class="ep-title">{{ $employee->name }}</div>
                    <div class="ep-sub">
                        {{ $profile->designation ?: ucfirst(str_replace('_', ' ', $employee->role)) }}
                        @if($profile->department) &middot; {{ $profile->department }} @endif
                    </div>
                    <div class="ep-tags">
                        <span class="ep-tag {{ $profile->isProbation() ? 'amber' : 'green' }}">
                            {{ $profile->isProbation() ? 'Probation' : 'Permanent' }}
                        </span>
                        @if($profile->joining_date)
                            <span class="ep-tag">Joined {{ $profile->joining_date->format('d M Y') }}</span>
                        @endif
                        @if($profile->exists)
                            <span class="ep-tag">{{ $profile->completeness() }}% file complete</span>
                        @endif
                    </div>
                </div>
            </div>
            <a href="{{ route('admin.hr.employees') }}" class="btn btn-ghost"
               style="border: 1px solid var(--border); text-decoration: none; font-weight: 600;">
                <i class="fas fa-arrow-left"></i> All employees
            </a>
        </div>
    </div>

    {{-- ---- this month, from attendance ---- --}}
    <div class="card" style="margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; border-bottom: 1px solid var(--border); padding-bottom: 1rem; margin-bottom: 1.2rem;">
            <div class="card-title" style="font-size: 1.1rem; font-weight: 700;">
                <i class="fas fa-calendar-check" style="color: var(--primary);"></i>
                Attendance &amp; salary — {{ $month->format('F Y') }}
            </div>
            <form method="GET" action="{{ route('admin.hr.employee', $employee->id) }}">
                <select name="month" onchange="this.form.submit()"
                        style="height: 36px; padding: 0 0.6rem; border-radius: 9px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-family: inherit;">
                    @foreach($months as $m)
                        <option value="{{ $m->format('Y-m') }}" {{ $month->format('Y-m') === $m->format('Y-m') ? 'selected' : '' }}>{{ $m->format('F Y') }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="ep-cards">
            <div class="ep-card"><span>Days present</span><strong style="color: var(--success);">{{ $pay['present_days'] }}</strong></div>
            <div class="ep-card"><span>Half / short</span><strong style="color: var(--warning);">{{ $pay['half_days'] }} / {{ $pay['short_days'] }}</strong></div>
            <div class="ep-card"><span>Absent</span><strong style="color: var(--danger);">{{ $pay['absent_days'] }}</strong></div>
            <div class="ep-card"><span>Leave taken</span><strong>{{ ($pay['leave_days'] ?? 0) + ($pay['unpaid_leave_days'] ?? 0) }}</strong></div>
            <div class="ep-card"><span>Late arrivals</span><strong style="color: {{ $pay['late_days'] ? 'var(--danger)' : 'var(--text-main)' }};">{{ $pay['late_days'] }}</strong></div>
            <div class="ep-card"><span>Hours worked</span><strong>{{ \App\Services\PayrollService::hoursText($pay['worked_minutes']) }}</strong></div>
            <div class="ep-card"><span>Monthly salary</span><strong>{{ $currency }}{{ number_format($pay['monthly_salary']) }}</strong></div>
            <div class="ep-card"><span>Payable this month</span><strong style="color: var(--success);">{{ $currency }}{{ number_format($pay['net_salary']) }}</strong></div>
        </div>

        <p style="font-size: 0.8rem; color: var(--text-muted); margin: 1.1rem 0 0;">
            {{ rtrim(rtrim(number_format($pay['credited_days'], 1), '0'), '.') }} of {{ $pay['working_days'] }} working days paid at
            {{ $currency }}{{ number_format($pay['per_day']) }} a day.
            @if($pay['late_deduction'] > 0)
                {{ $pay['late_days'] }} late arrivals cost {{ $currency }}{{ number_format($pay['late_deduction']) }}.
            @endif
            @if($pay['late_minutes'] > 0)
                Total lateness {{ \App\Services\PayrollService::hoursText($pay['late_minutes']) }}.
            @endif
            <a href="{{ route('admin.attendance', ['user_id' => $employee->id, 'payroll_month' => $month->format('Y-m')]) }}" style="color: var(--primary);">Open the full attendance log</a>.
        </p>
    </div>

    {{-- ---- the month, day by day ---- --}}
    @php
        $cells = collect($pay['days']);
        $first = \Carbon\Carbon::parse($cells->first()['date']);
        $lead = (int) $first->dayOfWeek;   // blanks before the 1st so columns line up
        $dayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    @endphp
    <div class="card" style="margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; border-bottom: 1px solid var(--border); padding-bottom: 1rem; margin-bottom: 1.2rem;">
            <div class="card-title" style="font-size: 1.1rem; font-weight: 700;">
                <i class="fas fa-calendar-days" style="color: var(--primary);"></i> {{ $month->format('F Y') }} — day by day
            </div>
            <div class="cal-legend">
                <span><i style="background: var(--success);"></i> Present</span>
                <span><i style="background: var(--warning);"></i> Half / short</span>
                <span><i style="background: var(--danger);"></i> Absent</span>
                <span><i style="background: var(--info);"></i> Leave</span>
                <span><i style="background: var(--secondary);"></i> Weekly off</span>
            </div>
        </div>

        <div class="cal-grid">
            @foreach($dayLabels as $label)
                <div class="cal-head">{{ $label }}</div>
            @endforeach
            @for($i = 0; $i < $lead; $i++)
                <div class="cal-blank"></div>
            @endfor

            @foreach($cells as $day)
                @php
                    $d = \Carbon\Carbon::parse($day['date']);
                    $att = $attendanceByDate[$day['date']] ?? null;
                    $breakCount = $att ? $att->breaks->count() : 0;

                    // The attendance row carries the HR engine's verdict, judged
                    // against that day's shift and the rules in force. Where it
                    // exists it is the answer; the salary sheet's own reading is
                    // only used for days nobody has judged yet.
                    $verdict = $att?->day_status;
                    $cellClass = $verdict ? 'st-' . $verdict : $day['status'];
                    $cellLate = $att && $att->late_minutes !== null ? (int) $att->late_minutes : (int) $day['late_minutes'];
                    $cellWorked = $att && $att->worked_minutes !== null ? (int) $att->worked_minutes : (int) $day['worked_minutes'];
                @endphp
                <button type="button" class="cal-day {{ $cellClass }}" onclick="hrOpenDay('{{ $day['date'] }}', this)"
                        aria-controls="hrDay_{{ $day['date'] }}" title="{{ $d->format('l d M Y') }}">
                    <b>{{ $d->format('j') }}</b>
                    <small>
                        @if($verdict)
                            {{ \App\Models\Attendance::DAY_STATUS_LABELS[$verdict] ?? '—' }}
                        @else
                            @switch($day['status'])
                                @case('full') Present @break
                                @case('half') Half day @break
                                @case('short') Short @break
                                @case('absent') Absent @break
                                @case('leave') @case('unpaid_leave') Leave @break
                                @case('off') Off @break
                                @default —
                            @endswitch
                        @endif
                    </small>
                    @if($day['clock_in'])
                        <small class="cal-time">{{ \App\Services\PayrollService::hoursText($cellWorked) }}@if($breakCount) &middot; {{ $breakCount }} br @endif</small>
                    @endif
                    @if($cellLate > 0)
                        <span class="cal-late" title="{{ $cellLate }} minutes late">L</span>
                    @endif
                </button>
            @endforeach
        </div>

        {{-- One panel per day, rendered here and simply shown on click: the break
             rows carry real forms, so nothing has to be built in the browser. --}}
        @foreach($cells as $day)
            @php
                $d = \Carbon\Carbon::parse($day['date']);
                $att = $attendanceByDate[$day['date']] ?? null;
            @endphp
            @php
                $verdict = $att?->day_status;
                $panelLate = $att && $att->late_minutes !== null ? (int) $att->late_minutes : (int) $day['late_minutes'];
                $panelEarly = $att ? (int) $att->early_off_minutes : 0;
                $panelWorked = $att && $att->worked_minutes !== null ? (int) $att->worked_minutes : (int) $day['worked_minutes'];
            @endphp
            <div class="cal-detail" id="hrDay_{{ $day['date'] }}" hidden>
                <div class="cal-detail-head">
                    <div>
                        <b>{{ $d->format('l, d M Y') }}</b>
                        <span class="cal-pill {{ $verdict ? 'st-' . $verdict : $day['status'] }}">
                            @if($verdict)
                                {{ \App\Models\Attendance::DAY_STATUS_LABELS[$verdict] ?? '—' }}@if($verdict === 'on_leave' && $day['leave_name']) — {{ $day['leave_name'] }}@endif
                            @else
                                @switch($day['status'])
                                    @case('full') Full day @break
                                    @case('half') Half day @break
                                    @case('short') Short day @break
                                    @case('absent') Absent @break
                                    @case('leave') {{ $day['leave_name'] ?? 'Leave' }} @break
                                    @case('unpaid_leave') {{ $day['leave_name'] ?? 'Leave' }} (unpaid) @break
                                    @case('off') Weekly off @break
                                    @default Upcoming
                                @endswitch
                            @endif
                        </span>
                        @if($att?->shift)
                            <span style="font-size: 0.74rem; color: var(--text-muted);">
                                {{ $att->shift->name }} · {{ $att->shift->timesText() }}
                            </span>
                        @endif
                        @if($att && $att->source !== 'web')
                            <span class="cal-pill" title="How this day was recorded">{{ ucfirst($att->source) }}</span>
                        @endif
                    </div>
                    <button type="button" class="btn btn-ghost" style="cursor: pointer;" onclick="hrOpenDay('{{ $day['date'] }}')">&times;</button>
                </div>

                <div class="cal-facts">
                    <div><span>Clocked in</span><b>{{ $day['clock_in'] ?: '—' }}</b></div>
                    <div><span>Clocked out</span><b>{{ $day['clock_out'] ?: '—' }}</b></div>
                    <div><span>Worked (net)</span><b>{{ \App\Services\PayrollService::hoursText($panelWorked) }}</b></div>
                    <div><span>On break</span><b>{{ $att ? $att->totalBreakDurationText() : '0s' }}</b></div>
                    <div><span>Late by</span><b style="color: {{ $panelLate > 0 ? 'var(--danger)' : 'inherit' }};">{{ $panelLate > 0 ? \App\Services\PayrollService::hoursText($panelLate) : 'on time' }}</b></div>
                    <div><span>Left early by</span><b style="color: {{ $panelEarly > 0 ? 'var(--danger)' : 'inherit' }};">{{ $panelEarly > 0 ? \App\Services\PayrollService::hoursText($panelEarly) : 'on time' }}</b></div>
                    <div><span>Breaks taken</span><b>{{ $att ? $att->breaks->count() : 0 }}</b></div>
                </div>

                @if($att && $att->breaks->count())
                    <table class="cal-breaks">
                        <thead><tr><th>Break</th><th>Left at</th><th>Back at</th><th>Away for</th><th></th></tr></thead>
                        <tbody>
                        @foreach($att->breaks->sortBy('break_start') as $break)
                            <tr>
                                <td>{{ ucfirst(str_replace('_', ' ', $break->type ?: 'break')) }}</td>
                                <td>{{ $break->break_start?->format('h:i:s A') ?: '—' }}</td>
                                <td>{{ $break->break_end ? $break->break_end->format('h:i:s A') : 'still away' }}</td>
                                <td>{{ $break->durationText() }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.hr.employee.break.delete', [$employee->id, $break->id]) }}"
                                          onsubmit="return confirm('Remove this break?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="cal-x" title="Remove this break"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="cal-none">No breaks recorded for this day.</p>
                @endif

                <form method="POST" action="{{ route('admin.hr.employee.attendance', $employee->id) }}" class="cal-fix">
                    @csrf
                    <input type="hidden" name="date" value="{{ $day['date'] }}">
                    <div>
                        <label for="in_{{ $day['date'] }}">Clock in</label>
                        <input type="time" id="in_{{ $day['date'] }}" name="clock_in" value="{{ $att?->clock_in?->format('H:i') }}">
                    </div>
                    <div>
                        <label for="out_{{ $day['date'] }}">Clock out</label>
                        <input type="time" id="out_{{ $day['date'] }}" name="clock_out" value="{{ $att?->clock_out?->format('H:i') }}">
                    </div>
                    <div>
                        <label for="st_{{ $day['date'] }}">Status</label>
                        {{-- Deliberately left on "work it out": pre-selecting the
                             day's current status meant every save put that status
                             back, so correcting a late could never clear it. An
                             override is something HR chooses, not a default. --}}
                        <select id="st_{{ $day['date'] }}" name="day_status"
                                style="padding: 0.45rem 0.6rem; border-radius: 9px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-family: inherit;">
                            <option value="" selected>Work it out from the times</option>
                            @foreach(\App\Models\Attendance::DAY_STATUS_LABELS as $value => $label)
                                <option value="{{ $value }}">{{ $label }}{{ ($att?->day_status ?? '') === $value ? ' (now)' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="flex: 1 1 200px;">
                        <label for="rm_{{ $day['date'] }}">Remarks</label>
                        <input type="text" id="rm_{{ $day['date'] }}" name="remarks" value="{{ $att?->remarks }}" placeholder="Optional"
                               style="width: 100%;">
                    </div>
                    <div style="flex: 1 1 220px;">
                        <label for="rs_{{ $day['date'] }}">Reason for the change <span style="color: var(--danger);">*</span></label>
                        <input type="text" id="rs_{{ $day['date'] }}" name="reason" required placeholder="e.g. forgot to clock out"
                               style="width: 100%;">
                    </div>
                    <button type="submit" class="btn btn-primary" style="height: 36px; padding: 0 1rem; border-radius: 9px; cursor: pointer; font-weight: 600;">
                        <i class="fas fa-floppy-disk"></i> Save day
                    </button>
                    <span class="cal-help">
                        Leave clock in empty and save to clear the day. Every change is kept in the log below with your name against it,
                        and salary follows immediately.
                    </span>
                </form>

                @php $dayLog = ($auditByDate[$day['date']] ?? collect()); @endphp
                @if($dayLog->count())
                    <div class="cal-log">
                        <b>Changes to this day</b>
                        @foreach($dayLog as $entry)
                            <div class="cal-log-row">
                                <span>
                                    <i class="fas fa-pen" style="color: var(--text-muted);"></i>
                                    {{ $entry->editor->name ?? 'Someone' }} &middot; {{ $entry->created_at->format('d M Y, h:i A') }}
                                    &middot; <em>{{ $entry->action }}</em>
                                </span>
                                <span class="cal-log-reason">{{ $entry->reason }}</span>
                                @if($entry->lines())
                                    <span class="cal-log-changes">{{ implode(' · ', $entry->lines()) }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- ---- month on month ---- --}}
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header"><div class="card-title"><i class="fas fa-chart-column" style="color: var(--primary);"></i> Last 12 months</div></div>
        <div style="overflow-x: auto;">
            <table>
                <thead><tr><th>Month</th><th>Present</th><th>Half / short</th><th>Absent</th><th>Leave</th><th>Late</th><th>Hours</th><th>Paid</th><th></th></tr></thead>
                <tbody>
                @foreach($history as $h)
                    <tr style="{{ $h['month']->format('Y-m') === $month->format('Y-m') ? 'background: rgba(99,102,241,0.06);' : '' }}">
                        <td style="font-weight: 600; color: var(--text-main);">{{ $h['month']->format('F Y') }}</td>
                        <td style="color: var(--success); font-weight: 600;">{{ $h['present'] }}</td>
                        <td>{{ $h['half'] }} / {{ $h['short'] }}</td>
                        <td style="color: {{ $h['absent'] ? 'var(--danger)' : 'inherit' }};">{{ $h['absent'] }}</td>
                        <td>{{ $h['leave'] }}</td>
                        <td>{{ $h['late'] }}</td>
                        <td>{{ \App\Services\PayrollService::hoursText($h['hours']) }}</td>
                        <td style="font-weight: 700;">{{ $currency }}{{ number_format($h['net']) }}</td>
                        <td>
                            <a href="{{ route('admin.hr.employee', ['user' => $employee->id, 'month' => $h['month']->format('Y-m')]) }}"
                               class="btn btn-ghost" style="border: 1px solid var(--border); padding: 0.28rem 0.7rem; font-size: 0.75rem; border-radius: 8px; text-decoration: none; white-space: nowrap;">Open</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- ---- the onboarding form ---- --}}
    <form method="POST" action="{{ route('admin.hr.employee.save', $employee->id) }}" enctype="multipart/form-data">
        @csrf
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-user-pen" style="color: var(--primary);"></i> Employee profile</div>
            </div>

            <div class="ep-section-title">Personal</div>
            <div class="ep-grid">
                <div>
                    <label for="epName">Name</label>
                    <input type="text" id="epName" name="name" value="{{ old('name', $employee->name) }}" required>
                </div>
                <div>
                    <label for="epFather">Father name</label>
                    <input type="text" id="epFather" name="father_name" value="{{ old('father_name', $profile->father_name) }}">
                </div>
                <div>
                    <label for="epDob">Date of birth</label>
                    <input type="date" id="epDob" name="date_of_birth" value="{{ old('date_of_birth', $profile->date_of_birth?->format('Y-m-d')) }}">
                </div>
                <div>
                    <label for="epIdCard">ID card (CNIC) number</label>
                    <input type="text" id="epIdCard" name="id_card_no" value="{{ old('id_card_no', $profile->id_card_no) }}" placeholder="00000-0000000-0">
                </div>
            </div>

            <div class="ep-section-title">Contact</div>
            <div class="ep-grid">
                <div>
                    <label for="epEmail">Work email</label>
                    <input type="email" id="epEmail" value="{{ $employee->email }}" disabled title="Changed in User Management">
                </div>
                <div>
                    <label for="epPersonalEmail">Personal email</label>
                    <input type="email" id="epPersonalEmail" name="personal_email" value="{{ old('personal_email', $profile->personal_email) }}">
                </div>
                <div>
                    <label for="epContact">Contact number</label>
                    <input type="text" id="epContact" name="contact_no" value="{{ old('contact_no', $profile->contact_no) }}">
                </div>
                <div>
                    <label for="epEmergencyName">Emergency contact (name)</label>
                    <input type="text" id="epEmergencyName" name="emergency_contact_name" value="{{ old('emergency_contact_name', $profile->emergency_contact_name) }}">
                </div>
                <div>
                    <label for="epEmergencyNo">Emergency contact (number)</label>
                    <input type="text" id="epEmergencyNo" name="emergency_contact_no" value="{{ old('emergency_contact_no', $profile->emergency_contact_no) }}">
                </div>
                <div>
                    <label for="epFamily">Family contact number</label>
                    <input type="text" id="epFamily" name="family_contact_no" value="{{ old('family_contact_no', $profile->family_contact_no) }}">
                </div>
            </div>
            <div class="ep-full">
                <label for="epAddress">Address</label>
                <textarea id="epAddress" name="address" style="min-height: 64px;">{{ old('address', $profile->address) }}</textarea>
            </div>

            <div class="ep-section-title">Employment</div>
            <div class="ep-grid">
                <div>
                    <label for="epDesignation">Designation</label>
                    <input type="text" id="epDesignation" name="designation" value="{{ old('designation', $profile->designation) }}">
                </div>
                <div>
                    <label for="epDepartment">Department</label>
                    {{-- A real department rather than a typed word: leave approval
                         and department quotas both hang off this link. --}}
                    <select id="epDepartment" name="department_id">
                        <option value="">Not set</option>
                        @foreach($departmentOptions as $option)
                            <option value="{{ $option->id }}" {{ (int) old('department_id', $profile->department_id) === $option->id ? 'selected' : '' }}>
                                {{ $option->name }}
                            </option>
                        @endforeach
                    </select>
                    <small style="display: block; font-size: 0.72rem; color: var(--text-muted); margin-top: 5px;">
                        Departments are added under <a href="{{ route('admin.hr.settings') }}?tab=departments" style="color: var(--primary);">HR Settings</a>.
                    </small>
                </div>
                <div>
                    <label for="epStatus">Probation / permanent</label>
                    <select id="epStatus" name="employment_status">
                        <option value="probation" {{ old('employment_status', $profile->employment_status) === 'permanent' ? '' : 'selected' }}>Probation</option>
                        <option value="permanent" {{ old('employment_status', $profile->employment_status) === 'permanent' ? 'selected' : '' }}>Permanent</option>
                    </select>
                </div>
                <div>
                    <label for="epJoining">Joining date</label>
                    <input type="date" id="epJoining" name="joining_date" value="{{ old('joining_date', $profile->joining_date?->format('Y-m-d')) }}">
                </div>
                <div>
                    <label for="epProbEnd">Probation ends</label>
                    <input type="date" id="epProbEnd" name="probation_end_date" value="{{ old('probation_end_date', $profile->probation_end_date?->format('Y-m-d')) }}">
                </div>
            </div>
            <div class="ep-full">
                <label for="epNotes">HR notes</label>
                <textarea id="epNotes" name="notes">{{ old('notes', $profile->notes) }}</textarea>
            </div>

            <div class="ep-section-title">Documents</div>
            <div class="ep-files">
                @foreach($files as $key => $file)
                    <div class="ep-file">
                        <b><i class="fas {{ $file['icon'] }}" style="color: var(--primary);"></i> {{ $file['label'] }}</b>
                        @if($file['path'])
                            <div class="have">
                                <a href="{{ asset('storage/' . $file['path']) }}" target="_blank" style="color: var(--primary); font-weight: 600;">
                                    <i class="fas fa-up-right-from-square"></i> View
                                </a>
                                <button type="button" onclick="epRemoveFile('{{ $key }}')" class="btn btn-ghost"
                                        style="border: 1px solid var(--border); padding: 0.15rem 0.5rem; font-size: 0.72rem; border-radius: 7px; cursor: pointer; color: var(--danger);">Remove</button>
                            </div>
                        @else
                            <div class="have" style="color: var(--text-muted);">Not uploaded yet</div>
                        @endif
                        <input type="file" name="{{ $key }}" accept="{{ $key === 'photo' ? 'image/*' : ($key === 'cv' ? '.pdf,.doc,.docx' : 'image/*,.pdf') }}">
                    </div>
                @endforeach
            </div>

            @if($errors->any())
                <p style="color: var(--danger); font-size: 0.82rem; margin-top: 1rem;">{{ $errors->first() }}</p>
            @endif

            <div class="ep-actions">
                <button type="submit" class="btn btn-primary" style="cursor: pointer; font-weight: 600;">
                    <i class="fas fa-floppy-disk"></i> Save profile
                </button>
            </div>
        </div>
    </form>

    {{-- The remove-file action is its own form so it never submits the profile. --}}
    <form id="epRemoveForm" method="POST" action="{{ route('admin.hr.employee.file.delete', $employee->id) }}" style="display: none;">
        @csrf
        <input type="hidden" name="field" id="epRemoveField">
    </form>

    {{-- ---- educational and other documents ---- --}}
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-graduation-cap" style="color: var(--primary);"></i> Educational &amp; other documents</div>
        </div>

        <form method="POST" action="{{ route('admin.hr.employee.document', $employee->id) }}" enctype="multipart/form-data"
              style="display: grid; grid-template-columns: 2fr 1fr 2fr auto; gap: 12px; align-items: end; margin-bottom: 1.2rem;">
            @csrf
            <div>
                <label for="docTitle" style="display: block; font-size: 0.78rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px;">Title</label>
                <input type="text" id="docTitle" name="title" placeholder="BSc degree, experience letter…" required
                       style="width: 100%; padding: 0.6rem 0.7rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-family: inherit;">
            </div>
            <div>
                <label for="docCategory" style="display: block; font-size: 0.78rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px;">Type</label>
                <select id="docCategory" name="category" style="width: 100%; padding: 0.6rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-family: inherit;">
                    <option value="education">Education</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div>
                <label for="docFile" style="display: block; font-size: 0.78rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px;">File</label>
                <input type="file" id="docFile" name="document" required accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx" style="width: 100%; font-size: 0.82rem; color: var(--text-muted);">
            </div>
            <button type="submit" class="btn btn-primary" style="height: 40px; padding: 0 1.1rem; border-radius: 10px; cursor: pointer; font-weight: 600;">
                <i class="fas fa-plus"></i> Add
            </button>
        </form>

        @forelse($employee->employeeDocuments->sortByDesc('created_at') as $doc)
            <div class="ep-doc">
                <div>
                    <b style="color: var(--text-main);">{{ $doc->title }}</b>
                    <small>
                        {{ ucfirst($doc->category) }} &middot; {{ $doc->sizeText() }} &middot; added {{ $doc->created_at->format('d M Y') }}
                    </small>
                </div>
                <div style="display: flex; gap: 8px;">
                    <a href="{{ asset('storage/' . $doc->path) }}" target="_blank" class="btn btn-ghost"
                       style="border: 1px solid var(--border); padding: 0.3rem 0.7rem; font-size: 0.76rem; border-radius: 8px; text-decoration: none;">
                        <i class="fas fa-eye"></i> View
                    </a>
                    <form method="POST" action="{{ route('admin.hr.employee.document.delete', [$employee->id, $doc->id]) }}"
                          onsubmit="return confirm('Remove {{ addslashes($doc->title) }}?');">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-ghost"
                                style="border: 1px solid var(--border); padding: 0.3rem 0.7rem; font-size: 0.76rem; border-radius: 8px; color: var(--danger); cursor: pointer;">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <p style="color: var(--text-muted); font-size: 0.86rem; margin: 0;">No documents attached yet.</p>
        @endforelse
    </div>

    {{-- ---- leave ---- --}}
    <div class="card" id="employeeLeaveCard" style="margin-bottom: 0;">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-plane-departure" style="color: var(--primary);"></i> Leave — {{ $month->format('Y') }}</div>
        </div>

        <div class="ep-quota">
            @foreach($quota as $q)
                @php $pct = $q['type']->annual_quota > 0 ? min(100, round($q['used'] / $q['type']->annual_quota * 100)) : 0; @endphp
                <div class="ep-quota-item" style="border-left-color: {{ $q['type']->colour }};">
                    <b>{{ $q['type']->name }}</b>
                    <small>
                        {{ rtrim(rtrim(number_format($q['used'], 1), '0'), '.') }} used this year
                        @if($q['type']->annual_quota > 0)
                            of {{ $q['type']->annual_quota }} &middot; {{ rtrim(rtrim(number_format($q['left'], 1), '0'), '.') }} left
                        @else
                            &middot; no yearly limit
                        @endif
                        @unless($q['type']->is_paid) &middot; unpaid @endunless
                    </small>
                    <small style="{{ $q['month_left'] !== null && $q['month_left'] <= 0 && $q['month_used'] > 0 ? 'color: var(--danger); font-weight: 700;' : '' }}">
                        {{ rtrim(rtrim(number_format($q['month_used'], 1), '0'), '.') }} in {{ $month->format('M') }}
                        @if($q['month_left'] !== null)
                            of {{ $q['type']->monthly_quota }} a month &middot; {{ rtrim(rtrim(number_format($q['month_left'], 1), '0'), '.') }} left
                        @endif
                    </small>
                    <div class="ep-bar"><i style="width: {{ $pct }}%; background: {{ $q['type']->colour }};"></i></div>
                </div>
            @endforeach
        </div>

        <div style="overflow-x: auto; margin-top: 1.3rem;">
            <table>
                <thead>
                    <tr><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Reason</th><th>Status</th><th>Decided by</th></tr>
                </thead>
                <tbody>
                @forelse($leaves as $leave)
                    <tr>
                        <td style="font-weight: 600; color: var(--text-main);">{{ $leave->type->name ?? '—' }}</td>
                        <td>{{ $leave->start_date->format('d M Y') }}</td>
                        <td>{{ $leave->end_date->format('d M Y') }}</td>
                        <td>{{ rtrim(rtrim(number_format($leave->days, 1), '0'), '.') }}{{ $leave->half_day ? ' (half)' : '' }}</td>
                        <td style="max-width: 280px; white-space: normal;">{{ $leave->reason ?: '—' }}</td>
                        <td>
                            <span class="ep-chip" style="background: {{ $leave->statusColour() }}1f; color: {{ $leave->statusColour() }};">
                                {{ ucfirst($leave->status) }}
                            </span>
                        </td>
                        <td style="font-size: 0.8rem; color: var(--text-muted);">
                            {{ $leave->decider->name ?? '—' }}
                            @if($leave->decided_at)<div>{{ $leave->decided_at->format('d M Y') }}</div>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="empty-state"><p>No leave recorded.</p></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ---- job description & targets ---- --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-bullseye" style="color: var(--primary);"></i> Job description &amp; targets</div>
        </div>

        @if($employee->jobDescription && filled($employee->jobDescription->responsibilities))
            @php $jd = $employee->jobDescription; @endphp
            <div style="font-size: 0.9rem; color: var(--text-main); margin-bottom: 1rem;">
                <b>{{ $jd->title ?: ($profile->designation ?: 'Role') }}</b>
                @if($jd->reports_to)<span style="color: var(--text-muted);"> &middot; reports to {{ $jd->reports_to }}</span>@endif
            </div>
            <ul style="margin: 0 0 1.2rem 1.1rem; padding: 0; color: var(--text-muted); font-size: 0.87rem; line-height: 1.9;">
                @foreach(array_slice($jd->lines('responsibilities'), 0, 6) as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        @else
            <p style="color: var(--text-muted); font-size: 0.86rem;">No job description written yet.</p>
        @endif

        @if($kpis->count())
            <div class="ep-quota">
                @foreach($kpis as $kpi)
                    <div class="ep-quota-item" style="border-left-color: {{ $kpi->progress() >= 80 ? 'var(--success)' : ($kpi->progress() >= 50 ? 'var(--warning)' : 'var(--danger)') }};">
                        <b>{{ $kpi->metric }}</b>
                        <small>{{ $kpi->valueText($kpi->achieved_value) }} of {{ $kpi->valueText($kpi->target_value) }} &middot; {{ $kpi->progress() }}%</small>
                        <div class="ep-bar"><i style="width: {{ $kpi->progress() }}%; background: {{ $kpi->progress() >= 80 ? 'var(--success)' : ($kpi->progress() >= 50 ? 'var(--warning)' : 'var(--danger)') }};"></i></div>
                    </div>
                @endforeach
            </div>
        @else
            <p style="color: var(--text-muted); font-size: 0.86rem; margin-bottom: 0;">No targets set for {{ $month->format('F Y') }}.</p>
        @endif

        <div class="ep-actions">
            <a href="{{ route('admin.hr.performance', ['month' => $month->format('Y-m')]) }}#emp{{ $employee->id }}" class="btn btn-ghost"
               style="border: 1px solid var(--border); text-decoration: none; font-weight: 600;">
                <i class="fas fa-pen-to-square"></i> Edit job description &amp; targets
            </a>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // One day open at a time: clicking the same day again closes it, so the page
    // never grows a stack of panels the reader has to tidy up.
    function hrOpenDay(date, cell) {
        var panel = document.getElementById('hrDay_' + date);
        if (!panel) return;
        var wasOpen = !panel.hidden;

        document.querySelectorAll('.cal-detail').forEach(function (p) { p.hidden = true; });
        document.querySelectorAll('.cal-day.is-open').forEach(function (c) { c.classList.remove('is-open'); });

        if (wasOpen) return;

        panel.hidden = false;
        if (cell) cell.classList.add('is-open');
        panel.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    function epRemoveFile(field) {
        if (!confirm('Remove this file?')) return;
        document.getElementById('epRemoveField').value = field;
        document.getElementById('epRemoveForm').submit();
    }
</script>
@endpush
@endsection
