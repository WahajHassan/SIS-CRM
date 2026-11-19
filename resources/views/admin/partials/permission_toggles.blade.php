{{--
    Access switches for one user.

    $user     - the user being edited, or null when adding a new one
    $uid      - a suffix that keeps element ids unique when several of these are on
                the same page (one per user row, plus the add form)

    The list comes from config/permissions.php, which is the same list the sidebar
    and the CheckSectionAccess middleware read. Adding a section to that file makes a
    switch appear here on its own.

    Laid out as a grid that flows into as many columns as the modal is wide, so the
    whole set stays on screen instead of becoming one long column to scroll through.
--}}
@php
    $sections = config('permissions.sections', []);
    $ownerOnly = config('permissions.superadmin_only', []);
    $actorIsOwner = auth()->user()->isSuperAdmin();

    // A company admin must not be able to hand out the owner level sections.
    $visible = collect($sections)->reject(fn ($s, $key) => in_array($key, $ownerOnly, true) && !$actorIsOwner);

    $current = $user ? $user->allowedSections() : ($defaultsFor ?? []);
@endphp

<div class="perm-box">
    <div class="perm-box-head">
        <div>
            <span class="perm-box-title"><i class="fas fa-sliders-h"></i> What can this person open?</span>
            <span class="perm-box-note">Off hides the menu item and refuses the address. Overview and My Profile always stay available.</span>
        </div>
        <div class="perm-box-actions">
            <button type="button" onclick="permsSetAll('{{ $uid }}', true)">All on</button>
            <button type="button" onclick="permsSetAll('{{ $uid }}', false)">All off</button>
            <button type="button" onclick="permsApplyRoleDefaults('{{ $uid }}')">Reset to role</button>
        </div>
    </div>

    {{-- Tells the controller the form carried these switches, so that everything
         being off is saved as "nothing allowed" rather than read as "not submitted". --}}
    <input type="hidden" name="permissions_submitted" value="1">

    <div class="perm-grid" id="perms_{{ $uid }}">
        {{-- The second argument keeps the section keys. Without it groupBy renumbers
             each group from zero, which turned every id into perm_<user>_0 and made
             clicking one switch toggle a different one. --}}
        @foreach($visible->groupBy('group', true) as $groupName => $groupSections)
            <div class="perm-group-head">{{ $groupName }}</div>

            @foreach($groupSections as $key => $section)
                @php $inputId = 'perm_' . $uid . '_' . $key; @endphp
                <label for="{{ $inputId }}" class="perm-item" title="{{ $section['help'] ?? '' }}">
                    <span class="perm-switch">
                        <input type="checkbox" id="{{ $inputId }}" name="permissions[]" value="{{ $key }}" data-section="{{ $key }}" {{ in_array($key, $current, true) ? 'checked' : '' }}>
                        <span class="perm-slider"></span>
                    </span>
                    <span class="perm-text">
                        <span class="perm-label"><i class="fas {{ $section['icon'] ?? 'fa-circle' }}"></i>{{ $section['label'] }}</span>
                        <span class="perm-help">{{ $section['help'] ?? '' }}</span>
                    </span>
                </label>
            @endforeach
        @endforeach
    </div>
</div>
