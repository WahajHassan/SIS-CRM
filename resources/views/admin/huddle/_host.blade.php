{{--
    Being asked for help, wherever you happen to be.

    This rides along on every screen, because somebody stuck on the leads page
    is asked for their screen on the leads page. It does nothing at all until
    somebody asks.

    It does not hold the sharing. That lives in a small window of its own, which
    is the whole reason the CRM can be walked through while it is on: a capture
    belongs to the document that started it, and this document is replaced every
    time somebody opens another section. What is left here is the question, and
    a line saying it is running.
--}}
<div class="hh-ask" id="hhAsk" hidden role="dialog" aria-modal="true" aria-labelledby="hhAskTitle">
    <div class="hh-ask-box">
        <div class="hh-ask-icon"><i class="fas fa-display"></i></div>
        <h3 id="hhAskTitle"><span id="hhAskWho">Somebody</span> wants to see your screen</h3>
        <p>You choose what they see: the CRM tab, one window, or your whole screen. You can change it or stop it whenever you like.</p>

        <label class="hh-ask-control">
            <input type="checkbox" id="hhAskControl">
            <span>Let them click and type as well</span>
        </label>

        <div class="hh-ask-actions">
            <button type="button" class="btn btn-ghost" id="hhDecline">No thanks</button>
            <button type="button" class="btn btn-primary" id="hhAccept"><i class="fas fa-share-from-square"></i> Share my screen</button>
        </div>
    </div>
</div>

{{-- While it is on: a line that follows you from page to page. --}}
<div class="hh-live" id="hhLive" hidden>
    <span class="hh-live-dot"></span>
    <span class="hh-live-text">Sharing with <b id="hhLiveWho">—</b></span>
    <button type="button" class="hh-live-btn" id="hhLiveWindow" title="Bring the sharing window back">
        <i class="fas fa-window-restore"></i> Controls
    </button>
    <button type="button" class="hh-live-btn is-stop" id="hhLiveStop" title="Stop sharing">
        <i class="fas fa-stop"></i> Stop
    </button>
</div>

