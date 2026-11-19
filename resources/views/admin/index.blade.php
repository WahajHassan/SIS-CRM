@extends('layouts.admin')

@section('title', 'Overview')

@section('content')

@php
    $me = auth()->user();
    $announcements = \App\Models\Announcement::board();
    $canPost = \App\Models\Announcement::canManage($me);

    // The newest training, for the strip across the top. The owner account is
    // not somebody the floor trains, so it is left off theirs.
    $trainingStrip = $me->isSuperAdmin()
        ? collect()
        : \App\Models\Training::for($me)->with('author')->newestFirst()->limit(8)->get();
@endphp

{{-- The first line of the day: who they are, what day it is, and when the next
     prayer is. --}}
@include('admin.partials.welcome')

{{-- Training the team is expected to watch, above everything else — where they
     already look, rather than behind a menu. --}}
@if(!$me->isSuperAdmin() && $me->canAccess('training'))
    @include('admin.partials.training_strip')
@endif

{{-- What an agent is working towards, at the very top of their own Overview.
     Closers are paid differently, so this is for agents only. --}}
@if(auth()->user()->role === 'agent')
    @include('admin.partials.agent_incentives')
@endif



@if(auth()->user()->isSuperAdmin())
<!-- Super Admin Dashboard -->
<div style="margin-bottom: 2rem;">
    <div class="section-head">
        <h2><i class="fas fa-globe"></i> Global System Overview</h2>
        <span class="section-sub">Across every company on the system</span>
    </div>
    {{-- Reorderable metric cards. data-sort-group names the row so a card
         dragged into the other row comes back to the right one on reload; the
         cards themselves are unchanged. --}}
    <div class="stat-card-grid" data-sort-group="global" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
        <div class="card kpi" style="margin-bottom: 0;">
            <div class="kpi-head">
                <div class="kpi-label">Total Companies</div>
                <span class="kpi-icon is-primary"><i class="fas fa-building"></i></span>
            </div>
            <div class="kpi-value" data-count="{{ (int) $super_stats->total_companies }}">{{ $super_stats->total_companies }}</div>
            <div class="kpi-foot"><span class="kpi-trend is-up">{{ $super_stats->active_companies }} active</span></div>
        </div>
        <div class="card kpi" style="margin-bottom: 0;">
            <div class="kpi-head">
                <div class="kpi-label">Total System Users</div>
                <span class="kpi-icon is-violet"><i class="fas fa-users"></i></span>
            </div>
            <div class="kpi-value" data-count="{{ (int) $super_stats->total_users }}">{{ $super_stats->total_users }}</div>
            <div class="kpi-foot"><span>signed up across the system</span></div>
        </div>
        <div class="card kpi" style="margin-bottom: 0;">
            <div class="kpi-head">
                <div class="kpi-label">Global Leads</div>
                <span class="kpi-icon is-info"><i class="fas fa-user-plus"></i></span>
            </div>
            <div class="kpi-value" data-count="{{ (int) $super_stats->total_leads }}">{{ $super_stats->total_leads }}</div>
            <div class="kpi-foot"><span>on every calling list</span></div>
        </div>
    </div>

    {{-- Six figures, once each. Total Revenue was on both of these rows, the
         same number twice — once bare and once with its trend. The one with the
         trend is the one worth keeping. --}}
<!-- Summary Stats -->
<div class="stat-card-grid" data-sort-group="summary" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
    <div class="card kpi" style="margin-bottom: 0;">
        <div class="kpi-head">
            <div class="kpi-label">Total Revenue</div>
            <span class="kpi-icon is-success"><i class="fas fa-dollar-sign"></i></span>
        </div>
        <div class="kpi-value" data-count="{{ (int) $stats->total_revenue }}" data-prefix="$">${{ number_format($stats->total_revenue) }}</div>
        <div class="kpi-foot">
            <span class="kpi-trend {{ str_contains($stats->revenue_trend, '+') ? 'is-up' : 'is-down' }}">
                <i class="fas {{ str_contains($stats->revenue_trend, '+') ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i>
                {{ $stats->revenue_trend }}
            </span>
            <span>vs last month</span>
        </div>
    </div>

    <div class="card kpi" style="margin-bottom: 0;">
        <div class="kpi-head">
            <div class="kpi-label">Deals Closed</div>
            <span class="kpi-icon is-primary"><i class="fas fa-handshake"></i></span>
        </div>
        <div class="kpi-value" data-count="{{ (int) $stats->deals_closed }}">{{ $stats->deals_closed }}</div>
        <div class="kpi-foot">
            <span class="kpi-trend {{ str_contains($stats->deals_trend, '+') ? 'is-up' : 'is-down' }}">
                <i class="fas {{ str_contains($stats->deals_trend, '+') ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i>
                {{ $stats->deals_trend }}
            </span>
            <span>vs last month</span>
        </div>
    </div>

    <div class="card kpi" style="margin-bottom: 0;">
        <div class="kpi-head">
            <div class="kpi-label">Avg Deal Size</div>
            <span class="kpi-icon is-info"><i class="fas fa-chart-line"></i></span>
        </div>
        <div class="kpi-value" data-count="{{ (int) $stats->avg_deal_size }}" data-prefix="$">${{ number_format($stats->avg_deal_size) }}</div>
        <div class="kpi-foot">
            <span class="kpi-trend {{ str_contains($stats->avg_deal_trend, '+') ? 'is-up' : 'is-down' }}">
                <i class="fas {{ str_contains($stats->avg_deal_trend, '+') ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i>
                {{ $stats->avg_deal_trend }}
            </span>
            <span>vs last month</span>
        </div>
    </div>

    <div class="card kpi" style="margin-bottom: 0;">
        <div class="kpi-head">
            <div class="kpi-label">Conversion Rate</div>
            <span class="kpi-icon is-warning"><i class="fas fa-bullseye"></i></span>
        </div>
        <div class="kpi-value" data-count="{{ (int) round($stats->conversion_rate) }}" data-suffix="%">{{ round($stats->conversion_rate, 1) }}%</div>
        <div class="kpi-foot">
            <span class="kpi-trend {{ $stats->conversion_rate >= 45 ? 'is-up' : '' }}">Goal 45%</span>
            <span>ideal</span>
        </div>
    </div>
