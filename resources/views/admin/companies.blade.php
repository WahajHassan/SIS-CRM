@extends('layouts.admin')

@section('title', 'Companies')

@section('content')
<div class="card">
    <div class="card-header">
        <div class="card-title">Register & Manage Companies</div>
        <button class="btn btn-primary" onclick="document.getElementById('addCompanyModal').style.display='flex'">+ Add Company</button>
    </div>
    <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">
        Add new companies to the CRM. Creating a company automatically creates its Administrator account.
    </p>

    @if(session('success'))
        <div class="alert alert-success" style="margin-bottom: 1.5rem; padding: 1rem; border-radius: 8px;">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger" style="margin-bottom: 1.5rem; padding: 1rem; border-radius: 8px; background: rgba(239,68,68,0.1); color: var(--danger);">
            <ul style="margin: 0; padding-left: 1.2rem;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Company Name</th>
                <th>Slug / URL</th>
                <th>Admin Name / Email</th>
                <th>Registered</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($companies as $c)
            <tr>
                <td style="color: var(--text-muted);">#{{ $c->id }}</td>
                <td style="font-weight: 600;">{{ $c->name }}</td>
                <td>
                    <a href="{{ route('scoreboard', ['company_slug' => $c->slug]) }}" target="_blank" style="color: var(--primary); text-decoration: underline;">
                        /scoreboard/{{ $c->slug }}
                    </a>
                </td>
                <td>
                    @if($c->users->first())
                        <div style="font-weight: 500;">{{ $c->users->first()->name }}</div>
                        <div style="font-size: 0.8rem; color: var(--text-muted);">{{ $c->users->first()->email }}</div>
                    @else
                        <span style="color: var(--danger); font-size: 0.8rem;">No Admin User</span>
                    @endif
                </td>
                <td style="color: var(--text-muted); font-size: 0.85rem;">{{ $c->created_at->format('M d, Y') }}</td>
                <td>
                    <span class="badge" style="background: {{ $c->is_active ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)' }}; color: {{ $c->is_active ? 'var(--success)' : 'var(--danger)' }};">
                        {{ $c->is_active ? 'Active' : 'Disabled' }}
                    </span>
                </td>
                <td>
                    <div style="display: flex; gap: 10px; align-items: center; justify-content: flex-start;">
                        <button onclick="document.getElementById('editCompanyModal_{{ $c->id }}').style.display='flex'" style="background: none; border: none; color: var(--primary); cursor: pointer; font-size: 1.1rem;"><i class="fas fa-edit"></i></button>
                        @if($c->id != 1)
                        <form action="{{ route('admin.companies.delete', $c->id) }}" method="POST" onsubmit="confirmDelete(event, this, 'WARNING: Deleting this company will permanently delete all its data, deals, users, and leads. Do you want to proceed?')" style="margin: 0; display: inline-flex;">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-ghost btn-sm" style="color: var(--danger);"><i class="fas fa-trash-alt"></i></button>
                        </form>
                        @else
                        <span style="color: var(--text-muted); font-size: 0.75rem;">(System Default)</span>
                        @endif
                    </div>
                </td>
            </tr>

            {{--
                Edit Company Modal.

                It sits in a row of its own. A bare <div> here is not allowed inside
                a table, and the browser lifts it out to just before the <table> —
                tearing the <form> apart on the way, so it arrived on screen with no
                fields and no CSRF token in it and could never save anything.
            --}}
            <tr class="modal-row">
            <td colspan="7" style="padding: 0; border: none;">
            <div id="editCompanyModal_{{ $c->id }}" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000; backdrop-filter: blur(5px);">
                <div class="card" style="width: 550px; margin: 0; text-align: left; max-height: 90vh; overflow-y: auto;">
                    <div class="card-header">
                        <div class="card-title">Edit Company: {{ $c->name }}</div>
                        <button type="button" class="btn btn-ghost" onclick="document.getElementById('editCompanyModal_{{ $c->id }}').style.display='none'">&times;</button>
                    </div>
                    <form action="{{ route('admin.companies.update', $c->id) }}" method="POST" enctype="multipart/form-data" style="padding: 1.5rem;">
                        @csrf
                        @method('PUT')
                        
                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 500;">Company Name</label>
                            <input type="text" name="name" value="{{ $c->name }}" required style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border); background: var(--card); color: var(--text-main);">
                        </div>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 500;">Slug (Custom URL)</label>
                            <input type="text" name="slug" value="{{ $c->slug }}" style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border); background: var(--card); color: var(--text-main);">
                        </div>

                        @if($c->id != 1)
                        <div style="margin-bottom: 1.5rem;">
                            <label style="display: block; font-size: 0.85rem; margin-bottom: 5px;">Account Status</label>
                            <select name="is_active" style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border);" onchange="document.getElementById('company_disable_message_{{ $c->id }}').style.display = this.value == '0' ? 'block' : 'none'">
                                <option value="1" {{ $c->is_active ? 'selected' : '' }}>Active</option>
                                <option value="0" {{ !$c->is_active ? 'selected' : '' }}>Disabled</option>
                            </select>
                        </div>
                        <div id="company_disable_message_{{ $c->id }}" style="margin-bottom: 1.5rem; display: {{ !$c->is_active ? 'block' : 'none' }};">
                            <label style="display: block; font-size: 0.85rem; margin-bottom: 5px;">Disable Message (shown to all company users)</label>
                            <textarea name="disable_message" rows="3" style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border);">{{ $c->disable_message }}</textarea>
                        </div>
                        @else
                        <input type="hidden" name="is_active" value="1">
                        @endif

                        <hr style="border: none; border-top: 1px solid var(--border); margin: 1.5rem 0;">
                        <h3 style="font-size: 1rem; margin-bottom: 1rem; color: var(--primary);">Branding (Optional)</h3>
                        
                        <div style="display: flex; gap: 15px; margin-bottom: 1.5rem;">
                            <div style="flex: 1; display: flex; flex-direction: column;">
                                <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 500;">Company Logo</label>
                                <div style="flex-grow: 1; display: flex; flex-direction: column; justify-content: flex-end;">
                                    @if($c->logo && file_exists(public_path($c->logo)))
                                        <div style="margin-bottom: 10px;">
                                            <img src="{{ url('file/' . $c->logo) }}" alt="Logo" style="height: 40px; max-width: 100%; object-fit: contain; border-radius: 4px;">
                                        </div>
                                    @endif
                                </div>
                                <input type="file" name="logo" accept="image/*" style="width: 100%; padding: 0.5rem; border-radius: 10px; border: 1px solid var(--border); background: var(--card); color: var(--text-main);">
                            </div>

                            <div style="flex: 1; display: flex; flex-direction: column;">
                                <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 500;">System Logo (Dark Mode)</label>
                                <div style="flex-grow: 1; display: flex; flex-direction: column; justify-content: flex-end;">
                                    @if($c->dark_logo)
                                        <div style="margin-bottom: 10px;">
                                            <img src="{{ url('file/' . $c->dark_logo) }}" alt="Dark Logo" style="height: 40px; max-width: 100%; object-fit: contain; border-radius: 4px;">
                                        </div>
                                    @endif
                                </div>
                                <input type="file" name="dark_logo" accept="image/*" style="width: 100%; padding: 0.5rem; border-radius: 10px; border: 1px solid var(--border); background: var(--card); color: var(--text-main);">
                            </div>

                            <div style="flex: 1; display: flex; flex-direction: column;">
                                <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 500;">Favicon</label>
                                <div style="flex-grow: 1; display: flex; flex-direction: column; justify-content: flex-end;">
                                    @if($c->favicon && file_exists(public_path($c->favicon)))
                                        <div style="margin-bottom: 10px;">
                                            <img src="{{ url('file/' . $c->favicon) }}" alt="Favicon" style="height: 40px; max-width: 100%; object-fit: contain; border-radius: 4px;">
                                        </div>
                                    @endif
                                </div>
                                <input type="file" name="favicon" accept="image/*" style="width: 100%; padding: 0.5rem; border-radius: 10px; border: 1px solid var(--border); background: var(--card); color: var(--text-main);">
                            </div>

                            <div style="flex: 1; display: flex; flex-direction: column;">
                                <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 500;">Theme Color</label>
                                <div style="flex-grow: 1; display: flex; flex-direction: column; justify-content: flex-end;"></div>
                                <input type="color" name="theme_color" value="{{ $c->theme_color ?? '#f99d4e' }}" style="width: 100%; height: 40px; padding: 2px; border-radius: 5px; border: 1px solid var(--border); background: var(--card);">
                            </div>
                        </div>

                        <!-- Main Breaks Configuration -->
                        <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border);">
                            <h4 style="font-size: 1.1rem; color: var(--text-main); margin-bottom: 0.5rem; font-weight: 700;">Main Breaks Configuration</h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                                <div style="flex: 1; display: flex; flex-direction: column;">
                                    <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 500;">Number of Breaks</label>
                                    <select name="number_of_breaks" style="width: 100%; padding: 0.6rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-input); color: var(--text-main);">
                                        <option value="1" {{ $c->number_of_breaks == 1 ? 'selected' : '' }}>1 Break</option>
                                        <option value="2" {{ (!$c->number_of_breaks || $c->number_of_breaks == 2) ? 'selected' : '' }}>2 Breaks</option>
                                    </select>
                                </div>

                                <div style="flex: 1; display: flex; flex-direction: column;">
                                    <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 500;">Break 1 Name</label>
                                    <input type="text" name="break_1_name" value="{{ $c->break_1_name ?? '1st Half Break' }}" style="width: 100%; padding: 0.6rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-input); color: var(--text-main);">
                                </div>

                                <div style="flex: 1; display: flex; flex-direction: column;">
                                    <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 500;">Break 2 Name</label>
                                    <input type="text" name="break_2_name" value="{{ $c->break_2_name ?? '2nd Half Break' }}" style="width: 100%; padding: 0.6rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-input); color: var(--text-main);">
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                                <div style="flex: 1; display: flex; flex-direction: column;">
                                    <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 500;">Break 1 Start</label>
                                    <input type="time" name="break_1_start" value="{{ $c->break_1_start ? \Carbon\Carbon::parse($c->break_1_start)->format('H:i') : '' }}" style="width: 100%; padding: 0.6rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-input); color: var(--text-main);">
                                </div>

                                <div style="flex: 1; display: flex; flex-direction: column;">
                                    <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 500;">Break 1 End</label>
                                    <input type="time" name="break_1_end" value="{{ $c->break_1_end ? \Carbon\Carbon::parse($c->break_1_end)->format('H:i') : '' }}" style="width: 100%; padding: 0.6rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-input); color: var(--text-main);">
                                </div>

                                <div style="flex: 1; display: flex; flex-direction: column;">
                                    <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 500;">Break 2 Start</label>
                                    <input type="time" name="break_2_start" value="{{ $c->break_2_start ? \Carbon\Carbon::parse($c->break_2_start)->format('H:i') : '' }}" style="width: 100%; padding: 0.6rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-input); color: var(--text-main);">
                                </div>

                                <div style="flex: 1; display: flex; flex-direction: column;">
                                    <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 500;">Break 2 End</label>
                                    <input type="time" name="break_2_end" value="{{ $c->break_2_end ? \Carbon\Carbon::parse($c->break_2_end)->format('H:i') : '' }}" style="width: 100%; padding: 0.6rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-input); color: var(--text-main);">
                                </div>
                            </div>
                        </div>

                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-primary" style="flex: 1; justify-content: center;">Update Company</button>
                            <button type="button" class="btn btn-ghost" onclick="document.getElementById('editCompanyModal_{{ $c->id }}').style.display='none'">Cancel</button>
                        </div>
                    </form>
                </div>
                </div>
            </div>
            </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div style="margin-top: 1rem;">
        {{ $companies->links() }}
    </div>
