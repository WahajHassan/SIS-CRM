@extends('layouts.admin')

@section('title', 'Attendance')

@section('content')
<div style="display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Top Filter & Summary bar -->
    <div class="card" style="margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 15px; flex-wrap: wrap; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border); padding-bottom: 1rem;">
            <div>
                <div class="card-title" style="margin-bottom: 5px; font-size: 1.2rem; font-weight: 700; color: var(--text-main);">Daily Attendance Logs</div>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">Monitor clock-in/out times, break durations, and total net working hours.</p>
            </div>
            
            <!-- Export Buttons -->
            <div style="display: flex; gap: 8px;">
                <a href="{{ route('admin.attendance.export', request()->all()) }}" class="btn btn-ghost" style="border: 1px solid var(--border); background: var(--bg-body); color: var(--text-main); font-weight: 600; text-decoration: none;">
                    <i class="fas fa-file-excel" style="color: #10b981;"></i> Export CSV (Excel)
                </a>
                <a href="{{ route('admin.attendance.print', request()->all()) }}" target="_blank" class="btn btn-ghost" style="border: 1px solid var(--border); background: var(--bg-body); color: var(--text-main); font-weight: 600; text-decoration: none;">
                    <i class="fas fa-file-pdf" style="color: #ef4444;"></i> Export PDF (Print)
                </a>
            </div>
        </div>

        <form method="GET" action="{{ route('admin.attendance') }}" style="display: grid; grid-template-columns: 1.5fr 1fr 1fr auto; gap: 15px; align-items: end; margin: 0; width: 100%;">
            <div>
                <label style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px;">Filter Employee</label>
                <select name="user_id" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border); outline: none; font-size: 0.85rem; font-family: inherit; background: var(--bg-card); height: 38px;">
                    <option value="">All Employees</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" {{ $selected_user_id == $user->id ? 'selected' : '' }}>{{ $user->display_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px;">Start Date</label>
                <input type="date" name="start_date" value="{{ $start_date }}" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border); outline: none; font-size: 0.85rem; font-family: inherit; height: 38px;">
            </div>
            <div>
                <label style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px;">End Date</label>
                <input type="date" name="end_date" value="{{ $end_date }}" style="width: 100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border); outline: none; font-size: 0.85rem; font-family: inherit; height: 38px;">
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary" style="padding: 0.6rem 1.5rem; border-radius: 8px; font-weight: 600; font-family: inherit; height: 38px; cursor: pointer;">
                    <i class="fas fa-filter"></i> Apply Filter
                </button>
                <a href="{{ route('admin.attendance') }}" class="btn btn-ghost" style="border: 1px solid var(--border); height: 38px; display: flex; align-items: center; justify-content: center; font-weight: 600; text-decoration: none;">
                    Clear
                </a>
            </div>
        </form>
    </div>

    <!-- Summary Stats Row -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
        <div class="card" style="margin: 0; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <div style="color: var(--text-muted); font-size: 0.8rem; margin-bottom: 5px;">Total Logs</div>
                <div style="font-size: 1.6rem; font-weight: 700; color: var(--text-main);">{{ $counts['all'] }}</div>
            </div>
            <div style="width: 45px; height: 45px; background: rgba(99,102,241,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--primary);">
                <i class="fas fa-clipboard-list"></i>
            </div>
        </div>
        
        <div class="card" style="margin: 0; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <div style="color: var(--text-muted); font-size: 0.8rem; margin-bottom: 5px;">Working</div>
                <div style="font-size: 1.6rem; font-weight: 700; color: var(--success);">{{ $counts['clocked_in'] }}</div>
            </div>
            <div style="width: 45px; height: 45px; background: rgba(16,185,129,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--success);">
                <i class="fas fa-user-clock"></i>
            </div>
        </div>

        <div class="card" style="margin: 0; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <div style="color: var(--text-muted); font-size: 0.8rem; margin-bottom: 5px;">On Break</div>
                <div style="font-size: 1.6rem; font-weight: 700; color: var(--info);">{{ $counts['on_break'] }}</div>
            </div>
            <div style="width: 45px; height: 45px; background: rgba(59,130,246,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--info);">
                <i class="fas fa-coffee"></i>
            </div>
        </div>

        <div class="card" style="margin: 0; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <div style="color: var(--text-muted); font-size: 0.8rem; margin-bottom: 5px;">Clocked Out</div>
                <div style="font-size: 1.6rem; font-weight: 700; color: var(--secondary);">{{ $counts['clocked_out'] }}</div>
            </div>
            <div style="width: 45px; height: 45px; background: rgba(100,116,139,0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--secondary);">
                <i class="fas fa-door-closed"></i>
            </div>
        </div>
    </div>

    @include('admin.partials.salary_summary')

    <!-- Attendance Table -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                @if($start_date === $end_date)
                    Logs for {{ \Carbon\Carbon::parse($start_date)->format('M d, Y') }}
                @else
                    Logs from {{ \Carbon\Carbon::parse($start_date)->format('M d, Y') }} to {{ \Carbon\Carbon::parse($end_date)->format('M d, Y') }}
                @endif
            </div>
        </div>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Employee Name</th>
                        <th>Role</th>
                        <th>Clock In</th>
                        <th>Break Sessions</th>
                        <th>Clock Out</th>
                        <th>Total Work Hours</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="attendanceRows">
                    @forelse($attendances as $att)
                    <tr>
                        <td style="font-size: 0.82rem; color: var(--text-muted); font-weight: 600;">
                            {{ $att->date->format('d M, Y') }}
                        </td>
                        <td><x-person :user="$att->user" :size="30" /></td>
                        <td>
                            <span class="badge" style="background: {{ $att->user->role === 'closer' ? 'rgba(59,130,246,0.1)' : 'rgba(16,185,129,0.1)' }}; color: {{ $att->user->role === 'closer' ? 'var(--info)' : 'var(--success)' }};">
                                {{ strtoupper($att->user->role) }}
                            </span>
                        </td>
                        <td style="font-weight: 600; color: var(--text-main);">
                            {{ $att->clock_in ? $att->clock_in->format('h:i A') : '--' }}
                        </td>
                        <td>
                            @if($att->breaks->count() > 0)
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    @foreach($att->breaks as $break)
                                        <div style="font-size: 0.78rem; color: var(--text-muted);">
                                            <i class="fas fa-coffee" style="color: var(--primary);"></i> 
                                            {{ $break->break_start->format('h:i A') }} - 
                                            {{ $break->break_end ? $break->break_end->format('h:i A') : 'Active' }}
                                            <span style="font-weight: 600; color: var(--text-main);"
                                                @if(!$break->break_end)
                                                    class="live-break-timer" data-start="{{ $break->break_start->timestamp }}"
                                                @endif
                                            >({{ $break->durationText() }})</span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <span style="font-size: 0.8rem; color: var(--text-muted);">No breaks</span>
                            @endif
                        </td>
                        <td style="font-weight: 600; color: var(--text-main);">
                            {{ $att->clock_out ? $att->clock_out->format('h:i A') : '--' }}
                        </td>
                        <td style="font-weight: 700; color: var(--success); font-size: 0.95rem;">
                            {{ $att->totalWorkedHoursText() }}
                        </td>
                        <td>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <span class="badge" style="
                                    @if($att->status === 'clocked_in') background: rgba(16,185,129,0.1); color: var(--success);
                                    @elseif($att->status === 'on_break') background: rgba(59,130,246,0.1); color: var(--info);
                                    @else background: rgba(100,116,139,0.1); color: var(--secondary);
                                    @endif
                                    padding: 0.35rem 0.8rem; border-radius: 12px; font-weight: 700;
                                ">
                                    {{ strtoupper($att->status) }}
                                </span>

                                @if($att->status === 'on_break' && (auth()->user()->isAdmin() || auth()->user()->isFloorManager()))
                                    <form action="{{ route('admin.attendance.force-end-break', $att->user->id) }}" method="POST" style="margin: 0;">
                                        @csrf
                                        <button type="submit" class="btn" style="padding: 0.3rem 0.7rem; font-size: 0.75rem; background: var(--danger); color: white; border-radius: 8px; font-weight: 700; border: none; cursor: pointer;" title="Force end this agent's break">
                                            <i class="fas fa-ban"></i> End Break
                                        </button>
                                    </form>
                                @endif
                                
                                @if($att->status === 'clocked_in' && (auth()->user()->isAdmin() || auth()->user()->isFloorManager()))
                                    @php
                                        $globalHalf1Started = \Illuminate\Support\Facades\Cache::get("global_break_state_half_1") === 'started';
                                        $globalHalf2Started = \Illuminate\Support\Facades\Cache::get("global_break_state_half_2") === 'started';
                                        $nextIsMain = in_array($att->getNextBreakType(), ['half_1', 'half_2']);
                                    @endphp
                                    @if($nextIsMain || $globalHalf1Started || $globalHalf2Started)
                                    <form action="{{ route('admin.attendance.force-start-main-break', $att->user->id) }}" method="POST" style="margin: 0;">
                                        @csrf
                                        <button type="submit" class="btn" style="padding: 0.3rem 0.7rem; font-size: 0.75rem; background: var(--success); color: white; border-radius: 8px; font-weight: 700; border: none; cursor: pointer;" title="Force start Main Break for this agent">
                                            <i class="fas fa-play"></i> Start Main Break
                                        </button>
                                    </form>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 3rem; color: var(--text-muted); ">
                            <i class="fas fa-history" style="font-size: 2rem; color: var(--border); display: block; margin-bottom: 1rem;"></i>
                            No attendance records logged for the selected filter.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Attendance never stops growing, so the table is a page at a time now.
             The filters travel with the page links. --}}
        @if($attendances->hasPages())
            <div style="padding: 1rem 0 0;">
                {{ $attendances->links() }}
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let serverTime = {{ time() }};
    
    // Live ticking timer for active breaks
    setInterval(function() {
        serverTime++;
        document.querySelectorAll('.live-break-timer').forEach(function(el) {
            let start = parseInt(el.getAttribute('data-start'));
            let diff = serverTime - start;
            if (diff < 0) diff = 0;
            
            let h = Math.floor(diff / 3600);
            let m = Math.floor((diff % 3600) / 60);
            let s = diff % 60;
            
            let text = '';
            if (h > 0) text += h + 'h ';
            if (m > 0 || h > 0) text += m + 'm ';
            text += s + 's';
            
            el.innerText = '(' + text.trim() + ')';
        });
    }, 1000);

    /*
     * Auto-refresh the log rows every 15 seconds so a clock-out or a break that
     * happened elsewhere shows up without a reload.
     *
     * This used to look for '.table-container table tbody', a class that appears
     * nowhere on this page, so it quietly refreshed nothing for as long as it has
     * existed. The rows now carry an id of their own.
     */
    setInterval(function() {
        const current = document.getElementById('attendanceRows');
        if (!current) return;

        fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(response => response.text())
            .then(html => {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const fresh = doc.getElementById('attendanceRows');
                if (fresh) current.innerHTML = fresh.innerHTML;
            })
            .catch(err => console.error('Error fetching attendance data', err));
    }, 15000);
});
</script>
@endpush
@endsection
