{{--
    The first line of somebody's day.

    Their name, the time of day, and the two things worth knowing before
    anything else: when the next prayer is, and whether they are clocked in.
    It is the top of the page, so it says something rather than taking up room.
--}}
@php
    $me = auth()->user();

    // Their shift decides what "morning" means, not the server's clock face.
    $hour = (int) now()->format('G');
    $greeting = match (true) {
        $hour < 5 => 'Still up',
        $hour < 12 => 'Good morning',
        $hour < 17 => 'Good afternoon',
        $hour < 21 => 'Good evening',
        default => 'Good evening',
    };

    // The first name is what people are called, not the row in the database.
    $firstName = trim(explode(' ', trim($me->display_name))[0] ?? $me->display_name);
@endphp

<div class="wc">
    <div class="wc-face">
        <x-face :user="$me" :size="46" />
    </div>

    <div class="wc-words">
        <h2>{{ $greeting }}, {{ $firstName }}</h2>
        <p id="wcLine">{{ now()->format('l, j F') }}</p>
    </div>

    {{-- Filled by the prayer-times script when it knows where this is. Hidden
         until then, rather than showing a row of dashes. --}}
    <div class="wc-prayer" id="wcPrayer" hidden>
        <span class="wc-prayer-what">Next prayer</span>
        <b id="wcPrayerName">—</b>
        <span class="wc-prayer-when" id="wcPrayerWhen">—</span>
    </div>
</div>

@push('styles')
<style>
    .wc {
        display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
        padding: 1rem 1.2rem; margin-bottom: 1.4rem;
        background: var(--bg-card); border: 1px solid var(--border);
        border-radius: var(--radius-lg); box-shadow: var(--shadow-xs);
    }
    .wc-face { flex-shrink: 0; }
    .wc-words { flex: 1 1 220px; min-width: 0; }
    .wc-words h2 { margin: 0; font-size: 1.12rem; font-weight: 700; letter-spacing: -0.01em; }
    .wc-words p { margin: 2px 0 0; font-size: 0.8rem; color: var(--text-muted); }

    .wc-prayer {
        display: flex; flex-direction: column; align-items: flex-end; gap: 1px;
        padding-left: 1.1rem; border-left: 1px solid var(--border);
    }
    .wc-prayer[hidden] { display: none; }
    .wc-prayer-what { font-size: 0.64rem; font-weight: 700; letter-spacing: 0.07em;
                      text-transform: uppercase; color: var(--text-soft); }
    .wc-prayer b { font-size: 0.95rem; font-weight: 700; color: var(--primary); }
    .wc-prayer-when { font-size: 0.75rem; color: var(--text-muted); font-variant-numeric: tabular-nums; }

    @media (max-width: 600px) {
        .wc-prayer { border-left: none; padding-left: 0; align-items: flex-start; width: 100%; }
    }
</style>
@endpush
