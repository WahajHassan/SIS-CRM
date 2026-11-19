@extends('layouts.admin')

@section('title', 'Attendance Reports')

@section('content')
{{--
    The month as it will be downloaded, and a popup that decides what actually
    goes in the file. The sheet on screen is the same report the file is built
    from, so nobody has to open a download to find out whether they picked the
    right month.
--}}
<style>
    .rp-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 15px; flex-wrap: wrap; }
    .rp-tools { display: flex; gap: 10px; align-items: end; flex-wrap: wrap; }
    .rp-tools label { display: block; font-size: 0.78rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; }
    .rp-tools select { height: 38px; padding: 0 0.6rem; border-radius: 9px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-family: inherit; font-size: 0.85rem; }

    .rp-sheet { overflow-x: auto; }
    .rp-sheet table { border-collapse: collapse; font-size: 0.78rem; }
    .rp-sheet th, .rp-sheet td { border: 1px solid var(--border); padding: 0.3rem 0.4rem; white-space: nowrap; }
    .rp-sheet thead th { background: var(--bg-body); font-size: 0.72rem; font-weight: 700; text-align: center; }
    .rp-sheet th.rp-name, .rp-sheet td.rp-name { text-align: left; position: sticky; left: 0; background: var(--bg-card); z-index: 1; min-width: 160px; }
    .rp-cell { text-align: center; font-weight: 700; font-size: 0.72rem; color: #1f2937; }
    .rp-total { text-align: center; font-variant-numeric: tabular-nums; }

    .rp-legend { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 1rem; font-size: 0.76rem; color: var(--text-muted); }
    .rp-legend span { display: inline-flex; align-items: center; gap: 6px; }
    .rp-legend i { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 18px; border-radius: 4px; font-style: normal; font-weight: 700; font-size: 0.68rem; color: #1f2937; }

    /* The download popup: the one dialog in this panel, because it is a decision
       taken before an action rather than a screen of its own. */
    .rp-modal { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55); display: flex; align-items: flex-start; justify-content: center; padding: 4vh 1rem; z-index: 3000; overflow-y: auto; }
    .rp-modal[hidden] { display: none !important; }
    .rp-box { background: var(--bg-card); border: 1px solid var(--border); border-radius: 18px; width: min(860px, 100%); padding: 1.6rem; box-shadow: 0 24px 60px rgba(0,0,0,0.28); }
    .rp-box h3 { margin: 0 0 0.3rem; font-size: 1.15rem; color: var(--text-main); }
    .rp-box p.rp-sub { margin: 0 0 1.3rem; font-size: 0.83rem; color: var(--text-muted); }

    .rp-section { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); margin: 1.4rem 0 0.8rem; }
    .rp-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; }
    .rp-grid label { display: block; font-size: 0.78rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; }
    .rp-grid select, .rp-grid input[type=text] { width: 100%; padding: 0.55rem 0.65rem; border-radius: 9px; border: 1px solid var(--border); background: var(--bg-body); color: var(--text-main); font-family: inherit; font-size: 0.86rem; }

    .rp-checks { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 10px; }
    .rp-check { display: flex; align-items: center; gap: 9px; padding: 0.55rem 0.7rem; border: 1px solid var(--border); border-radius: 10px; background: var(--bg-body); font-size: 0.85rem; color: var(--text-main); cursor: pointer; }
    .rp-check input { width: auto; }

    .rp-formats { display: flex; gap: 10px; flex-wrap: wrap; }
    .rp-format { display: inline-flex; align-items: center; gap: 8px; padding: 0.6rem 1.1rem; border: 1px solid var(--border); border-radius: 11px; background: var(--bg-body); cursor: pointer; font-size: 0.87rem; font-weight: 600; color: var(--text-main); }
    .rp-format input { width: auto; }

    .rp-presets { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .rp-preset { display: inline-flex; align-items: center; gap: 6px; border: 1px solid var(--border); border-radius: 999px; padding: 0.3rem 0.4rem 0.3rem 0.85rem; background: var(--bg-body); font-size: 0.82rem; }
    .rp-preset button { border: none; background: transparent; color: var(--text-muted); cursor: pointer; font-size: 0.8rem; padding: 0 4px; }
    .rp-preset button:hover { color: var(--danger); }
    .rp-preset .rp-use { color: var(--primary); font-weight: 600; }

    .rp-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 1.6rem; flex-wrap: wrap; }
