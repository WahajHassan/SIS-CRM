@props([
    'user' => null,
    'name' => null,
    'size' => 34,
    'sub' => null,
    'role' => false,
    'link' => null,
])

{{--
    A person in a list: their face, their name, and the tick if they have one.

    One component rather than forty hand-rolled versions, because the point of a
    verification tick is that it is in the same place every time. A tick that
    appears beside a name on one screen and not the next is not a tick, it is
    noise.

    `name` covers the rows where the CRM has a name but no account behind it —
    a performer who has not been given a login yet. They get a face and no tick,
    which is the honest answer.

    <x-person :user="$u" :size="38" :sub="$u->email" role />
--}}
@php
    $shown = $name ?? $user?->display_name ?? '—';
    $verified = $user?->isVerified() ?? false;
@endphp

<span {{ $attributes->merge(['class' => 'person']) }}>
    <x-face :user="$user" :size="$size" :tick="false" />

    <span class="person-who">
        <span class="person-name">
            @if($link)
                <a href="{{ $link }}">{{ $shown }}</a>
            @else
                {{ $shown }}
            @endif

            @if($verified)
                <i class="fas fa-circle-check person-tick"
                   title="Verified{{ $user->verifier ? ' by ' . $user->verifier->display_name : '' }}"
                   aria-label="Verified"></i>
            @endif
        </span>

        @if($sub || ($role && $user?->role))
            <span class="person-sub">
                {{ $sub ?? ucwords(str_replace('_', ' ', $user->role)) }}
            </span>
        @endif
    </span>
</span>
