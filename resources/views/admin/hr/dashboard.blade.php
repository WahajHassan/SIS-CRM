@extends('layouts.admin')

@section('title', 'HR Dashboard')

@section('content')
{{--
    Who is in today, who is not, and what is waiting on somebody.

    Everything shown here is read off the attendance the engine already judged,
    so this page cannot tell a different story from an employee's own file or
    the salary sheet.
--}}
@php
    $statusColours = [
        'present' => 'var(--success)',
        'late' => 'var(--warning)',
        'early_off' => 'var(--warning)',
        'late_early_off' => 'var(--warning)',
        'half_day' => 'var(--warning)',
        'absent' => 'var(--danger)',
        'on_leave' => 'var(--info)',
        'weekend' => 'var(--secondary)',
        'holiday' => 'var(--secondary)',
    ];
@endphp

<style>
    .hd-filters { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px; }
    .hd-filters > div { flex: 1 1 150px; min-width: 0; }
    .hd-filters > div:last-child { flex: 0 0 auto; }
    .hd-filters label { display: block; font-size: 0.68rem; font-weight: 700; letter-spacing: 0.06em;
                      text-transform: uppercase; color: var(--text-soft); margin-bottom: 5px; }
    .hd-filters input, .hd-filters select { width: 100%; padding: 0.55rem 0.7rem; }

    .hd-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(165px, 1fr)); gap: 0.9rem; }
    .hd-card {
        border: 1px solid var(--border); border-left-width: 3px;
        border-radius: var(--radius); padding: 0.95rem 1rem; background: var(--bg-card);
        box-shadow: var(--shadow-xs);
        transition: box-shadow var(--base) var(--ease), border-color var(--base) var(--ease);
    }
    .hd-card:hover { box-shadow: var(--shadow-sm); }
    .hd-card span { display: block; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.06em;
                    text-transform: uppercase; color: var(--text-soft); margin-bottom: 6px; }
    .hd-card strong { font-size: 1.6rem; font-weight: 700; color: var(--text-main); line-height: 1.1;
                      letter-spacing: -0.02em; font-variant-numeric: tabular-nums; }
    .hd-card small { display: block; font-size: 0.73rem; color: var(--text-muted); margin-top: 6px; }

    .hd-split { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem; }
    /* A group inside a card, so it is set off by its fill rather than by a
       second border drawn around a bordered thing. */
    .hd-box { background: var(--bg-subtle); border-radius: var(--radius); padding: 0.95rem 1rem; }
    .hd-box h4 { margin: 0 0 0.2rem; font-size: 0.9rem; font-weight: 700; color: var(--text-main); }
    .hd-box .hd-sub { font-size: 0.74rem; color: var(--text-muted); margin-bottom: 0.9rem; }
    .hd-bars { display: flex; height: 8px; border-radius: var(--radius-pill); overflow: hidden; background: var(--bg-card); }
    .hd-bars i { display: block; height: 100%; }
    .hd-legend { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; font-size: 0.76rem; color: var(--text-muted); }
    .hd-legend span { display: inline-flex; align-items: center; gap: 5px; }
    .hd-legend b { color: var(--text-main); }
    .hd-dot { width: 9px; height: 9px; border-radius: 3px; display: inline-block; }

    .hd-leave { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; }
    .hd-leave-card { border: 1px solid var(--border); border-left-width: 3px; border-radius: var(--radius); padding: 0.8rem 0.95rem; background: var(--bg-card); box-shadow: var(--shadow-xs); }
    .hd-leave-card b { display: block; font-size: 0.86rem; color: var(--text-main); }
    .hd-leave-card strong { font-size: 1.35rem; font-weight: 700; color: var(--text-main); }
    .hd-leave-card small { display: block; font-size: 0.73rem; color: var(--text-muted); }

    .hd-alert { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 0.6rem 0; border-bottom: 1px solid var(--border); font-size: 0.86rem; }
    .hd-alert:last-child { border-bottom: none; }
    .hd-alert b { color: var(--text-main); }
    .hd-pill { display: inline-block; padding: 0.2rem 0.6rem; border-radius: var(--radius-pill); font-size: 0.73rem; font-weight: 700; white-space: nowrap; }

    .hd-decide { display: flex; gap: 6px; flex-wrap: wrap; }
    .hd-decide button {
        border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main);
        border-radius: var(--radius-xs); padding: 0.3rem 0.65rem; font-size: 0.76rem;
        cursor: pointer; font-family: inherit; font-weight: 600;
        transition: background-color var(--fast) var(--ease), border-color var(--fast) var(--ease);
    }
    .hd-decide button:hover { background: var(--bg-subtle); border-color: var(--border-strong); }
    .hd-decide button.ok { color: var(--success); }
    .hd-decide button.no { color: var(--danger); }

    .hd-status { display: inline-block; padding: 0.2rem 0.6rem; border-radius: var(--radius-pill); font-size: 0.73rem; font-weight: 700; white-space: nowrap; }
