{{--
    The signup form itself.

    Shown in the page for whoever is asked for five a day — they come back to it
    five times, so it stays where they left it — and in a box for whoever only
    takes one now and then. Needs $categories and $states.
--}}
@if(session('error'))
    <div class="vr-error" style="background: rgba(239,68,68,0.08); border: 1px solid var(--danger); color: var(--danger);
                border-radius: 10px; padding: 0.6rem 0.8rem; margin: 0.8rem 0; font-size: 0.82rem;">
        {{ session('error') }}
    </div>
@endif

<form id="vrForm" action="{{ route('admin.viewrec.store') }}" method="POST" style="margin-top: 1rem;">
    @csrf

    <div class="vr-field">
        <label for="vrName">Name <span style="color: var(--danger);">*</span></label>
        <input type="text" id="vrName" name="name" value="{{ old('name') }}" required placeholder="Who you spoke to" autofocus>
    </div>

    <div class="vr-field">
        <label for="vrBusiness">Business name</label>
        <input type="text" id="vrBusiness" name="business_name" value="{{ old('business_name') }}" placeholder="e.g. Tucson Gutter Pros">
    </div>

    <div class="vr-pair">
        <div class="vr-field">
            <label for="vrPhone">Number</label>
            <input type="text" id="vrPhone" name="phone" value="{{ old('phone') }}" placeholder="(555) 555-1234">
        </div>
        <div class="vr-field">
            <label for="vrEmail">Email</label>
            <input type="email" id="vrEmail" name="email" value="{{ old('email') }}" placeholder="name@business.com">
        </div>
    </div>

    <div class="vr-field">
        <label for="vrTrade">Trade</label>
        <select id="vrTrade" name="business_category_id">
            <option value="">Not set</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" {{ (string) old('business_category_id') === (string) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="vr-pair">
        <div class="vr-field">
            <label for="vrState">State</label>
            <select id="vrState" name="usa_state_id" data-cities="vrCity">
                <option value="">Not set</option>
                @foreach($states as $state)
                    <option value="{{ $state->id }}" {{ (string) old('usa_state_id') === (string) $state->id ? 'selected' : '' }}>{{ $state->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="vr-field">
            <label for="vrCity">City</label>
            <select id="vrCity" name="city_id" data-selected="{{ old('city_id') }}">
                <option value="">Pick a state first</option>
            </select>
        </div>
    </div>

    <div class="vr-field">
        <label for="vrWebsite">Website</label>
        <input type="text" id="vrWebsite" name="website" value="{{ old('website') }}" placeholder="Optional">
    </div>

    <div class="vr-field">
        <label for="vrNotes">Notes</label>
        <textarea id="vrNotes" name="notes" placeholder="Anything worth remembering…">{{ old('notes') }}</textarea>
    </div>

    <button type="submit" class="btn btn-primary"
            style="width: 100%; justify-content: center; padding: 0.75rem; cursor: pointer; font-weight: 600;
                   background: linear-gradient(135deg, #f99d4e 0%, #e93b6e 100%);">
        <i class="fas fa-check"></i> Sign them up
    </button>

    <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.7rem; line-height: 1.5;">
        An email or a number is needed — without one of them nobody can be reached again.
    </div>
</form>
