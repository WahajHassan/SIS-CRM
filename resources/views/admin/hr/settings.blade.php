@extends('layouts.admin')

@section('title', 'HR Settings')

@section('content')
{{--
    Every rule the HR panel judges a day by, on one page.
    Tabs rather than five menu entries: these are all answers to "how does this
    office work", and HR usually comes here to change one of them and leave.
--}}
@php
    $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $offDays = $rules->weeklyOffDays();
    $tabs = [
        'rules' => ['label' => 'Attendance Rules', 'icon' => 'fa-sliders'],
        'shifts' => ['label' => 'Shifts', 'icon' => 'fa-clock'],
        'roster' => ['label' => 'Who works which shift', 'icon' => 'fa-user-clock'],
        'holidays' => ['label' => 'Working Days & Holidays', 'icon' => 'fa-calendar-day'],
        'departments' => ['label' => 'Departments', 'icon' => 'fa-sitemap'],
        'bonuses' => ['label' => 'Bonuses & achievements', 'icon' => 'fa-trophy'],
        'import' => ['label' => 'Import attendance', 'icon' => 'fa-file-arrow-up'],
    ];
@endphp

<style>
    .hs-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 1.4rem; }
    .hs-tab { padding: 0.55rem 1.1rem; border-radius: 999px; border: 1px solid var(--border); background: var(--bg-card);
              color: var(--text-muted); font-size: 0.86rem; font-weight: 600; cursor: pointer; font-family: inherit;
              display: inline-flex; align-items: center; gap: 8px; transition: background 0.15s ease, color 0.15s ease; }
    .hs-tab:hover { color: var(--text-main); }
    .hs-tab.active { background: var(--primary); border-color: var(--primary); color: #fff; }

    .hs-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.1rem; }
    .hs-grid label, .hs-form label { display: block; font-size: 0.79rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; }
    .hs-grid input, .hs-grid select, .hs-form input, .hs-form select, .hs-form textarea {
        width: 100%; padding: 0.58rem 0.7rem; border-radius: 10px; border: 1px solid var(--border);
        background: var(--bg-card); color: var(--text-main); font-family: inherit; font-size: 0.87rem;
    }
    .hs-grid small { display: block; font-size: 0.73rem; color: var(--text-muted); margin-top: 5px; line-height: 1.5; }

    .hs-section { font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;
                  color: var(--text-muted); margin: 1.8rem 0 1rem; padding-top: 1.2rem; border-top: 1px solid var(--border); }
    .hs-section:first-of-type { margin-top: 0; padding-top: 0; border-top: none; }

    .hs-days { display: flex; flex-wrap: wrap; gap: 10px; }
    .hs-day { display: inline-flex; align-items: center; gap: 7px; padding: 0.45rem 0.85rem; border-radius: 10px;
              border: 1px solid var(--border); background: var(--bg-card); font-size: 0.85rem; cursor: pointer; }
    .hs-day input { width: auto; }

    .hs-switch { display: flex; align-items: flex-start; gap: 10px; padding: 0.75rem 0.9rem; border: 1px solid var(--border);
                 border-radius: 11px; background: var(--bg-card); cursor: pointer; }
    .hs-switch input { width: auto; margin-top: 3px; }
    .hs-switch b { display: block; font-size: 0.86rem; color: var(--text-main); font-weight: 600; }
    .hs-switch small { display: block; font-size: 0.74rem; color: var(--text-muted); margin-top: 3px; line-height: 1.5; }

    .hs-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; align-items: end; }
    .hs-cell-input { width: 100%; min-width: 80px; padding: 0.4rem 0.55rem; border-radius: 8px; border: 1px solid var(--border);
                     background: var(--bg-card); color: var(--text-main); font-family: inherit; font-size: 0.84rem; }
    .hs-btn-sm { border: 1px solid var(--border); background: var(--bg-body); color: var(--text-main); border-radius: 8px;
                 padding: 0.32rem 0.7rem; font-size: 0.77rem; cursor: pointer; font-family: inherit; font-weight: 600; }
    .hs-btn-sm.danger { color: var(--danger); }

    .hs-log { border-bottom: 1px solid var(--border); padding: 0.6rem 0; font-size: 0.83rem; color: var(--text-main); }
    .hs-log:last-of-type { border-bottom: none; }
    .hs-log b { margin-right: 8px; }
    .hs-log-action { display: inline-block; padding: 0.1rem 0.5rem; border-radius: 999px; font-size: 0.68rem;
                     font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }
    .hs-log-created { background: rgba(16,185,129,0.14); color: var(--success); }
    .hs-log-updated { background: rgba(99,102,241,0.14); color: var(--primary); }
    .hs-log-deleted { background: rgba(239,68,68,0.14); color: var(--danger); }
    .hs-log-meta { font-size: 0.74rem; color: var(--text-muted); margin-left: 8px; }
    .hs-log-changes { font-size: 0.75rem; color: var(--text-muted); margin-top: 3px; }

    .hs-note { font-size: 0.79rem; color: var(--text-muted); margin: 1rem 0 0; line-height: 1.6; }
    .hs-pill { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700;
               background: rgba(99,102,241,0.12); color: var(--primary); }
    .hs-pill.off { background: rgba(100,116,139,0.14); color: var(--secondary); }
    .hs-pill.night { background: rgba(245,158,11,0.15); color: var(--warning); }