</style>

<div style="display: flex; flex-direction: column; gap: 1.5rem;">

    {{-- ---- filters ---- --}}
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-filter"></i> {{ \Carbon\Carbon::parse($date)->format('l, d M Y') }}</div>
            <span class="section-sub">who is in, who is not, and what is waiting on a decision</span>
        </div>

        <form method="GET" action="{{ route('admin.hr.dashboard') }}" class="hd-filters">
            <div>
                <label for="hdDate">Day</label>
                <input type="date" id="hdDate" name="date" value="{{ $date }}" onchange="this.form.submit()">
            </div>
            <div>
                <label for="hdDept">Department</label>
                <select id="hdDept" name="department_id" onchange="this.form.submit()">
                    <option value="">All departments</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}" {{ (string) $departmentId === (string) $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="hdShift">Shift</label>
                <select id="hdShift" name="shift_id" onchange="this.form.submit()">
                    <option value="">All shifts</option>
                    @foreach($shifts as $shift)
                        <option value="{{ $shift->id }}" {{ (string) $shiftId === (string) $shift->id ? 'selected' : '' }}>{{ $shift->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="hdUser">Employee</label>
                <select id="hdUser" name="user_id" onchange="this.form.submit()">
                    <option value="">Everyone</option>
                    @foreach($staff as $person)
                        <option value="{{ $person->id }}" {{ (string) $employeeId === (string) $person->id ? 'selected' : '' }}>{{ $person->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="hdFrom">Leave period from</label>
                <input type="date" id="hdFrom" name="from" value="{{ $from }}">
            </div>
            <div>
                <label for="hdTo">to</label>
                <input type="date" id="hdTo" name="to" value="{{ $to }}">
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary" >
                    <i class="fas fa-filter"></i> Apply
                </button>
                <a href="{{ route('admin.hr.dashboard') }}" class="btn btn-ghost"
                   >Clear</a>
            </div>
        </form>
    </div>

    {{-- ---- today ---- --}}
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header"><div class="card-title"><i class="fas fa-user-check" style="color: var(--primary);"></i> Today</div></div>

        <div class="hd-cards">
            <div class="hd-card" style="border-left-color: var(--success);">
                <span>Present</span>
                <strong style="color: var(--success);">{{ $counts['present'] }}</strong>
                <small>of {{ $today->count() }} on the roster</small>
            </div>
            <div class="hd-card" style="border-left-color: var(--warning);">
                <span>Late</span>
                <strong style="color: var(--warning);">{{ $counts['late_total'] }}</strong>
                <small>{{ $counts['late_early_off'] }} of them also left early</small>
            </div>
            <div class="hd-card" style="border-left-color: var(--danger);">
                <span>Absent</span>
                <strong style="color: var(--danger);">{{ $counts['absent'] }}</strong>
                <small>no check-in, no leave</small>
            </div>
            <div class="hd-card" style="border-left-color: var(--info);">
                <span>On leave</span>
                <strong style="color: var(--info);">{{ $counts['on_leave'] }}</strong>
                <small>approved leave today</small>
            </div>
            <div class="hd-card" style="border-left-color: var(--warning);">
                <span>Early off</span>
                <strong style="color: var(--warning);">{{ $counts['early_total'] }}</strong>
                <small>left before the shift ended</small>
            </div>
            <div class="hd-card" style="border-left-color: var(--secondary);">
                <span>Off today</span>
                <strong>{{ $counts['weekend'] + $counts['holiday'] }}</strong>
                <small>weekly off or holiday</small>
            </div>
        </div>
    </div>

    {{-- ---- by department and by shift ---- --}}
    <div class="hd-split">
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header"><div class="card-title"><i class="fas fa-sitemap" style="color: var(--primary);"></i> By department</div></div>
            @forelse($byDepartment as $name => $figures)
                @php $total = max(1, $figures['headcount']); @endphp
                <div class="hd-box" style="margin-bottom: 0.9rem;">
                    <h4>{{ $name }}</h4>
                    <div class="hd-sub">{{ $figures['headcount'] }} {{ \Illuminate\Support\Str::plural('person', $figures['headcount']) }}</div>
                    <div class="hd-bars">
                        @foreach(['present' => 'var(--success)', 'late_total' => 'var(--warning)', 'absent' => 'var(--danger)', 'on_leave' => 'var(--info)'] as $key => $colour)
                            @if($figures[$key] > 0)
                                <i style="width: {{ round($figures[$key] / $total * 100) }}%; background: {{ $colour }};"></i>
                            @endif
                        @endforeach
                    </div>
                    <div class="hd-legend">
                        <span><i class="hd-dot" style="background: var(--success);"></i> <b>{{ $figures['present'] }}</b> present</span>
                        <span><i class="hd-dot" style="background: var(--warning);"></i> <b>{{ $figures['late_total'] }}</b> late</span>
                        <span><i class="hd-dot" style="background: var(--danger);"></i> <b>{{ $figures['absent'] }}</b> absent</span>
                        <span><i class="hd-dot" style="background: var(--info);"></i> <b>{{ $figures['on_leave'] }}</b> on leave</span>
                    </div>
                </div>
            @empty
                <p style="color: var(--text-muted); font-size: 0.86rem;">Nobody is in a department yet.</p>
            @endforelse
        </div>

        <div class="card" style="margin-bottom: 0;">
            <div class="card-header"><div class="card-title"><i class="fas fa-clock" style="color: var(--primary);"></i> By shift</div></div>
            @foreach($byShift as $name => $figures)
                @php $total = max(1, $figures['headcount']); @endphp
                <div class="hd-box" style="margin-bottom: 0.9rem;">
                    <h4>{{ $name }}</h4>
                    <div class="hd-sub">{{ $figures['times'] }} &middot; {{ $figures['headcount'] }} {{ \Illuminate\Support\Str::plural('person', $figures['headcount']) }}</div>
                    <div class="hd-bars">
                        @foreach(['present' => 'var(--success)', 'late_total' => 'var(--warning)', 'absent' => 'var(--danger)', 'on_leave' => 'var(--info)'] as $key => $colour)
                            @if($figures[$key] > 0)
                                <i style="width: {{ round($figures[$key] / $total * 100) }}%; background: {{ $colour }};"></i>
                            @endif
                        @endforeach
                    </div>
                    <div class="hd-legend">
                        <span><i class="hd-dot" style="background: var(--success);"></i> <b>{{ $figures['present'] }}</b> present</span>
                        <span><i class="hd-dot" style="background: var(--warning);"></i> <b>{{ $figures['late_total'] }}</b> late</span>
                        <span><i class="hd-dot" style="background: var(--danger);"></i> <b>{{ $figures['absent'] }}</b> absent</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ---- alerts ---- --}}
    @if($nearlyLate->count() || $absentees->count())
    <div class="hd-split">
        @if($nearlyLate->count())
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-triangle-exclamation" style="color: var(--warning);"></i> One more late costs a day</div>
            </div>
            @foreach($nearlyLate as $alert)
                <div class="hd-alert">
                    <b>{{ $alert['user']->name }}</b>
                    <span class="hd-pill" style="background: rgba(245,158,11,0.16); color: var(--warning);">
                        {{ $alert['lates'] }} lates this month
                    </span>
                </div>
            @endforeach
            <p style="font-size: 0.76rem; color: var(--text-muted); margin: 0.8rem 0 0;">
                Every {{ $rules->lates_per_off }} lates in a month cost one day off.
            </p>
        </div>
        @endif

        @if($absentees->count())
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-user-xmark" style="color: var(--danger);"></i> Most absences this month</div>
            </div>
            @foreach($absentees as $alert)
                <div class="hd-alert">
                    <b>{{ $alert['user']->name }}</b>
                    <span class="hd-pill" style="background: rgba(239,68,68,0.14); color: var(--danger);">
                        {{ $alert['days'] }} {{ \Illuminate\Support\Str::plural('day', $alert['days']) }}
                    </span>
                </div>
            @endforeach
        </div>
        @endif
    </div>
    @endif

    {{-- ---- waiting on a decision ---- --}}
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-hourglass-half" style="color: var(--primary);"></i> Waiting on a decision
                @if($pending->count())
                    <span class="hd-pill" style="background: rgba(245,158,11,0.16); color: var(--warning); margin-left: 8px;">{{ $pending->count() }}</span>
                @endif
            </div>
        </div>

        @if($pending->count())
            <div style="overflow-x: auto;">
                <table>
                    <thead><tr><th>Employee</th><th>Type</th><th>Dates</th><th>Days</th><th>Reason</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @foreach($pending as $leave)
                        <tr>
                            <td style="font-weight: 600; color: var(--text-main);">
                                <a href="{{ route('admin.hr.employee', $leave->user_id) }}" style="color: inherit; text-decoration: none;">{{ $leave->user->name ?? '—' }}</a>
                            </td>
                            <td>{{ $leave->type->name ?? '—' }}</td>
                            <td>{{ $leave->start_date->format('d M') }} – {{ $leave->end_date->format('d M Y') }}</td>
                            <td>
                                @if($leave->hours)
                                    {{ rtrim(rtrim(number_format($leave->hours, 1), '0'), '.') }}h
                                @else
                                    {{ rtrim(rtrim(number_format($leave->days, 1), '0'), '.') }}
                                @endif
                            </td>
                            <td style="max-width: 240px; white-space: normal;">{{ $leave->reason ?: '—' }}</td>
                            <td>
                                <span class="hd-status" style="background: {{ $leave->statusColour() }}1f; color: {{ $leave->statusColour() }};">{{ $leave->statusLabel() }}</span>
                            </td>
                            <td>
                                <div class="hd-decide">
                                    <form method="POST" action="{{ route('admin.hr.leaves.decide', $leave->id) }}">
                                        @csrf
                                        <input type="hidden" name="status" value="approved">
                                        <button type="submit" class="ok"><i class="fas fa-check"></i> Approve</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.hr.leaves.decide', $leave->id) }}"
                                          onsubmit="this.decision_note.value = prompt('Reason for rejecting (optional)') || '';">
                                        @csrf
                                        <input type="hidden" name="status" value="rejected">
                                        <input type="hidden" name="decision_note" value="">
                                        <button type="submit" class="no"><i class="fas fa-xmark"></i> Reject</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0;">Nothing is waiting — every request has been decided.</p>
        @endif
    </div>

    {{-- ---- leave in the period ---- --}}
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-plane-departure" style="color: var(--primary);"></i>
                Leave taken, {{ \Carbon\Carbon::parse($from)->format('d M') }} – {{ \Carbon\Carbon::parse($to)->format('d M Y') }}
            </div>
        </div>
        <div class="hd-leave">
            @foreach($leaveCards as $card)
                <div class="hd-leave-card" style="border-left-color: {{ $card['type']->colour }};">
                    <b>{{ $card['type']->name }}</b>
                    <strong>{{ rtrim(rtrim(number_format($card['days'], 1), '0'), '.') }}</strong>
                    <small>
                        {{ \Illuminate\Support\Str::plural('day', $card['days']) }} &middot;
                        {{ $card['people'] }} {{ \Illuminate\Support\Str::plural('person', $card['people']) }}
                    </small>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ---- the roster itself ---- --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title">Everyone on {{ \Carbon\Carbon::parse($date)->format('d M Y') }}</div>
        </div>
        <div style="overflow-x: auto;">
            <table>
                <thead><tr><th>Employee</th><th>Department</th><th>Shift</th><th>In</th><th>Out</th><th>Worked</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse($today as $entry)
                    @php $colour = $statusColours[$entry['status']] ?? 'var(--secondary)'; @endphp
                    <tr>
                        <td style="font-weight: 600; color: var(--text-main);">{{ $entry['user']->name }}</td>
                        <td>{{ $entry['department'] }}</td>
                        <td>{{ $entry['shift']->name ?? '—' }}</td>
                        <td>{{ $entry['row']?->clock_in?->format('h:i A') ?: '—' }}</td>
                        <td>{{ $entry['row']?->clock_out?->format('h:i A') ?: '—' }}</td>
                        <td>{{ $entry['row'] ? \App\Services\PayrollService::hoursText((int) $entry['row']->worked_minutes) : '—' }}</td>
                        <td>
                            <span class="hd-status" style="background: {{ $colour }}1f; color: {{ $colour }};">
                                {{ \App\Models\Attendance::DAY_STATUS_LABELS[$entry['status']] ?? '—' }}
                            </span>
                            @if(($entry['row']?->late_minutes ?? 0) > 0)
                                <div style="font-size: 0.72rem; color: var(--danger); margin-top: 3px;">{{ $entry['row']->late_minutes }}m late</div>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.hr.employee', ['user' => $entry['user']->id, 'month' => \Carbon\Carbon::parse($date)->format('Y-m')]) }}"
                               class="btn btn-ghost" style="border: 1px solid var(--border); padding: 0.28rem 0.7rem; font-size: 0.75rem; border-radius: 8px; text-decoration: none; white-space: nowrap;">
                                Open file
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><div class="empty-state"><p>Nobody matches those filters.</p></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
