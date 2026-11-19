@extends('layouts.admin')

@section('title', 'Netflix')

@section('content')
{{--
    Something on in the background.

    Netflix will not be shown inside another site: every page it serves comes
    with a refusal to be framed, and its player will not run in a frame it does
    not own. So it opens in a window of its own, beside the CRM rather than
    inside it — which is the only way it works anywhere, and is said plainly on
    the page rather than left as a mystery.

    The panel underneath is for the links that do allow it, which is most of
    them: paste one and it plays here, and it keeps playing while the CRM is
    used because the frame is never reloaded.
--}}
<style>
    .nf-wrap { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 1.3rem; align-items: start; }
    @media (max-width: 1100px) { .nf-wrap { grid-template-columns: 1fr; } }

    /* ---- the launcher ---- */
    .nf-hero {
        position: relative; overflow: hidden;
        border-radius: var(--radius-lg); border: 1px solid var(--border);
        background: radial-gradient(120% 140% at 15% 10%, #2B1116 0%, #130A0D 45%, #0A0A0B 100%);
        padding: 2.2rem 2rem; color: #F5F5F7;
    }
    .nf-hero::after {
        content: ''; position: absolute; right: -60px; bottom: -80px;
        width: 320px; height: 320px; border-radius: 50%;
        background: radial-gradient(circle, rgba(229,9,20,0.28) 0%, rgba(229,9,20,0) 70%);
        pointer-events: none;
    }
    .nf-hero h2 { margin: 0 0 0.5rem; font-size: 1.5rem; font-weight: 800; letter-spacing: -0.02em; color: #fff; }
    .nf-hero p { margin: 0 0 1.4rem; font-size: 0.88rem; line-height: 1.65; color: rgba(245,245,247,0.72); max-width: 54ch; }
    .nf-go {
        display: inline-flex; align-items: center; gap: 9px;
        background: #E50914; color: #fff; border: none; border-radius: var(--radius-sm);
        padding: 0.7rem 1.3rem; font-family: inherit; font-size: 0.92rem; font-weight: 700; cursor: pointer;
        transition: filter var(--fast) var(--ease), transform var(--fast) var(--ease);
    }
    .nf-go:hover { filter: brightness(1.1); }
    .nf-go:active { transform: translateY(1px); }
    .nf-why { margin-top: 1rem; font-size: 0.76rem; color: rgba(245,245,247,0.5); line-height: 1.6; max-width: 56ch; }

    /* ---- the player ---- */
    .nf-stage {
        position: relative; background: #000; border-radius: var(--radius);
        overflow: hidden; aspect-ratio: 16 / 9; border: 1px solid var(--border);
    }
    .nf-stage iframe { width: 100%; height: 100%; border: 0; display: block; }
    /* Hidden by height rather than display, or the video stops playing. */
    .nf-stage.is-away { aspect-ratio: auto; height: 0; border-width: 0; }

    .nf-empty { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
                text-align: center; color: #8A8A8E; font-size: 0.85rem; padding: 2rem; }

    .nf-bar { display: flex; gap: 8px; align-items: center; margin-top: 0.9rem; flex-wrap: wrap; }
    .nf-bar input { flex: 1 1 260px; min-width: 0; }

    .nf-tip { font-size: 0.76rem; color: var(--text-muted); line-height: 1.6; }
    .nf-tip code { background: var(--bg-subtle); padding: 1px 6px; border-radius: 5px; font-size: 0.72rem; }

    .nf-side .card { margin-bottom: 1.1rem; }
    .nf-link {
        display: flex; align-items: center; gap: 10px; width: 100%;
        padding: 0.6rem 0.2rem; border: none; background: none; cursor: pointer;
        font-family: inherit; font-size: 0.86rem; color: var(--text-main); text-align: left;
        border-bottom: 1px solid var(--border);
    }
    .nf-link:last-child { border-bottom: none; }
    .nf-link:hover { color: var(--primary); }
    .nf-link i { width: 20px; text-align: center; }
    .nf-link .nf-spacer { flex: 1; }
    .nf-link small { color: var(--text-soft); font-size: 0.72rem; }
</style>

<div class="page-head">
    <div class="page-sub" style="margin: 0;">Put something on for a break. It keeps playing while you work.</div>
</div>

<div class="nf-wrap">
    <div>
        {{-- ---------- Netflix itself ---------- --}}
        <div class="nf-hero" style="margin-bottom: 1.3rem;">
            <h2><i class="fas fa-film" style="color: #E50914;"></i> Netflix</h2>
            <p>
                Opens in its own window, next to the CRM. Sign in there once and it
                stays signed in — the window can sit beside this one, or on your
                second screen.
            </p>

            <button type="button" class="nf-go" id="nfOpen">
                <i class="fas fa-up-right-from-square"></i> Open Netflix
            </button>

            <div class="nf-why">
                It cannot be shown inside this page: Netflix refuses to be put in a
                frame by another site, and its player will not run in one. Every
                site that does allow it can go in the panel below.
            </div>
        </div>

        {{-- ---------- anything that does embed ---------- --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-play"></i> Play something here</div>
                <button type="button" class="btn btn-ghost btn-sm" id="nfTuck" hidden>
                    <i class="fas fa-chevron-up"></i> <span id="nfTuckText">Tuck it away</span>
                </button>
            </div>

            <div class="nf-stage" id="nfStage">
                <div class="nf-empty" id="nfEmpty">
                    Paste a YouTube, Vimeo or Dailymotion link below and it plays here.
                </div>
                <iframe id="nfFrame" title="Whatever is playing" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>
            </div>

            <div class="nf-bar">
                <input type="text" id="nfUrl" placeholder="Paste a link…" autocomplete="off" spellcheck="false">
                <button type="button" class="btn btn-primary" id="nfPlay"><i class="fas fa-play"></i> Play</button>
                <button type="button" class="btn btn-ghost" id="nfClear" hidden>Clear</button>
            </div>

            <p class="nf-tip" style="margin: 0.8rem 0 0;">
                Works with <code>youtube.com</code>, <code>youtu.be</code>,
                <code>vimeo.com</code> and <code>dailymotion.com</code>. Tucking it
                away keeps it playing — the sound carries on while you work.
            </p>
        </div>
    </div>

    {{-- ---------- the other places ---------- --}}
    <div class="nf-side">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-tv"></i> Elsewhere</div>
            </div>

            <button type="button" class="nf-link" data-go="https://www.netflix.com">
                <i class="fas fa-film" style="color: #E50914;"></i>
                <span>Netflix</span><span class="nf-spacer"></span><small>own window</small>
            </button>
            <button type="button" class="nf-link" data-go="https://www.youtube.com">
                <i class="fab fa-youtube" style="color: #FF0000;"></i>
                <span>YouTube</span><span class="nf-spacer"></span><small>own window</small>
            </button>
            <button type="button" class="nf-link" data-go="https://www.primevideo.com">
                <i class="fab fa-amazon" style="color: #00A8E1;"></i>
                <span>Prime Video</span><span class="nf-spacer"></span><small>own window</small>
            </button>
            <button type="button" class="nf-link" data-go="https://www.disneyplus.com">
                <i class="fas fa-wand-magic-sparkles" style="color: #1F80E0;"></i>
                <span>Disney+</span><span class="nf-spacer"></span><small>own window</small>
            </button>
        </div>

        <div class="hd-note" style="background: var(--bg-subtle); border-radius: var(--radius); padding: 0.85rem 0.95rem; font-size: 0.78rem; color: var(--text-muted); line-height: 1.6;">
            <b style="color: var(--text-main); display: block; margin-bottom: 4px; font-size: 0.8rem;">A word on sound</b>
            Whatever is playing here keeps playing while you move around the CRM,
            as long as you do not close this tab. Headphones, though — the floor
            is still on calls.
        </div>
    </div>
</div>

<script>
/*
 * Turning a link into something that plays.
 *
 * Every one of these sites has a page you watch on and a separate address that
 * is allowed to be embedded; pasting the first and getting a blank box is the
 * usual disappointment, so the first is turned into the second here.
 */
(function () {
    var REMEMBER = 'crm.watch.url';

    var frame = document.getElementById('nfFrame');
    var stage = document.getElementById('nfStage');
    var empty = document.getElementById('nfEmpty');
    var field = document.getElementById('nfUrl');
    var tuck = document.getElementById('nfTuck');
    var clear = document.getElementById('nfClear');

    function embedFor(raw) {
        var url;
        try { url = new URL(String(raw).trim()); } catch (e) { return null; }

        var host = url.hostname.replace(/^www\./, '');

        if (host === 'youtu.be') {
            var short = url.pathname.slice(1);
            return short ? 'https://www.youtube.com/embed/' + encodeURIComponent(short) : null;
        }

        if (host === 'youtube.com' || host === 'm.youtube.com' || host === 'music.youtube.com') {
            if (url.pathname.startsWith('/embed/')) return url.href;

            var id = url.searchParams.get('v');
            if (id) {
                var at = url.searchParams.get('t');
                return 'https://www.youtube.com/embed/' + encodeURIComponent(id) +
                       (at ? '?start=' + encodeURIComponent(String(at).replace(/[^0-9]/g, '')) : '');
            }

            var list = url.searchParams.get('list');
            if (list) return 'https://www.youtube.com/embed/videoseries?list=' + encodeURIComponent(list);
            return null;
        }

        if (host === 'vimeo.com') {
            var bit = url.pathname.split('/').filter(Boolean)[0];
            return /^\d+$/.test(bit || '') ? 'https://player.vimeo.com/video/' + bit : null;
        }

        if (host === 'player.vimeo.com' || host === 'dailymotion.com') {
            if (host === 'dailymotion.com') {
                var vid = url.pathname.split('/').filter(Boolean).pop();
                return vid ? 'https://www.dailymotion.com/embed/video/' + encodeURIComponent(vid) : null;
            }
            return url.href;
        }

        if (host === 'dai.ly') {
            var tiny = url.pathname.slice(1);
            return tiny ? 'https://www.dailymotion.com/embed/video/' + encodeURIComponent(tiny) : null;
        }

        return null;
    }

    function play(raw, quietly) {
        var embed = embedFor(raw);

        if (!embed) {
            if (!quietly && typeof showToast === 'function') {
                showToast('Not that one',
                          'Netflix and the other subscription sites will not play inside a page. Try a YouTube, Vimeo or Dailymotion link.',
                          'error');
            }
            return false;
        }

        frame.src = embed;
        empty.hidden = true;
        tuck.hidden = false;
        clear.hidden = false;

        try { localStorage.setItem(REMEMBER, String(raw).trim()); } catch (e) {}
        return true;
    }

    document.getElementById('nfPlay').addEventListener('click', function () { play(field.value); });

    field.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); play(field.value); }
    });

    // Pasting is the whole gesture; waiting for a button press afterwards is a
    // step nobody wants.
    field.addEventListener('paste', function (e) {
        var text = (e.clipboardData || window.clipboardData).getData('text');
        if (!text) return;
        setTimeout(function () { play(text); }, 0);
    });

    clear.addEventListener('click', function () {
        frame.removeAttribute('src');
        field.value = '';
        empty.hidden = false;
        tuck.hidden = true;
        clear.hidden = true;
        stage.classList.remove('is-away');
        document.getElementById('nfTuckText').textContent = 'Tuck it away';
        try { localStorage.removeItem(REMEMBER); } catch (e) {}
    });

    tuck.addEventListener('click', function () {
        // Clipped rather than hidden: display:none would stop the video, and
        // the point of tucking it away is that it carries on.
        var away = stage.classList.toggle('is-away');
        document.getElementById('nfTuckText').textContent = away ? 'Bring it back' : 'Tuck it away';
        tuck.querySelector('i').className = away ? 'fas fa-chevron-down' : 'fas fa-chevron-up';
    });

    // Whatever was on last time.
    try {
        var last = localStorage.getItem(REMEMBER);
        if (last) { field.value = last; play(last, true); }
    } catch (e) {}

    /* ---- the places that need their own window ---- */

    function openOutside(href) {
        // A window rather than a tab: it can sit beside the CRM, and the CRM
        // stays where it is.
        window.open(href, 'crmWatch', 'width=1180,height=720');
    }

    document.getElementById('nfOpen').addEventListener('click', function () {
        openOutside('https://www.netflix.com');
    });

    document.querySelectorAll('.nf-link').forEach(function (link) {
        link.addEventListener('click', function () { openOutside(link.dataset.go); });
    });
})();
</script>
@endsection