</div>

</div>
@endif


@php
    $stats_row = \App\Models\ScoreboardStat::firstOrCreate([], ['monthly_target' => 250000]);
    $attendance = auth()->user()->todayAttendance();

    // Everybody on the staff works a shift, so everybody clocks in — this used to
    // be for agents and closers only, which left HR and the tech team with no way
    // to record their own day.
    $showTracker = !$me->isSuperAdmin();
    $myShift = $showTracker ? $me->shiftOn(now()) : null;
    $lateService = $showTracker ? new \App\Services\LateOffService() : null;
    $myLates = $lateService ? $lateService->lateCount($me, now()) : 0;
    $latesPerOff = (int) \App\Models\HrSetting::current()->lates_per_off;
@endphp

{{-- The tracker and the board side by side: the clock is what somebody comes
     here to press, the board is what they read while they are here. The owner
     account does not clock in, so for them the board simply takes the row. --}}
@if($showTracker)
<div class="dash-row">
<!-- Time Tracker Card -->
<style>
    /* The controls take what they need; the readings take the rest. */
    #timeTrackerCard { display: flex; flex-direction: column; }
    .tt-body {
        display: grid; grid-template-columns: max-content minmax(0, 1fr);
        gap: 1.75rem; align-items: center; align-content: center;
        /* The row is as tall as the announcement board beside it, so the card's
           contents sit in the middle of it rather than clinging to the top. */
        flex: 1;
    }
    @media (max-width: 900px) { .tt-body { grid-template-columns: 1fr; gap: 1.25rem; } }
