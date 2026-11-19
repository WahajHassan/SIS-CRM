@extends('layouts.admin')

@section('title', 'My Attendance')

@section('content')
{{--
    An employee's own month: the same calendar HR sees on their file, without the
    correction form — this is a record to read, not one to edit.
--}}
@php
    $cells = collect($pay['days']);
    $first = \Carbon\Carbon::parse($cells->first()['date']);
    $lead = (int) $first->dayOfWeek;
    $dayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    $remainingLates = $latesPerOff > 0 ? $latesPerOff - ($lates % $latesPerOff) : null;
@endphp

<style>
    .ma-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; }
    .ma-card { border: 1px solid var(--border); border-radius: 12px; padding: 0.85rem 1rem; background: var(--bg-card); }
    .ma-card span { display: block; font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px; }
    .ma-card strong { font-size: 1.4rem; font-weight: 700; color: var(--text-main); }

    .ma-legend { display: flex; gap: 12px; flex-wrap: wrap; font-size: 0.75rem; color: var(--text-muted); }
    .ma-legend span { display: inline-flex; align-items: center; gap: 5px; }
    .ma-legend i { width: 9px; height: 9px; border-radius: 3px; display: inline-block; }

    .ma-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 7px; }
    .ma-head { text-align: center; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: var(--text-muted); padding-bottom: 3px; }
    .ma-blank { min-height: 10px; }
    .ma-day { position: relative; border: 1px solid var(--border); border-left-width: 4px; border-radius: 11px; background: var(--bg-card);
              padding: 0.5rem 0.55rem; min-height: 78px; display: flex; flex-direction: column; gap: 2px; }
    .ma-day b { font-size: 0.95rem; font-weight: 700; color: var(--text-main); line-height: 1.1; }
    .ma-day small { font-size: 0.68rem; color: var(--text-muted); line-height: 1.35; }
    .ma-day .ma-time { color: var(--text-main); font-weight: 600; margin-top: auto; }
    .ma-day.st-present { border-left-color: var(--success); }
    .ma-day.st-late, .ma-day.st-early_off, .ma-day.st-late_early_off, .ma-day.st-half_day { border-left-color: var(--warning); }
    .ma-day.st-absent { border-left-color: var(--danger); background: rgba(239,68,68,0.07); }
    .ma-day.st-on_leave { border-left-color: var(--info); background: rgba(59,130,246,0.07); }
    .ma-day.st-weekend, .ma-day.st-holiday { border-left-color: var(--secondary); opacity: 0.7; }
    .ma-day.st-future { border-left-color: var(--border); opacity: 0.5; }
    .ma-late { position: absolute; top: 5px; right: 6px; width: 16px; height: 16px; border-radius: 50%; background: rgba(239,68,68,0.15); color: var(--danger); font-size: 0.62rem; font-weight: 800; display: flex; align-items: center; justify-content: center; }

    .ma-quota { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
    .ma-item { border: 1px solid var(--border); border-left-width: 4px; border-radius: 12px; padding: 0.85rem 1rem; background: var(--bg-card); }
    .ma-item b { display: block; color: var(--text-main); font-size: 0.9rem; }
    .ma-item small { color: var(--text-muted); font-size: 0.77rem; }
    .ma-bar { height: 6px; border-radius: 999px; background: var(--bg-body); border: 1px solid var(--border); overflow: hidden; margin-top: 8px; }
    .ma-bar i { display: block; height: 100%; }

    @media (max-width: 720px) {
        .ma-grid { gap: 4px; }
        .ma-day { min-height: 64px; padding: 0.4rem; border-left-width: 3px; }
        .ma-day small { font-size: 0.62rem; }
    }
</style>

<div style="display: flex; flex-direction: column; gap: 1.5rem;">
    <div class="card" style="margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 15px; flex-wrap: wrap; border-bottom: 1px solid var(--border); padding-bottom: 1rem; margin-bottom: 1.2rem;">
            <div>
                <div class="card-title" style="margin-bottom: 5px; font-size: 1.2rem; font-weight: 700;">
                    <i class="fas fa-calendar-days" style="color: var(--primary);"></i> My Attendance — {{ $month->format('F Y') }}
                </div>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">
                    Every day of the month as it was recorded. Something wrong? Ask HR to correct it.
                </p>
            </div>
            <form method="GET" action="{{ route('admin.hr.my_attendance') }}">
                <select name="month" onchange="this.form.submit()"
                        style="height: 38px; padding: 0 0.7rem; border-radius: 9px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-family: inherit;">
                    @foreach($months as $m)
                        <option value="{{ $m->format('Y-m') }}" {{ $month->format('Y-m') === $m->format('Y-m') ? 'selected' : '' }}>{{ $m->format('F Y') }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="ma-cards">
            <div class="ma-card"><span>Days present</span><strong style="color: var(--success);">{{ $pay['present_days'] }}</strong></div>
            <div class="ma-card"><span>Half / short</span><strong style="color: var(--warning);">{{ $pay['half_days'] }} / {{ $pay['short_days'] }}</strong></div>
            <div class="ma-card"><span>Absent</span><strong style="color: {{ $pay['absent_days'] ? 'var(--danger)' : 'var(--text-main)' }};">{{ $pay['absent_days'] }}</strong></div>
            <div class="ma-card"><span>On leave</span><strong>{{ ($pay['leave_days'] ?? 0) + ($pay['unpaid_leave_days'] ?? 0) }}</strong></div>
            <div class="ma-card">
                <span>Lates this month</span>
                <strong style="color: {{ $remainingLates === 1 ? 'var(--danger)' : 'var(--text-main)' }};">
                    {{ $lates }}@if($latesPerOff > 0)/{{ $latesPerOff }}@endif
                </strong>
                @if($remainingLates !== null && $lates > 0)
                    <small style="display: block; font-size: 0.72rem; color: var(--text-muted); margin-top: 4px;">
                        {{ $remainingLates }} more {{ \Illuminate\Support\Str::plural('late', $remainingLates) }} costs a day
                    </small>
                @endif
            </div>
            <div class="ma-card"><span>Hours worked</span><strong>{{ \App\Services\PayrollService::hoursText($pay['worked_minutes']) }}</strong></div>
        </div>

        @if($deductedOffs->count())
            <p style="font-size: 0.82rem; color: var(--danger); margin: 1rem 0 0;">
                <i class="fas fa-triangle-exclamation"></i>
                {{ $deductedOffs->count() }} {{ \Illuminate\Support\Str::plural('day', $deductedOffs->count()) }} deducted this month for lateness
                ({{ $deductedOffs->map(fn ($o) => $o->start_date->format('d M'))->implode(', ') }}).
            </p>
        @endif
    </div>

    {{-- ---- the month ---- --}}
    <div class="card" style="margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; border-bottom: 1px solid var(--border); padding-bottom: 1rem; margin-bottom: 1.2rem;">
            <div class="card-title" style="font-size: 1.05rem; font-weight: 700;">Day by day</div>
            <div class="ma-legend">
                <span><i style="background: var(--success);"></i> Present</span>
                <span><i style="background: var(--warning);"></i> Late / short</span>
                <span><i style="background: var(--danger);"></i> Absent</span>
                <span><i style="background: var(--info);"></i> Leave</span>
                <span><i style="background: var(--secondary);"></i> Off</span>
            </div>
        </div>

        <div class="ma-grid">
            @foreach($dayLabels as $label)
                <div class="ma-head">{{ $label }}</div>
            @endforeach
            @for($i = 0; $i < $lead; $i++)
                <div class="ma-blank"></div>
            @endfor

            @foreach($cells as $day)
                @php
                    $d = \Carbon\Carbon::parse($day['date']);
                    $att = $attendanceByDate[$day['date']] ?? null;
                    $verdict = $att?->day_status;
                    $class = $verdict ? 'st-' . $verdict : ($day['status'] === 'future' ? 'st-future' : 'st-' . $day['status']);
                    $late = $att?->late_minutes ?? 0;
                @endphp
                <div class="ma-day {{ $class }}" title="{{ $d->format('l d M Y') }}">
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
                    @if($att?->clock_in)
                        <small>{{ $att->clock_in->format('h:i A') }}@if($att->clock_out) – {{ $att->clock_out->format('h:i A') }}@endif</small>
                        <small class="ma-time">{{ \App\Services\PayrollService::hoursText((int) $att->worked_minutes) }}</small>
                    @endif
                    @if($late > 0)
                        <span class="ma-late" title="{{ $late }} minutes late">L</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- ---- what is left ---- --}}
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; border-bottom: 1px solid var(--border); padding-bottom: 1rem; margin-bottom: 1.2rem;">
            <div class="card-title" style="font-size: 1.05rem; font-weight: 700;">
                <i class="fas fa-plane-departure" style="color: var(--primary);"></i> My leave balance — {{ $month->format('Y') }}
            </div>
            <a href="{{ route('admin.hr.my_leaves') }}" class="btn btn-primary" style="text-decoration: none; font-weight: 600;">
                <i class="fas fa-paper-plane"></i> Ask for leave
            </a>
        </div>

        <div class="ma-quota">
            @foreach($quota as $q)
                @php $pct = $q['type']->annual_quota > 0 ? min(100, round($q['used'] / $q['type']->annual_quota * 100)) : 0; @endphp
                <div class="ma-item" style="border-left-color: {{ $q['type']->colour }};">
                    <b>{{ $q['type']->name }}</b>
                    <small>
                        {{ rtrim(rtrim(number_format($q['used'], 1), '0'), '.') }} taken
                        @if($q['type']->annual_quota > 0)
                            of {{ $q['type']->annual_quota }} &middot;
                            <b style="display: inline; color: var(--success);">{{ rtrim(rtrim(number_format($q['left'], 1), '0'), '.') }} left</b>
                        @else
                            &middot; no yearly limit
                        @endif
                    </small>
                    <div class="ma-bar"><i style="width: {{ $pct }}%; background: {{ $q['type']->colour }};"></i></div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