</style>

<div style="display: flex; flex-direction: column; gap: 1.5rem;">
    <div class="card" style="margin-bottom: 0;">
        <div style="border-bottom: 1px solid var(--border); padding-bottom: 1rem; margin-bottom: 1.3rem;">
            <div class="card-title" style="margin-bottom: 5px; font-size: 1.2rem; font-weight: 700;">
                <i class="fas fa-sliders" style="color: var(--primary);"></i> HR Settings
            </div>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">
                How this office works: the shifts, the working week, and the rules attendance and leave are judged by.
                Nothing here is fixed in the software — change it and every figure follows.
            </p>
        </div>

        <div class="hs-tabs">
            @foreach($tabs as $key => $meta)
                <button type="button" class="hs-tab {{ $tab === $key ? 'active' : '' }}" data-tab="{{ $key }}" onclick="hsShow('{{ $key }}')">
                    <i class="fas {{ $meta['icon'] }}"></i> {{ $meta['label'] }}
                </button>
            @endforeach
        </div>

        {{-- ================= RULES ================= --}}
        <div class="hs-panel" id="hsPanel-rules" @if($tab !== 'rules') hidden @endif>
            <form method="POST" action="{{ route('admin.hr.settings.rules') }}">
                @csrf

                <div class="hs-section">The working week</div>
                <label style="display: block; font-size: 0.79rem; font-weight: 600; color: var(--text-muted); margin-bottom: 8px;">Weekly offs</label>
                <div class="hs-days">
                    @foreach($dayNames as $num => $name)
                        <label class="hs-day">
                            <input type="checkbox" name="weekly_offs[]" value="{{ $num }}" {{ in_array($num, $offDays, true) ? 'checked' : '' }}>
                            {{ $name }}
                        </label>
                    @endforeach
                </div>
                <p class="hs-note">Nobody is marked absent on these days. A one-off exception for one person is set under “Working Days &amp; Holidays”.</p>

                <div class="hs-section">Arriving and leaving</div>
                <div class="hs-grid">
                    <div>
                        <label for="earlyCheckin">Early check-in window (minutes)</label>
                        <input type="number" id="earlyCheckin" name="early_checkin_minutes" value="{{ old('early_checkin_minutes', $rules->early_checkin_minutes) }}" min="0" max="720" required>
                        <small>Clocking in this long before the shift starts still counts as that shift — which is what keeps a 7 PM shift from being read as the day before.</small>
                    </div>
                    <div>
                        <label for="earlyOff">Early off from (minutes)</label>
                        <input type="number" id="earlyOff" name="early_off_minutes" value="{{ old('early_off_minutes', $rules->early_off_minutes) }}" min="0" max="240" required>
                        <small>Leaving this many minutes or more before the shift ends is an Early Off.</small>
                    </div>
                    <div>
                        <label for="halfDay">Half day below (minutes)</label>
                        <input type="number" id="halfDay" name="half_day_minutes" value="{{ old('half_day_minutes', $rules->half_day_minutes) }}" min="15" max="960" required>
                        <small>{{ \App\Services\PayrollService::hoursText((int) $rules->half_day_minutes) }} of work, breaks already taken off.</small>
                    </div>
                </div>

                <div class="hs-section">Viewrec</div>
                <div class="hs-grid">
                    <div>
                        <label for="signupsPerDay">Free signups a day</label>
                        <input type="number" id="signupsPerDay" name="free_signups_per_day"
                               value="{{ old('free_signups_per_day', $rules->free_signups_per_day) }}" min="0" max="100" required>
                        <small>What each person on the phones is asked for in a day. Shown on their Viewrec page as they go.</small>
                    </div>
                </div>

                <div class="hs-section">The clock</div>
                <div class="hs-grid">
                    <div style="grid-column: 1 / -1;">
                        <label for="requireBiometric" style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" id="requireBiometric" name="require_biometric_clock" value="1"
                                   {{ old('require_biometric_clock', $rules->require_biometric_clock) ? 'checked' : '' }}>
                            <span>Clocking in needs a fingerprint or face</span>
                        </label>
                        <small>
                            Uses the reader already in each person's laptop or phone — nothing
                            about their finger or face is stored here. It only applies to people
                            who have set a device up on their own profile, so switching it on
                            does not lock anybody out of the clock; they are asked the next time
                            they set one up.
                        </small>
                    </div>
                </div>

                <div class="hs-section">Lates</div>
                <div class="hs-grid">
                    <div>
                        <label for="latesPerOff">Lates that cost one off</label>
                        <input type="number" id="latesPerOff" name="lates_per_off" value="{{ old('lates_per_off', $rules->lates_per_off) }}" min="0" max="31" required>
                        <small>Counted per calendar month and reset each month. 0 switches the rule off.</small>
                    </div>
                    <div>
                        <label for="deductOrder">Where a deducted off comes from</label>
                        <select id="deductOrder" name="late_deduction_order">
                            <option value="casual_then_unpaid" {{ $rules->late_deduction_order === 'casual_then_unpaid' ? 'selected' : '' }}>Casual Leave first, then Unpaid</option>
                            <option value="unpaid" {{ $rules->late_deduction_order === 'unpaid' ? 'selected' : '' }}>Always Unpaid</option>
                        </select>
                        <small>Used when the software turns lates into an off.</small>
                    </div>
                </div>

                <div class="hs-section">Leave approval</div>
                <div class="hs-grid">
                    <div>
                        <label for="approvalFlow">Who approves leave</label>
                        <select id="approvalFlow" name="leave_approval_flow">
                            <option value="tl_then_hr" {{ $rules->leave_approval_flow === 'tl_then_hr' ? 'selected' : '' }}>Team Lead first, then HR</option>
                            <option value="hr_only" {{ $rules->leave_approval_flow === 'hr_only' ? 'selected' : '' }}>HR only</option>
                        </select>
                        <small>The team lead is set on the department below.</small>
                    </div>
                </div>

                <div class="hs-section">Switches</div>
                <div class="hs-grid">
                    <label class="hs-switch">
                        <input type="checkbox" name="half_day_enabled" value="1" {{ $rules->half_day_enabled ? 'checked' : '' }}>
                        <span><b>Use half days</b><small>Off: a short day is simply Present with fewer hours.</small></span>
                    </label>
                    <label class="hs-switch">
                        <input type="checkbox" name="early_off_counts_toward_lates" value="1" {{ $rules->early_off_counts_toward_lates ? 'checked' : '' }}>
                        <span><b>Early offs count toward the late rule</b><small>Off: early offs are recorded but never cost an off.</small></span>
                    </label>
                    <label class="hs-switch">
                        <input type="checkbox" name="auto_absent_enabled" value="1" {{ $rules->auto_absent_enabled ? 'checked' : '' }}>
                        <span><b>Mark absent automatically</b><small>After a shift ends, anyone with no check-in and no approved leave is marked Absent.</small></span>
                    </label>
                    <label class="hs-switch">
                        <input type="checkbox" name="sick_leave_requires_attachment" value="1" {{ $rules->sick_leave_requires_attachment ? 'checked' : '' }}>
                        <span><b>Sick leave needs proof</b><small>A document has to be attached to a sick leave request.</small></span>
                    </label>
                </div>

                @if($errors->any())
                    <p style="color: var(--danger); font-size: 0.83rem; margin-top: 1rem;">{{ $errors->first() }}</p>
                @endif

                <div style="margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary" style="cursor: pointer; font-weight: 600;">
                        <i class="fas fa-floppy-disk"></i> Save rules
                    </button>
                </div>
            </form>
        </div>

        {{-- ================= SHIFTS ================= --}}
        <div class="hs-panel" id="hsPanel-shifts" @if($tab !== 'shifts') hidden @endif>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr><th>Shift</th><th>Starts</th><th>Ends</th><th>Grace (min)</th><th>Break (min)</th><th>Crosses midnight</th><th>Active</th><th></th></tr>
                    </thead>
                    <tbody>
                    @forelse($shifts as $shift)
                        {{-- The inputs sit in their own cells and point at the form in
                             the last one: a form cannot legally wrap table cells. --}}
                        @php $fid = 'shiftForm_' . $shift->id; @endphp
                        <tr>
                            <td><input form="{{ $fid }}" class="hs-cell-input" type="text" name="name" value="{{ $shift->name }}" required style="width: 150px;"></td>
                            <td><input form="{{ $fid }}" class="hs-cell-input" type="time" name="start_time" value="{{ substr($shift->start_time, 0, 5) }}" required style="width: 120px;"></td>
                            <td><input form="{{ $fid }}" class="hs-cell-input" type="time" name="end_time" value="{{ substr($shift->end_time, 0, 5) }}" required style="width: 120px;"></td>
                            <td><input form="{{ $fid }}" class="hs-cell-input" type="number" name="grace_minutes" value="{{ $shift->grace_minutes }}" min="0" max="240" style="width: 80px;"></td>
                            <td>
                                <input form="{{ $fid }}" class="hs-cell-input" type="number" name="break_minutes" value="{{ $shift->break_minutes }}" min="0" max="240" style="width: 80px;">
                                <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 3px;">
                                    {{ \App\Services\PayrollService::hoursText($shift->workingMinutes()) }} of work
                                </div>
                            </td>
                            <td>
                                @if($shift->crosses_midnight)
                                    <span class="hs-pill night">Yes — day belongs to the start date</span>
                                @else
                                    <span class="hs-pill off">No</span>
                                @endif
                            </td>
                            <td><input form="{{ $fid }}" type="checkbox" name="is_active" value="1" {{ $shift->is_active ? 'checked' : '' }}></td>
                            <td>
                                <div style="display: flex; gap: 6px;">
                                    <form id="{{ $fid }}" method="POST" action="{{ route('admin.hr.settings.shifts.update', $shift->id) }}">
                                        @csrf @method('PUT')
                                        <button type="submit" class="hs-btn-sm">Save</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.hr.settings.shifts.delete', $shift->id) }}"
                                          onsubmit="return confirm('Delete {{ addslashes($shift->name) }}?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="hs-btn-sm danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="empty-state"><p>No shifts yet.</p></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="hs-section">Add a shift</div>
            <form method="POST" action="{{ route('admin.hr.settings.shifts.store') }}" class="hs-form">
                @csrf
                <div>
                    <label for="shiftName">Name</label>
                    <input type="text" id="shiftName" name="name" placeholder="e.g. Morning Shift" required>
                </div>
                <div>
                    <label for="shiftStart">Starts</label>
                    <input type="time" id="shiftStart" name="start_time" value="19:00" required>
                </div>
                <div>
                    <label for="shiftEnd">Ends</label>
                    <input type="time" id="shiftEnd" name="end_time" value="04:00" required>
                </div>
                <div>
                    <label for="shiftGrace">Grace (min)</label>
                    <input type="number" id="shiftGrace" name="grace_minutes" value="10" min="0" max="240" required>
                </div>
                <div>
                    <label for="shiftBreak">Break (min)</label>
                    <input type="number" id="shiftBreak" name="break_minutes" value="60" min="0" max="240" required>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary" style="height: 40px; padding: 0 1.1rem; border-radius: 10px; cursor: pointer; font-weight: 600;">
                        <i class="fas fa-plus"></i> Add shift
                    </button>
                </div>
            </form>
            <p class="hs-note">
                Whether a shift runs through midnight is worked out from its times — you never set it.
                For one that does, the whole night belongs to the date it <b>started</b>: a shift beginning Friday 7 PM
                and ending Saturday 4 AM is Friday’s attendance.
            </p>
        </div>

        {{-- ================= ROSTER ================= --}}
        <div class="hs-panel" id="hsPanel-roster" @if($tab !== 'roster') hidden @endif>
            <form method="POST" action="{{ route('admin.hr.settings.shifts.assign') }}" class="hs-form">
                @csrf
                <div>
                    <label for="asUser">Employee</label>
                    <select id="asUser" name="user_id" required>
                        @foreach($staff as $person)
                            <option value="{{ $person->id }}">{{ $person->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="asShift">Shift</label>
                    <select id="asShift" name="shift_id" required>
                        @foreach($shifts->where('is_active', true) as $shift)
                            <option value="{{ $shift->id }}">{{ $shift->name }} ({{ $shift->timesText() }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="asFrom">From</label>
                    <input type="date" id="asFrom" name="effective_from" value="{{ now()->toDateString() }}" required>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary" style="height: 40px; padding: 0 1.1rem; border-radius: 10px; cursor: pointer; font-weight: 600;">
                        <i class="fas fa-user-clock"></i> Assign
                    </button>
                </div>
            </form>
            <p class="hs-note">
                Moving somebody to another shift does not touch the months they have already worked —
                those keep being judged against the shift that was in force at the time.
            </p>

            <div class="hs-section">Today’s roster</div>
            <div style="overflow-x: auto;">
                <table>
                    <thead><tr><th>Employee</th><th>Department</th><th>Shift today</th><th>Times</th><th>On it since</th></tr></thead>
                    <tbody>
                    @foreach($roster as $row)
                        <tr>
                            <td style="font-weight: 600; color: var(--text-main);">{{ $row['user']->name }}</td>
                            <td>{{ $row['user']->employeeProfile?->departmentName() ?: '—' }}</td>
                            <td>
                                @if($row['shift'])
                                    <span class="hs-pill">{{ $row['shift']->name }}</span>
                                @else
                                    <span class="hs-pill off">Not assigned</span>
                                @endif
                            </td>
                            <td>
                                {{ $row['shift']?->timesText() ?: '—' }}
                                @if($row['shift'])
                                    <div style="font-size: 0.72rem; color: var(--text-muted);">
                                        {{ $row['shift']->breakText() }} &middot; {{ \App\Services\PayrollService::hoursText($row['shift']->workingMinutes()) }} of work
                                    </div>
                                @endif
                            </td>
                            <td>{{ $row['since'] ? $row['since']->format('d M Y') : '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ================= HOLIDAYS ================= --}}
        <div class="hs-panel" id="hsPanel-holidays" @if($tab !== 'holidays') hidden @endif>
            <form method="POST" action="{{ route('admin.hr.settings.holidays.store') }}" class="hs-form">
                @csrf
                <div>
                    <label for="hdName">Holiday</label>
                    <input type="text" id="hdName" name="name" placeholder="e.g. Eid ul Fitr" required>
                </div>
                <div>
                    <label for="hdStart">From</label>
                    <input type="date" id="hdStart" name="start_date" value="{{ now()->toDateString() }}" required>
                </div>
                <div>
                    <label for="hdEnd">To <span style="font-weight: 500;">(leave empty for one day)</span></label>
                    <input type="date" id="hdEnd" name="end_date">
                </div>
                <div>
                    <button type="submit" class="btn btn-primary" style="height: 40px; padding: 0 1.1rem; border-radius: 10px; cursor: pointer; font-weight: 600;">
                        <i class="fas fa-plus"></i> Add holiday
                    </button>
                </div>
            </form>

            <div class="hs-section">Public holidays</div>
            <div style="overflow-x: auto;">
                <table>
                    <thead><tr><th>Holiday</th><th>Dates</th><th>Days</th><th>Active</th><th></th></tr></thead>
                    <tbody>
                    @forelse($holidays as $holiday)
                        @php $hid = 'holidayForm_' . $holiday->id; @endphp
                        <tr>
                            <td><input form="{{ $hid }}" class="hs-cell-input" type="text" name="name" value="{{ $holiday->name }}" required style="width: 170px;"></td>
                            <td style="display: flex; gap: 6px; align-items: center;">
                                <input form="{{ $hid }}" class="hs-cell-input" type="date" name="start_date" value="{{ $holiday->start_date->toDateString() }}" required style="width: 150px;">
                                <span style="color: var(--text-muted);">–</span>
                                <input form="{{ $hid }}" class="hs-cell-input" type="date" name="end_date" value="{{ $holiday->end_date->toDateString() }}" style="width: 150px;">
                            </td>
                            <td>{{ $holiday->days() }}</td>
                            <td><input form="{{ $hid }}" type="checkbox" name="is_active" value="1" {{ $holiday->is_active ? 'checked' : '' }}></td>
                            <td>
                                <div style="display: flex; gap: 6px;">
                                    <form id="{{ $hid }}" method="POST" action="{{ route('admin.hr.settings.holidays.update', $holiday->id) }}">
                                        @csrf @method('PUT')
                                        <button type="submit" class="hs-btn-sm">Save</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.hr.settings.holidays.delete', $holiday->id) }}"
                                          onsubmit="return confirm('Remove {{ addslashes($holiday->name) }}?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="hs-btn-sm danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty-state"><p>No holidays added yet.</p></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="hs-section">One-off exceptions</div>
            <form method="POST" action="{{ route('admin.hr.settings.overrides.store') }}" class="hs-form">
                @csrf
                <div>
                    <label for="ovUser">Employee</label>
                    <select id="ovUser" name="user_id" required>
                        @foreach($staff as $person)
                            <option value="{{ $person->id }}">{{ $person->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="ovDate">Date</label>
                    <input type="date" id="ovDate" name="date" value="{{ now()->toDateString() }}" required>
                </div>
                <div>
                    <label for="ovType">This day is</label>
                    <select id="ovType" name="is_working">
                        <option value="1">A working day for them</option>
                        <option value="0">An off day for them</option>
                    </select>
                </div>
                <div>
                    <label for="ovReason">Reason</label>
                    <input type="text" id="ovReason" name="reason" placeholder="Optional">
                </div>
                <div>
                    <button type="submit" class="btn btn-primary" style="height: 40px; padding: 0 1.1rem; border-radius: 10px; cursor: pointer; font-weight: 600;">
                        <i class="fas fa-plus"></i> Save exception
                    </button>
                </div>
            </form>

            <div style="overflow-x: auto; margin-top: 1.2rem;">
                <table>
                    <thead><tr><th>Employee</th><th>Date</th><th>Counts as</th><th>Reason</th><th></th></tr></thead>
                    <tbody>
                    @forelse($overrides as $override)
                        <tr>
                            <td style="font-weight: 600; color: var(--text-main);">{{ $override->user->name ?? '—' }}</td>
                            <td>{{ $override->date->format('D, d M Y') }}</td>
                            <td>
                                <span class="hs-pill {{ $override->is_working ? '' : 'off' }}">{{ $override->is_working ? 'Working day' : 'Off day' }}</span>
                            </td>
                            <td>{{ $override->reason ?: '—' }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.hr.settings.overrides.delete', $override->id) }}" onsubmit="return confirm('Remove this exception?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="hs-btn-sm danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty-state"><p>No exceptions — everyone follows the weekly pattern.</p></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ================= DEPARTMENTS ================= --}}
        <div class="hs-panel" id="hsPanel-departments" @if($tab !== 'departments') hidden @endif>
            <div style="overflow-x: auto;">
                <table>
                    <thead><tr><th>Department</th><th>Code</th><th>Team lead</th><th>People</th><th>Active</th><th></th></tr></thead>
                    <tbody>
                    @forelse($departments as $department)
                        @php $did = 'deptForm_' . $department->id; @endphp
                        <tr>
                            <td><input form="{{ $did }}" class="hs-cell-input" type="text" name="name" value="{{ $department->name }}" required style="width: 160px;"></td>
                            <td><input form="{{ $did }}" class="hs-cell-input" type="text" name="code" value="{{ $department->code }}" style="width: 90px;"></td>
                            <td>
                                <select form="{{ $did }}" class="hs-cell-input" name="lead_user_id" style="width: 180px;">
                                    <option value="">Nobody</option>
                                    @foreach($staff as $person)
                                        <option value="{{ $person->id }}" {{ $department->lead_user_id == $person->id ? 'selected' : '' }}>{{ $person->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>{{ $department->profiles()->count() }}</td>
                            <td><input form="{{ $did }}" type="checkbox" name="is_active" value="1" {{ $department->is_active ? 'checked' : '' }}></td>
                            <td>
                                <div style="display: flex; gap: 6px;">
                                    <form id="{{ $did }}" method="POST" action="{{ route('admin.hr.settings.departments.update', $department->id) }}">
                                        @csrf @method('PUT')
                                        <button type="submit" class="hs-btn-sm">Save</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.hr.settings.departments.delete', $department->id) }}"
                                          onsubmit="return confirm('Delete {{ addslashes($department->name) }}?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="hs-btn-sm danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty-state"><p>No departments yet.</p></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="hs-section">Add a department</div>
            <form method="POST" action="{{ route('admin.hr.settings.departments.store') }}" class="hs-form">
                @csrf
                <div>
                    <label for="deptName">Name</label>
                    <input type="text" id="deptName" name="name" placeholder="e.g. Accounts" required>
                </div>
                <div>
                    <label for="deptCode">Code</label>
                    <input type="text" id="deptCode" name="code" placeholder="e.g. ACC">
                </div>
                <div>
                    <label for="deptLead">Team lead</label>
                    <select id="deptLead" name="lead_user_id">
                        <option value="">Nobody yet</option>
                        @foreach($staff as $person)
                            <option value="{{ $person->id }}">{{ $person->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary" style="height: 40px; padding: 0 1.1rem; border-radius: 10px; cursor: pointer; font-weight: 600;">
                        <i class="fas fa-plus"></i> Add department
                    </button>
                </div>
            </form>
            <p class="hs-note">
                The team lead approves their own department’s leave at the first step, when the approval flow is set to
                “Team Lead first, then HR”. Leave types and their allowances are set on the
                <a href="{{ route('admin.hr.leaves') }}" style="color: var(--primary);">Leave Management</a> page.
            </p>
        </div>
        {{-- ================= BONUSES ================= --}}
        <div class="hs-panel" id="hsPanel-bonuses" @if($tab !== 'bonuses') hidden @endif>
            <p class="hs-note" style="margin-top: 0;">
                What an agent is working towards, shown as blocks at the top of their own Overview. Change a figure here
                and their dashboard follows immediately. Every change is kept in the record at the bottom of this tab.
            </p>

            <div style="overflow-x: auto; margin-top: 1.2rem;">
                <table>
                    <thead>
                        <tr>
                            <th>Kind</th><th>Shown as</th><th>Sales needed ($)</th><th>Reward</th>
                            <th>Extra</th><th>Roles</th><th>Order</th><th>Active</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($incentives as $rule)
                        @php $iid = 'incForm_' . $rule->id; @endphp
                        <tr>
                            <td>
                                <select form="{{ $iid }}" class="hs-cell-input" name="kind" style="width: 150px;">
                                    @foreach(\App\Models\IncentiveRule::KINDS as $value => $label)
                                        <option value="{{ $value }}" {{ $rule->kind === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input form="{{ $iid }}" class="hs-cell-input" type="text" name="label" value="{{ $rule->label }}" required style="width: 170px;">
                                <input form="{{ $iid }}" class="hs-cell-input" type="text" name="note" value="{{ $rule->note }}" placeholder="small print" style="width: 170px; margin-top: 4px; font-size: 0.78rem;">
                            </td>
                            <td><input form="{{ $iid }}" class="hs-cell-input" type="number" step="0.01" min="0" name="threshold" value="{{ $rule->threshold !== null ? rtrim(rtrim(number_format($rule->threshold, 2, '.', ''), '0'), '.') : '' }}" style="width: 110px;"></td>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <input form="{{ $iid }}" class="hs-cell-input" type="number" step="0.01" min="0" name="reward_amount" value="{{ rtrim(rtrim(number_format($rule->reward_amount, 2, '.', ''), '0'), '.') }}" style="width: 100px;">
                                    <select form="{{ $iid }}" class="hs-cell-input" name="reward_currency" style="width: 75px;">
                                        <option value="PKR" {{ $rule->reward_currency === 'PKR' ? 'selected' : '' }}>PKR</option>
                                        <option value="USD" {{ $rule->reward_currency === 'USD' ? 'selected' : '' }}>USD</option>
                                    </select>
                                </div>
                            </td>
                            <td>
                                {{-- Only the streak rule uses these two. --}}
                                <div style="display: flex; gap: 5px; align-items: center;">
                                    <input form="{{ $iid }}" class="hs-cell-input" type="number" min="1" max="24" name="months" value="{{ $rule->months }}" placeholder="months" title="Months running" style="width: 80px;">
                                    <input form="{{ $iid }}" class="hs-cell-input" type="number" step="0.01" min="0" name="salary_increment" value="{{ $rule->salary_increment !== null ? rtrim(rtrim(number_format($rule->salary_increment, 2, '.', ''), '0'), '.') : '' }}" placeholder="increment" title="Salary increment in $" style="width: 100px;">
                                </div>
                            </td>
                            <td><input form="{{ $iid }}" class="hs-cell-input" type="text" name="roles" value="{{ $rule->roles }}" title="Comma separated, e.g. agent,closer" style="width: 110px;"></td>
                            <td><input form="{{ $iid }}" class="hs-cell-input" type="number" min="0" max="999" name="position" value="{{ $rule->position }}" style="width: 70px;"></td>
                            <td><input form="{{ $iid }}" type="checkbox" name="is_active" value="1" {{ $rule->is_active ? 'checked' : '' }}></td>
                            <td>
                                <div style="display: flex; gap: 6px;">
                                    <form id="{{ $iid }}" method="POST" action="{{ route('admin.hr.settings.incentives.update', $rule->id) }}">
                                        @csrf @method('PUT')
                                        <button type="submit" class="hs-btn-sm">Save</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.hr.settings.incentives.delete', $rule->id) }}"
                                          onsubmit="return confirm('Remove {{ addslashes($rule->label) }}? The record of it stays.');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="hs-btn-sm danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="empty-state"><p>Nothing set up yet.</p></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="hs-section">Add a bonus or achievement</div>
            <form method="POST" action="{{ route('admin.hr.settings.incentives.store') }}" class="hs-form">
                @csrf
                <div>
                    <label for="incKind">Kind</label>
                    <select id="incKind" name="kind" required>
                        @foreach(\App\Models\IncentiveRule::KINDS as $value => $label)
                            <option value="{{ $value }}" {{ $value === 'bounty' ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="incLabel">Shown as</label>
                    <input type="text" id="incLabel" name="label" placeholder="e.g. Sale above $5,000" required>
                </div>
                <div>
                    <label for="incNote">Small print</label>
                    <input type="text" id="incNote" name="note" placeholder="e.g. PKR 30,000 bounty">
                </div>
                <div>
                    <label for="incThreshold">Sales needed ($)</label>
                    <input type="number" id="incThreshold" name="threshold" step="0.01" min="0" placeholder="5000">
                </div>
                <div>
                    <label for="incReward">Reward</label>
                    <input type="number" id="incReward" name="reward_amount" step="0.01" min="0" value="0">
                </div>
                <div>
                    <label for="incCurrency">Currency</label>
                    <select id="incCurrency" name="reward_currency">
                        <option value="PKR">PKR</option>
                        <option value="USD">USD</option>
                    </select>
                </div>
                <div>
                    <label for="incMonths">Months (streak only)</label>
                    <input type="number" id="incMonths" name="months" min="1" max="24" placeholder="3">
                </div>
                <div>
                    <label for="incIncrement">Salary increment ($)</label>
                    <input type="number" id="incIncrement" name="salary_increment" step="0.01" min="0" placeholder="5000">
                </div>
                <div>
                    <label for="incRoles">Roles</label>
                    <input type="text" id="incRoles" name="roles" value="agent" placeholder="agent,closer">
                </div>
                <div>
                    <button type="submit" class="btn btn-primary" style="height: 40px; padding: 0 1.1rem; border-radius: 10px; cursor: pointer; font-weight: 600;">
                        <i class="fas fa-plus"></i> Add
                    </button>
                </div>
            </form>
            <p class="hs-note">
                A <b>target</b> is the figure everything else is measured against — there is only one.
                A <b>bounty</b> is earned by passing its sales figure in a month. A <b>streak</b> asks for the target to be hit
                several months running. An <b>allowance</b> is paid once the month's target is reached.
            </p>

            <div class="hs-section">Track record</div>
            @forelse($incentiveLogs as $log)
                <div class="hs-log">
                    <b>{{ $log->label }}</b>
                    <span class="hs-log-action hs-log-{{ $log->action }}">{{ $log->action }}</span>
                    <span class="hs-log-meta">{{ $log->editor->name ?? 'Someone' }} &middot; {{ $log->created_at->format('d M Y, h:i A') }}</span>
                    @if($log->lines())
                        <div class="hs-log-changes">{{ implode(' · ', $log->lines()) }}</div>
                    @endif
                </div>
            @empty
                <p class="hs-note" style="margin-top: 0;">No changes recorded yet.</p>
            @endforelse
        </div>

        {{-- ================= IMPORT ================= --}}
        <div class="hs-panel" id="hsPanel-import" @if($tab !== 'import') hidden @endif>
            <p class="hs-note" style="margin-top: 0;">
                For a biometric device or a spreadsheet of clock-ins. Every row is judged by the same rules as a web
                check-in, and kept marked as an import so a filled-in day is never mistaken for one somebody clocked
                themselves. The whole file is read first — one bad row and nothing is written.
            </p>

            <div style="border: 1px solid var(--border); border-radius: 12px; background: var(--bg-body); padding: 1rem 1.1rem; margin: 1.1rem 0; font-family: monospace; font-size: 0.8rem; color: var(--text-muted); overflow-x: auto;">
                employee,date,check in,check out<br>
                hamza,2026-09-25,19:02,04:05<br>
                sana@systematicitsolutions.com,2026-09-25,18:55,03:58
            </div>
            <p class="hs-note" style="margin-top: 0;">
                “employee” may be a username, an email, or the exact name. Times may be a time of day or a full
                timestamp; an end time earlier than the start is taken as the next morning.
            </p>

            <form method="POST" action="{{ route('admin.hr.settings.import') }}" enctype="multipart/form-data" class="hs-form" style="margin-top: 1.2rem;">
                @csrf
                <div>
                    <label for="imFile">CSV file</label>
                    <input type="file" id="imFile" name="file" accept=".csv,text/csv" required>
                </div>
                <div style="flex: 1 1 220px;">
                    <label for="imReason">Where it came from</label>
                    <input type="text" id="imReason" name="reason" placeholder="e.g. September export from the biometric device" required>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary" style="height: 40px; padding: 0 1.1rem; border-radius: 10px; cursor: pointer; font-weight: 600;">
                        <i class="fas fa-file-arrow-up"></i> Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Tabs are shown and hidden in place, and the chosen one is remembered per
    // user, so saving a setting does not throw HR back to the first tab.
    function hsShow(tab) {
        document.querySelectorAll('.hs-panel').forEach(function (panel) {
            panel.hidden = panel.id !== 'hsPanel-' + tab;
        });
        document.querySelectorAll('.hs-tab').forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.tab === tab);
        });
        try { localStorage.setItem('crm.hrSettingsTab.' + (window.CRM_USER_ID || 0), tab); } catch (e) { /* private mode */ }

        var url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        window.history.replaceState({}, '', url);
    }

    (function () {
        var url = new URL(window.location.href);
        if (url.searchParams.get('tab')) return;             // the page was asked for a tab
        try {
            var saved = localStorage.getItem('crm.hrSettingsTab.' + (window.CRM_USER_ID || 0));
            if (saved && document.getElementById('hsPanel-' + saved)) hsShow(saved);
        } catch (e) { /* private mode */ }
    })();
</script>
@endpush
@endsection