</style>
<div class="card dash-main" id="timeTrackerCard" style="margin-bottom: 0;">
    <div class="card-header" style="flex-wrap: wrap;">
        <div class="card-title">
            <i class="fas fa-clock"></i> Attendance &amp; time tracker
            @if($myShift)
                <span style="font-size: 0.78rem; font-weight: 600; color: var(--text-muted); margin-left: 10px;">
                    {{ $myShift->name }} &middot; {{ $myShift->timesText() }}
                    @if($myShift->crosses_midnight)
                        <span title="This shift runs past midnight; the whole night counts as the day it started">(overnight)</span>
                    @endif
                </span>
            @endif
        </div>
        @if($latesPerOff > 0)
            @php $remaining = $latesPerOff - ($myLates % $latesPerOff); @endphp
            <div style="font-size: 0.8rem; font-weight: 600; color: {{ $myLates % $latesPerOff === $latesPerOff - 1 ? 'var(--danger)' : 'var(--text-muted)' }};"
                 title="Every {{ $latesPerOff }} lates in a month cost one day off">
                Lates this month: {{ $myLates }}/{{ $latesPerOff }}
                @if($myLates > 0)
                    <span style="font-weight: 500;">&middot; {{ $remaining }} more {{ \Illuminate\Support\Str::plural('late', $remaining) }} costs a day</span>
                @endif
            </div>
        @endif
        <div>
            @if($attendance)
                @if($attendance->status === 'clocked_in')
                    <span class="badge badge-success">● Clocked in</span>
                @elseif($attendance->status === 'on_break')
                    <span class="badge badge-info">● On break</span>
                @elseif($attendance->status === 'clocked_out')
                    <span class="badge">● Clocked out</span>
                @endif
            @else
                <span class="badge badge-danger">● Not working</span>
            @endif
        </div>
    </div>
    
    <div class="tt-body">
        <!-- Controls -->
        <div>
            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                @if(!$attendance)
                    <!-- Not Checked In yet -->
                    <form action="{{ route('admin.attendance.clock-in') }}" method="POST" style="margin: 0;" data-wants-fingerprint>
                        @csrf
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-sign-in-alt"></i> Clock In
                        </button>
                    </form>
                @elseif($attendance->status === 'clocked_in')
                    <!-- Clocked In & Working -->
                    @php
                        $nextBreakType = $attendance->getNextBreakType();
                        $breakRequest = \App\Models\BreakRequest::where('user_id', auth()->id())->whereDate('created_at', \Carbon\Carbon::today())->latest()->first();
                    @endphp
                    @if($nextBreakType === 'short_1_or_half_1' || $nextBreakType === 'short_2_or_half_2' || $nextBreakType === 'short_3')
                        @php
                            $currentShortType = '';
                            $currentShortLabel = '';
                            if ($nextBreakType === 'short_1_or_half_1') { $currentShortType = 'short_1'; $currentShortLabel = '1st Short Break (5m)'; }
                            elseif ($nextBreakType === 'short_2_or_half_2') { $currentShortType = 'short_2'; $currentShortLabel = '2nd Short Break (5m)'; }
                            elseif ($nextBreakType === 'short_3') { $currentShortType = 'short_3'; $currentShortLabel = '3rd Temporary Break (5m)'; }
                        @endphp
                        
                        @if(!$breakRequest || $breakRequest->type !== $currentShortType)
                            <button type="button" onclick="requestShortBreak('{{ $currentShortType }}', this)" class="btn" style="padding: 0.8rem 1.5rem; border-radius: 12px; font-weight: 700; gap: 10px; font-size: 0.9rem; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; border: none; cursor: pointer;">
                                <i class="fas fa-hand-paper"></i> Request {{ $currentShortLabel }}
                            </button>
                        @elseif($breakRequest->status === 'pending')
                            <button type="button" class="btn" style="padding: 0.8rem 1.5rem; border-radius: 12px; font-weight: 700; gap: 10px; font-size: 0.9rem; background: #64748b; color: white; border: none; cursor: not-allowed;" disabled>
                                <i class="fas fa-spinner fa-spin"></i> Awaiting Approval...
                            </button>
                            
                            <script>
                                (function() {
                                    function checkAppealStatus() {
                                        fetch('{{ route("admin.attendance.appeal-status") }}?t=' + Date.now())
                                            .then(res => res.json())
                                            .then(data => {
                                                if (data.status === 'approved') {
                                                    const form = document.createElement('form');
                                                    form.method = 'POST';
                                                    form.action = '{{ route("admin.attendance.start-break") }}';
                                                    
                                                    const csrfInput = document.createElement('input');
                                                    csrfInput.type = 'hidden';
                                                    csrfInput.name = '_token';
                                                    csrfInput.value = '{{ csrf_token() }}';
                                                    form.appendChild(csrfInput);
                                                    
                                                    const typeInput = document.createElement('input');
                                                    typeInput.type = 'hidden';
                                                    typeInput.name = 'type';
                                                    typeInput.value = '{{ $currentShortType }}';
                                                    form.appendChild(typeInput);
                                                    
                                                    document.body.appendChild(form);
                                                    form.submit();
                                                } else if (data.status === 'declined') {
                                                    location.reload();
                                                }
                                            })
                                            .catch(err => console.error(err));
                                    }
                                    setInterval(checkAppealStatus, 3000);
                                })();
                            </script>
                        @elseif($breakRequest->status === 'approved')
                            <form action="{{ route('admin.attendance.start-break') }}" method="POST" style="margin: 0; display: inline-block;">
                                @csrf
                                <input type="hidden" name="type" value="{{ $currentShortType }}">
                                <button type="submit" class="btn" style="padding: 0.8rem 1.5rem; border-radius: 12px; font-weight: 700; gap: 10px; font-size: 0.9rem; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none; cursor: pointer;">
                                    <i class="fas fa-coffee"></i> Start {{ $currentShortLabel }}
                                </button>
                            </form>
                        @elseif($breakRequest->status === 'declined')
                            <div style="position: relative;">
                                <span style="position: absolute; bottom: 100%; left: 0; margin-bottom: 8px; font-size: 0.85rem; color: var(--danger); font-weight: 600; white-space: nowrap;">Request Declined by {{ $breakRequest->processor ? ucfirst($breakRequest->processor->role) : 'Admin/FM' }}.</span>
                                <button type="button" onclick="requestShortBreak('{{ $currentShortType }}', this)" class="btn" style="padding: 0.8rem 1.5rem; border-radius: 12px; font-weight: 700; gap: 10px; font-size: 0.9rem; background: linear-gradient(135deg, #ec4899 0%, #db2777 100%); color: white; border: none; cursor: pointer;">
                                    <i class="fas fa-hand-paper"></i> Request {{ $currentShortLabel }} Again
                                </button>
                            </div>
                        @endif
                    @elseif($nextBreakType === 'half_1' || $nextBreakType === 'half_2')
                        <div style="font-size: 0.95rem; color: var(--text-muted); font-weight: 600;">
                            <i class="fas fa-info-circle" style="color: var(--primary);"></i> Waiting for Admin to initiate Main Break...
                        </div>
                    @else
                        <span style="font-weight: 600; color: var(--text-muted); font-size: 0.95rem;">All breaks completed.</span>
                    @endif
                    
                    <form id="clockOutForm" action="{{ route('admin.attendance.clock-out') }}" method="POST" style="margin: 0;">
                        @csrf
                        <button type="button" onclick="showClockOutConfirm(false, 'clockOutForm')" class="btn btn-danger">
                            <i class="fas fa-sign-out-alt"></i> Clock Out
                        </button>
                    </form>
                @elseif($attendance->status === 'on_break')
                    <!-- Currently on Break -->
                    @php
                        $activeBreak = $attendance->breaks()->whereNull('break_end')->first();
                    @endphp
                    @if($activeBreak && in_array($activeBreak->type, ['half_1', 'half_2']))
                        <div style="font-size: 0.95rem; color: var(--warning); font-weight: 600;">
                            <i class="fas fa-lock"></i> Waiting for Admin to Resume Session
                        </div>
                    @else
                        <form action="{{ route('admin.attendance.end-break') }}" method="POST" style="margin: 0;">
                            @csrf
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-briefcase"></i> End Break & Resume
                            </button>
                        </form>
                    @endif
                    
                    <form id="clockOutFormBreak" action="{{ route('admin.attendance.clock-out') }}" method="POST" style="margin: 0;">
                        @csrf
                        <button type="button" onclick="showClockOutConfirm(true, 'clockOutFormBreak')" class="btn btn-danger">
                            <i class="fas fa-sign-out-alt"></i> Clock Out
                        </button>
                    </form>
                @elseif($attendance->status === 'clocked_out')
                    <!-- Finished day -->
                    <div style="font-weight: 600; color: var(--text-muted); font-size: 0.95rem;">
                        🎉 You have clocked out for the day. See you tomorrow!
                    </div>
                @endif
            </div>
        </div>
        
        <!-- Summary Stats -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div style="text-align: center; padding: 1rem; background: rgba(16, 185, 129, 0.05); border-radius: 12px;">
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px;">Clock In</div>
                <div style="font-weight: 700; font-size: 1rem;">
                    {{ $attendance ? $attendance->clock_in->format('h:i A') : '--:--' }}
                </div>
            </div>
            <div style="text-align: center; padding: 1rem; background: rgba(239, 68, 68, 0.05); border-radius: 12px;">
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px;">Clock Out</div>
                <div style="font-weight: 700; font-size: 1rem;">
                    {{ $attendance && $attendance->clock_out ? $attendance->clock_out->format('h:i A') : '--:--' }}
                </div>
            </div>
            <div style="text-align: center; padding: 1rem; background: rgba(59, 130, 246, 0.05); border-radius: 12px;">
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px;">Breaks</div>
                <div style="font-weight: 700; font-size: 1rem;">
                    {{ $attendance ? $attendance->breaks()->count() : 0 }}
                </div>
            </div>
            <div style="text-align: center; padding: 1rem; background: rgba(249, 157, 78, 0.05); border-radius: 12px;">
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px;">Status</div>
                <div style="font-weight: 700; font-size: 1rem;">
                    @if($attendance)
                        {{ ucfirst(str_replace('_', ' ', $attendance->status)) }}
                    @else
                        Not Started
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
{{-- end of the tracker card --}}

