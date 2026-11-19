@extends('layouts.admin')

@section('title', 'My Leave')

@section('content')
{{--
    An employee's own leave: what is left of each quota, and a form to ask for
    more. HR sees the request on their own page the moment it is sent.
--}}
<style>
    .ml-quota { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
    .ml-item { border: 1px solid var(--border); border-left-width: 4px; border-radius: 12px; padding: 0.85rem 1rem; background: var(--bg-card); }
    .ml-item b { display: block; color: var(--text-main); font-size: 0.9rem; }
    .ml-item small { color: var(--text-muted); font-size: 0.77rem; }
    .ml-bar { height: 6px; border-radius: 999px; background: var(--bg-body); border: 1px solid var(--border); overflow: hidden; margin-top: 8px; }
    .ml-bar i { display: block; height: 100%; }

    .ml-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; align-items: end; }
    .ml-form label { display: block; font-size: 0.78rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; }
    .ml-form input, .ml-form select { width: 100%; padding: 0.55rem 0.65rem; border-radius: 9px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-family: inherit; font-size: 0.86rem; }
    .ml-check { display: inline-flex; align-items: center; gap: 7px; font-size: 0.84rem; color: var(--text-main); }
    .ml-check input { width: auto; }
    .ml-chip { display: inline-block; padding: 0.22rem 0.6rem; border-radius: 999px; font-size: 0.73rem; font-weight: 700; }
</style>

