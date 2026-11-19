@props(['user' => null, 'size' => 34, 'badge' => false, 'tick' => true])

{{--
    A person, in one place.

    Their picture if they uploaded one, their initial on their own colour if
    they did not — and the same colour wherever they appear, because a letter
    that changes colour between the sidebar and the chat reads as two different
    people.

    The tick is drawn whenever the person has been verified, without being asked
    for: a mark that only appears where somebody remembered to ask for it is
    worse than none, because its absence stops meaning anything. Pass
    :tick="false" where it would have nothing to sit on, like a 20px row icon.

    <x-face :user="$user" :size="40" badge />
--}}
@php
    $url = $user?->avatar_url;
    $initial = $user?->initial ?? '?';
    $tone = $user?->tone ?? '#94A3B8';
    $round = max(6, (int) round($size * 0.3));

    $verified = $user?->isVerified() ?? false;

    // The role, said shortly. A badge the size of a thumbnail has room for a
    // word, not a job title.
    $badgeFor = [
        'superadmin' => ['Owner', '#F59E0B'],
        'admin' => ['Admin', '#6366F1'],
        'floor_manager' => ['Floor', '#6366F1'],
        'hr' => ['HR', '#EC4899'],
        'team_lead' => ['Lead', '#0EA5E9'],
        'closer' => ['Closer', '#10B981'],
        'agent' => ['Agent', '#F99D4E'],
        'support_manager' => ['Support', '#14B8A6'],
        'support_agent' => ['Support', '#14B8A6'],
        'qa' => ['QA', '#8B5CF6'],
        'editor' => ['Editor', '#8B5CF6'],
    ][$user?->role] ?? null;

    $says = $user
        ? $user->display_name
            . ($badgeFor ? ' · ' . $badgeFor[0] : '')
            . ($verified ? ' · verified' : '')
        : null;
@endphp

<span class="face{{ $verified ? ' is-verified' : '' }}"
      style="--face-size: {{ $size }}px; --face-round: {{ $round }}px;"
      @if($says) title="{{ $says }}" @endif>
    {{--
        The letter is always drawn, and the picture sits on top of it.

        A picture can fail for reasons that have nothing to do with this page —
        a file deleted from disk, a half-finished upload, the storage link not
        made on a new server. When it does, the browser draws its broken-image
        icon with the alt text spilling out beside it, which is what somebody
        reported seeing. Letting the letter show through instead means the worst
        case is simply the same as having uploaded no picture at all.
    --}}
    <span class="face-letter" style="background: {{ $tone }};">{{ $initial }}</span>

    @if($url)
        <img src="{{ $url }}" alt="" class="face-pic" loading="lazy"
             onerror="this.remove();">
    @endif

    @if($verified && $tick && $size >= 24)
        <span class="face-tick" aria-label="Verified"><i class="fas fa-check" aria-hidden="true"></i></span>
    @endif

    @if($badge && $badgeFor)
        <span class="face-badge" style="background: {{ $badgeFor[1] }};">{{ $badgeFor[0] }}</span>
    @endif
</span>
