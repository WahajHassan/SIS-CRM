@extends('layouts.admin')

@section('title', 'USA States')

@section('content')
<div class="card">
    <div class="card-header">
        <div class="card-title">USA States</div>
        @if(!auth()->user()->isAgent())
        <button class="btn btn-primary" onclick="document.getElementById('addStateModal').style.display='flex'">+ Add State</button>
        @endif
    </div>
    <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">
        Manage the scrolling USA states shown on the live scoreboard. Active states scroll below the live news.
    </p>

    <table>
        <thead>
            <tr>
                <th>Status</th>
                <th>State Name</th>
                <th>Created</th>
                @if(!auth()->user()->isAgent())
                <th>Actions</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($states as $s)
            <tr style="{{ !$s->is_active ? 'opacity: 0.5;' : '' }}">
                <td>
                    @if(!auth()->user()->isAgent())
                    <form action="{{ route('admin.usa_states.toggle', $s->id) }}" method="POST" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-sm" style="padding: 2px 4px;" title="Show or hide this on the scoreboard">
                            <span class="badge {{ $s->is_active ? 'badge-success' : 'badge-danger' }}">
                                {{ $s->is_active ? 'ACTIVE' : 'HIDDEN' }}
                            </span>
                        </button>
                    </form>
                    @else
                    <span class="badge {{ $s->is_active ? 'badge-success' : 'badge-danger' }}">
                        {{ $s->is_active ? 'ACTIVE' : 'HIDDEN' }}
                    </span>
                    @endif
                </td>
                <td style="font-weight: 500; max-width: 500px;">{{ $s->name }}</td>
                <td style="color: var(--text-muted); font-size: 0.85rem;">{{ $s->created_at->diffForHumans() }}</td>
                @if(!auth()->user()->isAgent())
                <td><div style="display: flex; gap: 4px; align-items: center;">
                    <button type="button" class="btn btn-ghost btn-sm" style="color: var(--primary);" title="Edit" onclick="document.getElementById('editStateModal_{{ $s->id }}').style.display='flex'"><i class="fas fa-edit"></i></button>
                    @if(auth()->user()->isAdmin())
                    <form action="{{ route('admin.usa_states.delete', $s->id) }}" method="POST" onsubmit="confirmDelete(event, this, \'Delete this state?\')" style="margin: 0; display: inline-flex;">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-ghost btn-sm" style="color: var(--danger);"><i class="fas fa-trash-alt"></i></button>
                    </form>
                    @endif

                    <!-- Edit State Modal -->
                    <div id="editStateModal_{{ $s->id }}" class="modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.55); backdrop-filter: blur(4px); align-items: center; justify-content: center; z-index: 1000; padding: 1rem;">
                        <div class="card" style="width: 550px; margin: 0; text-align: left;">
                            <div class="card-header">
                                <div class="card-title">Edit State</div>
                                <button type="button" class="btn btn-ghost" onclick="document.getElementById('editStateModal_{{ $s->id }}').style.display='none'">&times;</button>
                            </div>
                            <form action="{{ route('admin.usa_states.update', $s->id) }}" method="POST">
                                @csrf @method('PUT')
                                <div style="margin-bottom: 1rem;">
                                    <label style="display: block; font-size: 0.85rem; margin-bottom: 5px;">State Name</label>
                                    <input type="text" name="name" value="{{ $s->name }}" required style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border);">
                                </div>
                                <div style="display: flex; gap: 10px;">
                                    <button type="submit" class="btn btn-primary" style="flex: 1; justify-content: center;">Update State</button>
                                    <button type="button" class="btn btn-ghost" onclick="document.getElementById('editStateModal_{{ $s->id }}').style.display='none'">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div></td>
                @endif
            </tr>
            @endforeach
@if($states->isEmpty())
            <tr class="is-blank">
                <td colspan="4">
                    <div class="empty-state">
                        <i class="fas fa-map-location-dot"></i>
                        <h4>No states yet</h4>
                        <p>Add the states you work. They scroll below the live news on the scoreboard.</p>
                    </div>
                </td>
            </tr>
@endif

        </tbody>
    </table>

    <div style="margin-top: 1rem;">
        {{ $states->links() }}
    </div>
</div>

@if(!auth()->user()->isAgent())
<!-- Add State Modal -->
<div id="addStateModal" class="modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.55); backdrop-filter: blur(4px); align-items: center; justify-content: center; z-index: 1000; padding: 1rem;">
    <div class="card" style="width: 550px; margin: 0;">
        <div class="card-header">
            <div class="card-title">Add USA State</div>
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('addStateModal').style.display='none'">&times;</button>
        </div>
        <form action="{{ route('admin.usa_states.store') }}" method="POST">
            @csrf
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.85rem; margin-bottom: 5px;">State Name</label>
                <input type="text" name="name" required placeholder="E.g., California, Texas..." style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border);">
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary" style="flex: 1; justify-content: center;">Add State</button>
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('addStateModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