@include('admin.partials.announcement_board')
</div>
@endif
{{-- end of the top row --}}

{{-- Breaks are run by whoever is on the floor. The owner's dashboard is about
     the system, not about who is on lunch, so this stays with the admins. --}}
@if(auth()->user()->role === 'admin')
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Global Main Breaks Control -->
    <div class="card" style="margin-bottom: 0; background: var(--bg-card); border-radius: 20px; border: 1px solid var(--border); padding: 1.2rem;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 0.8rem; margin-bottom: 1rem;">
            <div class="card-title" style="display: flex; align-items: center; gap: 8px; font-size: 1rem; font-weight: 700; color: var(--text-main);">
                <i class="fas fa-utensils" style="color: var(--primary); font-size: 1.1rem;"></i> Global Main Breaks Control
            </div>
        </div>
        
        <div id="global-breaks-container">
            @include('admin.partials.global_breaks_control')
        </div>
        
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                setInterval(function() {
                    fetch('{{ route("admin.attendance.global-breaks-control") }}')
                        .then(response => response.text())
                        .then(html => {
                            document.getElementById('global-breaks-container').innerHTML = html;
                        })
                        .catch(error => console.error('Error fetching global breaks control:', error));
                }, 5000); // 5 seconds
            });
        </script>
    </div>

    <!-- Scoreboard Settings -->
    <div class="card" style="margin-bottom: 0; padding: 1.2rem;">
        <div class="card-header" style="padding-bottom: 0.8rem; margin-bottom: 1rem;">
            <div class="card-title" style="font-size: 1rem;"><i class="fas fa-tv" style="color: var(--primary);"></i> Scoreboard Display Settings</div>
        </div>
        <form action="{{ route('admin.stats.update') }}" method="POST">
            @csrf
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div>
                    <label style="display: block; font-size: 0.8rem; margin-bottom: 5px; font-weight: 600;">Monthly Target ($)</label>
                    <input type="number" name="monthly_target" value="{{ $stats_row->monthly_target }}" 
                           style="width: 100%; padding: 0.6rem; border-radius: 10px; border: 1px solid var(--border); font-size: 0.9rem;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.8rem; margin-bottom: 5px; font-weight: 600;">Achieved Prospects</label>
                    <input type="number" name="achieved_prospects" value="{{ $stats_row->achieved_prospects ?? 0 }}" 
                           style="width: 100%; padding: 0.6rem; border-radius: 10px; border: 1px solid var(--border); font-size: 0.9rem;">
                </div>
                <div style="grid-column: 1 / -1;">
                    <label style="display: block; font-size: 0.8rem; margin-bottom: 5px; font-weight: 600;">Live Update Text</label>
                    <input type="text" name="live_update" value="{{ $stats_row->live_update ?? '' }}" placeholder="E.g., New deal closed in NY!" 
                           style="width: 100%; padding: 0.6rem; border-radius: 10px; border: 1px solid var(--border); font-size: 0.9rem;">
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top: 1rem; padding: 0.6rem 1rem; font-size: 0.85rem; width: 100%; justify-content: center;">Update Scoreboard</button>
        </form>
    </div>
</div>
@endif

{{-- What has just happened and who did it: the two things somebody opens
     this page to find out. The charts and the board read around them. --}}
