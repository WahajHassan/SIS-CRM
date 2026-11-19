@extends('layouts.admin')

@section('title', 'Viewrec Signups')

@section('content')
{{--
    Viewrec free signups.

    The form is filled in while the business is still on the phone, so it is
    short and it stays on screen: five a day means coming back to it five times,
    and a form that has to be found again each time is a form that gets skipped.
--}}
@php
    $me = auth()->user();

    // The five a day is asked of the people on the phones. Whoever runs the
    // floor is not one of them, so their page is the list rather than a target.
    $hasTarget = in_array($me->role, ['agent', 'closer'], true);
@endphp

<style>
    /* The day's progress and the figures, side by side across the top of the
       list — the form keeps the right of the page to itself. */
    .vr-top { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.9rem; margin-bottom: 1.2rem; }
    @media (max-width: 760px) { .vr-top { grid-template-columns: 1fr; } }
    .vr-stat { background: var(--bg-card); border: 1px solid var(--border); border-left-width: 4px;
               border-radius: 14px; padding: 0.8rem 0.95rem; }
    .vr-stat span { display: block; font-size: 0.7rem; color: var(--text-muted); margin-bottom: 4px;
                    text-transform: uppercase; letter-spacing: 0.04em; font-weight: 700; }
    .vr-stat strong { font-size: 1.4rem; font-weight: 700; color: var(--text-main); }
    .vr-stat small { display: block; font-size: 0.7rem; color: var(--text-muted); margin-top: 3px; }

    /* Today, against the five. */
    .vr-goal { background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px;
               padding: 0.95rem 1.1rem; margin-bottom: 1.2rem; }
    .vr-goal-top { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 0.7rem; }
    .vr-goal-top b { font-size: 1.05rem; color: var(--text-main); }
    .vr-goal-top span { font-size: 0.82rem; color: var(--text-muted); }
    .vr-pips { display: flex; gap: 8px; }
    .vr-pip { flex: 1; height: 10px; border-radius: 999px; background: var(--bg-body); border: 1px solid var(--border); }
    .vr-pip.is-done { background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-color: transparent; }

    .vr-wrap { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 1.5rem; align-items: start; }
    @media (max-width: 1100px) { .vr-wrap { grid-template-columns: 1fr; } }

    /* The form is what somebody came here to use, so it sits at the top of its
       column with every field already in sight. */
    .vr-form-card { margin-bottom: 0; }
    .vr-form-card .vr-field { margin-bottom: 0.65rem; }
    .vr-form-card .vr-field textarea { min-height: 56px; }

    .vr-field { margin-bottom: 0.8rem; }
    .vr-field label { display: block; font-size: 0.78rem; font-weight: 600; color: var(--text-muted); margin-bottom: 5px; }
    .vr-field input, .vr-field select, .vr-field textarea {
        width: 100%; padding: 0.6rem 0.7rem; border-radius: 10px; border: 1px solid var(--border);
        background: var(--bg-body); color: var(--text-main); font-family: inherit; font-size: 0.86rem;
    }
    .vr-field textarea { min-height: 70px; resize: vertical; }
    .vr-pair { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

    /* One row, whatever is in it. Laid out as columns rather than as something
       that wraps: with flex the buttons kept dropping onto a second line as soon
       as the fields grew to fill the first. */
    .vr-filters { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(0, 1fr);
                  gap: 10px; align-items: end; }
    .vr-filters > div:last-child { width: max-content; }
    .vr-filters .btn { padding: 0 0.9rem; }
    @media (max-width: 900px) {
        .vr-filters { grid-auto-flow: row; grid-template-columns: 1fr 1fr; }
    }
    .vr-filters label { display: block; font-size: 0.68rem; font-weight: 700; letter-spacing: 0.06em;
                        text-transform: uppercase; color: var(--text-soft); margin-bottom: 5px; }
    .vr-filters input, .vr-filters select { width: 100%; height: 38px; padding: 0.45rem 0.6rem; border-radius: 9px;
        border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-size: 0.84rem; font-family: inherit; }

    .vr-status { border: 1px solid var(--border); border-radius: 999px; padding: 2px 9px; font-size: 0.72rem;
                 font-weight: 700; background: var(--bg-body); color: var(--text-main); font-family: inherit; cursor: pointer; }

    /* No form in the column means the list gets the page. */
    .vr-wrap.is-wide { grid-template-columns: 1fr; }

    .vr-modal { position: fixed; inset: 0; z-index: 3000; background: rgba(8, 12, 20, 0.6);
                backdrop-filter: blur(2px); display: flex; align-items: flex-start; justify-content: center;
                padding: 4vh 1.5rem; overflow-y: auto; }
    .vr-modal[hidden] { display: none; }
    .vr-modal-box { width: min(560px, 100%); background: var(--bg-card); border: 1px solid var(--border);
                    border-radius: 18px; box-shadow: 0 26px 60px rgba(0,0,0,0.35); overflow: hidden; }
    .vr-modal-head { display: flex; align-items: center; justify-content: space-between; gap: 10px;
                     padding: 0.9rem 1.2rem; border-bottom: 1px solid var(--border); }
    .vr-modal-head b { font-size: 0.98rem; color: var(--text-main); display: flex; align-items: center; gap: 8px; }
    .vr-modal-head button { border: none; background: transparent; color: var(--text-muted);
                            font-size: 1rem; cursor: pointer; padding: 4px 8px; border-radius: 8px; }
    .vr-modal-head button:hover { color: var(--danger); background: var(--bg-body); }
    .vr-modal-body { padding: 1.1rem 1.2rem 1.4rem; }
    .vr-modal-body .vr-field { margin-bottom: 0.7rem; }

    /* The board only earns a column of its own where there is room for one. */
    .vr-bottom { display: grid; gap: 1.5rem; align-items: start; }
    .vr-bottom.has-board { grid-template-columns: minmax(0, 1fr) 380px; }
    .vr-bottom > .card { margin-bottom: 0; }
    @media (max-width: 1300px) { .vr-bottom.has-board { grid-template-columns: 1fr; } }

    .vr-board { display: flex; flex-direction: column; gap: 9px; }
    .vr-row { display: grid; grid-template-columns: 140px 1fr 54px; gap: 10px; align-items: center; font-size: 0.84rem; }
    .vr-row b { font-weight: 600; color: var(--text-main); }
    .vr-bar { height: 9px; border-radius: 999px; background: var(--bg-body); border: 1px solid var(--border); overflow: hidden; }
    .vr-bar i { display: block; height: 100%; background: linear-gradient(135deg, #f99d4e 0%, #e93b6e 100%); }
    .vr-bar.is-met i { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
    .vr-count { text-align: right; font-variant-numeric: tabular-nums; font-weight: 700; color: var(--text-main); }
</style>

<div class="page-head">
    <div class="page-sub" style="margin: 0;">Free signups, five a day. Short details — enough to get back to them.</div>

    <div class="page-actions">
        @if($manages)
            <a href="{{ route('admin.viewrec.export', request()->all()) }}" class="btn btn-ghost">
                <i class="fas fa-file-csv" style="color: var(--success);"></i> Export CSV
            </a>
        @endif

        @unless($hasTarget)
            <button type="button" class="btn btn-primary" id="vrNewBtn" onclick="vrOpenForm()">
                <i class="fas fa-plus"></i> New free signup
            </button>
        @endunless
    </div>
</div>

<div class="vr-wrap {{ $hasTarget ? '' : 'is-wide' }}">
    <div>
{{-- What this person has done today — only for the people it is asked of. --}}
@if($hasTarget)
<div class="vr-goal">
    <div class="vr-goal-top">
        <b>
            @if($today['left'] > 0)
                {{ $today['done'] }} of {{ $today['target'] }} today
            @else
                All {{ $today['target'] }} done today
            @endif
        </b>
        <span>
            @if($today['left'] > 0)
                {{ $today['left'] }} more to go
            @else
                Anything past this is extra
            @endif
        </span>
    </div>
    <div class="vr-pips">
        @for($i = 1; $i <= max($today['target'], 1); $i++)
            <span class="vr-pip {{ $i <= $today['done'] ? 'is-done' : '' }}"></span>
        @endfor
    </div>
</div>
@endif

<div class="vr-top">
    <div class="card kpi" style="margin-bottom: 0;">
        <div class="kpi-head">
            <div class="kpi-label">Today</div>
            <span class="kpi-icon is-primary"><i class="fas fa-user-plus"></i></span>
        </div>
        <div class="kpi-value" data-count="{{ (int) ($hasTarget ? $today['done'] : $todayOnTheFloor) }}">{{ $hasTarget ? $today['done'] : $todayOnTheFloor }}</div>
        <div class="kpi-foot"><span>{{ $hasTarget ? 'out of ' . $today['target'] . ' asked for' : 'taken across the floor' }}</span></div>
    </div>
    <div class="card kpi" style="margin-bottom: 0;">
        <div class="kpi-head">
            <div class="kpi-label">This month</div>
            <span class="kpi-icon is-violet"><i class="fas fa-calendar-day"></i></span>
        </div>
        <div class="kpi-value" data-count="{{ (int) $monthTotal }}">{{ $monthTotal }}</div>
        <div class="kpi-foot"><span>{{ $manages ? 'across the floor' : 'company wide' }}</span></div>
    </div>
    <div class="card kpi" style="margin-bottom: 0;">
        <div class="kpi-head">
            <div class="kpi-label">On the list</div>
            <span class="kpi-icon is-success"><i class="fas fa-list-check"></i></span>
        </div>
        <div class="kpi-value" data-count="{{ (int) $signups->total() }}">{{ $signups->total() }}</div>
        <div class="kpi-foot"><span>matching what is filtered</span></div>
    </div>
</div>

        {{-- ---------------- the list ---------------- --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-filter" style="color: var(--primary);"></i> Find a signup</div>
            </div>

            <form method="GET" action="{{ route('admin.viewrec') }}" class="vr-filters" id="vrFilters">
                <div>
                    <label for="vrSearch">Search</label>
                    <input type="text" id="vrSearch" name="search" value="{{ request('search') }}" placeholder="Name, business, email…">
                </div>
                <div>
                    <label for="vrOn">Day</label>
                    <input type="date" id="vrOn" name="on" value="{{ request('on') }}">
                </div>
                @if($manages)
                    <div>
                        <label for="vrAgent">Who signed them up</label>
                        <select id="vrAgent" name="agent_id">
                            <option value="">Everybody</option>
                            @foreach($agents as $agent)
                                <option value="{{ $agent->id }}" {{ (string) request('agent_id') === (string) $agent->id ? 'selected' : '' }}>
                                    {{ $agent->display_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div>
                    <label for="vrStatus">Status</label>
                    <select id="vrStatus" name="status">
                        <option value="">Any</option>
                        @foreach(\App\Models\FreeSignup::STATUSES as $key => $label)
                            <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="btn btn-primary" style="height: 38px; padding: 0 1.1rem; border-radius: 9px; cursor: pointer; font-weight: 600;">Apply</button>
                    <a href="{{ route('admin.viewrec') }}" class="btn btn-ghost"
                       style="border: 1px solid var(--border); height: 38px; display: flex; align-items: center; padding: 0 0.9rem; border-radius: 9px; text-decoration: none; font-weight: 600;">Clear</a>
                </div>
            </form>
        </div>

        {{-- The list and who did what today, side by side: they answer the same
             question from two directions, so they are read together. --}}
        <div class="vr-bottom {{ $manages && $board->count() ? 'has-board' : '' }}">
        <div class="card">
            <div class="card-header">
                <div class="card-title">{{ $manages ? 'Everybody\'s signups' : 'Your signups' }}</div>
            </div>

            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Who</th>
                            <th>Contact</th>
                            <th>Trade / Where</th>
                            @if($manages)<th>Signed up by</th>@endif
                            <th>Status</th>
                            @if($manages)<th></th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($signups as $signup)
                            <tr>
                                <td style="white-space: nowrap; color: var(--text-muted); font-size: 0.82rem;">
                                    {{ $signup->created_at->format('d M, h:i A') }}
                                </td>
                                <td>
                                    <div style="font-weight: 600;">{{ $signup->business_name ?: $signup->name }}</div>
                                    @if($signup->business_name)
                                        <div style="font-size: 0.74rem; color: var(--text-muted);">{{ $signup->name }}</div>
                                    @endif
                                </td>
                                <td style="font-size: 0.82rem;">
                                    @if($signup->phone)<div><i class="fas fa-phone" style="opacity: 0.5;"></i> {{ $signup->phone }}</div>@endif
                                    @if($signup->email)<div><i class="fas fa-envelope" style="opacity: 0.5;"></i> {{ $signup->email }}</div>@endif
                                </td>
                                <td style="font-size: 0.8rem;">
                                    @if($signup->category)
                                        <span style="display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 0.68rem; font-weight: 700; background: rgba(99,102,241,0.12); color: #6366f1;">{{ $signup->category->name }}</span>
                                    @endif
                                    <div style="color: var(--text-muted); margin-top: 3px;">{{ $signup->place() ?: '—' }}</div>
                                </td>
                                @if($manages)
                                    <td style="font-size: 0.82rem;">{{ $signup->agent?->display_name ?? '—' }}</td>
                                @endif
                                <td>
                                    <form action="{{ route('admin.viewrec.status', $signup->id) }}" method="POST" style="margin: 0;">
                                        @csrf @method('PUT')
                                        <select name="status" class="vr-status" onchange="this.form.submit()">
                                            @foreach(\App\Models\FreeSignup::STATUSES as $key => $label)
                                                <option value="{{ $key }}" {{ $signup->status === $key ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                </td>
                                @if($manages)
                                    <td>
                                        <form action="{{ route('admin.viewrec.destroy', $signup->id) }}" method="POST"
                                              onsubmit="confirmDelete(event, this, 'Remove this signup?')" style="margin: 0;">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-ghost btn-sm" style="color: var(--danger);" title="Remove">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="fas fa-user-plus"></i>
                                        <strong>No free signups yet</strong>
                                        {{-- Where the form is depends on who is looking: beside the list
                                             for the people asked for five a day, in a box for everybody else. --}}
                                        <p>{{ $hasTarget ? 'The form beside this list is where they go.' : 'Use “New free signup” above to add one.' }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(method_exists($signups, 'links'))
                <div style="margin-top: 1rem;">{{ $signups->links() }}</div>
            @endif
        </div>

        {{-- ---------------- today, per person ---------------- --}}
        @if($manages && $board->count())
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-bullseye" style="color: var(--primary);"></i> Today, per person</div>
                    <div style="font-size: 0.8rem; color: var(--text-muted);">
                        {{ $board->where('met', true)->count() }} of {{ $board->count() }} have done their {{ $today['target'] }}
                    </div>
                </div>

                <div class="vr-board">
                    @foreach($board as $row)
                        <div class="vr-row">
                            <b>{{ $row['user']->display_name }}</b>
                            <div class="vr-bar {{ $row['met'] ? 'is-met' : '' }}">
                                <i style="width: {{ $row['progress'] }}%;"></i>
                            </div>
                            <div class="vr-count" style="{{ $row['met'] ? 'color: #10b981;' : '' }}">
                                {{ $row['done'] }}/{{ $row['target'] }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
        </div>
    </div>

    {{-- ---------------- the form ---------------- --}}
    @if($hasTarget)
        <div class="card vr-form-card" style="position: sticky; top: 1rem;">
            <div class="card-header" style="border-bottom: none; margin-bottom: 0;">
                <div class="card-title" style="display: flex; align-items: center; gap: 8px; color: var(--primary);">
                    <i class="fas fa-plus-circle"></i> New free signup
                </div>
            </div>

            @include('admin.viewrec._form')
        </div>
    @endif
</div>

{{-- For whoever only takes one now and then: the same form, in a box, out of
     the way of the list they actually came for. --}}
@unless($hasTarget)
    <div class="vr-modal" id="vrModal" hidden>
        <div class="vr-modal-box">
            <div class="vr-modal-head">
                <b><i class="fas fa-plus-circle" style="color: var(--primary);"></i> New free signup</b>
                <button type="button" onclick="vrCloseForm()" title="Close"><i class="fas fa-xmark"></i></button>
            </div>

            <div class="vr-modal-body">
                @include('admin.viewrec._form')
            </div>
        </div>
    </div>
@endunless

<script>
/*
 * The box, for whoever is not sitting in front of this form all day.
 *
 * It opens on the button and again on its own when something was refused —
 * otherwise the reason would be behind a box nobody has opened.
 */
function vrOpenForm() {
    var box = document.getElementById('vrModal');
    if (!box) return;

    box.hidden = false;
    document.body.style.overflow = 'hidden';

    var first = document.getElementById('vrName');
    if (first) first.focus();
}

function vrCloseForm() {
    var box = document.getElementById('vrModal');
    if (!box) return;

    box.hidden = true;
    document.body.style.overflow = '';
}

document.addEventListener('DOMContentLoaded', function () {
    var box = document.getElementById('vrModal');
    if (!box) return;

    // Clicking the dark around it closes it; the Escape key does too.
    box.addEventListener('click', function (e) { if (e.target === box) vrCloseForm(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') vrCloseForm(); });

    // Something was refused: open it again on the reason, rather than leaving
    // it behind a box nobody has opened.
    if (box.querySelector('.vr-error')) vrOpenForm();
});

/* The city list follows the state, the same as everywhere else it is asked for. */
(function () {
    var state = document.getElementById('vrState');
    var city = document.getElementById('vrCity');
    if (!state || !city) return;

    function load() {
        var wanted = city.dataset.selected || '';

        if (!state.value) {
            city.innerHTML = '<option value="">Pick a state first</option>';
            return;
        }

        city.innerHTML = '<option value="">Loading…</option>';

        fetch('{{ url('admin/states') }}/' + state.value + '/cities', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
        .then(function (res) { return res.ok ? res.json() : { cities: [] }; })
        .then(function (data) {
            var html = '<option value="">Not set</option>';

            (data.cities || []).forEach(function (c) {
                html += '<option value="' + c.id + '"' + (String(c.id) === String(wanted) ? ' selected' : '') + '>'
                     + c.name.replace(/</g, '&lt;') + '</option>';
            });

            city.innerHTML = html;
        })
        .catch(function () { city.innerHTML = '<option value="">Could not load cities</option>'; });
    }

    state.addEventListener('change', function () { city.dataset.selected = ''; load(); });

    if (state.value) load();
})();
</script>
@endsection