<div style="display: flex; flex-direction: column; gap: 1.5rem;">
    <div class="card" style="margin-bottom: 0;">
        <div style="border-bottom: 1px solid var(--border); padding-bottom: 1rem; margin-bottom: 1.3rem;">
            <div class="card-title" style="margin-bottom: 5px; font-size: 1.2rem; font-weight: 700;">
                <i class="fas fa-plane-departure" style="color: var(--primary);"></i> My Leave — {{ now()->year }}
            </div>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">
                What you have taken this year, and what is still yours to take.
            </p>
        </div>

        <div class="ml-quota">
            @foreach($quota as $q)
                @php $pct = $q['type']->annual_quota > 0 ? min(100, round($q['used'] / $q['type']->annual_quota * 100)) : 0; @endphp
                <div class="ml-item" style="border-left-color: {{ $q['type']->colour }};">
                    <b>{{ $q['type']->name }}</b>
                    <small>
                        {{ rtrim(rtrim(number_format($q['used'], 1), '0'), '.') }} taken this year
                        @if($q['type']->annual_quota > 0)
                            of {{ $q['type']->annual_quota }} &middot; <b style="display: inline; color: var(--success);">{{ rtrim(rtrim(number_format($q['left'], 1), '0'), '.') }} left</b>
                        @else
                            &middot; no yearly limit
                        @endif
                        @unless($q['type']->is_paid) &middot; unpaid @endunless
                    </small>
                    <small>
                        {{ rtrim(rtrim(number_format($q['month_used'], 1), '0'), '.') }} in {{ $q['month']->format('F') }}
                        @if($q['month_left'] !== null)
                            of {{ $q['type']->monthly_quota }} allowed this month &middot;
                            <b style="display: inline; color: {{ $q['month_left'] > 0 ? 'var(--success)' : 'var(--danger)' }};">{{ rtrim(rtrim(number_format($q['month_left'], 1), '0'), '.') }} left</b>
                        @endif
                    </small>
                    <div class="ml-bar"><i style="width: {{ $pct }}%; background: {{ $q['type']->colour }};"></i></div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="card" style="margin-bottom: 0;">
        <div class="card-header"><div class="card-title"><i class="fas fa-paper-plane" style="color: var(--primary);"></i> Ask for leave</div></div>
        <form method="POST" action="{{ route('admin.hr.leaves.store') }}" class="ml-form" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="user_id" value="{{ auth()->id() }}">
            <div>
                <label for="mlType">Type</label>
                {{-- Each option carries what it needs, so the form can ask for
                     hours or proof without a round trip to the server. --}}
                <select id="mlType" name="leave_type_id" required onchange="mlTypeChanged()">
                    @foreach($types as $type)
                        @php $q = collect($quota)->firstWhere('type.id', $type->id); @endphp
                        <option value="{{ $type->id }}"
                                data-hourly="{{ $type->isHourly() ? '1' : '0' }}"
                                data-maxhours="{{ $type->max_hours }}"
                                data-proof="{{ ($type->requires_attachment || ($type->code === 'SL' && $rules->sick_leave_requires_attachment)) ? '1' : '0' }}"
                                data-left="{{ $q ? rtrim(rtrim(number_format($q['left'], 1), '0'), '.') : '' }}"
                                data-quota="{{ $type->annual_quota }}">
                            {{ $type->name }}{{ $type->is_paid ? '' : ' (unpaid)' }}
                        </option>
                    @endforeach
                </select>
                <small id="mlBalance" style="display: block; font-size: 0.73rem; color: var(--text-muted); margin-top: 5px;"></small>
            </div>
            <div id="mlTimes" hidden style="display: flex; gap: 8px;">
                <div style="flex: 1;">
                    <label for="mlFrom">From time</label>
                    <input type="time" id="mlFrom" name="start_time">
                </div>
                <div style="flex: 1;">
                    <label for="mlTo">To time</label>
                    <input type="time" id="mlTo" name="end_time">
                </div>
            </div>
            <div id="mlProof">
                <label for="mlFile">Attachment <span id="mlProofNeeded" hidden style="color: var(--danger);">(required)</span></label>
                <input type="file" id="mlFile" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx"
                       style="font-size: 0.8rem; color: var(--text-muted);">
            </div>
            <div>
                <label for="mlStart">From</label>
                <input type="date" id="mlStart" name="start_date" value="{{ now()->toDateString() }}" required>
            </div>
            <div>
                <label for="mlEnd">To</label>
                <input type="date" id="mlEnd" name="end_date" value="{{ now()->toDateString() }}" required>
            </div>
            <div>
                <label for="mlReason">Reason</label>
                <input type="text" id="mlReason" name="reason" placeholder="Optional">
            </div>
            <div style="display: flex; align-items: center; height: 38px;">
                <label class="ml-check"><input type="checkbox" name="half_day" value="1"> Half day</label>
            </div>
            <div>
                <button type="submit" class="btn btn-primary" style="height: 38px; padding: 0 1.2rem; border-radius: 9px; cursor: pointer; font-weight: 600;">
                    <i class="fas fa-paper-plane"></i> Send request
                </button>
            </div>
        </form>
        <p style="font-size: 0.78rem; color: var(--text-muted); margin: 0.9rem 0 0;">
            @if($rules->requiresTeamLeadApproval())
                Your team lead sees it first, then HR.
            @else
                HR sees it straight away.
            @endif
            Weekly offs and public holidays inside the dates are not charged to your allowance.
        </p>
    </div>

    <div class="card">
        <div class="card-header"><div class="card-title">My leave record</div></div>
        <div style="overflow-x: auto;">
            <table>
                <thead><tr><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Reason</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse($leaves as $leave)
                    <tr>
                        <td style="font-weight: 600; color: var(--text-main);">{{ $leave->type->name ?? '—' }}</td>
                        <td>{{ $leave->start_date->format('d M Y') }}</td>
                        <td>{{ $leave->end_date->format('d M Y') }}</td>
                        <td>
                            @if($leave->hours)
                                {{ rtrim(rtrim(number_format($leave->hours, 1), '0'), '.') }}h
                                <div style="font-size: 0.72rem; color: var(--text-muted);">{{ \Carbon\Carbon::parse($leave->start_time)->format('h:i A') }}</div>
                            @else
                                {{ rtrim(rtrim(number_format($leave->days, 1), '0'), '.') }}{{ $leave->half_day ? ' (half)' : '' }}
                            @endif
                        </td>
                        <td style="max-width: 260px; white-space: normal;">
                            {{ $leave->reason ?: '—' }}
                            @if($leave->decision_note)
                                <div style="font-size: 0.75rem; color: var(--text-muted); font-style: italic;">HR: {{ $leave->decision_note }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="ml-chip" style="background: {{ $leave->statusColour() }}1f; color: {{ $leave->statusColour() }};">{{ $leave->statusLabel() }}</span>
                            @if($leave->attachment_path)
                                <div style="margin-top: 4px;">
                                    <a href="{{ asset('storage/' . $leave->attachment_path) }}" target="_blank" style="font-size: 0.72rem; color: var(--primary);">
                                        <i class="fas fa-paperclip"></i> attachment
                                    </a>
                                </div>
                            @endif
                        </td>
                        <td>
                            @if($leave->status === 'pending')
                                <form method="POST" action="{{ route('admin.hr.leaves.cancel', $leave->id) }}" onsubmit="return confirm('Withdraw this request?');">
                                    @csrf
                                    <button type="submit" class="btn btn-ghost"
                                            style="border: 1px solid var(--border); padding: 0.3rem 0.7rem; font-size: 0.76rem; border-radius: 8px; cursor: pointer;">Withdraw</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="empty-state"><p>You have not taken any leave yet.</p></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // The form asks for what the chosen type actually needs: hours for a short
    // leave, proof where the rules require it, and it says what is left.
    function mlTypeChanged() {
        var option = document.getElementById('mlType').selectedOptions[0];
        if (!option) return;

        var hourly = option.dataset.hourly === '1';
        var times = document.getElementById('mlTimes');
        times.hidden = !hourly;
        times.style.display = hourly ? 'flex' : 'none';
        document.getElementById('mlFrom').required = hourly;
        document.getElementById('mlTo').required = hourly;

        var proof = option.dataset.proof === '1';
        document.getElementById('mlProofNeeded').hidden = !proof;
        document.getElementById('mlFile').required = proof;

        var note = document.getElementById('mlBalance');
        if (hourly) {
            note.textContent = 'Counted in hours, up to ' + (option.dataset.maxhours || '3') + 'h at a time.';
        } else if (option.dataset.quota === '0') {
            note.textContent = 'No yearly limit on this one.';
        } else {
            note.textContent = option.dataset.left + ' left this year.';
        }
    }

    mlTypeChanged();
</script>
@endpush
@endsection