<div style="display: grid; grid-template-columns: {{ auth()->user()->isAgentOrCloser() ? '1fr' : '2fr 1fr' }}; gap: 1.5rem;">
    <!-- Recent Deals -->
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 1rem;">
            <div class="card-title" style="font-size: 1.05rem;">Recently Created Deals</div>
            <a href="{{ route('admin.deals') }}" class="btn-primary" style="padding: 5px 12px; font-size: 0.75rem; border-radius: 8px; text-decoration: none;">View All</a>
        </div>
        <div class="table-container">
            <table style="width: 100%; border-collapse: separate; border-spacing: 0 5px;">
                <thead>
                    <tr>
                        <th style="padding: 0 0.8rem 0.4rem; color: var(--text-muted); font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: none;">Deal Info</th>
                        <th style="padding: 0 0.8rem 0.4rem; color: var(--text-muted); font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: none;">Value</th>
                        <th style="padding: 0 0.8rem 0.4rem; color: var(--text-muted); font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: none; text-align: right;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($deals as $deal)
                    <tr style="background: var(--bg-body); transition: transform 0.2s ease, box-shadow 0.2s ease; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);" onmouseover="this.style.transform='scale(1.01)'; this.style.boxShadow='0 3px 10px rgba(0,0,0,0.04)';" onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 1px 3px rgba(0,0,0,0.02)';">
                        <td style="padding: 0.6rem 0.8rem; border-radius: 10px 0 0 10px; border: 1px solid transparent; border-right: none; background: transparent;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 32px; height: 32px; background: rgba(139, 92, 246, 0.1); color: #8b5cf6; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; flex-shrink: 0;">
                                    <i class="fas fa-file-signature"></i>
                                </div>
                                <span style="font-weight: 600; color: var(--text-main); font-size: 0.9rem;">{{ $deal->name }}</span>
                            </div>
                        </td>
                        <td style="padding: 0.6rem 0.8rem; font-weight: 700; color: var(--text-main); font-size: 0.9rem; border-top: 1px solid transparent; border-bottom: 1px solid transparent; background: transparent;">
                            ${{ number_format($deal->value / 1000, 1) }}k
                        </td>
                        <td style="padding: 0.6rem 0.8rem; text-align: right; border-radius: 0 10px 10px 0; border: 1px solid transparent; border-left: none; background: transparent;">
                            <span class="status-badge {{ strtolower($deal->status) }}" style="padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 0.7rem; letter-spacing: 0.5px; display: inline-block;">
                                {{ $deal->status }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
@if($deals->isEmpty())
            <tr class="is-blank">
                <td colspan="3">
                    <div class="empty-state">
                        <i class="fas fa-file-signature"></i>
                        <h4>No deals yet</h4>
                        <p>Deals closed by the floor will appear here as they are won.</p>
                    </div>
                </td>
            </tr>
@endif

                </tbody>
            </table>
        </div>
    </div>

    @if(!auth()->user()->isAgentOrCloser())
    <!-- Top Performers -->
    <div class="card">
        <div class="card-header" style="padding-bottom: 0.8rem;">
            <div class="card-title" style="font-size: 1.05rem;"><i class="fas fa-trophy" style="color: #fbbf24; margin-right: 8px; font-size: 1.1rem;"></i> Sales Leaderboard</div>
        </div>
        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
            @foreach($performers->sortByDesc('revenue')->take(5) as $performer)
            <div style="display: flex; align-items: center; gap: 1rem; padding: 0.6rem 0.8rem; background: var(--bg-body); border-radius: 10px; transition: all 0.2s ease; border: 1px solid transparent;" onmouseover="this.style.borderColor='var(--primary)'; this.style.transform='translateY(-2px)';" onmouseout="this.style.borderColor='transparent'; this.style.transform='translateY(0)';">
                @if($loop->iteration === 1)
                <div style="width: 36px; height: 36px; background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 800; font-size: 1rem; box-shadow: 0 3px 8px rgba(245, 158, 11, 0.3); flex-shrink: 0;">
                    1
                </div>
                @elseif($loop->iteration === 2)
                <div style="width: 36px; height: 36px; background: linear-gradient(135deg, #94a3b8 0%, #cbd5e1 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 800; font-size: 1rem; box-shadow: 0 3px 8px rgba(148, 163, 184, 0.3); flex-shrink: 0;">
                    2
                </div>
                @elseif($loop->iteration === 3)
                <div style="width: 36px; height: 36px; background: linear-gradient(135deg, #b45309 0%, #d97706 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 800; font-size: 1rem; box-shadow: 0 3px 8px rgba(180, 83, 9, 0.3); flex-shrink: 0;">
                    3
                </div>
                @else
                <div style="width: 36px; height: 36px; background: rgba(139, 92, 246, 0.08); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #8b5cf6; font-weight: 700; font-size: 0.95rem; flex-shrink: 0;">
                    {{ $loop->iteration }}
                </div>
                @endif
                
                {{-- Beside the rank, so the top of the board is people rather
                     than three numbers and a list of names. --}}
                <x-face :user="$performer->user" :size="32" />

                <div style="flex: 1; min-width: 0;">
                    <div style="font-weight: 700; font-size: 0.95rem; color: var(--text-main);">
                        {{ $performer->display_name }}
                        @if($performer->user?->isVerified())
                            <i class="fas fa-circle-check person-tick" title="Verified" aria-label="Verified"></i>
                        @endif
                    </div>
                    <div style="font-size: 0.8rem; color: var(--text-muted); display: flex; align-items: center; gap: 4px; margin-top: 1px;">
                        <i class="fas fa-dollar-sign" style="color: var(--success); font-size: 0.7rem;"></i>
                        <span style="font-weight: 700; color: var(--text-main);">${{ number_format($performer->revenue) }}</span> Revenue
                    </div>
                </div>
                
                <div style="text-align: right; background: rgba(249, 157, 78, 0.1); padding: 0.3rem 0.6rem; border-radius: 8px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                    <div style="font-weight: 800; color: var(--primary); font-size: 1.05rem; line-height: 1;">{{ $performer->deals_won }}</div>
                    <div style="font-size: 0.65rem; color: var(--primary); font-weight: 700; text-transform: uppercase; margin-top: 2px;">Deals</div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>

{{-- Prayer times sit in the right-hand column, beside the trend — a thing to
     glance at rather than a thing to read. --}}
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 2rem; align-items: start;">
    <!-- Sales Growth Chart -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div class="card-title">Sales Growth Trends</div>
        </div>
        <div id="salesGrowthChart" style="min-height: 300px;"></div>
    </div>

    <!-- Conversion Doughnut -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header">
                <div class="card-title">Conversion Rate</div>
            </div>
            <div style="height: 300px; display: flex; align-items: center; justify-content: center; position: relative;">
                <div id="conversionChart" style="width: 100%;"></div>
            </div>
        </div>

        @include('admin.partials.prayer_times')
    </div>
</div>


@if(auth()->user()->isSuperAdmin())
    {{-- The chart and the board, read after the figures rather than before
         them. The owner does not clock in, so this is where their board
         lives: beside something, rather than alone across the page. --}}
    <div class="dash-row" style="margin-top: 2rem;">
        <div class="card dash-main" style="margin-bottom: 0;">
            <div class="card-header">
                <div class="card-title">Deals Closed per Company</div>
            </div>
            <div id="super_company_chart" style="height: 300px; width: 100%;"></div>
        </div>
        @include('admin.partials.announcement_board')
    </div>
@endif

@push('scripts')
<script src="@versioned('js/device-keys.js')"></script>
<script>
/*
 * The fingerprint, before the clock.
 *
 * The form posts as it always did; what is added is a pause in front of it.
 * The device is asked to confirm who is here, the server checks the signature,
 * and only then does the form go. The server checks again at the other end, so
 * nothing here is load-bearing — this is what makes the reader appear, not what
 * decides the answer.
 */
(function () {
    var form = document.querySelector('form[data-wants-fingerprint]');
    if (!form || !window.DeviceKeys) return;

    var needed = null;      // asked once, when it first matters

    form.addEventListener('submit', function (e) {
        if (form.dataset.proved === 'yes') return;      // already done, let it go

        e.preventDefault();

        var button = form.querySelector('button[type=submit]');
        var wasSaying = button ? button.innerHTML : '';

        function go() {
            form.dataset.proved = 'yes';
            form.submit();
        }

        function stop(why) {
            if (button) { button.disabled = false; button.innerHTML = wasSaying; }
            if (typeof showToast === 'function') showToast('Not clocked in', why, 'error');
        }

        if (button) { button.disabled = true; button.innerHTML = '<i class="fas fa-fingerprint"></i> Checking…'; }

        DeviceKeys.list()
            .then(function (data) {
                needed = data.required && (data.devices || []).length > 0;

                // Nothing asked for, or nothing set up: the clock works as it
                // always has.
                if (!needed) { go(); return; }

                return DeviceKeys.prove().then(go);
            })
            .catch(function (err) {
                stop(err && err.message ? err.message : 'That did not go through.');
            });
    });
})();
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var salesOptions = {
        series: [{
            name: 'Revenue',
            data: {!! json_encode($monthly_revenue) !!}
        }],
        chart: { type: 'area', height: 300 },
        // A wash under the line rather than a coloured slab: the shape is the
        // information, the fill is only there to anchor it.
        fill: {
            type: 'gradient',
            gradient: { shadeIntensity: 1, opacityFrom: 0.28, opacityTo: 0.02, stops: [0, 92, 100] },
        },
        xaxis: { categories: {!! json_encode($months_labels) !!} },
        yaxis: {
            labels: {
                formatter: function (value) {
                    return value >= 1000 ? '$' + Math.round(value / 1000) + 'k' : '$' + Math.round(value);
                },
            },
        },
        tooltip: { y: { formatter: function (value) { return '$' + Number(value).toLocaleString(); } } },
    };

    var salesChart = new ApexCharts(document.querySelector("#salesGrowthChart"), salesOptions);
    salesChart.render();

    // Conversion Chart
    var convOptions = {
        series: [{{ $won_count }}, {{ $lost_count }}],
        chart: { type: 'donut', height: 290 },
        labels: ['Won', 'Lost'],
        // Won is the figure; lost is the track it is measured against, so it is
        // drawn as a track rather than as a second colour competing with it.
        colors: ['#10b981', 'rgba(148,163,184,0.16)'],
        dataLabels: { enabled: false },
        legend: { show: false },
        plotOptions: {
            pie: {
                donut: {
                    size: '85%',
                    labels: {
                        show: true,
                        name: {
                            show: true,
                            fontSize: '13px',
                            fontWeight: 600,
                            offsetY: 20
                        },
                        value: {
                            show: true,
                            fontSize: '34px',
                            fontWeight: 800,
                            color: 'var(--text-main)',
                            offsetY: -10,
                            formatter: function (val) {
                                // Default slice value formatter
                                return val;
                            }
                        },
                        total: {
                            show: true,
                            showAlways: true,
                            label: 'Avg. Rate',
                            formatter: function (w) {
                                const won = w.globals.seriesTotals[0];
                                const lost = w.globals.seriesTotals[1];
                                const total = won + lost;
                                return total === 0 ? "0%" : Math.round((won/total)*100) + "%";
                            }
                        }
                    }
                }
            }
        },
        stroke: { show: false }
    };

    var convChart = new ApexCharts(document.querySelector("#conversionChart"), convOptions);
    convChart.render();

    @if(auth()->user()->isSuperAdmin())
    // Super Admin Company Chart
    var superOptions = {
        series: [{
            name: 'Deals Won',
            data: {!! json_encode($super_stats->deals_by_company->pluck('won_deals_count')) !!}
        }],
        chart: { type: 'bar', height: 300 },
        plotOptions: { bar: { borderRadius: 6, borderRadiusApplication: 'end', columnWidth: '42%', distributed: true } },
        legend: { show: false },
        // The number sits above its column in ink, rather than in white on a
        // colour that may or may not be dark enough to carry it.
        // The number sits on the column, so it is written in white with a soft
        // shadow under it: grey ink reads on the page behind the chart but not
        // on a saturated bar, and the bars are saturated by design.
        dataLabels: {
            enabled: true,
            offsetY: 6,
            style: { fontSize: '12px', fontWeight: 700, colors: ['#fff'] },
            dropShadow: { enabled: true, top: 1, left: 0, blur: 2, opacity: 0.35 },
        },
        xaxis: { categories: {!! json_encode($super_stats->deals_by_company->pluck('name')) !!} },
        yaxis: { forceNiceScale: true },
        tooltip: { y: { formatter: function (val) { return val + ' deal' + (val === 1 ? '' : 's') + ' won'; } } },
    };
    var superChart = new ApexCharts(document.querySelector("#super_company_chart"), superOptions);
    superChart.render();
    @endif
});

