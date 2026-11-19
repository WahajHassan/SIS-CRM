@extends('layouts.admin')

@section('title', 'Employee Records')

@section('content')
{{--
    The employee directory. Every row is a CRM login, so HR and User Management
    are always looking at the same people — the HR record is the extra detail.
--}}
<style>
    .hr-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 15px; flex-wrap: wrap; }
    .hr-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
    .hr-stat { border: 1px solid var(--border); border-radius: 14px; padding: 1rem 1.1rem; background: var(--bg-card); display: flex; justify-content: space-between; align-items: center; gap: 10px; }
    .hr-stat span { display: block; font-size: 0.78rem; color: var(--text-muted); margin-bottom: 5px; }
    .hr-stat strong { font-size: 1.5rem; font-weight: 700; color: var(--text-main); }
    .hr-stat i { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: rgba(99,102,241,0.1); color: var(--primary); }

    .hr-filters { display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 12px; align-items: end; }
    .hr-filters label { display: block; font-size: 0.78rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; }
    .hr-filters input, .hr-filters select { width: 100%; height: 38px; padding: 0.5rem 0.65rem; border-radius: 9px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-family: inherit; font-size: 0.85rem; }

    .emp-cell { display: flex; align-items: center; gap: 11px; }
    .emp-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; background: var(--bg-body); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; font-weight: 700; color: var(--primary); flex-shrink: 0; }
    .emp-name { font-weight: 600; color: var(--text-main); }
    .emp-sub { font-size: 0.74rem; color: var(--text-muted); }

    .hr-badge { display: inline-block; padding: 0.22rem 0.6rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700; }
    .hr-badge.perm { background: rgba(16,185,129,0.12); color: var(--success); }
    .hr-badge.prob { background: rgba(245,158,11,0.15); color: var(--warning); }

    .hr-meter { width: 90px; height: 7px; border-radius: 999px; background: var(--bg-body); border: 1px solid var(--border); overflow: hidden; }
    .hr-meter i { display: block; height: 100%; background: var(--primary); }

    .hr-birthdays { display: flex; gap: 10px; flex-wrap: wrap; }
    .hr-birthday { border: 1px solid var(--border); border-radius: 12px; padding: 0.6rem 0.9rem; background: var(--bg-card); font-size: 0.82rem; }
    .hr-birthday b { display: block; color: var(--text-main); }

    .hr-hire { border: 1px solid var(--border); border-top: 3px solid var(--primary); border-radius: 12px; background: var(--bg-body); padding: 1.2rem; margin-bottom: 1.3rem; }
    .hr-hire-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.1rem; }
    .hr-hire-head b { font-size: 1rem; color: var(--text-main); }
    .hr-hire-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
    .hr-hire-grid label { display: block; font-size: 0.78rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; }
    .hr-hire-grid input, .hr-hire-grid select { width: 100%; padding: 0.55rem 0.65rem; border-radius: 9px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); font-family: inherit; font-size: 0.86rem; }

    @media (max-width: 900px) { .hr-filters { grid-template-columns: 1fr 1fr; } }
    @media (max-width: 560px) { .hr-filters { grid-template-columns: 1fr; } }
</style>

