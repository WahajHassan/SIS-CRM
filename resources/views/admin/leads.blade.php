@extends('layouts.admin')

@section('title', 'Calling Leads')

@section('content')
{{--
    The calling list.

    A lead is a business in a trade in a place, so that is what the form asks
    for: the trade from the list the company keeps, the state, and then the
    cities of that state. Typed-in places cannot be reported on; picked ones can.
--}}
@php
    $me = auth()->user();
    $canDelete = $me->isAdmin();
@endphp

<style>
    /* Only what belongs to this screen. Fields, controls and the filter row
       itself come from the design system. */
    .lead-field { margin-bottom: 0.9rem; display: flex; flex-direction: column; gap: 6px; }
    .lead-field label { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.04em;
                        text-transform: uppercase; color: var(--text-soft); }
    .lead-field input, .lead-field select, .lead-field textarea { width: 100%; padding: 0.55rem 0.7rem; }
    .lead-field textarea { resize: vertical; min-height: 76px; line-height: 1.5; }
    .lead-pair { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .lead-place { font-size: 0.8rem; color: var(--text-muted); }

    /* The trade is a label on a row, so it reads as one rather than as a word. */
    .lead-trade {
        display: inline-block; padding: 2px 9px; border-radius: var(--radius-pill);
        font-size: 0.72rem; font-weight: 700; white-space: nowrap;
        background: rgba(99,102,241,0.12); color: #6366F1;
    }
    html.dark-mode .lead-trade { background: rgba(129,140,248,0.16); color: #A5B4FC; }

    .lead-notes { font-size: 0.85rem; max-width: 170px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .lead-acts { display: flex; gap: 4px; align-items: center; }
    .lead-cols { display: flex; gap: 1.5rem; align-items: flex-start; flex-wrap: wrap; }
    .lead-cols > .lead-list { flex: 1 1 540px; min-width: 0; }
    .lead-cols > .lead-add { flex: 0 1 340px; min-width: 300px; position: sticky; top: 1rem; }
    @media (max-width: 1100px) { .lead-cols > .lead-add { position: static; } }

    /* A note about what the screen is for, not a card competing with one. */
    .lead-aside {
        margin-top: 1.25rem; padding: 0.95rem 1rem;
        background: var(--bg-subtle); border: 1px dashed var(--border-strong);
        border-radius: var(--radius);
    }
    .lead-aside b { display: block; font-size: 0.78rem; color: var(--text-main); margin-bottom: 4px; }
    .lead-aside span { font-size: 0.76rem; color: var(--text-muted); line-height: 1.5; }
</style>


<form id="leadFilters" method="GET" action="{{ route('admin.leads') }}" class="filter-bar">
        <div class="filter-field" style="flex: 1.4 1 190px;">
            <label for="leadSearch">Search</label>
            <input type="text" id="leadSearch" name="search" value="{{ request('search') }}" placeholder="Name, business, phone…">
        </div>
        <div class="filter-field">
            <label for="leadTrade">Trade</label>
            <select id="leadTrade" name="business_category_id">
                <option value="">Every trade</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" {{ (string) request('business_category_id') === (string) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-field">
            <label for="leadState">State</label>
            <select id="leadState" name="usa_state_id" data-cities="leadCity">
                <option value="">Every state</option>
                @foreach($states as $state)
                    <option value="{{ $state->id }}" {{ (string) request('usa_state_id') === (string) $state->id ? 'selected' : '' }}>{{ $state->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-field">
            <label for="leadCity">City</label>
            <select id="leadCity" name="city_id" data-selected="{{ request('city_id') }}">
                <option value="">Every city</option>
                @foreach($cities as $city)
                    <option value="{{ $city->id }}" {{ (string) request('city_id') === (string) $city->id ? 'selected' : '' }}>{{ $city->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-field">
            <label for="leadStatus">Call status</label>
            <select id="leadStatus" name="status">
                <option value="">Any outcome</option>
                @foreach(['No Answer', 'Callback Scheduled', 'Interested', 'Not Interested'] as $status)
                    <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ $status }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-go">
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
            <a href="{{ route('admin.leads') }}" class="btn btn-ghost">Clear</a>
            {{-- Carries whatever is in the filter bar, so the spreadsheet holds
                 the same leads the screen is showing. --}}
            <a href="{{ route('admin.leads.export', request()->query()) }}" class="btn btn-ghost"
               title="Download these leads as a spreadsheet">
                <i class="fas fa-file-csv"></i> Export
            </a>
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('leadImport').classList.add('show')"
                    title="Bring leads in from a spreadsheet">
                <i class="fas fa-file-import"></i> Import
            </button>
        </div>
</form>

{{-- Whatever the last import had to say. An import that only says "done"
     leaves somebody wondering about the other forty rows. --}}
@if(session('import_problems') && count(session('import_problems')))
<div class="card" style="border-left: 3px solid var(--warning);">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-triangle-exclamation" style="color: var(--warning);"></i> About that file</div>
    </div>
    <ul style="margin: 0; padding-left: 1.2rem; font-size: 0.84rem; color: var(--text-muted); line-height: 1.9;">
        @foreach(session('import_problems') as $problem)
            <li>{{ $problem }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="modal-overlay" id="leadImport">
    <div class="modal-content" style="max-width: 540px;">
        <button class="close-modal" onclick="document.getElementById('leadImport').classList.remove('show')"><i class="fas fa-times"></i></button>
        <h2 style="margin-bottom: 0.4rem;">Import leads</h2>
        <p class="field-hint" style="margin-bottom: 1.2rem;">
            A CSV whose first row names the columns. The file the Export button
            gives you is already the right shape — export, fix a column in Excel,
            and bring it back.
        </p>

        <form action="{{ route('admin.leads.import') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="field" style="margin-bottom: 1rem;">
                <label>The file</label>
                <input type="file" name="file" accept=".csv,text/csv" required class="control">
            </div>

            <div style="background: var(--bg-subtle); border-radius: 10px; padding: 0.8rem 0.9rem; margin-bottom: 1.2rem;">
                <div style="font-size: 0.76rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; color: var(--text-soft); margin-bottom: 6px;">Columns it understands</div>
                <div style="font-size: 0.82rem; color: var(--text-muted); line-height: 1.7;">
                    Name · Business · Phone · Email · Trade · State · City · Address · Website · Source · Status · Notes
                </div>
                <div class="field-hint" style="margin-top: 8px;">
                    Trade, state and city are matched to the lists this company already keeps —
                    anything that does not match is reported and the lead comes in without it.
                    A business already on the list is left alone rather than added twice.
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="fas fa-file-import"></i> Import them</button>
        </form>
    </div>
</div>

<div class="lead-cols">
    <!-- Active Leads History Table -->
    <div class="card lead-list">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-list-ul"></i> Calling leads history</div>
            <span class="chip">{{ number_format($leads->total()) }} total</span>
        </div>
        <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Business / Contact</th>
                    <th>Phone</th>
                    <th>Trade</th>
                    <th>Where</th>
                    <th>Calling List</th>
                    <th>Call Status</th>
                    <th>Notes</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($leads as $lead)
                <tr>
                    <td style="color: var(--text-muted); font-size: 0.85rem; white-space: nowrap;">{{ $lead->created_at->format('d M, Y') }}</td>
                    <td>
                        <div style="font-weight: 600;">{{ $lead->displayName() }}</div>
                        @if($lead->business_name && $lead->name !== $lead->business_name)
                            <div class="lead-place">{{ $lead->name }}</div>
                        @endif
                    </td>
                    <td style="font-size: 0.85rem; font-weight: 500; white-space: nowrap;">{{ $lead->phone ?: '—' }}</td>
                    <td>
                        @if($lead->category)
                            <span class="lead-trade">{{ $lead->category->name }}</span>
                        @else
                            <span style="color: var(--text-muted);">—</span>
                        @endif
                    </td>
                    <td class="lead-place">{{ $lead->place() ?: '—' }}</td>
                    <td style="font-size: 0.85rem;">{{ $lead->source }}</td>
                    <td>
                        @php
                            // The outcome, said in the system's own words for
                            // good, bad, waiting and nothing-yet.
                            $tone = match($lead->status) {
                                'Interested' => 'badge-success',
                                'Not Interested' => 'badge-danger',
                                'Callback Scheduled' => 'badge-warning',
                                default => '',
                            };
                        @endphp
                        <span class="badge {{ $tone }}">{{ $lead->status }}</span>
                    </td>
                    <td class="lead-notes" title="{{ $lead->notes }}">
                        {{ $lead->notes ?: '—' }}
                    </td>
                    <td>
                      <div class="lead-acts">
                        {{-- Hand it on with everything already known about the
                             business, so the closer is not typing it twice. --}}
                        <a href="{{ route('admin.transferred_leads', [
                                'lead_id' => $lead->id,
                                'business_name' => $lead->displayName(),
                                'business_category_id' => $lead->business_category_id,
                                'usa_state_id' => $lead->usa_state_id,
                                'city_id' => $lead->city_id,
                                'customer_name' => $lead->name,
                           ]) }}"
                           class="btn btn-ghost btn-sm" title="Hand this to a closer"
                           style="color: var(--success); text-decoration: none;">
                            <i class="fas fa-arrow-right-arrow-left"></i>
                        </a>
                        <button type="button" class="btn btn-ghost btn-sm" title="Edit"
                                onclick="document.getElementById('editLeadModal_{{ $lead->id }}').style.display='flex'"
                                style="color: var(--primary);"><i class="fas fa-edit"></i></button>
                        @if($canDelete)
                        <form action="{{ route('admin.leads.delete', $lead->id) }}" method="POST" onsubmit="confirmDelete(event, this, 'Remove this lead?')" style="margin: 0; display: inline-flex;">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-ghost btn-sm" title="Delete" style="color: var(--danger);"><i class="fas fa-trash-alt"></i></button>
                        </form>
                        @endif

                        <!-- Edit Lead Modal -->
                        <div id="editLeadModal_{{ $lead->id }}" class="modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.55); backdrop-filter: blur(4px); align-items: center; justify-content: center; z-index: 1000; padding: 1rem;">
                            <div class="card modal-content" style="width: 560px; max-width: 100%; max-height: 88vh; overflow-y: auto; margin: 0; text-align: left;">
                                <div class="card-header">
                                    <div class="card-title"><i class="fas fa-pen"></i> Edit calling lead</div>
                                    <button type="button" class="btn btn-ghost btn-icon" title="Close"
                                            onclick="document.getElementById('editLeadModal_{{ $lead->id }}').style.display='none'"><i class="fas fa-xmark"></i></button>
                                </div>
                                <form action="{{ route('admin.leads.update', $lead->id) }}" method="POST">
                                    @csrf @method('PUT')

                                    <div class="lead-pair">
                                        <div class="lead-field">
                                            <label>Contact Name</label>
                                            <input type="text" name="name" value="{{ $lead->name }}" required>
                                        </div>
                                        <div class="lead-field">
                                            <label>Business Name</label>
                                            <input type="text" name="business_name" value="{{ $lead->business_name }}">
                                        </div>
                                    </div>

                                    <div class="lead-pair">
                                        <div class="lead-field">
                                            <label>Phone Number</label>
                                            <input type="text" name="phone" value="{{ $lead->phone }}">
                                        </div>
                                        <div class="lead-field">
                                            <label>Email</label>
                                            <input type="email" name="email" value="{{ $lead->email }}">
                                        </div>
                                    </div>

                                    <div class="lead-field">
                                        <label>Trade / Category</label>
                                        <select name="business_category_id">
                                            <option value="">Not set</option>
                                            @foreach($categories as $category)
                                                <option value="{{ $category->id }}" {{ $lead->business_category_id == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="lead-pair">
                                        <div class="lead-field">
                                            <label>State</label>
                                            <select name="usa_state_id" data-cities="editCity_{{ $lead->id }}">
                                                <option value="">Not set</option>
                                                @foreach($states as $state)
                                                    <option value="{{ $state->id }}" {{ $lead->usa_state_id == $state->id ? 'selected' : '' }}>{{ $state->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="lead-field">
                                            <label>City</label>
                                            <select name="city_id" id="editCity_{{ $lead->id }}" data-selected="{{ $lead->city_id }}">
                                                <option value="">Not set</option>
                                                @if($lead->city)
                                                    <option value="{{ $lead->city->id }}" selected>{{ $lead->city->name }}</option>
                                                @endif
                                            </select>
                                        </div>
                                    </div>

                                    <div class="lead-field">
                                        <label>Address</label>
                                        <input type="text" name="address" value="{{ $lead->address }}">
                                    </div>

                                    <div class="lead-pair">
                                        <div class="lead-field">
                                            <label>Website</label>
                                            <input type="text" name="website" value="{{ $lead->website }}">
                                        </div>
                                        <div class="lead-field">
                                            <label>Calling List / Database</label>
                                            <input type="text" name="source" value="{{ $lead->source }}" required>
                                        </div>
                                    </div>

                                    <div class="lead-field">
                                        <label>Call Status / Outcome</label>
                                        <select name="status">
                                            @foreach(['No Answer', 'Callback Scheduled', 'Interested', 'Not Interested'] as $status)
                                                <option value="{{ $status }}" {{ $lead->status == $status ? 'selected' : '' }}>{{ $status }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="lead-field">
                                        <label>Call Notes / Conversation</label>
                                        <textarea name="notes" rows="3">{{ $lead->notes }}</textarea>
                                    </div>

                                    <div style="display: flex; gap: 10px; margin-top: 0.4rem;">
                                        <button type="submit" class="btn btn-primary" style="flex: 1;"><i class="fas fa-check"></i> Update lead</button>
                                        <button type="button" class="btn btn-ghost" onclick="document.getElementById('editLeadModal_{{ $lead->id }}').style.display='none'">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                      </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9">
                        <div class="empty-state">
                            <i class="fas fa-phone-slash"></i>
                            <strong>No calling leads here</strong>
                            <p>Nothing matches these filters. Clear them, or add the first lead with the form beside this list.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
        @if(method_exists($leads, 'links'))
            <div style="margin-top: 1rem;">
                {{ $leads->links() }}
            </div>
        @endif
    </div>

    <!-- Add New Lead Form -->
    <div class="card lead-add">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-plus-circle" style="color: var(--primary);"></i> Add calling lead</div>
        </div>
        <form id="newLeadForm" action="{{ route('admin.leads.store') }}" method="POST" style="margin-top: 1rem;">
            @csrf

            <div class="lead-field">
                <label>Contact Name</label>
                <input type="text" name="name" placeholder="Who answered" required>
            </div>
            <div class="lead-field">
                <label>Business Name</label>
                <input type="text" name="business_name" placeholder="e.g. Tucson Gutter Pros">
            </div>

            <div class="lead-pair">
                <div class="lead-field">
                    <label>Phone Number</label>
                    <input type="text" name="phone" placeholder="Client phone">
                </div>
                <div class="lead-field">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="Optional">
                </div>
            </div>

            <div class="lead-field">
                <label>Trade / Category</label>
                <select name="business_category_id">
                    <option value="">Choose a trade</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="lead-pair">
                <div class="lead-field">
                    <label>State</label>
                    <select name="usa_state_id" data-cities="newLeadCity">
                        <option value="">Choose a state</option>
                        @foreach($states as $state)
                            <option value="{{ $state->id }}">{{ $state->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lead-field">
                    <label>City</label>
                    <select name="city_id" id="newLeadCity">
                        <option value="">Pick a state first</option>
                    </select>
                </div>
            </div>

            <div class="lead-field">
                <label>Address</label>
                <input type="text" name="address" placeholder="Street, suite">
            </div>
            <div class="lead-field">
                <label>Website</label>
                <input type="text" name="website" placeholder="Optional">
            </div>
            <div class="lead-field">
                <label>Calling List / Database</label>
                <input type="text" name="source" placeholder="e.g. Google Maps, Yelp List" required>
            </div>
            <div class="lead-field">
                <label>Call Status / Outcome</label>
                <select name="status" required>
                    <option value="No Answer">No Answer</option>
                    <option value="Callback Scheduled">Callback Scheduled</option>
                    <option value="Interested">Interested</option>
                    <option value="Not Interested">Not Interested</option>
                </select>
            </div>
            <div class="lead-field">
                <label>Call Notes / Conversation</label>
                <textarea name="notes" placeholder="Call summary details..." rows="3"></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.7rem;"><i class="fas fa-floppy-disk"></i> Save calling lead</button>
        </form>

        <div class="lead-aside">
            <b>Calling lead tracker</b>
            <span>Track potential clients obtained through calling. Log their outcomes and follow-up notes.</span>
        </div>
    </div>
</div>

<script>
/*
 * Every state dropdown names the city dropdown that follows it, and filling one
 * fetches only that state's cities. Sending all eleven hundred to every page and
 * hiding most of them would be the same list, bigger and slower.
 */
(function () {
    var cache = {};

    function load(stateSelect) {
        var city = document.getElementById(stateSelect.dataset.cities);
        if (!city) return;

        var stateId = stateSelect.value;
        var wanted = city.dataset.selected || '';

        if (!stateId) {
            city.innerHTML = '<option value="">Pick a state first</option>';
            return;
        }

        var fill = function (cities) {
            var html = '<option value="">Every city</option>';

            cities.forEach(function (c) {
                html += '<option value="' + c.id + '"' + (String(c.id) === String(wanted) ? ' selected' : '') + '>'
                     + c.name.replace(/</g, '&lt;') + '</option>';
            });

            city.innerHTML = html;
        };

        if (cache[stateId]) { fill(cache[stateId]); return; }

        city.innerHTML = '<option value="">Loading…</option>';

        fetch('{{ url('admin/states') }}/' + stateId + '/cities', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
        .then(function (res) { return res.ok ? res.json() : { cities: [] }; })
        .then(function (data) {
            cache[stateId] = data.cities || [];
            fill(cache[stateId]);
        })
        .catch(function () { city.innerHTML = '<option value="">Could not load cities</option>'; });
    }

    document.querySelectorAll('select[data-cities]').forEach(function (stateSelect) {
        stateSelect.addEventListener('change', function () {
            var city = document.getElementById(stateSelect.dataset.cities);
            if (city) city.dataset.selected = '';     // a new state means a new choice
            load(stateSelect);
        });
    });
})();
</script>
@endsection