function requestShortBreak(type, btn) {
    btn.disabled = true;
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
    
    fetch('{{ route("admin.attendance.appeal-break") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ type: type })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (typeof showToast === 'function') {
                showToast('Break Request', data.message);
            } else {
                alert(data.message);
            }
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            if (typeof showToast === 'function') {
                showToast('Request Failed', data.error || 'Failed to submit request.');
            } else {
                alert(data.error || 'Failed to submit request.');
            }
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    })
    .catch(err => {
        console.error(err);
        if (typeof showToast === 'function') {
            showToast('Error', 'An error occurred.');
        } else {
            alert('An error occurred.');
        }
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    });
}

let globalBreakFormToSubmit = null;
function showGlobalBreakConfirm(desc, formId) {
    document.getElementById('globalBreakModalDesc').textContent = desc;
    globalBreakFormToSubmit = document.getElementById(formId);
    document.getElementById('globalBreakConfirmModal').classList.add('show');
}
</script>

<!-- Global Break Confirmation Modal -->
<div class="modal-overlay" id="globalBreakConfirmModal">
    <div class="modal-content" style="max-width: 450px; text-align: center; border-radius: 20px;">
        <button class="close-modal" onclick="document.getElementById('globalBreakConfirmModal').classList.remove('show')"><i class="fas fa-times"></i></button>
        <div style="font-size: 3.5rem; color: var(--warning); margin-bottom: 1rem;"><i class="fas fa-exclamation-circle"></i></div>
        <h2 style="margin-bottom: 0.5rem; font-size: 1.6rem;">Confirm Global Break</h2>
        <p id="globalBreakModalDesc" style="color: var(--text-muted); font-size: 1rem; margin-bottom: 2rem;">Are you sure?</p>
        <div style="display: flex; gap: 12px; justify-content: center;">
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('globalBreakConfirmModal').classList.remove('show')" style="flex: 1; padding: 0.9rem; font-weight: 600; color: var(--text-main); border: 1px solid var(--border); border-radius: 12px;">Cancel</button>
            <button type="button" class="btn" id="confirmGlobalBreakBtn" onclick="if(globalBreakFormToSubmit) globalBreakFormToSubmit.submit();" style="flex: 1; padding: 0.9rem; font-weight: 700; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; border-radius: 12px;"><i class="fas fa-check"></i> Yes, Start Break</button>
        </div>
    </div>