</div>

<!-- Add Company Modal -->
<div id="addCompanyModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000; backdrop-filter: blur(5px);">
    <div class="card" style="width: 550px; margin: 0; text-align: left; max-height: 90vh; overflow-y: auto;">
        <div class="card-header">
            <div class="card-title">Register New Company</div>
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('addCompanyModal').style.display='none'">&times;</button>
        </div>
        <form action="{{ route('admin.companies.store') }}" method="POST" style="padding: 1.5rem;">
            @csrf
            
            <h3 style="font-size: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 5px; margin-bottom: 1rem; color: var(--primary);">Company Info</h3>
            
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 500;">Company Name</label>
                <input type="text" name="name" required placeholder="E.g., ABC Solutions" style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border); background: var(--card); color: var(--text-main);">
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 500;">Slug (Custom URL) <span style="font-size: 0.75rem; color: var(--text-muted);">(Optional)</span></label>
                <input type="text" name="slug" placeholder="E.g., abc-solutions" style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border); background: var(--card); color: var(--text-main);">
            </div>

            <h3 style="font-size: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 5px; margin-bottom: 1rem; color: var(--primary);">Admin User Credentials</h3>
            
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 500;">Admin Name</label>
                <input type="text" name="admin_name" required placeholder="E.g., John Doe" style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border); background: var(--card); color: var(--text-main);">
            </div>

            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 500;">Admin Email</label>
                <input type="email" name="admin_email" required placeholder="E.g., admin@abc.com" style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border); background: var(--card); color: var(--text-main);">
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 500;">Admin Password</label>
                <input type="password" name="admin_password" required placeholder="Min 8 characters" style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border); background: var(--card); color: var(--text-main);">
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary" style="flex: 1; justify-content: center;">Register Company</button>
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('addCompanyModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>
@endsection
