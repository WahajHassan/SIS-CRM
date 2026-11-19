@extends('layouts.admin')

@section('title', 'Job Descriptions & KPIs')

@section('content')
{{--
    Two things per person: what the role is, and what the numbers are this month.
    Both are edited in the row itself, the same expanding panel the Team and Users
    pages use, so nothing here opens a dialog over the list.
--}}
<style>
    .kp-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 1rem; margin-bottom: 1.4rem; }
    .kp-stat { border: 1px solid var(--border); border-radius: 14px; padding: 0.95rem 1.1rem; background: var(--bg-card); }
    .kp-stat span { display: block; font-size: 0.76rem; color: var(--text-muted); margin-bottom: 4px; }
    .kp-stat strong { font-size: 1.45rem; font-weight: 700; color: var(--text-main); }

    .kp-bar { width: 120px; height: 7px; border-radius: 999px; background: var(--bg-body); border: 1px solid var(--border); overflow: hidden; }
    .kp-bar i { display: block; height: 100%; }
    .kp-sub { font-size: 0.74rem; color: var(--text-muted); }

    .kp-panel-row > td { padding: 0 !important; border: none !important; }
    .kp-panel { background: var(--bg-body); border-top: 3px solid var(--primary); border-bottom: 1px solid var(--border); padding: 1.5rem clamp(1rem, 4vw, 2.2rem); }
    .kp-panel-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem; }
    .kp-panel-title { font-size: 1.05rem; font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 8px; }
    .kp-panel-title i { color: var(--primary); }

    .kp-two { display: grid; grid-template-columns: 1fr 1fr; gap: 1.6rem; }
    @media (max-width: 1000px) { .kp-two { grid-template-columns: 1fr; } }

    .kp-field { margin-bottom: 0.9rem; }
    .kp-field label { display: block; font-size: 0.78rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; }
    .kp-field input, .kp-field textarea, .kp-field select {
        width: 100%; padding: 0.55rem 0.65rem; border-radius: 9px; border: 1px solid var(--border);
        background: var(--bg-card); color: var(--text-main); font-family: inherit; font-size: 0.86rem;
    }
    .kp-field textarea { min-height: 110px; resize: vertical; line-height: 1.6; }
    .kp-hint { font-size: 0.73rem; color: var(--text-muted); margin-top: 5px; }

    .kp-kpi { border: 1px solid var(--border); border-radius: 11px; padding: 0.75rem 0.9rem; background: var(--bg-card); margin-bottom: 10px; }
    .kp-kpi-top { display: flex; justify-content: space-between; align-items: center; gap: 10px; }
    .kp-kpi-top b { color: var(--text-main); font-size: 0.9rem; }
    .kp-kpi-inputs { display: flex; gap: 6px; align-items: center; margin-top: 8px; flex-wrap: wrap; }
    .kp-kpi-inputs input { width: 110px; padding: 0.35rem 0.5rem; border-radius: 7px; border: 1px solid var(--border); background: var(--bg-body); color: var(--text-main); font-family: inherit; font-size: 0.8rem; }
    .kp-kpi-inputs button { border: 1px solid var(--border); background: var(--bg-body); color: var(--text-main); border-radius: 7px; padding: 0.35rem 0.6rem; font-size: 0.78rem; cursor: pointer; font-family: inherit; font-weight: 600; }

    .kp-auto { display: inline-flex; align-items: center; gap: 6px; margin-top: 8px; font-size: 0.73rem; font-weight: 700; color: var(--primary); background: rgba(99,102,241,0.1); border-radius: 999px; padding: 0.2rem 0.6rem; }

    .kp-add { display: grid; grid-template-columns: 2fr 1.4fr 1fr 1fr 1fr auto; gap: 8px; align-items: end; margin-top: 1rem; }
    @media (max-width: 1100px) { .kp-add { grid-template-columns: 1fr 1fr 1fr; } }
    @media (max-width: 720px) { .kp-add { grid-template-columns: 1fr 1fr; } }
</style>