</div>
@endpush
@endsection

@push('scripts')
{{-- Reorderable metric cards. Uses the same engine as the sidebar
     (js/sortable-prefs.js), which the layout has already loaded. --}}
<script src="@versioned('js/stat-cards-sort.js')"></script>
<script>
    (function () {
        if (window.StatCardsSort) StatCardsSort.init(window.CRM_USER_ID);
    })();
</script>
@endpush

@push('styles')
<style>
    /* The clock on the left at 60%, the board on the right at 40%. */
    .dash-row { display: grid; grid-template-columns: 60% 40%; gap: 1.5rem; margin-bottom: 2rem; align-items: start; }
    .dash-row > .card { height: 100%; }
    .dash-row.dash-row-board-only { grid-template-columns: 1fr; }
    @media (max-width: 1100px) { .dash-row { grid-template-columns: 1fr; } }

    .ann-new, .ann-cancel { border: 1px solid var(--border); background: var(--bg-body); color: var(--text-main);
                            border-radius: 9px; padding: 0.35rem 0.8rem; font-size: 0.8rem; font-weight: 600;
                            cursor: pointer; font-family: inherit; }
    .ann-new:hover, .ann-cancel:hover { border-color: var(--primary); color: var(--primary); }

    .ann-form { display: flex; flex-direction: column; gap: 9px; margin-bottom: 1.1rem; padding-bottom: 1.1rem; border-bottom: 1px dashed var(--border); }
    .ann-form input[type=text], .ann-form textarea { width: 100%; padding: 0.55rem 0.7rem; border-radius: 10px;
        border: 1px solid var(--border); background: var(--bg-body); color: var(--text-main); font-family: inherit; font-size: 0.88rem; resize: vertical; }
    .ann-form-row { display: flex; gap: 14px; align-items: center; flex-wrap: wrap; font-size: 0.8rem; color: var(--text-muted); }
    .ann-form-row label { display: inline-flex; align-items: center; gap: 6px; cursor: pointer; }

    /*
     * The board is a fixed height whatever is on it: the card no longer grows
     * with every announcement posted, pushing the rest of the dashboard down.
     * The header and the form stay put and the list itself scrolls.
     */
    .card.dash-side { display: flex; flex-direction: column; height: 400px; }
    .card.dash-side .card-header { flex: 0 0 auto; }
    .card.dash-side .ann-form { flex: 0 0 auto; }

    .ann-list { display: flex; flex-direction: column; gap: 10px;
                flex: 1 1 auto; min-height: 0; overflow-y: auto; padding-right: 4px; }
    .ann-list::-webkit-scrollbar { width: 6px; }
    .ann-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 999px; }
    .ann-list::-webkit-scrollbar-track { background: transparent; }
    .ann { border: 1px solid var(--border); border-left: 3px solid var(--primary); border-radius: 12px; padding: 0.7rem 0.85rem; background: var(--bg-body); }
    .ann-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }
    .ann-top b { font-size: 0.88rem; color: var(--text-main); line-height: 1.4; }
    .ann p { margin: 5px 0 0; font-size: 0.82rem; color: var(--text-muted); line-height: 1.55; white-space: pre-wrap; }
    .ann small { display: block; margin-top: 6px; font-size: 0.72rem; color: var(--text-muted); }
    .ann-remove { border: none; background: transparent; color: var(--text-muted); cursor: pointer; font-size: 0.85rem; padding: 0 2px; }
    .ann-remove:hover { color: var(--danger); }
    .ann-empty { color: var(--text-muted); font-size: 0.86rem; margin: 0; }
</style>
@endpush

