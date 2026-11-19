@extends('layouts.admin')

@section('title', 'System Settings')

@section('content')
<div style="max-width: 1200px; margin: 0 auto;">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem; align-items: start;">
        <!-- Monthly Target Setting -->
        <div class="card" style="margin: 0;">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-bullseye" style="color: var(--primary);"></i> Revenue Goals</div>
            </div>
            <form action="{{ route('admin.stats.update') }}" method="POST">
                @csrf
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-size: 0.9rem; margin-bottom: 8px; font-weight: 600;">Monthly Sales Target ($)</label>
                    <div style="position: relative;">
                        <span style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted);">$</span>
                        <input type="number" name="monthly_target" value="{{ $stats_row->monthly_target }}" required {{ auth()->user()->isAgent() ? 'readonly' : '' }}
                               style="width: 100%; padding: 0.8rem 0.8rem 0.8rem 2rem; border-radius: 12px; border: 1px solid var(--border); font-size: 1rem; font-weight: 600; {{ auth()->user()->isAgent() ? 'background-color: var(--bg-body); cursor: not-allowed;' : '' }}">
                    </div>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 8px;">This target is used to calculate the progress bars on the public scoreboard.</p>
                </div>
                @if(!auth()->user()->isAgent())
                <button type="submit" class="btn btn-primary">Update Goal</button>
                @endif
            </form>
        </div>

        <!-- Active Leads Placeholder Setting -->
        <div class="card" style="margin: 0;">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-chart-line" style="color: var(--info);"></i> Display Settings</div>
            </div>
            <form action="{{ route('admin.stats.update') }}" method="POST">
                @csrf
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div>
                        <label style="display: block; font-size: 0.85rem; margin-bottom: 8px; font-weight: 600;">Leads Growth Trend (%)</label>
                        <input type="text" name="leads_trend" value="{{ $stats_row->leads_trend ?? '+0%' }}" {{ auth()->user()->isAgent() ? 'readonly' : '' }}
                               style="width: 100%; padding: 0.8rem; border-radius: 12px; border: 1px solid var(--border); {{ auth()->user()->isAgent() ? 'background-color: var(--bg-body); cursor: not-allowed;' : '' }}">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.85rem; margin-bottom: 8px; font-weight: 600;">Achieved Number of Prospects</label>
                        <input type="number" name="achieved_prospects" value="{{ $stats_row->achieved_prospects ?? 0 }}" {{ auth()->user()->isAgent() ? 'readonly' : '' }}
                               style="width: 100%; padding: 0.8rem; border-radius: 12px; border: 1px solid var(--border); {{ auth()->user()->isAgent() ? 'background-color: var(--bg-body); cursor: not-allowed;' : '' }}">
                    </div>
                    <div style="grid-column: span 2;">
                        <label style="display: block; font-size: 0.85rem; margin-bottom: 8px; font-weight: 600;">Live Update Text</label>
                        <input type="text" name="live_update" value="{{ $stats_row->live_update ?? '' }}" placeholder="E.g., New deal closed in NY!" {{ auth()->user()->isAgent() ? 'readonly' : '' }}
                               style="width: 100%; padding: 0.8rem; border-radius: 12px; border: 1px solid var(--border); {{ auth()->user()->isAgent() ? 'background-color: var(--bg-body); cursor: not-allowed;' : '' }}">
                    </div>
                </div>
                @if(!auth()->user()->isAgent())
                <button type="submit" class="btn btn-primary" style="margin-top: 1.5rem;">Save Changes</button>
                @endif
            </form>
        </div>
    </div>

    <!-- Company Branding (Admin Only) -->
    @if(auth()->user()->isAdmin() && !auth()->user()->isSuperAdmin())
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-paint-brush" style="color: var(--primary);"></i> Company Branding</div>
        </div>
        <form action="{{ route('admin.settings.branding.update') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.5rem;">
                <div style="flex: 1; display: flex; flex-direction: column;">
                    <label style="display: block; font-size: 0.85rem; margin-bottom: 8px; font-weight: 600;">Company Logo</label>
                    <div style="flex-grow: 1; display: flex; flex-direction: column; justify-content: flex-end;">
                        @if(auth()->check() && auth()->user()->company && auth()->user()->company->logo && file_exists(public_path(auth()->user()->company->logo)))
                            <div style="margin-bottom: 10px;">
                                <img src="{{ url('file/' . auth()->user()->company->logo) }}" alt="Logo" style="height: 50px; max-width: 100%; object-fit: contain; border-radius: 4px;">
                            </div>
                        @endif
                    </div>
                    <input type="file" name="logo" accept="image/*" style="width: 100%; padding: 0.5rem; border-radius: 12px; border: 1px solid var(--border);">
                </div>

                <div style="flex: 1; display: flex; flex-direction: column;">
                    <label style="display: block; font-size: 0.85rem; margin-bottom: 8px; font-weight: 600;">System Logo (Dark Mode)</label>
                    <div style="flex-grow: 1; display: flex; flex-direction: column; justify-content: flex-end;">
                        @if(auth()->check() && auth()->user()->company && auth()->user()->company->dark_logo && file_exists(public_path(auth()->user()->company->dark_logo)))
                            <div style="margin-bottom: 10px;">
                                <img src="{{ url('file/' . auth()->user()->company->dark_logo) }}" alt="Dark Logo" style="height: 50px; max-width: 100%; object-fit: contain; border-radius: 4px;">
                            </div>
                        @endif
                    </div>
                    <input type="file" name="dark_logo" accept="image/*" style="width: 100%; padding: 0.5rem; border-radius: 12px; border: 1px solid var(--border);">
                </div>
                <div style="flex: 1; display: flex; flex-direction: column;">
                    <label style="display: block; font-size: 0.85rem; margin-bottom: 8px; font-weight: 600;">Favicon</label>
                    <div style="flex-grow: 1; display: flex; flex-direction: column; justify-content: flex-end;">
                        @if(auth()->check() && auth()->user()->company && auth()->user()->company->favicon && file_exists(public_path(auth()->user()->company->favicon)))
                            <div style="margin-bottom: 10px;">
                                <img src="{{ url('file/' . auth()->user()->company->favicon) }}" alt="Favicon" style="height: 50px; max-width: 100%; object-fit: contain; border-radius: 4px;">
                            </div>
                        @endif
                    </div>
                    <input type="file" name="favicon" accept="image/*" style="width: 100%; padding: 0.5rem; border-radius: 12px; border: 1px solid var(--border);">
                </div>
                <div style="flex: 1; display: flex; flex-direction: column;">
                    <label style="display: block; font-size: 0.85rem; margin-bottom: 8px; font-weight: 600;">Primary Theme Color</label>
                    <div style="flex-grow: 1; display: flex; flex-direction: column; justify-content: flex-end;"></div>
                    <input type="color" name="theme_color" value="{{ auth()->user()->company->theme_color ?? '#f99d4e' }}" style="width: 100%; height: 45px; padding: 2px; border-radius: 12px; border: 1px solid var(--border);">
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top: 1.5rem;">Update Branding</button>
        </form>
    </div>

    <!-- Main Breaks Configuration -->
    <div class="card" style="margin-top: 1.5rem;">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-clock" style="color: var(--warning);"></i> Main Breaks Configuration</div>
        </div>
        <form action="{{ route('admin.settings.branding.update') }}" method="POST">
            @csrf
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1.5rem;">
                Configure the number of main breaks and their names for your company.
            </p>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                    <div style="flex: 1; display: flex; flex-direction: column;">
                        <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 600;">Number of Breaks</label>
                        <select name="number_of_breaks" style="width: 100%; padding: 0.6rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-input); color: var(--text-main);">
                            <option value="1" {{ auth()->user()->company && auth()->user()->company->number_of_breaks == 1 ? 'selected' : '' }}>1 Break</option>
                            <option value="2" {{ (!auth()->user()->company || !auth()->user()->company->number_of_breaks || auth()->user()->company->number_of_breaks == 2) ? 'selected' : '' }}>2 Breaks</option>
                        </select>
                    </div>

                    <div style="flex: 1; display: flex; flex-direction: column;">
                        <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 600;">Break 1 Name</label>
                        <input type="text" name="break_1_name" value="{{ auth()->user()->company->break_1_name ?? '1st Half Break' }}" style="width: 100%; padding: 0.6rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-input); color: var(--text-main);">
                    </div>

                    <div style="flex: 1; display: flex; flex-direction: column;">
                        <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 600;">Break 2 Name</label>
                        <input type="text" name="break_2_name" value="{{ auth()->user()->company->break_2_name ?? '2nd Half Break' }}" style="width: 100%; padding: 0.6rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-input); color: var(--text-main);">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                    <div style="flex: 1; display: flex; flex-direction: column;">
                        <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 600;">Break 1 Start Time</label>
                        <input type="time" name="break_1_start" value="{{ auth()->user()->company->break_1_start ? \Carbon\Carbon::parse(auth()->user()->company->break_1_start)->format('H:i') : '' }}" style="width: 100%; padding: 0.6rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-input); color: var(--text-main);">
                    </div>

                    <div style="flex: 1; display: flex; flex-direction: column;">
                        <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 600;">Break 1 End Time</label>
                        <input type="time" name="break_1_end" value="{{ auth()->user()->company->break_1_end ? \Carbon\Carbon::parse(auth()->user()->company->break_1_end)->format('H:i') : '' }}" style="width: 100%; padding: 0.6rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-input); color: var(--text-main);">
                    </div>

                    <div style="flex: 1; display: flex; flex-direction: column;">
                        <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 600;">Break 2 Start Time</label>
                        <input type="time" name="break_2_start" value="{{ auth()->user()->company->break_2_start ? \Carbon\Carbon::parse(auth()->user()->company->break_2_start)->format('H:i') : '' }}" style="width: 100%; padding: 0.6rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-input); color: var(--text-main);">
                    </div>

                    <div style="flex: 1; display: flex; flex-direction: column;">
                        <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 600;">Break 2 End Time</label>
                        <input type="time" name="break_2_end" value="{{ auth()->user()->company->break_2_end ? \Carbon\Carbon::parse(auth()->user()->company->break_2_end)->format('H:i') : '' }}" style="width: 100%; padding: 0.6rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-input); color: var(--text-main);">
                    </div>
                </div>
            <button type="submit" class="btn btn-primary" style="margin-top: 1.5rem;">Update Break Settings</button>
        </form>
    </div>
    @endif

    <!-- Account Information -->
    <div class="card" style="margin-top: 1.5rem;">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-user-shield" style="color: var(--success);"></i> Account Security</div>
        </div>
        <div style="display: flex; align-items: center; gap: 1.5rem; padding: 0.5rem;">
            <div style="width: 60px; height: 60px; background: var(--bg-body); border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: var(--primary); border: 1px solid var(--border);">
                <i class="fas fa-user"></i>
            </div>
            <div>
                <div style="font-weight: 700; font-size: 1.1rem;">{{ auth()->user()->name }}</div>
                <div style="color: var(--text-muted); font-size: 0.9rem;">{{ auth()->user()->email }}</div>
            </div>
            <div style="margin-left: auto;">
                <button onclick="document.getElementById('passwordModal').style.display='flex'" class="btn btn-ghost" style="border: 1px solid var(--border);">Change Password</button>
            </div>
        </div>
    </div>
