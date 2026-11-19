@extends('layouts.admin')

@section('title', 'Global Settings')

@section('content')
<div class="card" style="max-width: 600px; margin: 0 auto;">
    <div class="card-header">
        <h2 class="card-title">Global System Settings</h2>
    </div>
    
    <form action="{{ route('admin.settings.global.update') }}" method="POST" enctype="multipart/form-data" style="padding: 1.5rem;">
        @csrf
        <div style="margin-bottom: 2rem;">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Maintenance Mode</label>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1rem;">
                When enabled, the entire system will be inaccessible to all users except Super Admins. Use this during upgrades or critical fixes.
            </p>
            
            <label style="display: flex; align-items: center; cursor: pointer;">
                <input type="checkbox" name="maintenance_mode" value="1" {{ $is_maintenance ? 'checked' : '' }} style="width: 20px; height: 20px; margin-right: 10px; cursor: pointer;">
                <span style="font-weight: 600; color: {{ $is_maintenance ? 'var(--danger)' : 'var(--text-main)' }}">
                    {{ $is_maintenance ? 'Maintenance Mode is currently ON' : 'Enable Maintenance Mode' }}
                </span>
            </label>
        </div>

        <div style="margin-bottom: 2rem;">
            <h3 style="font-size: 1.1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; margin-bottom: 1rem; color: var(--primary);">System Branding (Super Admin Only)</h3>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1.5rem;">
                This logo and favicon will only be shown on the Super Admin dashboard and will not affect any other companies.
            </p>
            
            <div style="display: flex; gap: 1.5rem; margin-bottom: 1.5rem;">
                <div style="flex: 1; display: flex; flex-direction: column;">
                    <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 500;">Super Admin Logo</label>
                    <div style="flex-grow: 1; display: flex; flex-direction: column; justify-content: flex-end;">
                        @if(auth()->check() && auth()->user()->company && auth()->user()->company->logo && file_exists(public_path(auth()->user()->company->logo)))
                            <div style="margin-bottom: 10px;">
                                <img src="{{ url('file/' . auth()->user()->company->logo) }}" alt="Logo" style="height: 40px; max-width: 100%; object-fit: contain; border-radius: 4px;">
                            </div>
                        @endif
                    </div>
                    <input type="file" name="logo" accept="image/*" style="width: 100%; padding: 0.5rem; border-radius: 10px; border: 1px solid var(--border); background: var(--card); color: var(--text-main);">
                </div>
                
                <div style="flex: 1; display: flex; flex-direction: column;">
                    <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 500;">System Logo (Dark Mode)</label>
                    <div style="flex-grow: 1; display: flex; flex-direction: column; justify-content: flex-end;">
                        @if(auth()->user()->company->dark_logo)
                            <div style="margin-bottom: 10px;">
                                <img src="{{ url('file/' . auth()->user()->company->dark_logo) }}" alt="Dark Logo" style="height: 40px; max-width: 100%; object-fit: contain; border-radius: 4px;">
                            </div>
                        @endif
                    </div>
                    <input type="file" name="dark_logo" accept="image/*" style="width: 100%; padding: 0.5rem; border-radius: 10px; border: 1px solid var(--border); background: var(--card); color: var(--text-main);">
                </div>

                <div style="flex: 1; display: flex; flex-direction: column;">
                    <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 500;">Favicon</label>
                    <div style="flex-grow: 1; display: flex; flex-direction: column; justify-content: flex-end;">
                        @if(auth()->check() && auth()->user()->company && auth()->user()->company->favicon && file_exists(public_path(auth()->user()->company->favicon)))
                            <div style="margin-bottom: 10px;">
                                <img src="{{ url('file/' . auth()->user()->company->favicon) }}" alt="Favicon" style="height: 40px; max-width: 100%; object-fit: contain; border-radius: 4px;">
                            </div>
                        @endif
                    </div>
                    <input type="file" name="favicon" accept="image/*" style="width: 100%; padding: 0.5rem; border-radius: 10px; border: 1px solid var(--border); background: var(--card); color: var(--text-main);">
                </div>
            </div>
        </div>

        <!-- Global Break Settings -->
        <div style="margin-bottom: 2rem;">
            <h3 style="font-size: 1.1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; margin-bottom: 1rem; color: var(--primary);">Main Breaks Configuration</h3>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1.5rem;">
                Configure the number of main breaks and their names for your system.
            </p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                <div style="flex: 1; display: flex; flex-direction: column;">
                    <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 500;">Number of Breaks</label>
                    <select name="number_of_breaks" style="width: 100%; padding: 0.6rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-input); color: var(--text-main);">
                        <option value="1" {{ auth()->user()->company && auth()->user()->company->number_of_breaks == 1 ? 'selected' : '' }}>1 Break</option>
                        <option value="2" {{ (!auth()->user()->company || !auth()->user()->company->number_of_breaks || auth()->user()->company->number_of_breaks == 2) ? 'selected' : '' }}>2 Breaks</option>
                    </select>
                </div>

                <div style="flex: 1; display: flex; flex-direction: column;">
                    <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 500;">Break 1 Name</label>
                    <input type="text" name="break_1_name" value="{{ auth()->user()->company->break_1_name ?? '1st Half Break' }}" style="width: 100%; padding: 0.6rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-input); color: var(--text-main);">
                </div>

                <div style="flex: 1; display: flex; flex-direction: column;">
                    <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 500;">Break 2 Name</label>
                    <input type="text" name="break_2_name" value="{{ auth()->user()->company->break_2_name ?? '2nd Half Break' }}" style="width: 100%; padding: 0.6rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-input); color: var(--text-main);">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                <div style="flex: 1; display: flex; flex-direction: column;">
                    <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 500;">Break 1 Start Time</label>
                    <input type="time" name="break_1_start" value="{{ auth()->user()->company->break_1_start ? \Carbon\Carbon::parse(auth()->user()->company->break_1_start)->format('H:i') : '' }}" style="width: 100%; padding: 0.6rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-input); color: var(--text-main);">
                </div>

                <div style="flex: 1; display: flex; flex-direction: column;">
                    <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 500;">Break 1 End Time</label>
                    <input type="time" name="break_1_end" value="{{ auth()->user()->company->break_1_end ? \Carbon\Carbon::parse(auth()->user()->company->break_1_end)->format('H:i') : '' }}" style="width: 100%; padding: 0.6rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-input); color: var(--text-main);">
                </div>

                <div style="flex: 1; display: flex; flex-direction: column;">
                    <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 500;">Break 2 Start Time</label>
                    <input type="time" name="break_2_start" value="{{ auth()->user()->company->break_2_start ? \Carbon\Carbon::parse(auth()->user()->company->break_2_start)->format('H:i') : '' }}" style="width: 100%; padding: 0.6rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-input); color: var(--text-main);">
                </div>

                <div style="flex: 1; display: flex; flex-direction: column;">
                    <label style="display: block; font-size: 0.85rem; margin-bottom: 5px; font-weight: 500;">Break 2 End Time</label>
                    <input type="time" name="break_2_end" value="{{ auth()->user()->company->break_2_end ? \Carbon\Carbon::parse(auth()->user()->company->break_2_end)->format('H:i') : '' }}" style="width: 100%; padding: 0.6rem; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-input); color: var(--text-main);">
                </div>
            </div>
        </div>

        <div style="text-align: right; border-top: 1px solid var(--border); padding-top: 1.5rem;">
            <button type="submit" class="btn" style="background: var(--primary); color: white;">Save Global Settings</button>
        </div>
    </form>
</div>
@endsection