@push('scripts')
<script>
    /*
     * The clock and the board both talk to the server directly and put the
     * answer straight on the page. Pressing Clock In used to reload the whole
     * dashboard — charts, menus and all — for one button.
     */
    (function () {
        var card = document.getElementById('timeTrackerCard');
        if (!card) return;

        card.addEventListener('submit', function (e) {
            var form = e.target;
            // Every button in this card — clocking in and out, starting and
            // ending a break — goes the same way: post it, say what happened,
            // and put the fresh card back.
            if (!form.matches('form[action*="/attendance/"]')) return;

            e.preventDefault();

            var button = form.querySelector('button[type=submit]');
            if (button) { button.disabled = true; button.style.opacity = '0.7'; }

            fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
            })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
                if (typeof showToast === 'function') {
                    showToast(result.ok ? 'Attendance' : 'Attendance', result.data.message || 'Done', result.ok ? 'success' : 'error');
                }
                return refreshTracker();
            })
            .catch(function (err) {
                if (typeof showToast === 'function') showToast('Attendance', 'Could not reach the server: ' + err.message, 'error');
                if (button) { button.disabled = false; button.style.opacity = ''; }
            });
        });

        /* The card is full of state — breaks, appeals, the next break due — so
           rather than guessing at it in the browser, just this card is fetched
           again and swapped in. One small request instead of a whole page. */
        window.refreshTracker = function () {
            return fetch(window.location.pathname, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (res) { return res.text(); })
                .then(function (html) {
                    var fresh = new DOMParser().parseFromString(html, 'text/html').getElementById('timeTrackerCard');
                    if (fresh) card.innerHTML = fresh.innerHTML;
                });
        };
    })();

    function annToggleForm() {
        var form = document.getElementById('annForm');
        if (!form) return;
        form.hidden = !form.hidden;
        if (!form.hidden) document.getElementById('annTitle').focus();
    }

    function annPost(e) {
        e.preventDefault();

        var button = document.getElementById('annSubmit');
        var body = {
            title: document.getElementById('annTitle').value,
            body: document.getElementById('annBody').value,
            is_pinned: document.getElementById('annPinned').checked ? 1 : 0,
            notify: document.getElementById('annNotify').checked ? 1 : 0,
        };

        button.disabled = true;

        fetch('{{ route('admin.announcements.store') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        })
        .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
        .then(function (result) {
            button.disabled = false;

            if (!result.ok) {
                var problem = result.data.message || 'That could not be posted.';
                if (typeof showToast === 'function') showToast('Announcement', problem, 'error');
                return;
            }

            if (typeof window.annAlreadyShown === 'function') {
                window.annAlreadyShown(result.data.announcement.id);
            }

            annPrepend(result.data.announcement);
            document.getElementById('annForm').reset();
            document.getElementById('annForm').hidden = true;
            if (typeof showToast === 'function') showToast('Announcement', 'Posted.', 'success');
        })
        .catch(function (err) {
            button.disabled = false;
            if (typeof showToast === 'function') showToast('Announcement', 'Could not reach the server: ' + err.message, 'error');
        });
    }

    // Only HR and admins may take an announcement down, so only they are given
    // the button — one that arrives on an employee's board must not offer it.
    var ANN_CAN_POST = @json($canPost);

    function annPrepend(item) {
        var list = document.getElementById('annList');
        var empty = document.getElementById('annEmpty');
        if (empty) empty.remove();

        var el = document.createElement('div');
        el.className = 'ann';
        el.dataset.id = item.id;
        el.innerHTML =
            '<div class="ann-top"><b>' + (item.pinned ? '<i class="fas fa-thumbtack"></i> ' : '') + annEscape(item.title) + '</b>' +
            (ANN_CAN_POST
                ? '<button type="button" class="ann-remove" title="Take it down" onclick="annRemove(' + item.id + ', this)"><i class="fas fa-xmark"></i></button>'
                : '') +
            '</div>' +
            (item.body ? '<p>' + annEscape(item.body) + '</p>' : '') +
            '<small>' + annEscape(item.author) + ' · just now</small>';

        list.prepend(el);
    }

    function annRemove(id, button) {
        if (!confirm('Take this announcement down?')) return;

        button.disabled = true;

        fetch('{{ url('admin/announcements') }}/' + id, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            credentials: 'same-origin',
            body: JSON.stringify({ _method: 'DELETE' }),
        })
        .then(function (res) { return res.json(); })
        .then(function () {
            var card = button.closest('.ann');
            if (card) card.remove();

            var list = document.getElementById('annList');
            if (list && !list.querySelector('.ann')) {
                list.innerHTML = '<p class="ann-empty" id="annEmpty">Nothing announced yet.</p>';
            }
        })
        .catch(function () { button.disabled = false; });
    }

    // Announcements are typed by people, so they are put on the page as text.
    function annEscape(value) {
        var div = document.createElement('div');
        div.textContent = value == null ? '' : value;
        return div.innerHTML;
    }

    /*
     * An announcement posted by HR should reach the people it is for without
     * them reloading anything. The board asks for the list every few seconds and
     * puts anything it has not seen at the top, with a toast to say it arrived.
     *
     * It also asks the moment a tab is looked at again, so coming back to the
     * dashboard shows the board as it stands rather than as it was when the tab
     * was last in front.
     */
    (function () {
        var list = document.getElementById('annList');
        if (!list) return;

        var known = new Set([].map.call(list.querySelectorAll('.ann'), function (el) { return String(el.dataset.id); }));
        var firstPass = true;

        /*
         * Whoever posts an announcement already has it on their board, put there
         * the moment the server accepted it. Telling the poll about it keeps the
         * next tick from adding a second copy and announcing it to the person who
         * wrote it.
         */
        window.annAlreadyShown = function (id) { known.add(String(id)); };

        function poll() {
            fetch('{{ route('admin.announcements.index') }}', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(function (data) {
                if (!data || !data.announcements) return;

                // Oldest first, so several at once land in the right order.
                data.announcements.slice().reverse().forEach(function (item) {
                    if (known.has(String(item.id))) return;
                    known.add(String(item.id));

                    if (firstPass) return;              // already on the page

                    annPrepend(item);

                    if (typeof showToast === 'function') {
                        showToast('Announcement', item.title, 'info');
                    }
                });

                firstPass = false;
            })
            .catch(function () { /* the next tick will try again */ });
        }

        poll();
        setInterval(poll, 10000);

        // Coming back to the tab should not mean waiting for the next tick.
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) poll();
        });
        window.addEventListener('focus', poll);
    })();
</script>
@endpush
