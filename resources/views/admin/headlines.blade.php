@extends('layouts.admin')

@section('title', 'News Headlines')

@section('content')
<div class="card">
    <div class="card-header">
        <div class="card-title">Scoreboard News Ticker</div>
        @if(!auth()->user()->isAgent())
        <button class="btn btn-primary" onclick="document.getElementById('addHeadlineModal').style.display='flex'">+ Add Headline</button>
        @endif
    </div>
    <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">
        Manage the scrolling news ticker headlines shown on the live scoreboard. Active headlines scroll across the top of the display.
    </p>

    <table>
        <thead>
            <tr>
                <th>Status</th>
                <th>Headline Text</th>
                <th>Created</th>
                @if(!auth()->user()->isAgent())
                <th>Actions</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($headlines as $h)
            <tr style="{{ !$h->is_active ? 'opacity: 0.5;' : '' }}">
                <td>
                    @if(!auth()->user()->isAgent())
                    <form action="{{ route('admin.headlines.toggle', $h->id) }}" method="POST" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-sm" style="padding: 2px 4px;" title="Show or hide this on the scoreboard">
                            <span class="badge {{ $h->is_active ? 'badge-success' : 'badge-danger' }}">
                                {{ $h->is_active ? 'ACTIVE' : 'HIDDEN' }}
                            </span>
                        </button>
                    </form>
                    @else
                    <span class="badge {{ $h->is_active ? 'badge-success' : 'badge-danger' }}">
                        {{ $h->is_active ? 'ACTIVE' : 'HIDDEN' }}
                    </span>
                    @endif
                </td>
                <td style="font-weight: 500; max-width: 500px;">{{ $h->headline }}</td>
                <td style="color: var(--text-muted); font-size: 0.85rem;">{{ $h->created_at->diffForHumans() }}</td>
                @if(!auth()->user()->isAgent())
                <td><div style="display: flex; gap: 4px; align-items: center;">
                    <button type="button" class="btn btn-ghost btn-sm" style="color: var(--primary);" title="Edit" onclick="document.getElementById('editHeadlineModal_{{ $h->id }}').style.display='flex'"><i class="fas fa-edit"></i></button>
                    @if(auth()->user()->isAdmin())
                    <form action="{{ route('admin.headlines.delete', $h->id) }}" method="POST" onsubmit="confirmDelete(event, this, \'Delete this headline?\')" style="margin: 0; display: inline-flex;">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-ghost btn-sm" style="color: var(--danger);"><i class="fas fa-trash-alt"></i></button>
                    </form>
                    @endif

                    <!-- Edit Headline Modal -->
                    <div id="editHeadlineModal_{{ $h->id }}" class="modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.55); backdrop-filter: blur(4px); align-items: center; justify-content: center; z-index: 1000; padding: 1rem;">
                        <div class="card" style="width: 550px; margin: 0; text-align: left;">
                            <div class="card-header">
                                <div class="card-title">Edit Headline</div>
                                <button class="btn btn-ghost" onclick="document.getElementById('editHeadlineModal_{{ $h->id }}').style.display='none'">&times;</button>
                            </div>
                            <form action="{{ route('admin.headlines.update', $h->id) }}" method="POST">
                                @csrf @method('PUT')
                                <div style="margin-bottom: 1rem;">
                                    <label style="display: block; font-size: 0.85rem; margin-bottom: 5px;">Headline Text</label>
                                    <input type="text" name="headline" value="{{ $h->headline }}" required style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border);">
                                </div>
                                <div style="display: flex; gap: 10px;">
                                    <button type="submit" class="btn btn-primary" style="flex: 1; justify-content: center;">Update Headline</button>
                                    <button type="button" class="btn btn-ghost" onclick="document.getElementById('editHeadlineModal_{{ $h->id }}').style.display='none'">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div></td>
                @endif
            </tr>
            @endforeach
        </tbody>
    </table>

    <div style="margin-top: 1rem;">
        {{ $headlines->links() }}
    </div>
</div>

@if(!auth()->user()->isAgent())
<!-- Add Headline Modal -->
<div id="addHeadlineModal" class="modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.55); backdrop-filter: blur(4px); align-items: center; justify-content: center; z-index: 1000; padding: 1rem;">
    <div class="card" style="width: 550px; margin: 0;">
        <div class="card-header">
            <div class="card-title">Add News Headline</div>
            <button class="btn btn-ghost" onclick="document.getElementById('addHeadlineModal').style.display='none'">&times;</button>
        </div>
        <form action="{{ route('admin.headlines.store') }}" method="POST">
            @csrf
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.85rem; margin-bottom: 5px;">Headline Text</label>
                <input type="text" name="headline" required placeholder="Enter the news headline text..." style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border);">
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary" style="flex: 1; justify-content: center;">Add Headline</button>
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('addHeadlineModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