<div style="display: flex; flex-direction: column; gap: 1.5rem;">
    <div class="card" style="margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 15px; flex-wrap: wrap; border-bottom: 1px solid var(--border); padding-bottom: 1rem; margin-bottom: 1.3rem;">
            <div>
                <div class="card-title" style="margin-bottom: 5px; font-size: 1.2rem; font-weight: 700;">
                    <i class="fas fa-bullseye" style="color: var(--primary);"></i> Job Descriptions &amp; KPIs
                </div>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">
                    What each person is here to do, and the targets they are measured on this month.
                </p>
            </div>
            <form method="GET" action="{{ route('admin.hr.performance') }}">
                <select name="month" onchange="this.form.submit()"
                        style="height: 38px; padding: 0 0.7rem; border-radius: 9px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-family: inherit;">
                    @foreach($months as $m)
                        <option value="{{ $m->format('Y-m') }}" {{ $month->format('Y-m') === $m->format('Y-m') ? 'selected' : '' }}>{{ $m->format('F Y') }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="kp-stats">
            <div class="kp-stat"><span>Have a job description</span><strong>{{ $totals['with_jd'] }}</strong></div>
            <div class="kp-stat"><span>Have targets this month</span><strong>{{ $totals['with_kpi'] }}</strong></div>
            <div class="kp-stat"><span>At 80% or better</span><strong style="color: var(--success);">{{ $totals['on_track'] }}</strong></div>
            <div class="kp-stat"><span>Below half</span><strong style="color: var(--danger);">{{ $totals['behind'] }}</strong></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><div class="card-title">Team — {{ $month->format('F Y') }}</div></div>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr><th>Employee</th><th>Role / designation</th><th>Job description</th><th>Targets</th><th>Progress</th><th></th></tr>
                </thead>
                <tbody>
                @foreach($rows as $row)
                    @php $u = $row['user']; $jd = $row['jd']; @endphp
                    <tr id="emp{{ $u->id }}">
                        <td style="font-weight: 600; color: var(--text-main);">
                            {{ $u->name }}
                            <div class="kp-sub">{{ $u->employeeProfile?->department ?: '—' }}</div>
                        </td>
                        <td>
                            {{ $u->employeeProfile?->designation ?: ucfirst(str_replace('_', ' ', $u->role)) }}
                            <div class="kp-sub" style="text-transform: uppercase;">{{ str_replace('_', ' ', $u->role) }}</div>
                        </td>
                        <td>
                            @if($jd && filled($jd->responsibilities))
                                <span style="color: var(--success); font-weight: 600;"><i class="fas fa-circle-check"></i> {{ count($jd->lines('responsibilities')) }} duties</span>
                            @else
                                <span style="color: var(--text-muted);">Not written</span>
                            @endif
                        </td>
                        <td>{{ $row['kpis']->count() ?: '—' }}</td>
                        <td>
                            @if($row['progress'] !== null)
                                @php $c = $row['progress'] >= 80 ? 'var(--success)' : ($row['progress'] >= 50 ? 'var(--warning)' : 'var(--danger)'); @endphp
                                <div class="kp-bar"><i style="width: {{ $row['progress'] }}%; background: {{ $c }};"></i></div>
                                <div class="kp-sub">{{ $row['progress'] }}% of target</div>
                            @else
                                <span style="color: var(--text-muted);">—</span>
                            @endif
                        </td>
                        <td>
                            <button type="button" class="btn btn-ghost" onclick="kpToggle({{ $u->id }}, this)"
                                    aria-expanded="false" aria-controls="kpPanel_{{ $u->id }}"
                                    style="border: 1px solid var(--border); padding: 0.35rem 0.8rem; font-size: 0.78rem; border-radius: 8px; cursor: pointer; white-space: nowrap;">
                                <i class="fas fa-pen-to-square"></i> Edit
                            </button>
                        </td>
                    </tr>

                    <tr id="kpPanel_{{ $u->id }}" class="kp-panel-row" style="display: none;">
                        <td colspan="6">
                            <div class="kp-panel">
                                <div class="kp-panel-head">
                                    <div class="kp-panel-title"><i class="fas fa-user-gear"></i> {{ $u->name }} — {{ $month->format('F Y') }}</div>
                                    <button type="button" class="btn btn-ghost" style="cursor: pointer;" onclick="kpToggle({{ $u->id }})">&times;</button>
                                </div>

                                <div class="kp-two">
                                    {{-- job description --}}
                                    <form method="POST" action="{{ route('admin.hr.jd.save', $u->id) }}">
                                        @csrf
                                        <h4 style="font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); margin: 0 0 1rem;">Job description</h4>
                                        <div class="kp-field">
                                            <label for="jdTitle{{ $u->id }}">Job title</label>
                                            <input type="text" id="jdTitle{{ $u->id }}" name="title" value="{{ $jd->title ?? ($u->employeeProfile?->designation) }}" placeholder="e.g. Front-end Developer">
                                        </div>
                                        <div class="kp-field">
                                            <label for="jdReports{{ $u->id }}">Reports to</label>
                                            <input type="text" id="jdReports{{ $u->id }}" name="reports_to" value="{{ $jd->reports_to ?? '' }}" placeholder="e.g. Tech Lead">
                                        </div>
                                        <div class="kp-field">
                                            <label for="jdPurpose{{ $u->id }}">Purpose of the role</label>
                                            <textarea id="jdPurpose{{ $u->id }}" name="purpose" style="min-height: 70px;">{{ $jd->purpose ?? '' }}</textarea>
                                        </div>
                                        <div class="kp-field">
                                            <label for="jdResp{{ $u->id }}">Responsibilities</label>
                                            <textarea id="jdResp{{ $u->id }}" name="responsibilities">{{ $jd->responsibilities ?? '' }}</textarea>
                                            <div class="kp-hint">One per line.</div>
                                        </div>
                                        <div class="kp-field">
                                            <label for="jdReq{{ $u->id }}">Requirements / skills</label>
                                            <textarea id="jdReq{{ $u->id }}" name="requirements" style="min-height: 80px;">{{ $jd->requirements ?? '' }}</textarea>
                                            <div class="kp-hint">One per line.</div>
                                        </div>
                                        <div class="kp-field">
                                            <label for="jdTools{{ $u->id }}">Tools used</label>
                                            <input type="text" id="jdTools{{ $u->id }}" name="tools" value="{{ $jd->tools ?? '' }}" placeholder="CRM, Git, Figma…">
                                        </div>
                                        <button type="submit" class="btn btn-primary" style="cursor: pointer; font-weight: 600;">
                                            <i class="fas fa-floppy-disk"></i> Save job description
                                        </button>
                                        @if($jd && $jd->updated_at)
                                            <div class="kp-hint">Last saved {{ $jd->updated_at->diffForHumans() }}{{ $jd->editor ? ' by ' . $jd->editor->name : '' }}.</div>
                                        @endif
                                    </form>

                                    {{-- targets --}}
                                    <div>
                                        <h4 style="font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); margin: 0 0 1rem;">
                                            Targets for {{ $month->format('F Y') }}
                                        </h4>

                                        @forelse($row['kpis'] as $kpi)
                                            @php $c = $kpi->progress() >= 80 ? 'var(--success)' : ($kpi->progress() >= 50 ? 'var(--warning)' : 'var(--danger)'); @endphp
                                            <div class="kp-kpi">
                                                <div class="kp-kpi-top">
                                                    <b>{{ $kpi->metric }}</b>
                                                    <span style="color: {{ $c }}; font-weight: 700; font-size: 0.85rem;">{{ $kpi->progress() }}%</span>
                                                </div>
                                                <div class="kp-sub" style="margin-top: 4px;">
                                                    {{ $kpi->valueText($kpi->achieved_value) }} of {{ $kpi->valueText($kpi->target_value) }}
                                                    @if($kpi->notes) &middot; {{ $kpi->notes }} @endif
                                                </div>
                                                <div class="kp-bar" style="width: 100%; margin-top: 7px;"><i style="width: {{ $kpi->progress() }}%; background: {{ $c }};"></i></div>

                                                @if($kpi->isAuto())
                                                    <div class="kp-auto"><i class="fas fa-bolt"></i> Counted by the system — {{ $kpi->sourceLabel() }}</div>
                                                @endif

                                                <div class="kp-kpi-inputs">
                                                    <form method="POST" action="{{ route('admin.hr.kpi.update', $kpi->id) }}" style="display: flex; gap: 6px; align-items: center;">
                                                        @csrf @method('PUT')
                                                        <input type="number" step="0.01" min="0" name="target_value" value="{{ rtrim(rtrim(number_format($kpi->target_value, 2, '.', ''), '0'), '.') }}" title="Target" aria-label="Target for {{ $kpi->metric }}">
                                                        @unless($kpi->isAuto())
                                                            {{-- A system-counted figure would only be overwritten on the next
                                                                 page load, so only its goal is offered for editing. --}}
                                                            <input type="number" step="0.01" min="0" name="achieved_value" value="{{ rtrim(rtrim(number_format($kpi->achieved_value, 2, '.', ''), '0'), '.') }}" title="Achieved" aria-label="Achieved for {{ $kpi->metric }}" required>
                                                        @endunless
                                                        <button type="submit"><i class="fas fa-check"></i> Update</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('admin.hr.kpi.delete', $kpi->id) }}" onsubmit="return confirm('Remove this target?');">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" style="color: var(--danger);"><i class="fas fa-trash"></i></button>
                                                    </form>
                                                </div>
                                            </div>
                                        @empty
                                            <p style="color: var(--text-muted); font-size: 0.85rem;">No targets set for this month yet.</p>
                                        @endforelse

                                        <form method="POST" action="{{ route('admin.hr.kpi.store', $u->id) }}" class="kp-add">
                                            @csrf
                                            <input type="hidden" name="period" value="{{ $month->toDateString() }}">
                                            <div class="kp-field" style="margin: 0;">
                                                <label for="kpiMetric{{ $u->id }}">Target</label>
                                                <input type="text" id="kpiMetric{{ $u->id }}" name="metric" placeholder="Sales closed, tickets solved…" required>
                                            </div>
                                            <div class="kp-field" style="margin: 0;">
                                                <label for="kpiSource{{ $u->id }}">Counted from</label>
                                                <select id="kpiSource{{ $u->id }}" name="source" onchange="kpSourceChanged({{ $u->id }})">
                                                    @foreach($sources as $key => $source)
                                                        <option value="{{ $key }}">{{ $source['label'] }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="kp-field" id="kpiUnitWrap{{ $u->id }}" style="margin: 0;">
                                                <label for="kpiUnit{{ $u->id }}">Measured in</label>
                                                <select id="kpiUnit{{ $u->id }}" name="unit">
                                                    <option value="number">Number</option>
                                                    <option value="amount">Amount</option>
                                                    <option value="percent">Percent</option>
                                                </select>
                                            </div>
                                            <div class="kp-field" style="margin: 0;">
                                                <label for="kpiTarget{{ $u->id }}">Target</label>
                                                <input type="number" id="kpiTarget{{ $u->id }}" name="target_value" step="0.01" min="0" value="0" required>
                                            </div>
                                            <div class="kp-field" id="kpiDoneWrap{{ $u->id }}" style="margin: 0;">
                                                <label for="kpiDone{{ $u->id }}">Achieved</label>
                                                <input type="number" id="kpiDone{{ $u->id }}" name="achieved_value" step="0.01" min="0" value="0">
                                            </div>
                                            <button type="submit" class="btn btn-primary" style="height: 38px; padding: 0 1rem; border-radius: 9px; cursor: pointer; font-weight: 600;">
                                                <i class="fas fa-plus"></i> Add
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // A target counted from the CRM brings its own unit and its own score, so the
    // two fields that would be ignored are taken off the form rather than left
    // there to be filled in for nothing.
    function kpSourceChanged(id) {
        var auto = document.getElementById('kpiSource' + id).value !== 'manual';
        var unit = document.getElementById('kpiUnitWrap' + id);
        var done = document.getElementById('kpiDoneWrap' + id);
        if (unit) unit.hidden = auto;
        if (done) done.hidden = auto;
    }

    function kpToggle(id, btn) {
        var row = document.getElementById('kpPanel_' + id);
        if (!row) return;
        var open = !(row.style.display === 'none' || row.style.display === '');
        row.style.display = open ? 'none' : 'table-row';
        if (btn) btn.setAttribute('aria-expanded', open ? 'false' : 'true');
    }

    // Arriving from an employee's file with #emp12 in the address opens that row.
    (function () {
        var hash = window.location.hash;
        if (!/^#emp\d+$/.test(hash)) return;
        var id = hash.slice(4);
        var row = document.getElementById('kpPanel_' + id);
        if (!row) return;
        row.style.display = 'table-row';
        document.getElementById('emp' + id).scrollIntoView({ block: 'center' });
    })();
</script>
@endpush
@endsection
