@extends('layouts.admin')

@section('title', 'Floor Decorum')

@section('content')
{{--
    What the floor can be called out for.

    Rush hour used to be an hour with one message because that is what the code
    said. Each of these is a row now: rename it, re-time it, change what the
    scoreboard says, switch it off, or add one nobody thought of.
--}}
<style>
    .fs-grid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 1.3rem; align-items: start; }
    @media (max-width: 1100px) { .fs-grid { grid-template-columns: 1fr; } }

    .fs-row {
        display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: 14px;
        align-items: center; padding: 0.9rem 0.2rem;
        border-bottom: 1px solid var(--border);
    }
    .fs-row:last-of-type { border-bottom: none; }
    .fs-row.is-off { opacity: 0.5; }

    .fs-mark {
        width: 38px; height: 38px; border-radius: var(--radius-sm);
        display: flex; align-items: center; justify-content: center;
        font-size: 0.95rem; flex-shrink: 0;
    }
    .fs-what b { display: block; font-size: 0.92rem; }
    .fs-what span { font-size: 0.78rem; color: var(--text-muted); }
    .fs-acts { display: flex; gap: 6px; align-items: center; }

    .fs-edit { padding: 1rem 0.2rem 0.4rem; border-bottom: 1px solid var(--border); }
    .fs-edit .field-row { margin-bottom: 0.8rem; }
    .fs-tone { height: 38px; width: 54px; padding: 3px; cursor: pointer; }
    .fs-live { display: inline-flex; align-items: center; gap: 8px; font-size: 0.82rem; cursor: pointer; }
</style>

<div class="page-head">
    <div class="page-sub" style="margin: 0;">
        What the floor gets called out for, and for how long. These are the items under the Rush Hour button.
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success"><i class="fas fa-circle-check"></i> {{ session('success') }}</div>
@endif

<div class="fs-grid">
    {{-- ---------- what there is ---------- --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-bullhorn"></i> What the floor is called to</div>
            <span class="chip">{{ $moments->count() }}</span>
        </div>

        @forelse($moments as $moment)
            <div class="fs-row {{ $moment->is_active ? '' : 'is-off' }}">
                <span class="fs-mark" style="background: {{ $moment->tone }}1F; color: {{ $moment->tone }};">
                    <i class="fas {{ $moment->icon }}"></i>
                </span>

                <span class="fs-what">
                    <b>{{ $moment->name }}</b>
                    <span>{{ $moment->minutes }} min · “{{ $moment->message }}”{{ $moment->is_active ? '' : ' · switched off' }}</span>
                </span>

                <span class="fs-acts">
                    <button type="button" class="btn btn-ghost btn-sm" title="Edit"
                            onclick="document.getElementById('fsEdit{{ $moment->id }}').hidden = !document.getElementById('fsEdit{{ $moment->id }}').hidden">
                        <i class="fas fa-pen"></i>
                    </button>

                    <form action="{{ route('admin.moments.destroy', $moment) }}" method="POST" style="margin: 0;"
                          onsubmit="confirmDelete(event, this, 'Remove {{ addslashes($moment->name) }}?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-ghost btn-sm" title="Remove" style="color: var(--danger);">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </form>
                </span>
            </div>

            {{-- The same fields as the form beside it, opened in place. --}}
            <form action="{{ route('admin.moments.update', $moment) }}" method="POST"
                  class="fs-edit" id="fsEdit{{ $moment->id }}" hidden>
                @csrf @method('PUT')

                <div class="field-row">
                    <label class="field">
                        <span>Name</span>
                        <input type="text" name="name" value="{{ $moment->name }}" maxlength="60" required>
                    </label>
                    <label class="field">
                        <span>Minutes</span>
                        <input type="number" name="minutes" value="{{ $moment->minutes }}" min="1" max="480" required>
                    </label>
                </div>

                <label class="field" style="margin-bottom: 0.8rem;">
                    <span>What the floor sees</span>
                    <input type="text" name="message" value="{{ $moment->message }}" maxlength="160" required>
                </label>

                <div class="field-row">
                    <label class="field">
                        <span>Icon</span>
                        <input type="text" name="icon" value="{{ $moment->icon }}" placeholder="fa-mug-hot">
                    </label>
                    <label class="field">
                        <span>Colour</span>
                        <input type="color" name="tone" value="{{ $moment->tone }}" class="fs-tone">
                    </label>
                </div>

                <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 0.9rem;">
                    <label class="fs-live">
                        <input type="checkbox" name="is_active" value="1" {{ $moment->is_active ? 'checked' : '' }}>
                        <span>In the menu</span>
                    </label>
                    <span class="field-hint">Switched off, it stays here but nobody can call it.</span>
                </div>

                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check"></i> Save</button>
            </form>
        @empty
            <div class="empty-state">
                <i class="fas fa-bullhorn"></i>
                <strong>Nothing to call out yet</strong>
                <p>Add the first one with the form beside this list.</p>
            </div>
        @endforelse
    </div>

    {{-- ---------- adding one ---------- --}}
    <div>
        <div class="card" style="position: sticky; top: 1rem;">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-plus-circle" style="color: var(--primary);"></i> Add one</div>
            </div>

            <form action="{{ route('admin.moments.store') }}" method="POST">
                @csrf

                <label class="field" style="margin-bottom: 0.9rem;">
                    <span>Name</span>
                    <input type="text" name="name" placeholder="e.g. Tea Break" maxlength="60" required>
                </label>

                <label class="field" style="margin-bottom: 0.9rem;">
                    <span>What the floor sees</span>
                    <input type="text" name="message" placeholder="e.g. TEA BREAK — 10 MINUTES" maxlength="160" required>
                </label>

                <div class="field-row" style="margin-bottom: 0.9rem;">
                    <label class="field">
                        <span>Minutes</span>
                        <input type="number" name="minutes" value="15" min="1" max="480" required>
                    </label>
                    <label class="field">
                        <span>Colour</span>
                        <input type="color" name="tone" value="#F99D4E" class="fs-tone">
                    </label>
                </div>

                <label class="field" style="margin-bottom: 0.6rem;">
                    <span>Icon</span>
                    <input type="text" name="icon" placeholder="fa-mug-hot">
                </label>
                <p class="field-hint" style="margin: 0 0 0.9rem;">
                    Any Font Awesome name, like <code>fa-mug-hot</code>, <code>fa-mosque</code> or <code>fa-utensils</code>.
                </p>

                <label class="fs-live" style="margin-bottom: 1rem;">
                    <input type="checkbox" name="is_active" value="1" checked>
                    <span>Put it in the menu</span>
                </label>

                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-plus"></i> Add it
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