<style>
    .hh-ask { position: fixed; inset: 0; z-index: 4000; display: flex; align-items: center; justify-content: center;
              background: rgba(15, 23, 42, 0.55); backdrop-filter: blur(4px); padding: 1rem; }
    .hh-ask[hidden] { display: none; }
    .hh-ask-box { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg);
                  box-shadow: var(--shadow-lg); padding: 1.6rem; width: 420px; max-width: 100%; text-align: center;
                  animation: dsPop var(--base) var(--ease) backwards; }
    .hh-ask-icon { width: 54px; height: 54px; margin: 0 auto 0.9rem; border-radius: 50%;
                   background: var(--primary-soft); color: var(--primary);
                   display: flex; align-items: center; justify-content: center; font-size: 1.3rem; }
    .hh-ask-box h3 { margin: 0 0 0.4rem; font-size: 1.05rem; font-weight: 700; }
    .hh-ask-box p { margin: 0 0 1.1rem; font-size: 0.85rem; color: var(--text-muted); line-height: 1.6; }
    .hh-ask-control { display: flex; align-items: center; justify-content: center; gap: 9px;
                      font-size: 0.85rem; color: var(--text-main); margin-bottom: 1.2rem; cursor: pointer; }
    .hh-ask-actions { display: flex; gap: 10px; }
    .hh-ask-actions .btn { flex: 1; }

    .hh-live { position: fixed; left: 50%; top: 14px; transform: translateX(-50%); z-index: 3900;
               display: flex; align-items: center; gap: 10px; padding: 0.5rem 0.65rem 0.5rem 0.9rem;
               background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-pill);
               box-shadow: 0 10px 30px rgba(15, 23, 42, 0.22); font-size: 0.82rem; color: var(--text-main); }
    .hh-live[hidden] { display: none; }
    .hh-live-dot { width: 9px; height: 9px; border-radius: 50%; background: #ef4444; animation: hhPulse 1.1s infinite; flex-shrink: 0; }
    .hh-live-text b { font-weight: 700; }
    .hh-live-btn { border: 1px solid var(--border); background: var(--bg-body); color: var(--text-main);
                   border-radius: var(--radius-pill); padding: 0.3rem 0.75rem; font-size: 0.76rem; font-weight: 600;
                   cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 6px;
                   transition: border-color var(--fast) var(--ease), color var(--fast) var(--ease); }
    .hh-live-btn:hover { border-color: var(--primary); color: var(--primary); }
    .hh-live-btn.is-stop { background: #ef4444; color: #fff; border-color: transparent; }
    .hh-live-btn.is-stop:hover { background: #dc2626; color: #fff; }

    @keyframes hhPulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.35; } }
</style>

<script>
(function () {
    var BASE = @json(url('admin/huddle'));
    var MINE = @json(route('admin.huddle.mine'));
    var TOKEN = document.querySelector('meta[name="csrf-token"]')
        ? document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        : '{{ csrf_token() }}';

    var askEl = document.getElementById('hhAsk');
    var liveEl = document.getElementById('hhLive');
    if (!askEl || !liveEl) return;

    var pending = null;
    var liveId = null;

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

    /* ---- being asked ---------------------------------------------------- */

    document.getElementById('hhDecline').addEventListener('click', function () {
        if (!pending) return;
        var id = pending.id;
        pending = null;
        askEl.hidden = true;
        post(BASE + '/' + id + '/answer', { accept: false }).catch(function () {});
    });

    document.getElementById('hhAccept').addEventListener('click', function () {
        if (!pending) return;

        var id = pending.id;
        var withControl = document.getElementById('hhAskControl').checked;
        pending = null;
        askEl.hidden = true;

        // Opened inside the click, before anything is awaited, or the browser
        // treats it as a pop-up that nobody asked for and blocks it.
        var win = window.open('', 'crmHuddleShare', 'width=380,height=300,menubar=no,toolbar=no,location=no');

        if (!win) {
            post(BASE + '/' + id + '/answer', { accept: false }).catch(function () {});
            if (typeof showToast === 'function') {
                showToast('Blocked', 'Your browser blocked the sharing window. Allow pop-ups for this site and try again.', 'error');
            }
            return;
        }

        post(BASE + '/' + id + '/answer', { accept: true, can_control: withControl })
            .then(function (data) {
                liveId = data.huddle.id;
                win.location = BASE + '/' + liveId + '/share';
                paintLive(data.huddle.with);
            })
            .catch(function () {
                try { win.close(); } catch (e) {}
            });
    });

    /* ---- while it is on ------------------------------------------------- */

    function paintLive(who) {
        document.getElementById('hhLiveWho').textContent = who || 'a colleague';
        liveEl.hidden = false;
    }

    document.getElementById('hhLiveWindow').addEventListener('click', function () {
        if (!liveId) return;
        // Named, so this brings the one that is already open back to the front
        // rather than starting a second.
        window.open(BASE + '/' + liveId + '/share', 'crmHuddleShare', 'width=380,height=300');
    });

    document.getElementById('hhLiveStop').addEventListener('click', function () {
        if (!liveId) return;
        var id = liveId;
        liveId = null;
        liveEl.hidden = true;
        post(BASE + '/' + id + '/end', {}).catch(function () {});
    });

    /* ---- is anybody asking, and am I already sharing? ------------------- */

    function look() {
        if (pending) return;

        get(MINE)
            .then(function (data) {
                // Moving to another section replaces this page, so what is
                // running is read back from the server rather than remembered.
                if (data.live && data.live.role === 'host' && data.live.status === 'active') {
                    liveId = data.live.id;
                    paintLive(data.live.with);
                } else {
                    liveId = null;
                    liveEl.hidden = true;
                }

                if (data.asking && !liveId) {
                    pending = data.asking;
                    document.getElementById('hhAskWho').textContent = data.asking.from || 'A colleague';
                    document.getElementById('hhAskControl').checked = false;
                    askEl.hidden = false;
                }
            })
            .catch(function () {});
    }

    /*
     * How often to ask whether anybody is asking.
     *
     * Every open CRM page runs this, so the interval is the whole feature's
     * cost on a busy floor. Six seconds while somebody is looking at the page;
     * far less when the tab is in the background, where nobody would see the
     * question anyway; and less again once a huddle is already running, because
     * then the only thing left to learn is when it ends.
     */
    function pace() {
        if (document.hidden) return 25000;
        if (liveId) return 15000;
        return 6000;
    }

    var beat = null;

    function schedule() {
        if (beat) clearTimeout(beat);
        beat = setTimeout(function () { look(); schedule(); }, pace());
    }

    // Coming back to the tab is the moment to catch up, rather than waiting out
    // the long background interval.
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) { look(); }
        schedule();
    });

    look();
    schedule();
})();
</script>
