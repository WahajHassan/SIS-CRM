@extends('layouts.admin')

@section('title', 'Support Dashboard')

@section('content')
<div class="page-head">
    <div class="page-sub" style="margin: 0;">Manage client onboarding and support requests.</div>
</div>

@if(session('success'))
<div class="alert alert-success"><i class="fas fa-circle-check"></i> {{ session('success') }}</div>
@endif

@php
    $attendance = auth()->user()->todayAttendance();
@endphp

@if(auth()->user()->isSupport())
<!-- Time Tracker Card (For Support Users) -->
<div class="card" style="margin-bottom: 2rem; background: var(--bg-card); border-radius: 20px; border: 1px solid var(--border); padding: 1.5rem;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 1rem; margin-bottom: 1.5rem;">
        <div class="card-title" style="display: flex; align-items: center; gap: 8px; font-size: 1.1rem; font-weight: 700; color: var(--text-main);">
            <i class="fas fa-clock" style="color: var(--primary); font-size: 1.2rem;"></i> Attendance & Time Tracker
        </div>
        <div>
            @if($attendance)
                @if($attendance->status === 'clocked_in')
                    <span class="badge" style="background: rgba(16,185,129,0.1); color: var(--success); font-weight: 700; padding: 0.4rem 1rem; border-radius: 20px;">● CLOCKED IN</span>
                @elseif($attendance->status === 'on_break')
                    <span class="badge" style="background: rgba(59,130,246,0.1); color: var(--info); font-weight: 700; padding: 0.4rem 1rem; border-radius: 20px;">● ON BREAK</span>
                @elseif($attendance->status === 'clocked_out')
                    <span class="badge" style="background: rgba(100,116,139,0.1); color: var(--secondary); font-weight: 700; padding: 0.4rem 1rem; border-radius: 20px;">● CLOCKED OUT</span>
                @endif
            @else
                <span class="badge" style="background: rgba(239,68,68,0.1); color: var(--danger); font-weight: 700; padding: 0.4rem 1rem; border-radius: 20px;">● NOT WORKING</span>
            @endif
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 2rem; align-items: center; flex-wrap: wrap;">
        <!-- Controls -->
        <div>
            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                @if(!$attendance)
                    <!-- Not Checked In yet -->
                    <form action="{{ route('admin.attendance.clock-in') }}" method="POST" style="margin: 0;">
                        @csrf
                        <button type="submit" class="btn btn-primary" style="padding: 0.8rem 1.8rem; border-radius: 12px; font-weight: 700; gap: 10px; font-size: 0.95rem; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none; cursor: pointer;">
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
                        <button type="button" onclick="showClockOutConfirm(false, 'clockOutForm')" class="btn" style="padding: 0.8rem 1.8rem; border-radius: 12px; font-weight: 700; gap: 10px; font-size: 0.95rem; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; border: none; cursor: pointer;">
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
                            <button type="submit" class="btn" style="padding: 0.8rem 1.8rem; border-radius: 12px; font-weight: 700; gap: 10px; font-size: 0.95rem; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none; cursor: pointer;">
                                <i class="fas fa-briefcase"></i> End Break & Resume
                            </button>
                        </form>
                    @endif

                    <form id="clockOutFormBreak" action="{{ route('admin.attendance.clock-out') }}" method="POST" style="margin: 0;">
                        @csrf
                        <button type="button" onclick="showClockOutConfirm(true, 'clockOutFormBreak')" class="btn" style="padding: 0.8rem 1.8rem; border-radius: 12px; font-weight: 700; gap: 10px; font-size: 0.95rem; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; border: none; cursor: pointer;">
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
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px;">Net Work Hours</div>
                <div style="font-weight: 700; font-size: 1rem;">
                    {{ $attendance ? $attendance->totalWorkedHoursText() : '0h 0m' }}
                </div>
            </div>
            <div style="text-align: center; padding: 1rem; background: rgba(245, 158, 11, 0.05); border-radius: 12px;">
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px;">Break Taken</div>
                <div style="font-weight: 700; font-size: 1rem;">
                    {{ $attendance ? $attendance->totalBreakDurationText() : '0m' }}
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Stats Cards -->
<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.1rem; margin-bottom: 1.3rem;">
    <div class="card kpi" style="margin-bottom: 0;">
        <div class="kpi-head">
            <div class="kpi-label">Total tickets</div>
            <span class="kpi-icon is-primary"><i class="fas fa-ticket"></i></span>
        </div>
        <div class="kpi-value" data-count="{{ (int) $stats['total'] }}">{{ $stats['total'] }}</div>
        <div class="kpi-foot"><span>raised in all</span></div>
    </div>
    <div class="card kpi" style="margin-bottom: 0;">
        <div class="kpi-head">
            <div class="kpi-label">Pending</div>
            <span class="kpi-icon is-danger"><i class="fas fa-hourglass-start"></i></span>
        </div>
        <div class="kpi-value" data-count="{{ (int) $stats['pending'] }}">{{ $stats['pending'] }}</div>
        <div class="kpi-foot"><span>nobody has picked up</span></div>
    </div>
    <div class="card kpi" style="margin-bottom: 0;">
        <div class="kpi-head">
            <div class="kpi-label">In progress</div>
            <span class="kpi-icon is-warning"><i class="fas fa-spinner"></i></span>
        </div>
        <div class="kpi-value" data-count="{{ (int) $stats['in_progress'] }}">{{ $stats['in_progress'] }}</div>
        <div class="kpi-foot"><span>being worked through</span></div>
    </div>
    <div class="card kpi" style="margin-bottom: 0;">
        <div class="kpi-head">
            <div class="kpi-label">Resolved</div>
            <span class="kpi-icon is-success"><i class="fas fa-circle-check"></i></span>
        </div>
        <div class="kpi-value" data-count="{{ (int) $stats['resolved'] }}">{{ $stats['resolved'] }}</div>
        <div class="kpi-foot"><span>closed out</span></div>
    </div>
