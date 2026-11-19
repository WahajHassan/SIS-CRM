@extends('layouts.admin')

@section('title', 'Business Categories')

@section('content')
<div class="card">
    <div class="card-header">
        <div class="card-title">Scoreboard Business Categories</div>
        @if(!auth()->user()->isAgent())
        <button class="btn btn-primary" onclick="document.getElementById('addCategoryModal').style.display='flex'">+ Add Category</button>
        @endif
    </div>
    <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">
        Manage the scrolling business categories shown on the live scoreboard. Active categories scroll below the live news.
    </p>

    <table>
        <thead>
            <tr>
                <th>Status</th>
                <th>Category Name</th>
                <th>Created</th>
                @if(!auth()->user()->isAgent())
                <th>Actions</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($categories as $c)
            <tr style="{{ !$c->is_active ? 'opacity: 0.5;' : '' }}">
                <td>
                    @if(!auth()->user()->isAgent())
                    <form action="{{ route('admin.categories.toggle', $c->id) }}" method="POST" style="display:inline;">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-sm" style="padding: 2px 4px;" title="Show or hide this on the scoreboard">
                            <span class="badge {{ $c->is_active ? 'badge-success' : 'badge-danger' }}">
                                {{ $c->is_active ? 'ACTIVE' : 'HIDDEN' }}
                            </span>
                        </button>
                    </form>
                    @else
                    <span class="badge {{ $c->is_active ? 'badge-success' : 'badge-danger' }}">
                        {{ $c->is_active ? 'ACTIVE' : 'HIDDEN' }}
                    </span>
                    @endif
                </td>
                <td style="font-weight: 500; max-width: 500px;">{{ $c->name }}</td>
                <td style="color: var(--text-muted); font-size: 0.85rem;">{{ $c->created_at->diffForHumans() }}</td>
                @if(!auth()->user()->isAgent())
                <td><div style="display: flex; gap: 4px; align-items: center;">
                    <button type="button" class="btn btn-ghost btn-sm" style="color: var(--primary);" title="Edit" onclick="document.getElementById('editCategoryModal_{{ $c->id }}').style.display='flex'"><i class="fas fa-edit"></i></button>
                    @if(auth()->user()->isAdmin())
                    <form action="{{ route('admin.categories.delete', $c->id) }}" method="POST" onsubmit="confirmDelete(event, this, \'Delete this category?\')" style="margin: 0; display: inline-flex;">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-ghost btn-sm" style="color: var(--danger);"><i class="fas fa-trash-alt"></i></button>
                    </form>
                    @endif

                    <!-- Edit Category Modal -->
                    <div id="editCategoryModal_{{ $c->id }}" class="modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.55); backdrop-filter: blur(4px); align-items: center; justify-content: center; z-index: 1000; padding: 1rem;">
                        <div class="card" style="width: 550px; margin: 0; text-align: left;">
                            <div class="card-header">
                                <div class="card-title">Edit Category</div>
                                <button type="button" class="btn btn-ghost" onclick="document.getElementById('editCategoryModal_{{ $c->id }}').style.display='none'">&times;</button>
                            </div>
                            <form action="{{ route('admin.categories.update', $c->id) }}" method="POST">
                                @csrf @method('PUT')
                                <div style="margin-bottom: 1rem;">
                                    <label style="display: block; font-size: 0.85rem; margin-bottom: 5px;">Category Name</label>
                                    <input type="text" name="name" value="{{ $c->name }}" required style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border);">
                                </div>
                                <div style="display: flex; gap: 10px;">
                                    <button type="submit" class="btn btn-primary" style="flex: 1; justify-content: center;">Update Category</button>
                                    <button type="button" class="btn btn-ghost" onclick="document.getElementById('editCategoryModal_{{ $c->id }}').style.display='none'">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div></td>
                @endif
            </tr>
            @endforeach
@if($categories->isEmpty())
            <tr class="is-blank">
                <td colspan="5">
                    <div class="empty-state">
                        <i class="fas fa-tags"></i>
                        <h4>No business categories yet</h4>
                        <p>Add the trades your floor sells into. They scroll on the live scoreboard.</p>
                    </div>
                </td>
            </tr>
@endif

        </tbody>
    </table>

    <div style="margin-top: 1rem;">
        {{ $categories->links() }}
    </div>
</div>

@if(!auth()->user()->isAgent())
<!-- Add Category Modal -->
<div id="addCategoryModal" class="modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.55); backdrop-filter: blur(4px); align-items: center; justify-content: center; z-index: 1000; padding: 1rem;">
    <div class="card" style="width: 550px; margin: 0;">
        <div class="card-header">
            <div class="card-title">Add Business Category</div>
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('addCategoryModal').style.display='none'">&times;</button>
        </div>
        <form action="{{ route('admin.categories.store') }}" method="POST">
            @csrf
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.85rem; margin-bottom: 5px;">Category Name</label>
                <input type="text" name="name" required placeholder="E.g., Technology, Healthcare..." style="width: 100%; padding: 0.7rem; border-radius: 10px; border: 1px solid var(--border);">
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary" style="flex: 1; justify-content: center;">Add Category</button>
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('addCategoryModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