</div>

<!-- Password Change Modal -->
<div id="passwordModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 100%; max-width: 450px; position: relative;">
        <button type="button" class="btn btn-ghost btn-icon" title="Close" style="position: absolute; right: 16px; top: 16px;" onclick="document.getElementById('passwordModal').style.display='none'"><i class="fas fa-xmark"></i></button>
        <div class="card-header">
            <div class="card-title">Update Security Credentials</div>
        </div>
        <form action="{{ route('admin.password.update') }}" method="POST">
            @csrf
            <div style="margin-bottom: 1.2rem;">
                <label style="display: block; font-size: 0.85rem; margin-bottom: 6px; font-weight: 600;">Current Password</label>
                <input type="password" name="current_password" required style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border);">
            </div>
            <div style="margin-bottom: 1.2rem;">
                <label style="display: block; font-size: 0.85rem; margin-bottom: 6px; font-weight: 600;">New Password</label>
                <input type="password" name="password" required style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border);">
            </div>
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.85rem; margin-bottom: 6px; font-weight: 600;">Confirm New Password</label>
                <input type="password" name="password_confirmation" required style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border);">
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">Update Password</button>
        </form>
    </div>
</div>

@if($errors->has('current_password') || $errors->has('password'))
    <script>document.getElementById('passwordModal').style.display='flex'</script>
@endif
@endsection