</div>

<!-- Tickets Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-inbox"></i> Recent tickets</div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Ticket #</th>
                    <th>Subject</th>
                    <th>Deal</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Assigned To</th>
                    <th>Date</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tickets as $ticket)
                <tr style="border-bottom: 1px solid var(--border);">
                    <td style=" color: var(--text-main); font-weight: 500;">#{{ $ticket->id }}</td>
                    <td style=" color: var(--text-main);">{{ Str::limit($ticket->subject, 30) }}</td>
                    <td style=" color: var(--text-muted);">{{ optional($ticket->deal)->name ?? 'N/A' }}</td>
                    <td style="">
                        @if($ticket->status === 'pending_verification')
                            <span style="background-color: rgba(239,68,68,0.1); color: var(--danger); padding: 4px 8px; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">Pending</span>
                        @elseif($ticket->status === 'in_progress')
                            <span style="background-color: rgba(245,158,11,0.1); color: var(--warning); padding: 4px 8px; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">In Progress</span>
                        @elseif($ticket->status === 'resolved')
                            <span style="background-color: rgba(16,185,129,0.1); color: var(--success); padding: 4px 8px; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">Resolved</span>
                        @else
                            <span style="background-color: rgba(107,114,128,0.1); color: var(--text-muted); padding: 4px 8px; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">{{ ucfirst($ticket->status) }}</span>
                        @endif
                    </td>
                    <td style="">
                        @if($ticket->priority === 'urgent' || $ticket->priority === 'high')
                            <span style="color: var(--danger); font-weight: bold;"><i class="fas fa-exclamation-circle"></i> {{ ucfirst($ticket->priority) }}</span>
                        @else
                            <span style="color: var(--text-muted);">{{ ucfirst($ticket->priority) }}</span>
                        @endif
                    </td>
                    <td style=" color: var(--text-muted);">{{ optional($ticket->assignedTo)->name ?? 'Unassigned' }}</td>
                    <td style=" color: var(--text-muted); font-size: 0.9rem;">{{ $ticket->created_at->format('M d, Y') }}</td>
                    <td style=" text-align: right;">
                        <a href="{{ route('support.show', $ticket->id) }}" class="btn btn-primary" style="padding: 6px 12px; font-size: 0.85rem;">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="padding: 20px; text-align: center; color: var(--text-muted);">No support tickets found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
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
</script>
@endpush
