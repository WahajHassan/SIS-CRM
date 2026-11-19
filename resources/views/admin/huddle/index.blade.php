@extends('layouts.admin')

@section('title', 'Quick Huddle')

@section('content')
{{--
    Quick Huddle.

    Somebody is stuck. Explaining it takes longer than showing it, so they get
    shown: ask a colleague, they say yes, and their CRM appears here. If they
    hand over the mouse as well, this page can click in it.

    What the browser can and cannot do is worth being plain about. This is not
    AnyDesk: it reaches the CRM in their browser, not their computer. Their
    desktop, their files and their other windows are not in it and cannot be.
--}}
@php
    $me = auth()->user();
@endphp

<style>
    .hd-wrap { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 1.3rem; align-items: start; }
    @media (max-width: 1100px) { .hd-wrap { grid-template-columns: 1fr; } }

    /* ---- the stage ---- */
    .hd-stage {
        background: #0B1220; border: 1px solid var(--border); border-radius: var(--radius-lg);
        overflow: hidden; position: relative; min-height: 440px;
        display: flex; align-items: center; justify-content: center;
    }
    .hd-stage video { width: 100%; height: 100%; display: block; background: #0B1220; }
    .hd-stage video[hidden] { display: none; }

    /* The surface that takes the clicks, laid exactly over the picture so a
       click here lands where it looks like it lands over there. */
    .hd-surface { position: absolute; inset: 0; cursor: not-allowed; }
    .hd-surface.can-control { cursor: crosshair; }

    .hd-idle { color: #94A3B8; text-align: center; padding: 2rem; max-width: 46ch; }
    .hd-idle i { font-size: 2rem; display: block; margin-bottom: 0.9rem; opacity: 0.6; }
    .hd-idle strong { display: block; color: #E2E8F0; font-size: 1rem; margin-bottom: 6px; }
    .hd-idle p { font-size: 0.85rem; line-height: 1.6; margin: 0; }

    .hd-stage-bar {
        position: absolute; left: 0; right: 0; bottom: 0;
        display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
        padding: 0.7rem 0.9rem;
        background: linear-gradient(to top, rgba(2, 6, 23, 0.92), rgba(2, 6, 23, 0));
        color: #E2E8F0; font-size: 0.82rem;
    }
    .hd-stage-bar .hd-who { font-weight: 700; }
    .hd-stage-bar .hd-spacer { flex: 1; }
    .hd-pill {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 0.2rem 0.6rem; border-radius: var(--radius-pill);
        font-size: 0.72rem; font-weight: 700; background: rgba(255,255,255,0.14);
    }
    .hd-pill.is-live { background: rgba(239,68,68,0.9); }
    .hd-pill.is-control { background: rgba(16,185,129,0.9); }

    /* ---- the floor ---- */
    .hd-person {
        display: flex; align-items: center; gap: 10px;
        padding: 0.6rem 0.2rem; border-bottom: 1px solid var(--border);
    }
    .hd-person:last-child { border-bottom: none; }
    .hd-person .hd-name { font-weight: 600; font-size: 0.88rem; color: var(--text-main); }
    .hd-person .hd-role { font-size: 0.72rem; color: var(--text-muted); }
    .hd-person .hd-spacer { flex: 1; }

    .hd-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--text-soft); flex-shrink: 0; }
    .hd-dot.is-on { background: var(--success); box-shadow: 0 0 0 3px rgba(16,185,129,0.18); }

    .hd-shield {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 0.74rem; color: var(--text-soft); white-space: nowrap;
        padding: 0.36rem 0.7rem;
    }

    .hd-note {
        background: var(--bg-subtle); border-radius: var(--radius);
        padding: 0.85rem 0.95rem; font-size: 0.78rem; color: var(--text-muted); line-height: 1.6;
    }
    .hd-note b { color: var(--text-main); display: block; margin-bottom: 4px; font-size: 0.8rem; }
</style>

<div class="page-head">
    <div class="page-sub" style="margin: 0;">
        Ask a colleague to show you their CRM — and, if they say so, work in it with them.
    </div>
</div>

<div class="hd-wrap">
    {{-- ---------- what they are showing ---------- --}}
    <div>
        <div class="hd-stage" id="hdStage">
            <div class="hd-idle" id="hdIdle">
                <i class="fas fa-display"></i>
                <strong>Nobody is sharing with you yet</strong>
                <p>Pick somebody on the right and ask. When they say yes, their CRM appears here — and if they hand over the mouse, you can work in it.</p>
            </div>

            <video id="hdScreen" autoplay playsinline muted hidden></video>
            <div class="hd-surface" id="hdSurface" hidden tabindex="0"
                 aria-label="Their screen. Click and type here to work in it."></div>

            <div class="hd-stage-bar" id="hdStageBar" hidden>
                <span class="hd-pill is-live"><i class="fas fa-circle" style="font-size: 7px;"></i> Live</span>
                <span class="hd-who" id="hdWho">—</span>
                <span class="hd-pill" id="hdControlPill">Watching only</span>
                <span class="hd-spacer"></span>
                <button type="button" class="btn btn-danger btn-sm" id="hdLeave">
                    <i class="fas fa-right-from-bracket"></i> Leave
                </button>
            </div>
        </div>
    </div>

    {{-- ---------- who you can ask ---------- --}}
    <div>
        <div class="card" style="margin-bottom: 1.1rem;">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-users"></i> Your team</div>
                <span class="chip" id="hdOnlineCount">—</span>
            </div>

            <div id="hdPeople">
                @forelse($people as $person)
                    <div class="hd-person" data-id="{{ $person['id'] }}">
                        <span class="hd-dot {{ $person['online'] ? 'is-on' : '' }}"></span>
                        <span class="face" style="--face-size: 32px; --face-round: 10px;">
                            @if($person['face'])
                                <img src="{{ $person['face'] }}" alt="" class="face-pic" loading="lazy">
                            @else
                                <span class="face-letter" style="background: {{ $person['tone'] }};">{{ $person['initial'] }}</span>
                            @endif
                        </span>
                        <span>
                            <span class="hd-name">
                                {{ $person['name'] }}
                                @if($person['verified'])
                                    <i class="fas fa-circle-check person-tick" title="Verified" aria-label="Verified"></i>
                                @endif
                            </span>
                            <span class="hd-role" style="display: block;">{{ $person['role'] }}</span>
                        </span>
                        <span class="hd-spacer"></span>
                        @if($person['canAsk'])
                            <button type="button" class="btn btn-ghost btn-sm hd-ask"
                                    data-id="{{ $person['id'] }}" data-name="{{ $person['name'] }}"
                                    title="{{ $person['online'] ? 'Ask to see their screen' : 'They are not at their desk' }}"
                                    {{ $person['online'] ? '' : 'disabled' }}>
                                <i class="fas fa-hand"></i> Ask
                            </button>
                        @else
                            {{-- Said rather than hidden: a missing button looks
                                 like something broken. --}}
                            <span class="hd-shield" title="{{ $person['why'] }}">
                                <i class="fas fa-lock"></i> Not from here
                            </span>
                        @endif
                    </div>
                @empty
                    <div class="empty-state">
                        <i class="fas fa-user-group"></i>
                        <strong>Nobody else here yet</strong>
                        <p>Quick Huddle is between people in the same company.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="hd-note" style="margin-bottom: 1rem;">
            <b>Who can be asked</b>
            Anyone on the floor can be asked for their screen. The owner and HR
            cannot — their screens carry pay, files and personnel matters, so a
            huddle is not how you reach them. Both of them can ask anybody.
        </div>

        <div class="hd-note">
            <b>What the other person sees</b>
            They are asked before anything is shared, they pick what to share in
            their own browser, and they can stop it at any moment. Letting you
            click is a second, separate yes — and they can take it back without
            ending the huddle.
        </div>
    </div>
</div>

@include('admin.huddle._engine')
@endsection