</style>

<div style="display: flex; flex-direction: column; gap: 1.5rem;">
    <div class="card" style="margin-bottom: 0;">
        <div class="rp-head" style="border-bottom: 1px solid var(--border); padding-bottom: 1rem; margin-bottom: 1.2rem;">
            <div>
                <div class="card-title" style="margin-bottom: 5px; font-size: 1.2rem; font-weight: 700;">
                    <i class="fas fa-file-lines" style="color: var(--primary);"></i> Attendance Reports
                </div>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">
                    {{ $report['month']->format('F Y') }} — every day of the month, for everybody. Download it as a sheet, a PDF or a CSV.
                </p>
            </div>

            <form method="GET" action="{{ route('admin.hr.reports') }}" class="rp-tools">
                <div>
                    <label for="rpMonth">Month</label>
                    <select id="rpMonth" name="month" onchange="this.form.submit()">
                        @foreach($months as $m)
                            <option value="{{ $m->format('Y-m') }}" {{ $month->format('Y-m') === $m->format('Y-m') ? 'selected' : '' }}>{{ $m->format('F Y') }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="rpDept">Department</label>
                    <select id="rpDept" name="department_id" onchange="this.form.submit()">
                        <option value="">All departments</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" {{ request('department_id') == $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="button" class="btn btn-primary" onclick="rpOpen()" style="height: 38px; padding: 0 1.3rem; border-radius: 9px; cursor: pointer; font-weight: 600;">
                    <i class="fas fa-download"></i> Download
                </button>
            </form>
        </div>

        <div class="rp-sheet">
            <table>
                <thead>
                    <tr>
                        <th class="rp-name">Employee</th>
                        <th>ID</th>
                        <th>Department</th>
                        <th>Shift</th>
                        @foreach($report['days'] as $day)
                            <th title="{{ \Carbon\Carbon::parse($day['date'])->format('l d M') }}">{{ $day['number'] }}</th>
                        @endforeach
                        <th>P</th><th>L</th><th>A</th><th>Leave</th><th>Hours</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($report['rows'] as $row)
                    <tr>
                        <td class="rp-name">{{ $row['user']->name }}</td>
                        <td>{{ $row['id'] }}</td>
                        <td>{{ $row['department'] }}</td>
                        <td>{{ $row['shift'] }}</td>
                        @foreach($report['days'] as $day)
                            @php $cell = $row['cells'][$day['date']] ?? ['code' => '', 'colour' => null]; @endphp
                            <td class="rp-cell" style="{{ $cell['colour'] ? 'background: ' . $cell['colour'] . ';' : '' }}"
                                title="{{ \Carbon\Carbon::parse($day['date'])->format('d M') }}">{{ $cell['code'] }}</td>
                        @endforeach
                        <td class="rp-total">{{ $row['totals']['present'] + $row['totals']['half_day'] }}</td>
                        <td class="rp-total">{{ $row['totals']['late'] }}</td>
                        <td class="rp-total">{{ $row['totals']['absent'] }}</td>
                        <td class="rp-total">{{ $row['totals']['leave'] }}</td>
                        <td class="rp-total">{{ round($row['totals']['worked_minutes'] / 60, 1) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 9 + count($report['days']) }}" style="text-align: center; padding: 2.5rem; color: var(--text-muted); ">
                            Nobody matches those filters.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="rp-legend">
            @foreach($report['legend'] as $entry)
                <span><i style="background: {{ $entry['colour'] }};">{{ $entry['code'] }}</i> {{ $entry['label'] }}</span>
            @endforeach
        </div>
    </div>
</div>

{{-- ---- the download popup ---- --}}
<div class="rp-modal" id="rpModal" hidden>
    <div class="rp-box">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px;">
            <div>
                <h3>Download attendance</h3>
                <p class="rp-sub">Pick what goes in the file. Only what is ticked is written.</p>
            </div>
            <button type="button" class="btn btn-ghost" style="cursor: pointer;" onclick="rpClose()">&times;</button>
        </div>

        @if($presets->count())
            <div class="rp-section" style="margin-top: 0;">Saved presets</div>
            <div class="rp-presets">
                @foreach($presets as $preset)
                    <span class="rp-preset">
                        <a href="javascript:void(0)" class="rp-use"
                           onclick='rpApplyPreset(@json(["report_type" => $preset->report_type, "filters" => $preset->filters, "columns" => $preset->columns]))'>
                            {{ $preset->name }}
                        </a>
                        <form method="POST" action="{{ route('admin.hr.reports.preset.delete', $preset->id) }}"
                              onsubmit="return confirm('Remove “{{ addslashes($preset->name) }}”?');" style="display: inline;">
                            @csrf @method('DELETE')
                            <button type="submit" title="Remove"><i class="fas fa-xmark"></i></button>
                        </form>
                    </span>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('admin.hr.reports.download') }}" id="rpForm">
            @csrf

            <div class="rp-section">What to download</div>
            <div class="rp-grid">
                <div>
                    <label for="rpType">Report</label>
                    <select id="rpType" name="report_type" onchange="rpTypeChanged()">
                        <option value="overview">All employees — one row each</option>
                        <option value="single">One employee — one row per day</option>
                    </select>
                </div>
                <div>
                    <label for="rpFormMonth">Month</label>
                    <select id="rpFormMonth" name="month">
                        @foreach($months as $m)
                            <option value="{{ $m->format('Y-m-01') }}" {{ $month->format('Y-m') === $m->format('Y-m') ? 'selected' : '' }}>{{ $m->format('F Y') }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="rpFormDept">Department</label>
                    <select id="rpFormDept" name="department_id">
                        <option value="">All departments</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" {{ request('department_id') == $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="rpFormShift">Shift</label>
                    <select id="rpFormShift" name="shift_id">
                        <option value="">All shifts</option>
                        @foreach($shifts as $shift)
                            <option value="{{ $shift->id }}">{{ $shift->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div id="rpEmployeeWrap" hidden>
                    <label for="rpFormUser">Employee</label>
                    <select id="rpFormUser" name="user_id">
                        <option value="">Choose somebody</option>
                        @foreach($staff as $person)
                            <option value="{{ $person->id }}">{{ $person->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="rp-section">
                Columns and sections
                <button type="button" onclick="rpAll(true)" style="margin-left: 10px; border: 1px solid var(--border); background: var(--bg-body); color: var(--text-main); border-radius: 7px; padding: 0.2rem 0.6rem; font-size: 0.72rem; cursor: pointer; font-family: inherit;">Select all</button>
                <button type="button" onclick="rpAll(false)" style="border: 1px solid var(--border); background: var(--bg-body); color: var(--text-main); border-radius: 7px; padding: 0.2rem 0.6rem; font-size: 0.72rem; cursor: pointer; font-family: inherit;">Clear all</button>
            </div>
            <div class="rp-checks">
                @foreach($sections as $key => $label)
                    <label class="rp-check">
                        <input type="checkbox" name="sections[]" value="{{ $key }}" checked>
                        {{ $label }}
                    </label>
                @endforeach
            </div>

            <div class="rp-section">Format</div>
            <div class="rp-formats">
                <label class="rp-format"><input type="radio" name="format" value="xlsx" checked> <i class="fas fa-file-excel" style="color: #10b981;"></i> Excel (.xlsx)</label>
                <label class="rp-format"><input type="radio" name="format" value="pdf"> <i class="fas fa-file-pdf" style="color: #ef4444;"></i> PDF</label>
                <label class="rp-format"><input type="radio" name="format" value="csv"> <i class="fas fa-file-csv" style="color: #6366f1;"></i> CSV</label>
            </div>

            <div class="rp-actions">
                <button type="button" class="btn btn-ghost" onclick="rpSavePreset()"
                        style="border: 1px solid var(--border); cursor: pointer; font-weight: 600;">
                    <i class="fas fa-bookmark"></i> Save as preset
                </button>
                <button type="button" class="btn btn-ghost" onclick="rpClose()" style="border: 1px solid var(--border); cursor: pointer;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="cursor: pointer; font-weight: 600;">
                    <i class="fas fa-download"></i> Download
                </button>
            </div>
        </form>

        {{-- Saving a preset posts the same choices to a different address, so the
             two never fall out of step. --}}
        <form method="POST" action="{{ route('admin.hr.reports.preset') }}" id="rpPresetForm" hidden>
            @csrf
            <input type="hidden" name="name" id="rpPresetName">
            <input type="hidden" name="report_type" id="rpPresetType">
            <input type="hidden" name="format" id="rpPresetFormat">
            <input type="hidden" name="department_id" id="rpPresetDept">
            <input type="hidden" name="shift_id" id="rpPresetShift">
            <input type="hidden" name="user_id" id="rpPresetUser">
            <div id="rpPresetSections"></div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function rpOpen() { document.getElementById('rpModal').hidden = false; rpTypeChanged(); }
    function rpClose() { document.getElementById('rpModal').hidden = true; }

    // One employee means picking one; the whole company means not having to.
    function rpTypeChanged() {
        var single = document.getElementById('rpType').value === 'single';
        document.getElementById('rpEmployeeWrap').hidden = !single;
        document.getElementById('rpFormUser').required = single;
    }

    function rpAll(state) {
        document.querySelectorAll('#rpForm input[name="sections[]"]').forEach(function (box) { box.checked = state; });
    }

    function rpApplyPreset(preset) {
        document.getElementById('rpType').value = preset.report_type || 'overview';

        var filters = preset.filters || {};
        document.getElementById('rpFormDept').value = filters.department_id || '';
        document.getElementById('rpFormShift').value = filters.shift_id || '';
        document.getElementById('rpFormUser').value = filters.user_id || '';

        var format = filters.format || 'xlsx';
        var radio = document.querySelector('#rpForm input[name=format][value="' + format + '"]');
        if (radio) radio.checked = true;

        var columns = preset.columns || [];
        document.querySelectorAll('#rpForm input[name="sections[]"]').forEach(function (box) {
            box.checked = columns.indexOf(box.value) !== -1;
        });

        rpTypeChanged();
    }

    function rpSavePreset() {
        var name = prompt('Name this preset — payroll, team meeting, whatever you will recognise next month:');
        if (!name) return;

        document.getElementById('rpPresetName').value = name;
        document.getElementById('rpPresetType').value = document.getElementById('rpType').value;
        document.getElementById('rpPresetFormat').value = document.querySelector('#rpForm input[name=format]:checked').value;
        document.getElementById('rpPresetDept').value = document.getElementById('rpFormDept').value;
        document.getElementById('rpPresetShift').value = document.getElementById('rpFormShift').value;
        document.getElementById('rpPresetUser').value = document.getElementById('rpFormUser').value;

        var holder = document.getElementById('rpPresetSections');
        holder.innerHTML = '';
        document.querySelectorAll('#rpForm input[name="sections[]"]:checked').forEach(function (box) {
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'sections[]';
            hidden.value = box.value;
            holder.appendChild(hidden);
        });

        document.getElementById('rpPresetForm').submit();
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !document.getElementById('rpModal').hidden) rpClose();
    });
</script>
@endpush
@endsection
