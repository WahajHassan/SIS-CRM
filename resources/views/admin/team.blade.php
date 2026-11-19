@extends('layouts.admin')

@section('title', 'Sales Team')

@section('content')
<div class="header" style="margin-bottom: 2rem;">
    <div class="card" style="width: 100%; margin-bottom: 0;">
        <form action="{{ route('admin.stats.update') }}" method="POST" style="display: flex; align-items: center; justify-content: space-between; gap: 2rem;">
            @csrf
            <div>
                <h3 style="font-size: 1rem; margin-bottom: 5px;">Company Monthly Target</h3>
                <p style="font-size: 0.8rem; color: var(--text-muted);">Set the total goal for the entire sales team.</p>
            </div>
            <div style="display: flex; align-items: center; gap: 15px;">
                <div style="position: relative;">
                    <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-weight: 700; color: var(--text-muted);">$</span>
                    <input type="number" name="monthly_target" value="{{ $stats_row->monthly_target }}" {{ auth()->user()->isAgent() ? 'disabled' : '' }} style="padding: 0.7rem 1rem 0.7rem 25px; border-radius: 10px; border: 1px solid var(--border); font-weight: 700; width: 180px;">
                </div>
                @if(!auth()->user()->isAgent())
                <button type="submit" class="btn btn-primary">Update Goal</button>
                @endif
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; gap: 15px; flex-wrap: wrap;">
        <div class="card-title">Team Members</div>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <form method="GET" action="{{ route('admin.team') }}" style="display: flex; gap: 8px; margin: 0;">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search team member..." style="padding: 0.5rem 1rem; border-radius: 8px; border: 1px solid var(--border); outline: none; font-size: 0.85rem; width: 220px; font-family: inherit;">
                <button type="submit" class="btn btn-ghost" style="border: 1px solid var(--border); background: var(--bg-card); padding: 0.5rem 1rem; border-radius: 8px;"><i class="fas fa-search"></i></button>
                @if(request('search'))
                    <a href="{{ route('admin.team') }}" class="btn btn-ghost" style="border: 1px solid var(--border); padding: 0.5rem 1rem; border-radius: 8px; color: var(--danger);"><i class="fas fa-times"></i></a>
                @endif
            </form>
            @if(!auth()->user()->isAgent())
            <button class="btn btn-primary" onclick="document.getElementById('addMemberModal').style.display='flex'">+ Add New Member</button>
            @endif
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Member</th>
                <th>Role</th>
                <th>Closer</th>
                <th>Monthly Goal</th>
                <th>Transfers</th>
                <th>Sales</th>
                <th>Revenue (MTD)</th>
                <th>Conversion</th>
                <th>Performance</th>
                @if(!auth()->user()->isAgent())
                <th>Actions</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($performers as $p)
            <tr>
                <td>
                    <div style="display: flex; align-items: center; gap: 12px;">
{{-- A performer may or may not have a login behind them; where there
                             is one, this is the same face and the same tick as everywhere
                             else, and where there is not, it is a name and no claim. --}}
                        <x-face :user="$p->user" :size="35" />
                        <div style="display: flex; flex-direction: column;">
                            <div style="font-weight: 600; display: flex; align-items: center; gap: 8px;">
                                {{ $p->display_name }}
                                @if($p->user?->isVerified())
                                    <i class="fas fa-circle-check person-tick" title="Verified" aria-label="Verified"></i>
                                @endif
                                @if($p->status == 'DRAFT')
                                    <span style="font-size: 0.6rem; background: rgba(156,163,175,0.2); color: var(--text-muted); padding: 2px 6px; border-radius: 4px; font-weight: 700; letter-spacing: 0.5px;">DRAFT</span>
                                @elseif($p->status == 'INACTIVE')
                                    <span style="font-size: 0.6rem; background: rgba(239,68,68,0.1); color: var(--danger); padding: 2px 6px; border-radius: 4px; font-weight: 700; letter-spacing: 0.5px;">INACTIVE</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </td>
                <td style="color: var(--text-muted); font-size: 0.85rem;">{{ $p->role }}</td>
                <td>
                    @if(!auth()->user()->isAgent())
                    <form action="{{ route('admin.performers.toggle-closer', $p->id) }}" method="POST" style="display:inline-flex;">
                        @csrf
                        <button type="submit" style="background: none; border: none; cursor: pointer; padding: 0; display: inline-flex; outline: none;">
                            <span class="badge" style="background: {{ $p->is_closer ? 'rgba(16,185,129,0.1)' : 'rgba(100,116,139,0.1)' }}; color: {{ $p->is_closer ? 'var(--success)' : 'var(--text-muted)' }};">
                                {{ $p->is_closer ? '✓ CLOSER' : '— NO' }}
                            </span>
                        </button>
                    </form>
                    @else
                    <span class="badge" style="background: {{ $p->is_closer ? 'rgba(16,185,129,0.1)' : 'rgba(100,116,139,0.1)' }}; color: {{ $p->is_closer ? 'var(--success)' : 'var(--text-muted)' }};">
                        {{ $p->is_closer ? '✓ CLOSER' : '— NO' }}
                    </span>
                    @endif
                </td>
                <td><span style="font-weight: 700;">${{ number_format($p->effective_goal) }}</span></td>
                <td style="text-align: center; font-weight: 700; color: var(--primary);">{{ $p->effective_transferred }}</td>
                <td style="text-align: center; font-weight: 800; color: var(--green);">{{ $p->effective_sales }}</td>
                <td style="font-weight: 700; color: var(--text-main);">${{ number_format($p->revenue) }}</td>
                <td>
                    <span class="badge" style="background: rgba(59,130,246,0.1); color: var(--info);">{{ $p->conversion_rate }}%</span>
                </td>
                <td style="width: 150px;">
                    @php $progress = min(100, ($p->revenue / max(1, $p->effective_goal)) * 100); @endphp
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 5px;">{{ round($progress) }}% of goal</div>
                    <div style="width: 100%; height: 6px; background: var(--bg-subtle); border-radius: var(--radius-pill); overflow: hidden;">
                        <div style="width: {{ $progress }}%; height: 100%; background: {{ $progress >= 100 ? 'var(--success)' : 'var(--primary)' }};"></div>
                    </div>
                </td>
                @if(!auth()->user()->isAgent())
                <td style="display: flex; gap: 10px; align-items: center; justify-content: flex-start;">
                    <button type="button" onclick="toggleEditRow({{ $p->id }})" style="background: none; border: none; color: var(--primary); cursor: pointer;" title="Edit"><i class="fas fa-edit"></i></button>
                    @if(auth()->user()->isAdmin())
                    <form action="{{ route('admin.performers.delete', $p->id) }}" method="POST" onsubmit="confirmDelete(event, this, '{{ $p->status == 'INACTIVE' ? 'Reactivate' : 'Deactivate' }} this member?')" style="margin: 0; display: inline-flex;">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-ghost btn-sm" style="color: {{ $p->status == 'INACTIVE' ? 'var(--success)' : 'var(--danger)' }};" title="{{ $p->status == 'INACTIVE' ? 'Reactivate' : 'Deactivate' }}"><i class="fas {{ $p->status == 'INACTIVE' ? 'fa-user-check' : 'fa-user-slash' }}"></i></button>
                    </form>
                    @if(in_array(auth()->user()->role, ['admin', 'superadmin']))
                    <form action="{{ route('admin.performers.force-delete', $p->id) }}" method="POST" onsubmit="confirmDelete(event, this, 'Permanently delete this member? This action cannot be undone.')" style="margin: 0; display: inline-flex;">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-ghost btn-sm" style="color: var(--danger);" title="Permanently Delete"><i class="fas fa-trash-alt"></i></button>
                    </form>
                    @endif
                    
                    @php $teamUser = \App\Models\User::where('sales_performer_id', $p->id)->first(); @endphp
                    @if(auth()->user()->isAdmin() && $teamUser)
                    <button onclick="document.getElementById('historyModal_{{ $teamUser->id }}').style.display='flex'" style="background: none; border: none; color: var(--info); cursor: pointer;" title="Login History"><i class="fas fa-history"></i></button>
                    @endif
                    @endif

                    <!-- Login History Modal -->
                    @if(auth()->user()->isAdmin() && isset($teamUser))
                    <div id="historyModal_{{ $teamUser->id }}" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000;">
                        <div class="card" style="width: 700px; margin: 0; text-align: left; max-height: 80vh; overflow-y: auto;">
                            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                                <div class="card-title">Login History: {{ $p->name }}</div>
                                <div style="display: flex; gap: 10px;">
                                    <form action="{{ route('admin.users.login_history.delete', $teamUser->id) }}" method="POST" onsubmit="confirmDelete(event, this, 'Are you sure you want to clear all login history for this user?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-danger" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;"><i class="fas fa-trash-alt"></i> Clear History</button>
                                    </form>
                                    <button class="btn btn-ghost" onclick="document.getElementById('historyModal_{{ $teamUser->id }}').style.display='none'">&times;</button>
                                </div>
                            </div>
                            <div style="padding: 1rem 0;">
                                @php $logs = $teamUser->loginLogs()->latest()->take(20)->get(); @endphp
                                <div style="margin-bottom: 1rem; font-weight: 600;">Total Logins: {{ $teamUser->loginLogs()->count() }}</div>
                                @if($logs->count() > 0)
                                <table class="table" style="font-size: 0.85rem; width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="border-bottom: 1px solid var(--border); text-align: left;">
                                            <th style="padding: 0.5rem;">Date & Time</th>
                                            <th style="padding: 0.5rem;">IP Address</th>
                                            <th style="padding: 0.5rem;">Device/Browser</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($logs as $log)
                                        <tr style="border-bottom: 1px solid var(--border);">
                                            <td style="padding: 0.5rem;">{{ $log->created_at->format('M d, Y h:i A') }}</td>
                                            <td style="padding: 0.5rem;">{{ $log->ip_address ?: 'Unknown' }}</td>
                                            <td style="padding: 0.5rem; color: var(--text-muted); font-size: 0.75rem;">{{ Str::limit($log->user_agent, 60) }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                @else
                                <div style="color: var(--text-muted);">No login history found.</div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endif

                </td>
                @endif
            </tr>

            {{--
             | Edit form, inline underneath the member it belongs to.
             |
             | This was a position:fixed overlay nested inside the Actions <td>.
             | As a table row it opens in place instead of covering the table, so
             | the row being edited stays visible above the form.
            --}}
            @if(!auth()->user()->isAgent())
            <tr id="editMemberRow_{{ $p->id }}" class="edit-row" style="display: none;">
                <td colspan="10" style="padding: 0; border-top: none;">
                    <div class="edit-row-inner">
                        <div class="card" style="margin: 0; text-align: left; box-shadow: none; background: transparent; border: none;">
                                <div class="card-header">
                                    <div class="card-title">Edit Team Member</div>
                                    <button class="btn btn-ghost" onclick="toggleEditRow({{ $p->id }})">&times;</button>
                                </div>
                                <form action="{{ route('admin.performers.update', $p->id) }}" method="POST" enctype="multipart/form-data">
                                    @csrf @method('PUT')
                                    
                                    <div style="display: flex; gap: 15px; margin-bottom: 1rem;">
                                        <div style="flex: 1;">
                                            <label style="display: block; font-size: 0.85rem; margin-bottom: 5px;">Full Name</label>
                                            <input type="text" name="name" required value="{{ $p->name }}" style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border);">
                                        </div>
                                        <div style="flex: 1;">
                                            <label style="display: block; font-size: 0.85rem; margin-bottom: 5px;">Pseudo Name</label>
                                            <input type="text" name="sudo_name" value="{{ $p->sudo_name }}" style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border);">
                                        </div>
                                        <div style="flex: 1;">
                                            <label style="display: block; font-size: 0.85rem; margin-bottom: 5px;">Employee ID Number</label>
                                            <input type="text" name="employee_id_number" value="{{ $p->employee_id_number }}" style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border);">
                                        </div>
                                    </div>
    
                                    <div style="display: flex; gap: 15px; margin-bottom: 1rem;">
                                        <div style="flex: 1;">
                                            <label style="display: block; font-size: 0.85rem; margin-bottom: 5px;">Role</label>
                                            <select name="role" style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-card);">
                                                <option value="Sales Agent" {{ ($p->role ?? 'Sales Agent') == 'Sales Agent' ? 'selected' : '' }}>Sales Agent</option>
                                                <option value="Floor Manager" {{ ($p->role ?? '') == 'Floor Manager' ? 'selected' : '' }}>Floor Manager</option>
                                                <option value="Closer" {{ ($p->role ?? '') == 'Closer' ? 'selected' : '' }}>Closer</option>
                                            </select>
                                        </div>
                                        <div style="flex: 1;">
                                            <label style="display: block; font-size: 0.85rem; margin-bottom: 5px;">ID Card Number</label>
                                            <input type="text" name="id_card_number" value="{{ $p->id_card_number }}" style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border);">
                                        </div>
                                        <div style="flex: 1;">
                                            <label style="display: block; font-size: 0.85rem; margin-bottom: 5px;">Monthly Target</label>
                                            <input type="number" name="goal" value="{{ $p->effective_goal }}" style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border);">
                                        </div>
                                    </div>
    
                                    <div style="display: flex; gap: 15px; margin-bottom: 1rem;">
                                        <div style="flex: 1;">
                                            <label style="display: block; font-size: 0.85rem; margin-bottom: 5px;">Transfers</label>
                                            <input type="number" name="number_of_transferred" value="{{ $p->effective_transferred }}" style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border);">
                                        </div>
                                        <div style="flex: 1;">
                                            <label style="display: block; font-size: 0.85rem; margin-bottom: 5px;">Number of Sales</label>
                                            <input type="number" name="number_of_sales" value="{{ $p->effective_sales }}" style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border);">
                                        </div>
                                        <div style="flex: 1;">
                                            <label style="display: block; font-size: 0.85rem; margin-bottom: 5px;">Amount of Sales</label>
                                            <input type="number" step="0.01" name="revenue" value="{{ $p->revenue }}" style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border);">
                                        </div>
                                    </div>
    
                                    <div style="display: flex; gap: 15px; margin-bottom: 1rem;">
                                        <div style="flex: 1;">
                                            <label style="display: block; font-size: 0.85rem; margin-bottom: 5px;">Contract Type</label>
                                            <select name="contract_type" style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-card);">
                                                <option value="">Select Type</option>
                                                <option value="probation" {{ $p->contract_type == 'probation' ? 'selected' : '' }}>Probation</option>
                                                <option value="permanent" {{ $p->contract_type == 'permanent' ? 'selected' : '' }}>Permanent</option>
                                            </select>
                                        </div>
                                        <div style="flex: 1;">
                                            <label style="display: block; font-size: 0.85rem; margin-bottom: 5px;">Contract File (PDF)</label>
                                            <input type="file" name="contract_file" accept=".pdf" style="width: 100%; padding: 0.5rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-card);">
                                            @if($p->contract_file)
                                                <div style="margin-top: 5px; font-size: 0.75rem;">
                                                    <a href="{{ Storage::url($p->contract_file) }}" target="_blank" style="color: var(--primary); text-decoration: underline;">View Current Contract</a>
                                                </div>
                                            @endif
                                        </div>
                                        <div style="flex: 1;">
                                            <label style="display: block; font-size: 0.85rem; margin-bottom: 5px;">Police Clearance</label>
                                            <input type="file" name="police_clearance_certificate" accept="image/*,.pdf" style="width: 100%; padding: 0.5rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-card);">
                                            @if($p->police_clearance_certificate)
                                                <div style="margin-top: 5px; font-size: 0.75rem;">
                                                    <a href="{{ Storage::url($p->police_clearance_certificate) }}" target="_blank" style="color: var(--primary); text-decoration: underline;">View Certificate</a>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
    
                                    <div style="display: flex; gap: 15px; margin-bottom: 1.5rem;">
                                        <div style="flex: 1;">
                                            <label style="display: block; font-size: 0.85rem; margin-bottom: 5px;">ID Card Front</label>
                                            <input type="file" name="id_card_front" accept="image/*,.pdf" style="width: 100%; padding: 0.5rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-card);">
                                            @if($p->id_card_front)
                                                <div style="margin-top: 5px; font-size: 0.75rem;">
                                                    <a href="{{ Storage::url($p->id_card_front) }}" target="_blank" style="color: var(--primary); text-decoration: underline;">View ID Front</a>
                                                </div>
                                            @endif
                                        </div>
                                        <div style="flex: 1;">
                                            <label style="display: block; font-size: 0.85rem; margin-bottom: 5px;">ID Card Back</label>
                                            <input type="file" name="id_card_back" accept="image/*,.pdf" style="width: 100%; padding: 0.5rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-card);">
                                            @if($p->id_card_back)
                                                <div style="margin-top: 5px; font-size: 0.75rem;">
                                                    <a href="{{ Storage::url($p->id_card_back) }}" target="_blank" style="color: var(--primary); text-decoration: underline;">View ID Back</a>
                                                </div>
                                            @endif
                                        </div>
                                        <div style="flex: 1;"></div>
                                    </div>
    
                                    <div style="display: flex; gap: 10px;">
                                        <button type="submit" class="btn btn-primary" style="flex: 1; justify-content: center;">Update Member</button>
                                        <button type="button" class="btn btn-ghost" onclick="toggleEditRow({{ $p->id }})">Cancel</button>
                                    </div>
                                </form>
                            </div>
                    </div>
                </td>
            </tr>
            @endif
            @endforeach
