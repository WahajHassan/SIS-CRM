@extends('layouts.admin')

@section('title', 'User Management')

@section('content')
<style>
    /* Access switches. The grid flows into as many columns as the panel is wide,
       which keeps all of them visible instead of one tall column to scroll. */
    .perm-box { border: 1px solid var(--border); border-radius: 12px; padding: 0.9rem 1rem 1rem; background: var(--bg-card); margin-bottom: 1.4rem; }
    .perm-box-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: 0.9rem; }
    .perm-box-title { display: block; font-size: 0.92rem; font-weight: 700; color: var(--text-main); }
    .perm-box-title i { margin-right: 6px; color: var(--primary); }
    .perm-box-note { display: block; font-size: 0.75rem; color: var(--text-muted); margin-top: 2px; max-width: 46ch; line-height: 1.4; }
    .perm-box-actions { display: flex; gap: 6px; flex-shrink: 0; }
    .perm-box-actions button { border: 1px solid var(--border); background: var(--bg-body); color: var(--text-main); border-radius: 7px; padding: 0.3rem 0.6rem; font-size: 0.73rem; font-weight: 600; cursor: pointer; white-space: nowrap; }
    .perm-box-actions button:hover { border-color: var(--primary); color: var(--primary); }

    .perm-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(235px, 1fr)); gap: 4px 14px; align-items: start; }
    .perm-group-head { grid-column: 1 / -1; font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.07em; color: var(--text-muted); font-weight: 700; padding: 0.55rem 0 0.15rem; border-top: 1px solid var(--border); margin-top: 0.35rem; }
    .perm-grid .perm-group-head:first-child { border-top: none; margin-top: 0; padding-top: 0; }

    .perm-item { display: flex; align-items: flex-start; gap: 9px; padding: 0.4rem 0.45rem; border-radius: 8px; cursor: pointer; min-width: 0; }
    .perm-item:hover { background: rgba(128,128,128,0.07); }
    .perm-text { min-width: 0; }
    .perm-label { display: block; font-size: 0.82rem; font-weight: 600; color: var(--text-main); line-height: 1.3; }
    .perm-label i { width: 15px; margin-right: 5px; color: var(--text-muted); font-size: 0.78rem; }
    .perm-help { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-size: 0.7rem; color: var(--text-muted); line-height: 1.35; margin-top: 1px; }

    /* On/off switch */
    .perm-switch { position: relative; display: inline-block; width: 36px; height: 20px; flex-shrink: 0; margin-top: 1px; }
    .perm-switch input { opacity: 0; width: 0; height: 0; position: absolute; }
    .perm-slider { position: absolute; inset: 0; background: #cbd5e1; border-radius: 20px; transition: background .18s ease; }
    .perm-slider:before { content: ""; position: absolute; height: 14px; width: 14px; left: 3px; top: 3px; background: #fff; border-radius: 50%; transition: transform .18s ease; box-shadow: 0 1px 3px rgba(0,0,0,.25); }
    .perm-switch input:checked + .perm-slider { background: linear-gradient(135deg, #f99d4e 0%, #e93b6e 100%); }
    .perm-switch input:checked + .perm-slider:before { transform: translateX(16px); }
    .perm-switch input:focus-visible + .perm-slider { outline: 2px solid var(--primary); outline-offset: 2px; }
    html.dark-mode .perm-slider { background: #475569; }

    @media (max-width: 560px) {
        .perm-grid { grid-template-columns: 1fr; }
        .perm-box-head { flex-direction: column; }
    }

    /*
     * Inline expanding panels, replacing what used to be position:fixed popups.
     *
     * A centred overlay box kept fighting the page around it — the sidebar showing
     * through, the box itself scrolling to a stale position or losing its own top
     * edge off-screen depending on the exact combination of flexbox centering,
     * backdrop-filter and z-index in play. None of that exists here: the panel is
     * an ordinary block in the normal page flow, directly below the row it belongs
     * to, and it can never be positioned wrong because nothing ever takes it out of
     * that flow. It just pushes the rows below it down while it's open.
     */
    .inline-panel-row > td { padding: 0 !important; border: none !important; }
    .inline-panel {
        background: var(--bg-body);
        border-top: 3px solid var(--primary);
        border-bottom: 1px solid var(--border);
        padding: 1.5rem clamp(1rem, 4vw, 2.5rem);
    }
    .inline-panel-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem; }
    .inline-panel-title { font-size: 1.05rem; font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 8px; }
    .inline-panel-title i { color: var(--primary); }

    /* The panel spans the full table width, so the fields flow into as many columns
       as fit instead of sitting in a narrow strip with empty space beside it — the
       same auto-fill approach as .perm-grid, so the two line up visually. */
    .field-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
    .field-grid .field-wide { grid-column: 1 / -1; }
    .field-grid label { display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 600; }
    .field-grid input, .field-grid select, .field-grid textarea {
        width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border);
        background: var(--bg-card); color: var(--text-main);
    }
</style>
<script>
    /* Defaults per role, read from config/permissions.php so the form and the
       server always agree on what a role starts with. */
    const PERM_ROLE_DEFAULTS = @json(collect(config('permissions.role_defaults', []))->map(function ($sections) {
        return $sections === ['*'] ? array_keys(config('permissions.sections', [])) : $sections;
    }));

    function permsSetAll(uid, on) {
        document.querySelectorAll('#perms_' + uid + ' input[type=checkbox]').forEach(cb => cb.checked = on);
    }

    /* Ticks whatever the chosen role normally gets. Used by the "Reset to role"
       button and whenever the role dropdown changes on the add form. */
    function permsApplyRoleDefaults(uid, role) {
        const container = document.getElementById('perms_' + uid);
        if (!container) return;

        if (!role) {
            const select = uid === 'add'
                ? document.getElementById('add_role_select')
                : document.querySelector('#editUserPanel_' + uid + ' select[name=role]');
            role = select ? select.value : null;
        }

        const allowed = PERM_ROLE_DEFAULTS[role] || [];
        container.querySelectorAll('input[type=checkbox]').forEach(cb => {
            cb.checked = allowed.includes(cb.dataset.section);
        });
    }

    /* Shows or hides one inline panel. shownDisplay is 'table-row' for the per-user
       panels (they live in a <tr>) and 'block' for the add-user panel (a plain div). */
    function toggleInline(id, shownDisplay) {
        const el = document.getElementById(id);
        if (!el) return;
        el.style.display = (el.style.display === 'none' || el.style.display === '') ? shownDisplay : 'none';
    }

    document.addEventListener('DOMContentLoaded', function () {
        const addRole = document.getElementById('add_role_select');
        if (addRole) {
            addRole.addEventListener('change', () => permsApplyRoleDefaults('add', addRole.value));
        }
    });
</script>
<div style="display: block;">
    <!-- Users Table -->
    <div class="card" style="width: 100%;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <div class="card-title">Registered Users</div>
            <button class="btn btn-primary" onclick="toggleInline('addUserPanel', 'block')">+ Add User</button>
        </div>

        <!-- Add New User Panel -->
        <div id="addUserPanel" class="inline-panel" style="display: none; border-radius: 14px; margin-bottom: 1.5rem;">
            <div class="inline-panel-head">
                <div class="inline-panel-title"><i class="fas fa-user-plus"></i> Add New User</div>
                <button type="button" class="btn btn-ghost" onclick="toggleInline('addUserPanel', 'block')">&times;</button>
            </div>

            <form action="{{ route('admin.users.store') }}" method="POST">
                @csrf

                @if($errors->any())
                    <div class="alert alert-danger" style="background: rgba(239,68,68,0.1); color: var(--danger); font-size: 0.85rem; padding: 10px; border-radius: 8px; margin-bottom: 1rem;">
                        @foreach ($errors->all() as $error)
                            <div>- {{ $error }}</div>
                        @endforeach
                    </div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger" style="background: rgba(239,68,68,0.1); color: var(--danger); font-size: 0.85rem; padding: 10px; border-radius: 8px; margin-bottom: 1rem;">
                        {{ session('error') }}
                    </div>
                @endif

                <div class="field-grid">
                    <div>
                        <label>Full Name</label>
                        <input type="text" name="name" required>
                    </div>
                    <div>
                        <label>Username</label>
                        <input type="text" name="username" required autocomplete="off">
                    </div>
                    <div>
                        <label>Email Address</label>
                        <input type="email" name="email" required autocomplete="off">
                    </div>
                    <div>
                        <label>Password</label>
                        <input type="password" name="password" required minlength="8" autocomplete="new-password">
                    </div>
                    <div>
                        <label>Assign Role</label>
                        <select name="role" id="add_role_select" onchange="filterAddPerformers(this.value)">
                            @if(auth()->user()->isSuperAdmin())
                            <option value="superadmin">Super Admin</option>
                            <option value="admin">Admin</option>
                            @endif
                            @if(!auth()->user()->isFloorManager())
                            <option value="editor">Editor</option>
                            <option value="floor_manager">Floor Manager</option>
                            <option value="support_manager">Support Manager</option>
                            <option value="support_agent">Support Agent</option>
                            <option value="hr">HR</option>
                            <option value="team_lead">Team Lead</option>
                            @endif
                            <option value="agent" selected>Agent</option>
                            <option value="qa">QA</option>
                            <option value="closer">Closer</option>
                        </select>
                    </div>
                    @if(auth()->user()->isSuperAdmin())
                    <div>
                        <label>Assign Company</label>
                        {{-- With a single company there is nothing to choose, and an
                             empty pick only creates a user belonging to nobody, so the
                             one company is selected and the blank option is dropped. --}}
                        <select name="company_id">
                            @if($companies->count() !== 1)
                                <option value="">Select Company</option>
                            @endif
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}" {{ $companies->count() === 1 ? 'selected' : '' }}>{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div id="add_performer_div" style="display: block;">
                        <label>Linked Sales Performer</label>
                        <select name="sales_performer_id" id="add_performer_select">
                            <option value="">Select Performer</option>
                            @foreach($performers as $p)
                                <option value="{{ $p->id }}" data-is-closer="{{ $p->is_closer ? '1' : '0' }}">{{ $p->display_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @include('admin.partials.permission_toggles', [
                    'user' => null,
                    'uid' => 'add',
                    'defaultsFor' => config('permissions.role_defaults.agent', []),
                ])

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1; justify-content: center; background: linear-gradient(135deg, #f99d4e 0%, #e93b6e 100%);">Create User</button>
                    <button type="button" class="btn btn-ghost" onclick="toggleInline('addUserPanel', 'block')">Cancel</button>
                </div>
            </form>
        </div>

        @if(!empty($search))
            {{-- Arrived here from the search bar; say so, and give a way out. --}}
            <div class="field-hint" style="margin-bottom: 0.9rem; display: flex; align-items: center; gap: 10px;">
                Showing {{ $users->count() }} match(es) for &ldquo;<b>{{ $search }}</b>&rdquo;
                <a href="{{ route('admin.users') }}" class="btn btn-ghost btn-sm">Show everybody</a>
            </div>
        @endif

        <div style="overflow-x: auto;">
            <table style="width: 100%; min-width: 800px;">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email & Username</th>
                        <th>Role</th>
                        @if(auth()->user()->isSuperAdmin())
                        <th>Company</th>
                        <th>Status</th>
                        @endif
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @php $colCount = auth()->user()->isSuperAdmin() ? 6 : 4; @endphp
                    @foreach($users as $user)
                    <tr>
                        <td><x-person :user="$user" :size="36" /></td>
                        <td style="color: var(--text-muted);">{{ $user->email }}<br><small style="color: var(--primary); font-weight: 600;">{{ $user->username }}</small></td>
                        @if(auth()->user()->isSuperAdmin())
                        <td style="color: var(--text-muted); font-size: 0.85rem;">{{ $user->company ? $user->company->name : 'N/A (Superadmin)' }}</td>
                        <td>
                            <span class="badge" style="background: {{ $user->is_active ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)' }}; color: {{ $user->is_active ? 'var(--success)' : 'var(--danger)' }};">
                                {{ $user->is_active ? 'Active' : 'Disabled' }}
                            </span>
                        </td>
                        @endif
                        <td>
                            <span class="badge" style="
                                @if($user->role == 'superadmin') background: rgba(139,92,246,0.1); color: #8b5cf6;
                                @elseif($user->role == 'admin') background: rgba(239,68,68,0.1); color: var(--danger);
                                @elseif($user->role == 'editor') background: rgba(249,157,78,0.1); color: var(--primary);
                                @elseif($user->role == 'closer') background: rgba(59,130,246,0.1); color: var(--info);
                                @else background: rgba(16,185,129,0.1); color: var(--success);
                                @endif
                            ">{{ strtoupper($user->role) }}</span>
                            @if($user->isPendingApproval())
                                <span class="badge" style="background: rgba(245,158,11,0.12); color: var(--warning); margin-left: 4px;" title="Cannot sign in and is off the scoreboard until approved">
                                    <i class="fas fa-hourglass-half" style="font-size: 0.7rem;"></i> PENDING
                                </span>
                            @endif
                            @if(in_array($user->role, ['agent', 'closer', 'support_manager', 'support_agent', 'floor_manager']) && $user->performer)
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 5px;">{{ ucwords(str_replace('_', ' ', $user->role)) }}: {{ $user->performer->display_name }}</div>
                            @endif
                        </td>
                        <td>
                            <div style="display: flex; gap: 4px; align-items: center; justify-content: flex-start;">
                                @if($user->id !== auth()->id())
                                    @if(!auth()->user()->isSuperAdmin() && in_array($user->role, ['superadmin', 'admin']))
                                    <span style="font-size: 0.8rem; color: var(--text-muted);">(Restricted)</span>
                                    @elseif(auth()->user()->isFloorManager() && !in_array($user->role, ['agent', 'closer']))
                                    <span style="font-size: 0.8rem; color: var(--text-muted);">(Restricted)</span>
                                    @else
                                    @if(auth()->user()->mayImpersonate($user))
                                        <a href="{{ route('admin.impersonate', $user->id) }}" class="btn btn-ghost btn-sm" style="color: var(--info); text-decoration: none;" title="View Dashboard (Impersonate)"><i class="fas fa-user-secret"></i></a>
                                    @endif
                                    @if(auth()->user()->mayVerify($user))
                                    <form action="{{ route('admin.users.verify', $user->id) }}" method="POST" style="margin: 0; display: inline-flex;">
                                        @csrf
                                        <button type="submit" class="btn btn-ghost btn-sm"
                                                style="color: {{ $user->isVerified() ? 'var(--info)' : 'var(--text-soft)' }};"
                                                title="{{ $user->isVerified()
                                                    ? 'Verified' . ($user->verifier ? ' by ' . $user->verifier->display_name : '') . ' — click to remove'
                                                    : 'Mark as verified: their identity has been checked' }}">
                                            <i class="fas fa-circle-check"></i>
                                        </button>
                                    </form>
                                    @endif
                                    @if($user->isPendingApproval())
                                    <form action="{{ route('admin.users.approve', $user->id) }}" method="POST" style="margin: 0; display: inline-flex;">
                                        @csrf
                                        <button type="submit" class="btn btn-primary btn-sm" title="Approve — unlocks their dashboard and puts them live on the scoreboard">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                    </form>
                                    @endif
                                    <button type="button" class="btn btn-ghost btn-sm" style="color: var(--primary);" onclick="toggleInline('editUserRow_{{ $user->id }}', 'table-row')" title="Edit Role"><i class="fas fa-edit"></i></button>
                                    <button type="button" class="btn btn-ghost btn-sm" style="color: var(--warning);" onclick="toggleInline('resetPasswordRow_{{ $user->id }}', 'table-row')" title="Reset Password"><i class="fas fa-key"></i></button>
                                    <form action="{{ route('admin.users.delete', $user->id) }}" method="POST" onsubmit="confirmDelete(event, this, 'Delete this user?')" style="margin: 0; display: inline-flex;">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-ghost btn-sm" style="color: var(--danger);" title="Delete User"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                    @endif
                                @else
                                <span style="font-size: 0.8rem; color: var(--text-muted);">(You)</span>
                                @endif
                            </div>
                        </td>
                    </tr>

                    <!-- Edit Role & Info — opens as a panel directly below this row -->
                    <tr id="editUserRow_{{ $user->id }}" class="inline-panel-row" style="display: none;">
                        <td colspan="{{ $colCount }}">
                            <div class="inline-panel" id="editUserPanel_{{ $user->id }}">
                                <div class="inline-panel-head">
                                    <div class="inline-panel-title"><i class="fas fa-user-edit"></i> Update {{ $user->display_name }}</div>
                                    <button type="button" class="btn btn-ghost" onclick="toggleInline('editUserRow_{{ $user->id }}', 'table-row')">&times;</button>
                                </div>
                                <form action="{{ route('admin.users.update', $user->id) }}" method="POST">
                                    @csrf @method('PUT')
                                    <div class="field-grid">
                                        <div>
                                            <label>Username</label>
                                            <input type="text" name="username" value="{{ $user->username }}" required autocomplete="off">
                                        </div>
                                        <div>
                                            <label>Role</label>
                                            <select name="role" onchange="filterEditPerformers({{ $user->id }}, this.value); permsApplyRoleDefaults({{ $user->id }}, this.value);">
                                            @if(auth()->user()->isSuperAdmin())
                                            <option value="superadmin" {{ $user->role == 'superadmin' ? 'selected' : '' }}>Super Admin</option>
                                            <option value="admin" {{ $user->role == 'admin' ? 'selected' : '' }}>Admin (Full Access)</option>
                                            @endif
                                            @if(!auth()->user()->isFloorManager())
                                            <option value="editor" {{ $user->role == 'editor' ? 'selected' : '' }}>Editor (Add/Edit only)</option>
                                            <option value="floor_manager" {{ $user->role == 'floor_manager' ? 'selected' : '' }}>Floor Manager</option>
                                            <option value="support_manager" {{ $user->role == 'support_manager' ? 'selected' : '' }}>Support Manager</option>
                                            <option value="support_agent" {{ $user->role == 'support_agent' ? 'selected' : '' }}>Support Agent</option>
                                            <option value="hr" {{ $user->role == 'hr' ? 'selected' : '' }}>HR</option>
                                            <option value="team_lead" {{ $user->role == 'team_lead' ? 'selected' : '' }}>Team Lead</option>
                                            @endif
                                            <option value="agent" {{ $user->role == 'agent' ? 'selected' : '' }}>Agent</option>
                                            <option value="qa" {{ $user->role == 'qa' ? 'selected' : '' }}>QA</option>
                                            <option value="closer" {{ $user->role == 'closer' ? 'selected' : '' }}>Closer</option>
                                        </select>
                                        </div>
                                        @if(auth()->user()->isSuperAdmin() && $user->role !== 'superadmin')
                                        <div>
                                            <label>Assign Company</label>
                                            <select name="company_id">
                                                @foreach($companies as $company)
                                                    <option value="{{ $company->id }}" {{ $user->company_id == $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        @endif
                                        @if(auth()->user()->isSuperAdmin())
                                        <div>
                                            <label>Account Status</label>
                                            <select name="is_active" onchange="document.getElementById('disable_message_container_{{ $user->id }}').style.display = this.value == '0' ? 'block' : 'none'">
                                                <option value="1" {{ $user->is_active ? 'selected' : '' }}>Active</option>
                                                <option value="0" {{ !$user->is_active ? 'selected' : '' }}>Disabled</option>
                                            </select>
                                        </div>
                                        @endif
                                        <div>
                                            <label>Monthly Salary</label>
                                            <input type="number" name="monthly_salary" step="0.01" min="0" value="{{ rtrim(rtrim(number_format((float) $user->monthly_salary, 2, '.', ''), '0'), '.') ?: '0' }}">
                                            <small style="display: block; font-size: 0.72rem; color: var(--text-muted); margin-top: 4px;">
                                                Used on the Attendance page to work out this month's pay.
                                            </small>
                                        </div>
                                        <div id="edit_performer_{{ $user->id }}" style="display: {{ in_array($user->role, ['agent', 'closer', 'support_manager', 'support_agent', 'floor_manager']) ? 'block' : 'none' }};">
                                            <label>Linked Sales Performer</label>
                                            <select name="sales_performer_id" id="edit_performer_select_{{ $user->id }}">
                                                <option value="">Select Performer</option>
                                                @foreach($performers as $p)
                                                    <option value="{{ $p->id }}" data-is-closer="{{ $p->is_closer ? '1' : '0' }}" {{ $user->sales_performer_id == $p->id ? 'selected' : '' }}>{{ $p->display_name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        @if(auth()->user()->isSuperAdmin())
                                        <div id="disable_message_container_{{ $user->id }}" class="field-wide" style="display: {{ !$user->is_active ? 'block' : 'none' }};">
                                            <label>Disable Message (shown to user)</label>
                                            <textarea name="disable_message" rows="2">{{ $user->disable_message }}</textarea>
                                        </div>
                                        @endif
                                    </div>
                                    @if($user->role !== 'superadmin')
                                    @include('admin.partials.permission_toggles', ['user' => $user, 'uid' => $user->id])
                                    @else
                                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1.2rem;">
                                        <i class="fas fa-key"></i> Super Admin reaches every section by design, so there is nothing to switch here.
                                    </p>
                                    @endif

                                    <div style="display: flex; gap: 10px;">
                                        <button type="submit" class="btn btn-primary" style="flex: 1; justify-content: center;">Update Info</button>
                                        <button type="button" class="btn btn-ghost" onclick="toggleInline('editUserRow_{{ $user->id }}', 'table-row')">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </td>
                    </tr>

                    <!-- Reset Password — opens as a panel directly below this row -->
                    <tr id="resetPasswordRow_{{ $user->id }}" class="inline-panel-row" style="display: none;">
                        <td colspan="{{ $colCount }}">
                            <div class="inline-panel">
                                <div class="inline-panel-head">
                                    <div class="inline-panel-title"><i class="fas fa-key"></i> Reset Password for {{ $user->display_name }}</div>
                                    <button type="button" class="btn btn-ghost" onclick="toggleInline('resetPasswordRow_{{ $user->id }}', 'table-row')">&times;</button>
                                </div>
                                <form action="{{ route('admin.users.password.update', $user->id) }}" method="POST" style="max-width: 420px;">
                                    @csrf @method('PUT')
                                    <div style="margin-bottom: 1.5rem;">
                                        <label style="display: block; font-size: 0.85rem; margin-bottom: 5px;">New Password</label>
                                        <input type="password" name="password" required minlength="8" style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main);" placeholder="Enter new password (min 8 chars)">
                                    </div>
                                    <div style="display: flex; gap: 10px;">
                                        <button type="submit" class="btn btn-primary" style="flex: 1; justify-content: center;">Update Password</button>
                                        <button type="button" class="btn btn-ghost" onclick="toggleInline('resetPasswordRow_{{ $user->id }}', 'table-row')">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const performersList = [
    @foreach($performers as $p)
        { id: "{{ $p->id }}", name: {!! json_encode($p->display_name) !!}, isCloser: {{ $p->is_closer ? 'true' : 'false' }} },
    @endforeach
];

function filterEditPerformers(userId, role, selectedValue = null) {
    const performerDiv = document.getElementById('edit_performer_' + userId);
    const performerSelect = document.getElementById('edit_performer_select_' + userId);
    if (!performerDiv || !performerSelect) return;

    if (role === 'agent' || role === 'closer' || role === 'support_manager' || role === 'support_agent' || role === 'floor_manager') {
        performerDiv.style.display = 'block';

        // Save current selection if not explicitly passed
        const currentVal = selectedValue !== null ? selectedValue : performerSelect.value;

        performerSelect.innerHTML = '<option value="">Select Performer</option>';

        performersList.forEach(p => {
            let showOpt = false;
            if (role === 'closer') {
                showOpt = p.isCloser === true;
            } else if (role === 'agent') {
                showOpt = p.isCloser === false;
            } else {
                showOpt = true; // Show all for support and floor manager
            }

            if (showOpt) {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = p.name;
                if (String(p.id) === String(currentVal)) {
                    opt.selected = true;
                }
                performerSelect.appendChild(opt);
            }
        });
    } else {
        performerDiv.style.display = 'none';
        performerSelect.value = '';
    }
}

function filterAddPerformers(role) {
    const performerDiv = document.getElementById('add_performer_div');
    const performerSelect = document.getElementById('add_performer_select');
    if (!performerDiv || !performerSelect) return;

    if (role === 'agent' || role === 'closer' || role === 'support_manager' || role === 'support_agent' || role === 'floor_manager') {
        performerDiv.style.display = 'block';

        const currentVal = performerSelect.value;

        performerSelect.innerHTML = '<option value="">Select Performer</option>';

        performersList.forEach(p => {
            let showOpt = false;
            if (role === 'closer') {
                showOpt = p.isCloser === true;
            } else if (role === 'agent') {
                showOpt = p.isCloser === false;
            } else {
                showOpt = true; // Show all for support and floor manager
            }

            if (showOpt) {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = p.name;
                if (String(p.id) === String(currentVal)) {
                    opt.selected = true;
                }
                performerSelect.appendChild(opt);
            }
        });
    } else {
        performerDiv.style.display = 'none';
        performerSelect.value = '';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Initial filter for Add User Form
    const addRoleSelect = document.getElementById('add_role_select');
    if (addRoleSelect) {
        filterAddPerformers(addRoleSelect.value);
    }

    // Initial filter for all Edit user role panels
    @foreach($users as $user)
        filterEditPerformers({{ $user->id }}, '{{ $user->role }}', '{{ $user->sales_performer_id }}');
    @endforeach
});
</script>
@endpush
