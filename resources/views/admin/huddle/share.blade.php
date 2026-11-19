<!DOCTYPE html>
<html lang="en" class="{{ request()->cookie('theme') === 'dark' ? 'dark-mode' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sharing — Quick Huddle</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /*
            The sharing window.

            Small on purpose: it has one job, and the reason it exists is that it
            must not be the window anybody navigates. The CRM carries on in the
            tab behind it; this one holds the capture and stays put.
        */
        :root {
            --bg: #F5F7FA; --card: #FFFFFF; --ink: #0F172A; --muted: #64748B;
            --line: #E6EAF1; --primary: #F99D4E; --danger: #EF4444; --success: #10B981;
        }
        html.dark-mode {
            --bg: #0A0E17; --card: #121827; --ink: #F1F5F9; --muted: #94A3B8;
            --line: rgba(148,163,184,.16);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; font-family: 'Outfit', sans-serif; background: var(--bg); color: var(--ink);
            display: flex; flex-direction: column; height: 100vh; padding: 14px; gap: 12px;
        }
        .hs-head { display: flex; align-items: center; gap: 9px; }
        .hs-dot { width: 10px; height: 10px; border-radius: 50%; background: var(--danger); flex-shrink: 0; }
        .hs-dot.is-waiting { background: var(--muted); }
        @keyframes hsPulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
        .hs-dot.is-live { animation: hsPulse 1.2s infinite; }
        .hs-head b { font-size: 0.95rem; }
        .hs-sub { font-size: 0.78rem; color: var(--muted); line-height: 1.55; margin: 0; }

        .hs-card {
            background: var(--card); border: 1px solid var(--line); border-radius: 14px;
            padding: 0.85rem 0.9rem; display: flex; flex-direction: column; gap: 10px;
        }
        .hs-row { display: flex; align-items: center; gap: 9px; font-size: 0.82rem; }
        .hs-row .hs-spacer { flex: 1; }

        .hs-btn {
            font-family: inherit; font-size: 0.8rem; font-weight: 600; cursor: pointer;
            border: 1px solid var(--line); background: var(--bg); color: var(--ink);
            border-radius: 999px; padding: 0.4rem 0.85rem;
            display: inline-flex; align-items: center; gap: 7px;
        }
        .hs-btn:hover { border-color: var(--primary); color: var(--primary); }
        .hs-btn.is-on { border-color: var(--success); color: var(--success); }
        .hs-btn.is-stop { background: var(--danger); color: #fff; border-color: transparent; }
        .hs-btn.is-stop:hover { background: #DC2626; color: #fff; }
        .hs-btn[disabled] { opacity: 0.55; cursor: not-allowed; }

        .hs-foot { margin-top: auto; display: flex; gap: 8px; }
        .hs-foot .hs-btn { flex: 1; justify-content: center; }
        .hs-note { font-size: 0.72rem; color: var(--muted); line-height: 1.5; }
    </style>
</head>
<body>
    <div class="hs-head">
        <span class="hs-dot is-waiting" id="hsDot"></span>
        <b id="hsTitle">Starting…</b>
    </div>

    <p class="hs-sub" id="hsSub">Pick what to share in the box your browser is about to show you — a tab, a window, or your whole screen.</p>

    <div class="hs-card">
        <div class="hs-row">
            <i class="fas fa-user" style="color: var(--muted);"></i>
            <span>With <b id="hsWho">{{ $guest }}</b></span>
            <span class="hs-spacer"></span>
            <span id="hsClock" style="font-variant-numeric: tabular-nums; color: var(--muted);">0:00</span>
        </div>

        <div class="hs-row">
            <button type="button" class="hs-btn" id="hsControl" disabled>
                <i class="fas fa-mouse-pointer"></i> <span id="hsControlText">Watching only</span>
            </button>
            <span class="hs-spacer"></span>
            <button type="button" class="hs-btn" id="hsSwap" title="Share something else instead">
                <i class="fas fa-display"></i> Change
            </button>
        </div>

        <p class="hs-note" id="hsWhat" style="margin: 0;">Sharing a browser tab.</p>
    </div>

    <p class="hs-note">
        Keep this little window open — it is what holds the sharing. The CRM
        behind it works as normal, and you can move around it freely.
    </p>

    <div class="hs-foot">
        <button type="button" class="hs-btn is-stop" id="hsStop"><i class="fas fa-stop"></i> Stop sharing</button>
    </div>

<script>
/*
 * The host's half of a huddle, in a window of its own.
 *
 * It lives here rather than in the CRM page for one reason: a capture belongs
 * to the document that started it, and the CRM page is the one being walked
 * through. Put them together and the first click that opens another section
 * throws the sharing away — which is exactly what it used to do.
 *
 * So this window holds the capture and never navigates, the CRM tab carries on
 * behind it, and the clicks arriving from the other side are applied to that
 * tab through window.opener.
 */
(function () {
    var HUDDLE = @json($huddle->id);
    var BASE = @json(url('admin/huddle'));
    var TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    var ICE = { iceServers: [
        { urls: 'stun:stun.l.google.com:19302' },
        { urls: 'stun:stun1.l.google.com:19302' },
    ] };

    var peer = null, channel = null, stream = null;
    var since = 0, polling = false, timer = null, clockTimer = null;
    var canControl = @json((bool) $huddle->can_control);
    var sharingATab = true;
    var startedAt = Date.now();
    var over = false;

    var dot = document.getElementById('hsDot');
    var title = document.getElementById('hsTitle');
    var sub = document.getElementById('hsSub');
    var controlBtn = document.getElementById('hsControl');

    function post(path, body) {
        return fetch(path, {
            method: 'POST', credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json', 'X-CSRF-TOKEN': TOKEN,
                'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json',
            },
            body: JSON.stringify(body || {}),
        }).then(function (r) { return r.ok ? r.json() : Promise.reject(r); });
    }

    function get(path) {
        return fetch(path, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        }).then(function (r) { return r.ok ? r.json() : Promise.reject(r); });
    }

    /* ---- the capture ---------------------------------------------------- */

    /*
     * Ask for something to share.
     *
     * Everything the browser can offer is on the table — a tab, a window, or
     * the whole screen. The whole screen used to be taken out of the picker
     * because a click arrives as a fraction of the frame and only means
     * anything if the frame is a tab, but that traded away the common case to
     * protect the rare one: most of the time somebody wants to be watched, not
     * driven. So the screen is back, and handing over the mouse is what turns
     * itself off when what is shared is not a tab.
     *
     * This window stays out of the list, so it cannot be pointed at itself.
     */
    function pick() {
        return navigator.mediaDevices.getDisplayMedia({
            // A hint, not a rule: it opens the picker on the tab list, which is
            // where most people are going, and the other lists are still there.
            video: { displaySurface: 'browser', frameRate: 12 },
            audio: false,
            monitorTypeSurfaces: 'include',
            selfBrowserSurface: 'exclude',
            surfaceSwitching: 'include',
        });
    }

    /*
     * Swap what is being shared without dropping the call.
     *
     * The connection and the data channel are untouched; only the picture
     * changes, by handing the sender a different track. Cancelling the picker
     * leaves what was already being shared exactly as it was.
     */
    async function swap() {
        var next;

        try {
            next = await pick();
        } catch (e) {
            return;  // they changed their mind
        }

        var sender = peer && peer.getSenders().filter(function (s) {
            return s.track && s.track.kind === 'video';
        })[0];

        if (!sender) {
            next.getTracks().forEach(function (t) { t.stop(); });
            return;
        }

        var track = next.getVideoTracks()[0];
        await sender.replaceTrack(track);

        // The old one has to be stopped by hand, or the browser keeps telling
        // them they are still sharing it.
        if (stream) stream.getTracks().forEach(function (t) { t.stop(); });

        stream = next;
        track.addEventListener('ended', function () { finish('host'); });

        checkSurface();
    }

    async function begin() {
        try {
            // The CRM tab, picked by the host. It cannot be preferred
            // automatically from here, because the tab to capture is the one
            // behind this window, not this one.
            stream = await pick();
        } catch (e) {
            title.textContent = 'Not shared';
            sub.textContent = 'Nothing was shared. You can close this window.';
            post(BASE + '/' + HUDDLE + '/end', {}).catch(function () {});
            return;
        }

        stream.getVideoTracks().forEach(function (track) {
            track.addEventListener('ended', function () { finish('host'); });
        });

        checkSurface();

        dot.classList.remove('is-waiting');
        dot.classList.add('is-live');
        title.textContent = 'Sharing your screen';
        controlBtn.disabled = !sharingATab;
        paintControl();

        peer = new RTCPeerConnection(ICE);
        stream.getTracks().forEach(function (track) { peer.addTrack(track, stream); });

        channel = peer.createDataChannel('control', { ordered: true });
        channel.onmessage = onRemoteAction;

        peer.onicecandidate = function (e) {
            if (e.candidate) send('ice', JSON.stringify(e.candidate));
        };
        peer.onconnectionstatechange = function () {
            if (['failed', 'closed'].indexOf(peer.connectionState) >= 0) finish('timeout');
        };

        var offer = await peer.createOffer();
        await peer.setLocalDescription(offer);
        send('offer', JSON.stringify(peer.localDescription));

        timer = setInterval(poll, 1200);
        clockTimer = setInterval(tick, 500);
        // They can switch what is shared without stopping, so the check is not
        // a one-off.
        setInterval(checkSurface, 2500);
        poll();
    }

    /*
     * Is what they picked the CRM tab this window came from?
     *
     * A click arrives as a fraction of the frame and is applied to that tab, so
     * the two have to be the same thing. There is no way to ask the browser
     * which tab was picked, but a tab capture is exactly its viewport, so the
     * shape of the picture answers it: if the frame is not the shape of the CRM
     * tab, they picked something else, and clicking is turned off until they
     * do not.
     *
     * Watching carries on either way — seeing the wrong window is harmless, and
     * they can switch with the browser's own button.
     */
    function checkSurface() {
        var track = stream && stream.getVideoTracks()[0];
        var settings = track && track.getSettings ? track.getSettings() : {};
        var win = crm();

        var surface = settings.displaySurface;
        var isTab = surface === undefined || surface === 'browser';
        var fits = true;

        if (isTab && win && settings.width && settings.height && win.innerWidth && win.innerHeight) {
            var framed = settings.width / settings.height;
            var tabbed = win.innerWidth / win.innerHeight;
            fits = Math.abs(framed - tabbed) / tabbed < 0.05;
        }

        sharingATab = isTab && fits;

        // What they picked, named plainly, so nobody has to guess what the
        // other side is looking at.
        var what = document.getElementById('hsWhat');
        if (what) {
            what.textContent = surface === 'monitor' ? 'Sharing your whole screen — everything on it is visible.'
                : surface === 'window' ? 'Sharing one window.'
                : sharingATab ? 'Sharing the CRM tab.'
                : 'Sharing a different browser tab.';
        }

        sub.textContent = sharingATab
            ? 'They can see the CRM tab. Move around it as normal.'
            : surface === 'monitor'
                ? 'They can see everything on your screen. Clicking needs the CRM tab, so it is off — use Change to switch.'
                : surface === 'window'
                    ? 'They can see that window. Clicking needs the CRM tab, so it is off — use Change to switch.'
                    : 'That is not the CRM tab. Use Change to switch, and clicking will come back.';

        controlBtn.disabled = !sharingATab;
        paintControl();
    }

    function tick() {
        var seconds = Math.floor((Date.now() - startedAt) / 1000);
        document.getElementById('hsClock').textContent =
            Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0');
    }

    function send(kind, payload) {
        post(BASE + '/' + HUDDLE + '/signal', { kind: kind, payload: payload }).catch(function () {});
    }

    function poll() {
        if (over || polling) return;
        polling = true;

        get(BASE + '/' + HUDDLE + '/signals?since=' + since)
            .then(function (data) {
                canControl = !!data.canControl;
                paintControl();

                if (data.status !== 'active') { finish(null); return; }

                (data.signals || []).forEach(function (signal) {
                    since = Math.max(since, signal.id);

                    try {
                        var body = JSON.parse(signal.payload);

                        if (signal.kind === 'answer') {
                            if (peer.signalingState !== 'have-local-offer') return;
                            peer.setRemoteDescription(new RTCSessionDescription(body));
                        } else if (signal.kind === 'ice') {
                            peer.addIceCandidate(new RTCIceCandidate(body)).catch(function () {});
                        }
                    } catch (e) { /* a signal that will not parse is one to skip */ }
                });
            })
            .catch(function () {})
            .then(function () { polling = false; });
    }

    function paintControl() {
        document.getElementById('hsControlText').textContent =
            !sharingATab ? 'Needs a tab' : (canControl ? 'They can click' : 'Watching only');
        controlBtn.classList.toggle('is-on', canControl);
        if (!canControl) hideGhost();
    }

    controlBtn.addEventListener('click', function () {
        post(BASE + '/' + HUDDLE + '/control', { can_control: !canControl })
            .then(function (data) { canControl = !!data.huddle.canControl; paintControl(); })
            .catch(function () {});
    });

    document.getElementById('hsSwap').addEventListener('click', function () {
        if (stream) swap();
    });

    document.getElementById('hsStop').addEventListener('click', function () { finish('host'); });

    function finish(by) {
        if (over) return;
        over = true;

        if (timer) clearInterval(timer);
        if (clockTimer) clearInterval(clockTimer);
        if (channel) { try { channel.close(); } catch (e) {} }
        if (peer) { try { peer.close(); } catch (e) {} }
        if (stream) stream.getTracks().forEach(function (t) { t.stop(); });

        hideGhost();
        dot.classList.remove('is-live');
        dot.classList.add('is-waiting');
        title.textContent = 'Sharing has stopped';
        sub.textContent = 'You can close this window.';
        controlBtn.disabled = true;

        if (by) post(BASE + '/' + HUDDLE + '/end', {}).catch(function () {});
        setTimeout(function () { window.close(); }, 1200);
    }

    window.addEventListener('beforeunload', function () {
        if (!over) {
            // Closing this window is the end of the sharing, and the other side
            // should be told rather than left watching a frozen picture.
            navigator.sendBeacon(BASE + '/' + HUDDLE + '/end?_token=' + encodeURIComponent(TOKEN));
            if (stream) stream.getTracks().forEach(function (t) { t.stop(); });
        }
    });

    /* ---- their clicks, applied to the CRM tab --------------------------- */

    /** The CRM tab this window was opened from, whatever page it is on now. */
    function crm() {
        try {
            if (!window.opener || window.opener.closed) return null;
            return window.opener;
        } catch (e) { return null; }
    }

    var lastClick = { x: -1, y: -1, at: 0 };

    function onRemoteAction(event) {
        var action;
        try { action = JSON.parse(event.data); } catch (e) { return; }

        var win = crm();
        if (!win) return;

        if (action.t === 'cursor') { paintGhost(win, action); return; }
        if (!canControl || !sharingATab) return;

        var doc = win.document;
        var x = Math.round((action.x || 0) * win.innerWidth);
        var y = Math.round((action.y || 0) * win.innerHeight);

        if (action.t === 'scroll') {
            win.scrollBy({ top: action.dy || 0, left: action.dx || 0, behavior: 'auto' });
            return;
        }

        if (action.t === 'key') { typeInto(doc, action); return; }

        if (action.t !== 'click') return;

        // A hurried hand clicks twice and the second one opens the same thing
        // again. Two clicks in the same spot in a third of a second is one
        // click as far as the CRM is concerned.
        var now = Date.now();
        if (now - lastClick.at < 400 && Math.abs(x - lastClick.x) < 6 && Math.abs(y - lastClick.y) < 6) return;
        lastClick = { x: x, y: y, at: now };

        var target = doc.elementFromPoint(x, y);
        if (!target) return;
        if (target.closest('#hhLive, #hhAsk, #hhGhost')) return;

        var field = target.closest('input, textarea, select, [contenteditable]');
        if (field) field.focus();

        target.click();
    }

    function typeInto(doc, action) {
        var el = doc.activeElement;
        if (!el) return;

        var editable = (el.matches && el.matches('input, textarea')) || el.isContentEditable;
        if (!editable) return;

        if (action.key === 'Enter') {
            if (el.form && el.tagName === 'INPUT') el.form.requestSubmit();
            return;
        }

        if (action.key === 'Backspace') {
            if (el.isContentEditable) { doc.execCommand('delete'); return; }
            el.value = el.value.slice(0, -1);
            el.dispatchEvent(new el.ownerDocument.defaultView.Event('input', { bubbles: true }));
            return;
        }

        if (action.key && action.key.length === 1) {
            if (el.isContentEditable) { doc.execCommand('insertText', false, action.key); return; }
            el.value = el.value + action.key;
            el.dispatchEvent(new el.ownerDocument.defaultView.Event('input', { bubbles: true }));
        }
    }

    /** Their pointer, drawn in the CRM tab so the host can follow it. */
    function paintGhost(win, action) {
        if (!canControl) return;

        var doc = win.document;
        var ghost = doc.getElementById('hhGhost');

        // The CRM page is replaced every time they move section, so the mark is
        // put back rather than assumed to still be there.
        if (!ghost) {
            ghost = doc.createElement('div');
            ghost.id = 'hhGhost';
            ghost.style.cssText = 'position:fixed; z-index:3950; pointer-events:none; width:18px; height:18px;' +
                'border-radius:50% 50% 50% 2px; background:#F99D4E; box-shadow:0 2px 6px rgba(15,23,42,.4);' +
                'transition:transform 90ms linear;';
            doc.body.appendChild(ghost);
        }

        ghost.hidden = false;
        ghost.style.transform = 'translate(' +
            Math.round((action.x || 0) * win.innerWidth) + 'px, ' +
            Math.round((action.y || 0) * win.innerHeight) + 'px)';
    }

    function hideGhost() {
        var win = crm();
        if (!win) return;
        try {
            var ghost = win.document.getElementById('hhGhost');
            if (ghost) ghost.remove();
        } catch (e) { /* the tab went somewhere we cannot reach */ }
    }

    begin();
})();
</script>
</body>
</html>
