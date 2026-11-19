@extends('layouts.admin')

@push('styles')
<style>
    .mp-face {
        display: flex; gap: 1.2rem; align-items: center; flex-wrap: wrap;
        padding: 1rem; margin-bottom: 1.4rem;
        background: var(--bg-subtle); border-radius: var(--radius);
    }
    .mp-face-now { flex-shrink: 0; padding-bottom: 6px; }
    .mp-face-what { flex: 1 1 260px; min-width: 0; }
    .mp-face-what b { display: block; font-size: 0.9rem; margin-bottom: 4px; }
    .mp-face-what p { margin: 0 0 0.8rem; font-size: 0.8rem; color: var(--text-muted); line-height: 1.6; max-width: 54ch; }
    .mp-face-do { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .mp-face-off { display: inline-flex; align-items: center; gap: 7px; font-size: 0.8rem; color: var(--text-muted); cursor: pointer; }
</style>
@endpush

@section('title', 'My Profile')

@section('content')
<div class="card" style="max-width: 860px; margin: 0 auto;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-user-circle"></i> My profile</div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form action="{{ route('admin.my_profile.update') }}" method="POST" enctype="multipart/form-data">
        @csrf @method('PUT')

        {{-- Their face, and the badge that says what they do here. --}}
        <div class="mp-face">
            <div class="mp-face-now">
                <x-face :user="$user" :size="92" badge />
            </div>

            <div class="mp-face-what">
                <b>Your picture</b>
                <p>It shows up beside your name all over the CRM — the sidebar, chat,
                   the leaderboard. Square works best. Without one you get your
                   initial on a colour that stays yours.</p>

                <div class="mp-face-do">
                    <label class="btn btn-ghost btn-sm" style="cursor: pointer; margin: 0;">
                        <i class="fas fa-camera"></i> Choose a picture
                        <input type="file" name="avatar" accept="image/*" id="mpAvatar" hidden>
                    </label>

                    @if($user->avatar)
                        <label class="mp-face-off">
                            <input type="checkbox" name="remove_avatar" value="1">
                            <span>Take it off</span>
                        </label>
                    @endif

                    <span class="field-hint" id="mpAvatarName"></span>
                </div>
            </div>
        </div>

        <div class="field-row" style="margin-bottom: 1rem;">
            <div class="field">
                <label>Full Name</label>
                <input type="text" name="name" value="{{ $user->name }}" required class="control">
            </div>
            <div class="field">
                <label>Email Address</label>
                <input type="email" name="email" value="{{ $user->email }}" required class="control">
            </div>
            <div class="field">
                <label>Role</label>
                <input type="text" value="{{ strtoupper($user->role) }}" disabled class="control">
            </div>
        </div>

        {{-- The CRM emails you when something needs you. Anybody who sits in it
             all day will want that off, and a system that mails people who asked
             it not to teaches them to filter it — which loses the ones that
             mattered. --}}
        <div class="field" style="margin-bottom: 1.2rem;">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 600;">
                <input type="checkbox" name="email_notifications" value="1"
                       {{ $user->email_notifications ? 'checked' : '' }}
                       style="width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer;">
                Email me when something needs me
            </label>
            <span class="field-hint">
                Leave approvals, a lead handed to you, announcements from the floor.
                The bell at the top of the page carries on either way.
                @unless(\App\Notifications\SystemAnnouncement::mailIsSetUp())
                    <br><b style="color: var(--warning);">No mail server is set up on this CRM yet, so nothing is being sent.</b>
                @endunless
            </span>
        </div>

        @if(!auth()->user()->isAdmin() && auth()->user()->role != 'superadmin')
        <div style="border-top: 1px solid var(--border); margin: 1.6rem 0 1.2rem; padding-top: 1.3rem;">
            <h4 style="margin: 0 0 0.2rem; font-size: 0.95rem;">Sales performer details</h4>
            <p class="field-hint" style="margin: 0 0 1rem;">Kept by HR — what you can change here is your pseudo name and your documents.</p>
        </div>

        <div class="field-row" style="margin-bottom: 1rem;">
            <div class="field">
                <label>Employee ID Number</label>
                <input type="text" value="{{ $performer?->employee_id_number }}" disabled class="control">
            </div>
            <div class="field">
                <label>Pseudo Name</label>
                <input type="text" name="sudo_name" value="{{ $performer?->sudo_name }}" class="control">
            </div>
            <div class="field">
                <label>ID Card Number</label>
                <input type="text" value="{{ $performer?->id_card_number }}" disabled class="control">
            </div>
        </div>

        <div class="field-row" style="margin-bottom: 1rem;">
            <div class="field">
                <label>Monthly Target</label>
                <input type="number" value="{{ $performer?->effective_goal }}" disabled class="control">
            </div>
            <div class="field">
                <label>Contract Type</label>
                <select disabled class="control">
                    <option value="">Select Type</option>
                    <option value="probation" {{ $performer?->contract_type == 'probation' ? 'selected' : '' }}>Probation</option>
                    <option value="permanent" {{ $performer?->contract_type == 'permanent' ? 'selected' : '' }}>Permanent</option>
                </select>
            </div>
            <div class="field">
                <label>Contract File (PDF)</label>
                <input type="file" name="contract_file" accept=".pdf" class="control" style="font-size: 0.8rem; padding: 0.45rem 0.6rem;">
                @if($performer?->contract_file)
                    <div class="field-hint" style="margin-top: 5px;">
                        <a href="{{ Storage::url($performer->contract_file) }}" target="_blank" style="color: var(--primary); text-decoration: underline;">View Current Contract</a>
                    </div>
                @endif
            </div>
        </div>

        <div class="field-row" style="margin-bottom: 1rem;">
            <div class="field">
                <label>ID Card Front</label>
                <input type="file" name="id_card_front" accept="image/*,.pdf" class="control" style="font-size: 0.8rem; padding: 0.45rem 0.6rem;">
                @if($performer?->id_card_front)
                    <div class="field-hint" style="margin-top: 5px;">
                        <a href="{{ Storage::url($performer->id_card_front) }}" target="_blank" style="color: var(--primary); text-decoration: underline;">View ID Front</a>
                    </div>
                @endif
            </div>
            <div class="field">
                <label>ID Card Back</label>
                <input type="file" name="id_card_back" accept="image/*,.pdf" class="control" style="font-size: 0.8rem; padding: 0.45rem 0.6rem;">
                @if($performer?->id_card_back)
                    <div class="field-hint" style="margin-top: 5px;">
                        <a href="{{ Storage::url($performer->id_card_back) }}" target="_blank" style="color: var(--primary); text-decoration: underline;">View ID Back</a>
                    </div>
                @endif
            </div>
            <div class="field">
                <label>Police Clearance</label>
                <input type="file" name="police_clearance_certificate" accept="image/*,.pdf" class="control" style="font-size: 0.8rem; padding: 0.45rem 0.6rem;">
                @if($performer?->police_clearance_certificate)
                    <div class="field-hint" style="margin-top: 5px;">
                        <a href="{{ Storage::url($performer->police_clearance_certificate) }}" target="_blank" style="color: var(--primary); text-decoration: underline;">View Certificate</a>
                    </div>
                @endif
            </div>
        </div>
        @endif

        <div style="display: flex; justify-content: flex-end; margin-top: 0.4rem;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Update profile</button>
        </div>
    </form>
</div>

{{-- The fingerprint or face they clock in with, under the form rather than in
     it: it is set up once, not saved with everything else. --}}
@include('admin.partials.device_keys')

<script>
/* What they picked, shown before it is saved. */
(function () {
    var field = document.getElementById('mpAvatar');
    if (!field) return;

    field.addEventListener('change', function () {
        var file = field.files && field.files[0];
        if (!file) return;

        document.getElementById('mpAvatarName').textContent = file.name;

        var holder = document.querySelector('.mp-face-now .face');
        if (!holder) return;

        var url = URL.createObjectURL(file);
        var letter = holder.querySelector('.face-letter');
        var pic = holder.querySelector('.face-pic');

        if (pic) {
            pic.src = url;
        } else if (letter) {
            var img = document.createElement('img');
            img.className = 'face-pic';
            img.alt = 'Your picture';
            img.src = url;
            letter.replaceWith(img);
        }
    });
})();
</script>
@endsection
