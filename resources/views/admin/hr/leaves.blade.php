@extends('layouts.admin')

@section('title', 'Leave Management')

@section('content')
{{--
    The leave desk. Requests waiting on a decision come first, because that is the
    only part of this page that is holding somebody up. Approved leave of a paid
    type stops counting as absence on the salary sheet.
--}}
<style>
    .lv-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 1rem; margin-bottom: 1.4rem; }
    .lv-stat { border: 1px solid var(--border); border-radius: 14px; padding: 0.95rem 1.1rem; background: var(--bg-card); }
    .lv-stat span { display: block; font-size: 0.76rem; color: var(--text-muted); margin-bottom: 4px; }
    .lv-stat strong { font-size: 1.45rem; font-weight: 700; color: var(--text-main); }

    .lv-filters { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px; }
    .lv-filters > div { flex: 1 1 150px; min-width: 0; }
    .lv-filters > div:last-child { flex: 0 0 auto; }
    .lv-filters label, .lv-form label { display: block; font-size: 0.78rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; }
    .lv-filters select, .lv-form input, .lv-form select, .lv-form textarea {
        width: 100%; padding: 0.55rem 0.65rem; border-radius: 9px; border: 1px solid var(--border);
        background: var(--bg-card); color: var(--text-main); font-family: inherit; font-size: 0.86rem;
    }

    .lv-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; align-items: end; }
    .lv-form .wide { grid-column: span 2; }
    .lv-check { display: inline-flex; align-items: center; gap: 7px; font-size: 0.84rem; color: var(--text-main); font-weight: 500; }
    .lv-check input { width: auto; }

    .lv-chip { display: inline-block; padding: 0.22rem 0.6rem; border-radius: 999px; font-size: 0.73rem; font-weight: 700; }
    .lv-type-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin-right: 6px; }

    .lv-decide { display: flex; gap: 6px; flex-wrap: wrap; }
    .lv-decide button { border: 1px solid var(--border); background: var(--bg-body); color: var(--text-main); border-radius: 8px; padding: 0.3rem 0.65rem; font-size: 0.76rem; cursor: pointer; font-family: inherit; font-weight: 600; }
    .lv-decide button.ok { color: var(--success); }
    .lv-decide button.no { color: var(--danger); }

    .lv-quota-num { text-align: center; font-variant-numeric: tabular-nums; }
    .lv-quota-num b { color: var(--text-main); }
    .lv-quota-num small { display: block; color: var(--text-muted); font-size: 0.72rem; }
    .lv-quota-num small.lv-month-over { color: var(--danger); font-weight: 700; }

    .lv-tabs { display: flex; gap: 8px; margin-bottom: 1.2rem; flex-wrap: wrap; }
    .lv-tab { padding: 0.45rem 1rem; border-radius: 999px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-muted); font-size: 0.84rem; font-weight: 600; cursor: pointer; font-family: inherit; }
    .lv-tab.active { background: var(--primary); border-color: var(--primary); color: #fff; }
</style>

<div style="display: flex; flex-direction: column; gap: 1.5rem;">
    <div class="card" style="margin-bottom: 0;">
        <div style="border-bottom: 1px solid var(--border); padding-bottom: 1rem; margin-bottom: 1.3rem;">
            <div class="card-title" style="margin-bottom: 5px; font-size: 1.2rem; font-weight: 700;">
                <i class="fas fa-calendar-check" style="color: var(--primary);"></i> Leave Management
            </div>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">
                Requests, quotas and the kinds of leave this company gives. Approved paid leave is not counted as absence in salary,
                and the days are marked on the attendance automatically.
                @if($leadOnly)
                    <b>You are seeing your own department.</b>
                @elseif($flow->requiresTeamLeadApproval())
                    Team leads approve first; HR has the final word.
                @endif
            </p>
        </div>

        <div class="lv-stats">
            <div class="lv-stat"><span>Waiting on you</span><strong style="color: var(--warning);">{{ $pendingCount }}</strong></div>
            <div class="lv-stat"><span>Approved in {{ $year }}</span><strong style="color: var(--success);">{{ $requests->where('status', 'approved')->count() }}</strong></div>
            <div class="lv-stat"><span>Rejected in {{ $year }}</span><strong style="color: var(--danger);">{{ $requests->where('status', 'rejected')->count() }}</strong></div>
            <div class="lv-stat"><span>Leave days taken</span><strong>{{ rtrim(rtrim(number_format($requests->where('status', 'approved')->sum('days'), 1), '0'), '.') }}</strong></div>
        </div>

        <form method="GET" action="{{ route('admin.hr.leaves') }}" class="lv-filters">
            <div>
                <label for="lvYear">Year</label>
                <select id="lvYear" name="year" onchange="this.form.submit()">
                    @foreach($years as $y)
                        <option value="{{ $y }}" {{ (int) $year === $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="lvStatus">Status</label>
                <select id="lvStatus" name="status" onchange="this.form.submit()">
                    <option value="">All</option>
                    @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'] as $key => $label)
                        <option value="{{ $key }}" {{ $status === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="lvUser">Employee</label>
                <select id="lvUser" name="user_id" onchange="this.form.submit()">
                    <option value="">Everyone</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" {{ (string) $userId === (string) $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display: flex; gap: 8px;">
                <a href="{{ route('admin.hr.leaves') }}" class="btn btn-ghost"
                   style="border: 1px solid var(--border); height: 36px; display: flex; align-items: center; padding: 0 1rem; border-radius: 9px; text-decoration: none; font-weight: 600;">Clear</a>
            </div>
        </form>
    </div>

    {{-- ---- record a leave ---- --}}
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header"><div class="card-title"><i class="fas fa-plus" style="color: var(--primary);"></i> Record a leave</div></div>
        <form method="POST" action="{{ route('admin.hr.leaves.store') }}" class="lv-form">
            @csrf
            <div>
                <label for="nlUser">Employee</label>
                <select id="nlUser" name="user_id" required>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="nlType">Leave type</label>
                <select id="nlType" name="leave_type_id" required>
                    @foreach($types->where('is_active', true) as $type)
                        <option value="{{ $type->id }}">{{ $type->name }}{{ $type->is_paid ? '' : ' (unpaid)' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="nlStart">From</label>
                <input type="date" id="nlStart" name="start_date" value="{{ now()->toDateString() }}" required>
            </div>
            <div>
                <label for="nlEnd">To</label>
                <input type="date" id="nlEnd" name="end_date" value="{{ now()->toDateString() }}" required>
            </div>
            <div class="wide">
                <label for="nlReason">Reason</label>
                <input type="text" id="nlReason" name="reason" placeholder="Optional">
            </div>
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <label class="lv-check"><input type="checkbox" name="half_day" value="1"> Half day</label>
                <label class="lv-check"><input type="checkbox" name="approve_now" value="1" checked> Approve straight away</label>
            </div>
            <div>
                <button type="submit" class="btn btn-primary" >
                    <i class="fas fa-check"></i> Save leave
                </button>
            </div>
        </form>
        <p style="font-size: 0.78rem; color: var(--text-muted); margin: 0.9rem 0 0;">
            Weekly offs inside the dates are not charged to the quota.
        </p>
    </div>

    {{-- ---- requests ---- --}}
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div class="card-title">Leave records — {{ $year }}</div>
        </div>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr><th>Employee</th><th>Type</th><th>Dates</th><th>Days</th><th>Reason</th><th>Status</th><th>Decision</th><th></th></tr>
                </thead>
                <tbody>
                @forelse($requests as $leave)
                    <tr>
                        <td style="font-weight: 600; color: var(--text-main);">
                            <a href="{{ route('admin.hr.employee', $leave->user_id) }}" style="color: inherit; text-decoration: none;">{{ $leave->user->name ?? 'Deleted user' }}</a>
                        </td>
                        <td>
                            <span class="lv-type-dot" style="background: {{ $leave->type->colour ?? '#6366f1' }};"></span>{{ $leave->type->name ?? '—' }}
                            @unless($leave->type?->is_paid)<div style="font-size: 0.72rem; color: var(--danger);">unpaid</div>@endunless
                        </td>
                        <td>
                            {{ $leave->start_date->format('d M') }} – {{ $leave->end_date->format('d M Y') }}
                        </td>
                        <td>{{ rtrim(rtrim(number_format($leave->days, 1), '0'), '.') }}{{ $leave->half_day ? ' (half)' : '' }}</td>
                        <td style="max-width: 260px; white-space: normal;">{{ $leave->reason ?: '—' }}</td>
                        <td>
                            <span class="lv-chip" style="background: {{ $leave->statusColour() }}1f; color: {{ $leave->statusColour() }};">{{ $leave->statusLabel() }}</span>
                            @if($leave->attachment_path)
                                <div style="margin-top: 4px;">
                                    <a href="{{ asset('storage/' . $leave->attachment_path) }}" target="_blank" style="font-size: 0.72rem; color: var(--primary);">
                                        <i class="fas fa-paperclip"></i> proof
                                    </a>
                                </div>
                            @endif
                        </td>
                        <td style="font-size: 0.79rem; color: var(--text-muted);">
                            @if($leave->tl_approved_at)
                                <div>TL: {{ $leave->teamLead->name ?? '—' }} &middot; {{ $leave->tl_approved_at->format('d M') }}</div>
                            @endif
                            @if($leave->decided_at)
                                {{ $leave->decider->name ?? '—' }}<div>{{ $leave->decided_at->format('d M Y') }}</div>
                                @if($leave->decision_note)<div style="font-style: italic;">{{ $leave->decision_note }}</div>@endif
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <div class="lv-decide">
                                @if($leave->isOpen())
                                    <form method="POST" action="{{ route('admin.hr.leaves.decide', $leave->id) }}">
                                        @csrf
                                        <input type="hidden" name="status" value="approved">
                                        <button type="submit" class="ok">
                                            <i class="fas fa-check"></i>
                                            {{ $flow->requiresTeamLeadApproval() && $leadOnly && $leave->status === 'pending' ? 'Pass to HR' : 'Approve' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.hr.leaves.decide', $leave->id) }}"
                                          onsubmit="this.decision_note.value = prompt('Reason for rejecting (optional)') || '';">
                                        @csrf
                                        <input type="hidden" name="status" value="rejected">
                                        <input type="hidden" name="decision_note" value="">
                                        <button type="submit" class="no"><i class="fas fa-xmark"></i> Reject</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.hr.leaves.destroy', $leave->id) }}"
                                          onsubmit="return confirm('Delete this leave record?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="no"><i class="fas fa-trash"></i></button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="text-align: center; padding: 3rem; color: var(--text-muted); ">
                        <i class="fas fa-calendar-check" style="font-size: 2rem; color: var(--border); display: block; margin-bottom: 1rem;"></i>
                        No leave records for this filter.
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ---- quota board ---- --}}
    <div class="card" style="margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; border-bottom: 1px solid var(--border); padding-bottom: 1rem; margin-bottom: 1.2rem;">
            <div>
                <div class="card-title" style="font-size: 1.1rem; font-weight: 700;">
                    <i class="fas fa-table-cells" style="color: var(--primary);"></i> Leave quota — {{ $year }}
                </div>
                <p style="font-size: 0.8rem; color: var(--text-muted); margin: 4px 0 0;">
                    Big figure is the year; underneath is {{ $boardMonth->format('F') }}, against the monthly allowance set below.
                </p>
            </div>
            <form method="GET" action="{{ route('admin.hr.leaves') }}">
                <input type="hidden" name="year" value="{{ $year }}">
                <input type="hidden" name="status" value="{{ $status }}">
                <input type="hidden" name="user_id" value="{{ $userId }}">
                <select name="board_month" onchange="this.form.submit()"
                        style="height: 36px; padding: 0 0.6rem; border-radius: 9px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-family: inherit;">
                    @for($m = 1; $m <= 12; $m++)
                        @php $option = \Carbon\Carbon::create((int) $year, $m, 1); @endphp
                        <option value="{{ $option->format('Y-m') }}" {{ $boardMonth->format('Y-m') === $option->format('Y-m') ? 'selected' : '' }}>
                            {{ $option->format('F Y') }}
                        </option>
                    @endfor
                </select>
            </form>
        </div>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        @foreach($types as $type)
                            <th class="lv-quota-num">
                                <span class="lv-type-dot" style="background: {{ $type->colour }};"></span>{{ $type->name }}
                                <div style="font-weight: 500; text-transform: none; font-size: 0.68rem; color: var(--text-muted);">{{ $type->allowanceText() }}</div>
                            </th>
                        @endforeach
                        <th class="lv-quota-num">Total taken</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($quotaBoard as $row)
                    <tr>
                        <td style="font-weight: 600; color: var(--text-main);">{{ $row['user']->name }}</td>
                        @foreach($row['cells'] as $cell)
                            <td class="lv-quota-num">
                                <b>{{ rtrim(rtrim(number_format($cell['used'], 1), '0'), '.') }}</b>
                                <small>
                                    @if($cell['type']->annual_quota > 0)
                                        {{ rtrim(rtrim(number_format($cell['left'], 1), '0'), '.') }} left this year
                                    @else
                                        no yearly limit
                                    @endif
                                </small>
                                <small @class(['lv-month-over' => $cell['month_left'] !== null && $cell['month_left'] <= 0 && $cell['month_used'] > 0])>
                                    {{ rtrim(rtrim(number_format($cell['month_used'], 1), '0'), '.') }} in {{ $boardMonth->format('M') }}
                                    @if($cell['month_left'] !== null)
                                        &middot; {{ rtrim(rtrim(number_format($cell['month_left'], 1), '0'), '.') }} left
                                    @endif
                                </small>
                            </td>
                        @endforeach
                        <td class="lv-quota-num"><b>{{ rtrim(rtrim(number_format($row['total_used'], 1), '0'), '.') }}</b></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- ---- leave types ---- --}}
    <div class="card">
        <div class="card-header"><div class="card-title"><i class="fas fa-tags" style="color: var(--primary);"></i> Types of leave</div></div>

        <div style="overflow-x: auto; margin-bottom: 1.3rem;">
            <table>
                <thead><tr><th>Name</th><th>Code</th><th>Days a year</th><th>Days a month</th><th>Paid</th><th>Proof</th><th>Colour</th><th>Active</th><th></th></tr></thead>
                <tbody>
                @foreach($types as $type)
                    {{--
                        A form cannot wrap table cells — the browser hoists it out of
                        the table and the row stops saving. The inputs sit in their own
                        cells and point at the form in the last one with form="…",
                        which is valid HTML and submits the whole row.
                    --}}
                    @php $fid = 'ltForm_' . $type->id; @endphp
                    <tr>
                        <td>
                            <input form="{{ $fid }}" type="text" name="name" value="{{ $type->name }}" required
                                   style="width: 160px; padding: 0.4rem 0.55rem; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-family: inherit;">
                            @if($type->is_system)
                                <div style="font-size: 0.68rem; color: var(--text-muted); margin-top: 3px;">created by the system</div>
                            @elseif($type->isHourly())
                                <div style="font-size: 0.68rem; color: var(--text-muted); margin-top: 3px;">counted in hours, up to {{ rtrim(rtrim(number_format($type->max_hours, 1), '0'), '.') }}h</div>
                            @endif
                        </td>
                        <td><input form="{{ $fid }}" type="text" name="code" value="{{ $type->code }}" maxlength="10" title="Printed on the monthly sheet"
                                   style="width: 70px; text-transform: uppercase; padding: 0.4rem 0.55rem; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-family: inherit;"></td>
                        <td><input form="{{ $fid }}" type="number" name="annual_quota" value="{{ $type->annual_quota }}" min="0" max="365" title="0 means no yearly limit"
                                   style="width: 80px; padding: 0.4rem 0.55rem; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-family: inherit;"></td>
                        <td><input form="{{ $fid }}" type="number" name="monthly_quota" value="{{ $type->monthly_quota }}" min="0" max="31" title="0 means no monthly limit"
                                   style="width: 80px; padding: 0.4rem 0.55rem; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-family: inherit;"></td>
                        <td><input form="{{ $fid }}" type="checkbox" name="is_paid" value="1" {{ $type->is_paid ? 'checked' : '' }}></td>
                        <td><input form="{{ $fid }}" type="checkbox" name="requires_attachment" value="1" {{ $type->requires_attachment ? 'checked' : '' }} title="A document must be attached when applying"></td>
                        <td><input form="{{ $fid }}" type="color" name="colour" value="{{ $type->colour }}" style="width: 44px; height: 30px; padding: 2px; border-radius: 7px; border: 1px solid var(--border); background: var(--bg-card);"></td>
                        <td><input form="{{ $fid }}" type="checkbox" name="is_active" value="1" {{ $type->is_active ? 'checked' : '' }}></td>
                        <td>
                            <div style="display: flex; gap: 6px;">
                                <form id="{{ $fid }}" method="POST" action="{{ route('admin.hr.leave_types.update', $type->id) }}">
                                    @csrf @method('PUT')
                                    <button type="submit" class="btn btn-ghost" style="border: 1px solid var(--border); padding: 0.3rem 0.65rem; font-size: 0.76rem; border-radius: 8px; cursor: pointer;">Save</button>
                                </form>
                                <form method="POST" action="{{ route('admin.hr.leave_types.delete', $type->id) }}" onsubmit="return confirm('Delete {{ addslashes($type->name) }}?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-ghost" style="border: 1px solid var(--border); padding: 0.3rem 0.65rem; font-size: 0.76rem; border-radius: 8px; color: var(--danger); cursor: pointer;"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <form method="POST" action="{{ route('admin.hr.leave_types.store') }}" class="lv-form">
            @csrf
            <div>
                <label for="ltName">New leave type</label>
                <input type="text" id="ltName" name="name" placeholder="e.g. Marriage Leave" required>
            </div>
            <div>
                <label for="ltQuota">Days a year</label>
                <input type="number" id="ltQuota" name="annual_quota" value="0" min="0" max="365" required>
            </div>
            <div>
                <label for="ltMonthly">Days a month</label>
                <input type="number" id="ltMonthly" name="monthly_quota" value="0" min="0" max="31" required>
            </div>
            <div>
                <label for="ltCode">Code</label>
                <input type="text" id="ltCode" name="code" maxlength="10" placeholder="e.g. ML" style="text-transform: uppercase;">
            </div>
            <div>
                <label for="ltColour">Colour</label>
                <input type="color" id="ltColour" name="colour" value="#6366f1" style="height: 38px; padding: 3px;">
            </div>
            <div style="display: flex; align-items: center; gap: 14px; height: 38px; flex-wrap: wrap;">
                <label class="lv-check"><input type="checkbox" name="is_paid" value="1" checked> Paid leave</label>
                <label class="lv-check"><input type="checkbox" name="requires_attachment" value="1"> Needs proof</label>
            </div>
            <div>
                <button type="submit" class="btn btn-primary" >
                    <i class="fas fa-plus"></i> Add type
                </button>
            </div>
        </form>
        <p style="font-size: 0.78rem; color: var(--text-muted); margin: 1rem 0 0;">
            This is where every allowance is set. 0 in either box means no limit of that kind.
            An employee asking for more than their allowance is refused; you can still record it for them, and the
            note will say it is over.
        </p>
    </div>
</div>
@endsection