@if($performers->isEmpty())
            <tr class="is-blank">
                <td colspan="6">
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h4>Nobody on the team yet</h4>
                        <p>Use “Add New Member” above to put your first agent or closer on the board.</p>
                    </div>
                </td>
            </tr>
@endif

        </tbody>
    </table>
</div>

<!-- Simple Modal for Adding Member -->
<div id="addMemberModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000;">
    <div class="card" style="width: 800px; margin: 0;">
        <div class="card-header">
            <div class="card-title">Add Team Member</div>
            <button class="btn btn-ghost" onclick="document.getElementById('addMemberModal').style.display='none'">&times;</button>
        </div>
        <form action="{{ route('admin.performers.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div style="display: flex; gap: 15px; margin-bottom: 1rem;">
                <div style="flex: 1;">
                    <label style="display: block; font-size: 0.85rem; margin-bottom: 5px;">Full Name</label>
                    <input type="text" name="name" required style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border);">
                </div>
                <div style="flex: 1;">
                    <label style="display: block; font-size: 0.85rem; margin-bottom: 5px;">Pseudo Name</label>
                    <input type="text" name="sudo_name" style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border);">
                </div>
                <div style="flex: 1;">
                    <label style="display: block; font-size: 0.85rem; margin-bottom: 5px;">Employee ID Number</label>
                    <input type="text" name="employee_id_number" style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border);">
                </div>
            </div>

            <div style="display: flex; gap: 15px; margin-bottom: 1rem;">
                <div style="flex: 1;">
                    <label style="display: block; font-size: 0.85rem; margin-bottom: 5px;">Role</label>
                    <select name="role" style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-card);">
                        <option value="Sales Agent" selected>Sales Agent</option>
                        <option value="Floor Manager">Floor Manager</option>
                        <option value="Closer">Closer</option>
                    </select>
                </div>
                <div style="flex: 1;">
                    <label style="display: block; font-size: 0.85rem; margin-bottom: 5px;">ID Card Number</label>
                    <input type="text" name="id_card_number" style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border);">
                </div>
                <div style="flex: 1;">
                    <label style="display: block; font-size: 0.85rem; margin-bottom: 5px;">Monthly Target</label>
                    <input type="number" name="goal" style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border);">
                </div>
            </div>

            <div style="display: flex; gap: 15px; margin-bottom: 1rem;">
                <div style="flex: 1;">
                    <label style="display: block; font-size: 0.85rem; margin-bottom: 5px;">Contract Type</label>
                    <select name="contract_type" style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-card);">
                        <option value="">Select Type</option>
                        <option value="probation">Probation</option>
                        <option value="permanent">Permanent</option>
                    </select>
                </div>
                <div style="flex: 1;">
                    <label style="display: block; font-size: 0.85rem; margin-bottom: 5px;">Contract File (PDF)</label>
                    <input type="file" name="contract_file" accept=".pdf" style="width: 100%; padding: 0.5rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-card);">
                </div>
                <div style="flex: 1;">
                    <label style="display: block; font-size: 0.85rem; margin-bottom: 5px;">Police Clearance</label>
                    <input type="file" name="police_clearance_certificate" accept="image/*,.pdf" style="width: 100%; padding: 0.5rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-card);">
                </div>
            </div>

            <div style="display: flex; gap: 15px; margin-bottom: 1.5rem;">
                <div style="flex: 1;">
                    <label style="display: block; font-size: 0.85rem; margin-bottom: 5px;">ID Card Front</label>
                    <input type="file" name="id_card_front" accept="image/*,.pdf" style="width: 100%; padding: 0.5rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-card);">
                </div>
                <div style="flex: 1;">
                    <label style="display: block; font-size: 0.85rem; margin-bottom: 5px;">ID Card Back</label>
                    <input type="file" name="id_card_back" accept="image/*,.pdf" style="width: 100%; padding: 0.5rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-card);">
                </div>
                <div style="flex: 1;"></div>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary" style="flex: 1; justify-content: center;">Save Member</button>
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('addMemberModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('styles')
<style>
    /*
     * The edit form is a table row now, not an overlay. It has to opt out of the
     * table's row striping and hover tint, or it picks up the zebra background
     * meant for member rows and stops reading as an attached panel.
     */
    tr.edit-row,
    tr.edit-row:hover,
    tr.edit-row:nth-child(even) { background: transparent !important; }

    .edit-row-inner {
        padding: 1.25rem 1.5rem 1.5rem;
        background: var(--bg-body);
        border-left: 3px solid var(--primary);
        animation: editRowIn 0.22s ease;
    }
    html.dark-mode .edit-row-inner { background: rgba(255, 255, 255, 0.025); }

    @keyframes editRowIn {
        from { opacity: 0; transform: translateY(-6px); }
        to   { opacity: 1; transform: none; }
    }
</style>
@endpush

@push('scripts')
<script>
    /*
     * Only one editor open at a time: opening a second one while the first is
     * still expanded pushes rows around and makes it easy to type into the wrong
     * person's form.
     */
    function toggleEditRow(id) {
        var row = document.getElementById('editMemberRow_' + id);
        if (!row) return;

        var alreadyOpen = row.style.display === 'table-row';

        document.querySelectorAll('tr.edit-row').forEach(function (r) {
            r.style.display = 'none';
        });

        if (alreadyOpen) return;

        row.style.display = 'table-row';
        var first = row.querySelector('input[name="name"]');
        if (first) first.focus();
        row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
</script>
@endpush