<div style="display: flex; flex-direction: column; gap: 1.5rem;">
    <div class="card" style="margin-bottom: 0;">
        <div class="hr-head" style="border-bottom: 1px solid var(--border); padding-bottom: 1rem; margin-bottom: 1.3rem;">
            <div>
                <div class="card-title" style="margin-bottom: 5px; font-size: 1.2rem; font-weight: 700;">
                    <i class="fas fa-id-card" style="color: var(--primary);"></i> Employee Records
                </div>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">
                    Everyone on the team, their personnel file and what is still missing from it.
                </p>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <button type="button" class="btn btn-primary" onclick="hrToggleHire()" style="cursor: pointer; font-weight: 600;">
                    <i class="fas fa-user-plus"></i> Onboard new employee
                </button>
                <a href="{{ route('admin.hr.employees.export') }}" class="btn btn-ghost"
                   style="border: 1px solid var(--border); background: var(--bg-body); color: var(--text-main); font-weight: 600; text-decoration: none;">
                    <i class="fas fa-file-excel" style="color: #10b981;"></i> Export CSV
                </a>
            </div>
        </div>

        <div class="hr-stats">
            <div class="hr-stat">
                <div><span>Employees</span><strong>{{ $stats['total'] }}</strong></div>
                <i class="fas fa-users"></i>
            </div>
            <div class="hr-stat">
                <div><span>Permanent</span><strong style="color: var(--success);">{{ $stats['permanent'] }}</strong></div>
                <i class="fas fa-user-check" style="background: rgba(16,185,129,0.1); color: var(--success);"></i>
            </div>
            <div class="hr-stat">
                <div><span>On probation</span><strong style="color: var(--warning);">{{ $stats['probation'] }}</strong></div>
                <i class="fas fa-hourglass-half" style="background: rgba(245,158,11,0.12); color: var(--warning);"></i>
            </div>
            <div class="hr-stat">
                <div><span>Files incomplete</span><strong style="color: var(--danger);">{{ $stats['incomplete'] }}</strong></div>
                <i class="fas fa-folder-open" style="background: rgba(239,68,68,0.1); color: var(--danger);"></i>
            </div>
        </div>

        {{--
            Hiring happens here rather than in User Management: one form makes the
            login and the personnel file together, and gives the starter the
            targets their role is measured on, so nothing is half-created.
        --}}
        <div id="hrHire" class="hr-hire" @if(!$errors->any()) hidden @endif>
            <div class="hr-hire-head">
                <b><i class="fas fa-user-plus" style="color: var(--primary);"></i> Onboard a new employee</b>
                <button type="button" class="btn btn-ghost" style="cursor: pointer;" onclick="hrToggleHire()">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.hr.employees.store') }}">
                @csrf
                <div class="hr-hire-grid">
                    <div>
                        <label for="hName">Full name</label>
                        <input type="text" id="hName" name="name" value="{{ old('name') }}" required>
                    </div>
                    <div>
                        <label for="hUsername">Username (to sign in)</label>
                        <input type="text" id="hUsername" name="username" value="{{ old('username') }}" required>
                    </div>
                    <div>
                        <label for="hEmail">Work email</label>
                        <input type="email" id="hEmail" name="email" value="{{ old('email') }}" required>
                    </div>
                    <div>
                        <label for="hPassword">Password</label>
                        <input type="text" id="hPassword" name="password" value="{{ old('password') }}" minlength="8" required
                               placeholder="At least 8 characters">
                    </div>
                    <div>
                        <label for="hRole">Role in the CRM</label>
                        <select id="hRole" name="role" required>
                            <option value="agent">Agent</option>
                            <option value="closer">Closer</option>
                            <option value="support_agent">Support Agent</option>
                            <option value="support_manager">Support Manager</option>
                            <option value="editor">Editor</option>
                            <option value="qa">QA</option>
                            <option value="floor_manager">Floor Manager</option>
                            <option value="team_lead">Team Lead</option>
                            <option value="hr">HR</option>
                        </select>
                    </div>
                    <div>
                        <label for="hDesignation">Designation</label>
                        <input type="text" id="hDesignation" name="designation" value="{{ old('designation') }}" placeholder="e.g. Sales Executive">
                    </div>
                    <div>
                        <label for="hDepartment">Department</label>
                        <select id="hDepartment" name="department_id">
                            <option value="">Not set</option>
                            @foreach($departmentOptions as $option)
                                <option value="{{ $option->id }}" {{ (int) old('department_id') === $option->id ? 'selected' : '' }}>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="hContact">Contact number</label>
                        <input type="text" id="hContact" name="contact_no" value="{{ old('contact_no') }}">
                    </div>
                    <div>
                        <label for="hJoining">Joining date</label>
                        <input type="date" id="hJoining" name="joining_date" value="{{ old('joining_date', now()->toDateString()) }}">
                    </div>
                    <div>
                        <label for="hStatus">Probation / permanent</label>
                        <select id="hStatus" name="employment_status">
                            <option value="probation">Probation</option>
                            <option value="permanent">Permanent</option>
                        </select>
                    </div>
                    <div>
                        <label for="hSalary">Monthly salary</label>
                        <input type="number" id="hSalary" name="monthly_salary" value="{{ old('monthly_salary', 0) }}" min="0" step="0.01">
                    </div>
                </div>

                @if($errors->any())
                    <p style="color: var(--danger); font-size: 0.82rem; margin: 0.8rem 0 0;">{{ $errors->first() }}</p>
                @endif

                <div style="display: flex; gap: 10px; margin-top: 1.1rem; flex-wrap: wrap; align-items: center;">
                    <button type="submit" class="btn btn-primary" style="cursor: pointer; font-weight: 600;">
                        <i class="fas fa-check"></i> Create employee
                    </button>
                    <span style="font-size: 0.78rem; color: var(--text-muted);">
                        Creates their login, their file and the starting targets for that role. You can finish the rest of the file afterwards.
                    </span>
                </div>
            </form>
        </div>

        <form method="GET" action="{{ route('admin.hr.employees') }}" class="hr-filters">
            <div>
                <label for="hrSearch">Search</label>
                <input type="text" id="hrSearch" name="q" value="{{ $search }}" placeholder="Name, email, designation, ID card no">
            </div>
            <div>
                <label for="hrDept">Department</label>
                <select id="hrDept" name="department">
                    <option value="">All departments</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept }}" {{ $department === $dept ? 'selected' : '' }}>{{ $dept }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="hrStatus">Status</label>
                <select id="hrStatus" name="employment_status">
                    <option value="">All</option>
                    <option value="permanent" {{ $employmentStatus === 'permanent' ? 'selected' : '' }}>Permanent</option>
                    <option value="probation" {{ $employmentStatus === 'probation' ? 'selected' : '' }}>Probation</option>
                </select>
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary" style="height: 38px; padding: 0 1.2rem; border-radius: 9px; cursor: pointer; font-weight: 600;">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <a href="{{ route('admin.hr.employees') }}" class="btn btn-ghost"
                   style="border: 1px solid var(--border); height: 38px; display: flex; align-items: center; padding: 0 1rem; border-radius: 9px; text-decoration: none; font-weight: 600;">Clear</a>
            </div>
        </form>
    </div>

    @if($birthdays->count())
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header"><div class="card-title"><i class="fas fa-cake-candles" style="color: var(--warning);"></i> Birthdays in the next 30 days</div></div>
        <div class="hr-birthdays">
            @foreach($birthdays as $b)
                <div class="hr-birthday">
                    <b>{{ $b['user']->name }}</b>
                    {{ $b['date']->format('d M') }}
                    <span style="color: var(--text-muted);">
                        &middot; {{ $b['date']->isToday() ? 'today' : $b['date']->diffForHumans(null, true) . ' away' }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="card">
        <div class="card-header">
            <div class="card-title">{{ $employees->count() }} {{ \Illuminate\Support\Str::plural('employee', $employees->count()) }}</div>
        </div>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Designation</th>
                        <th>Department</th>
                        <th>Contact</th>
                        <th>Joined</th>
                        <th>Status</th>
                        <th>File</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($employees as $employee)
                    @php
                        $p = $employee->employeeProfile;
                        $complete = $p ? $p->completeness() : 0;
                    @endphp
                    <tr>
                        <td>
                            <div class="emp-cell">
{{-- One picture per person: whatever they uploaded themselves, else the
                                     photograph HR took, else their initial. --}}
                                <x-person :user="$employee" :size="40"
                                          :sub="$employee->email ?: '@' . $employee->username" />
                            </div>
                        </td>
                        <td>{{ $p?->designation ?: '—' }}
                            <div class="emp-sub" style="text-transform: uppercase;">{{ str_replace('_', ' ', $employee->role) }}</div>
                        </td>
                        <td>{{ $p?->department ?: '—' }}</td>
                        <td>
                            {{ $p?->contact_no ?: '—' }}
                            @if($p?->emergency_contact_no)
                                <div class="emp-sub">SOS {{ $p->emergency_contact_no }}</div>
                            @endif
                        </td>
                        <td>{{ $p?->joining_date?->format('d M Y') ?: '—' }}</td>
                        <td>
                            <span class="hr-badge {{ ($p?->employment_status ?? 'probation') === 'permanent' ? 'perm' : 'prob' }}">
                                {{ ($p?->employment_status ?? 'probation') === 'permanent' ? 'Permanent' : 'Probation' }}
                            </span>
                        </td>
                        <td>
                            <div class="hr-meter" title="{{ $complete }}% of the file is filled in">
                                <i style="width: {{ $complete }}%; background: {{ $complete >= 80 ? 'var(--success)' : ($complete >= 40 ? 'var(--warning)' : 'var(--danger)') }};"></i>
                            </div>
                            <div class="emp-sub">{{ $complete }}% complete</div>
                        </td>
                        <td>
                            <a href="{{ route('admin.hr.employee', $employee->id) }}" class="btn btn-ghost"
                               style="border: 1px solid var(--border); padding: 0.35rem 0.8rem; font-size: 0.78rem; border-radius: 8px; text-decoration: none; white-space: nowrap;">
                                <i class="fas fa-folder-open"></i> Open file
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 3rem; color: var(--text-muted); ">
                            <i class="fas fa-user-slash" style="font-size: 2rem; color: var(--border); display: block; margin-bottom: 1rem;"></i>
                            No employee matches that.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function hrToggleHire() {
        var box = document.getElementById('hrHire');
        if (!box) return;
        box.hidden = !box.hidden;
        if (!box.hidden) {
            box.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            var first = document.getElementById('hName');
            if (first) first.focus();
        }
    }
</script>
@endpush
@endsection
