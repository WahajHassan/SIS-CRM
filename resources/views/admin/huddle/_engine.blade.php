<script>
/*
 * The guest's side of a huddle: asking, watching, and — when it is handed over
 * — working in the other person's CRM.
 *
 * The picture arrives straight from their browser. What goes back is small:
 * where the pointer is, and the clicks and keys, as fractions of the frame so
 * that a click at the middle of what is on screen here is a click at the middle
 * of what is on screen there, whatever size either window happens to be.
 */
(function () {
    var BASE = @json(url('admin/huddle'));
    var PEOPLE = @json(route('admin.huddle.people'));
    var TOKEN = document.querySelector('meta[name="csrf-token"]')
        ? document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        : '{{ csrf_token() }}';

    var ICE = { iceServers: [
        { urls: 'stun:stun.l.google.com:19302' },
        { urls: 'stun:stun1.l.google.com:19302' },
    ] };

    var stage = document.getElementById('hdStage');
    var video = document.getElementById('hdScreen');
    var surface = document.getElementById('hdSurface');
    var idle = document.getElementById('hdIdle');
    var bar = document.getElementById('hdStageBar');
    if (!stage || !video) return;

    var huddle = null;
    var peer = null;
    var channel = null;
    var since = 0;
    var signalTimer = null;
    var canControl = false;

    function post(path, body) {
        return fetch(path, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': TOKEN,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify(body || {}),
        }).then(function (r) {
            if (r.ok) return r.json();
            return r.json().then(function (e) { return Promise.reject(e); }, function () { return Promise.reject({}); });
        });
    }

    function get(path) {
        return fetch(path, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        }).then(function (r) { return r.ok ? r.json() : Promise.reject(r); });
    }

    /* ---- asking --------------------------------------------------------- */

    document.getElementById('hdPeople').addEventListener('click', function (e) {
        var button = e.target.closest('.hd-ask');
        if (!button || button.disabled) return;

        var name = button.dataset.name;
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-hourglass-half"></i> Asked';

        post(BASE + '/request', { host_id: Number(button.dataset.id) })
            .then(function (data) {
                huddle = data.huddle;
                waitForYes(name);
            })
            .catch(function (err) {
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-hand"></i> Ask';

                var why = err && err.errors && err.errors.host_id ? err.errors.host_id[0] : 'That did not go through.';
                if (typeof showToast === 'function') showToast('Not asked', why, 'error');
            });
    });

    function waitForYes(name) {
        idle.innerHTML = '<i class="fas fa-hourglass-half"></i>' +
            '<strong>Waiting for ' + escapeHtml(name) + '</strong>' +
            '<p>They have been asked. Nothing is shared until they say yes.</p>';

        startWatchingForAnswer();
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /* ---- watching ------------------------------------------------------- */

    function startWatchingForAnswer() {
        if (signalTimer) clearInterval(signalTimer);
        since = 0;
        signalTimer = setInterval(pollSignals, 1200);
        pollSignals();
    }

    function ensurePeer() {
        if (peer) return;

        peer = new RTCPeerConnection(ICE);

        peer.ontrack = function (e) {
            video.srcObject = e.streams[0];
            video.hidden = false;
            idle.hidden = true;
            bar.hidden = false;
            surface.hidden = false;
            video.play().catch(function () {});
        };

        // The host opens the channel; this side is handed it.
        peer.ondatachannel = function (e) {
            channel = e.channel;
        };

        peer.onicecandidate = function (e) {
            if (!e.candidate) return;
            send('ice', JSON.stringify(e.candidate));
        };

        peer.onconnectionstatechange = function () {
            if (['failed', 'closed'].indexOf(peer.connectionState) >= 0) leave(false);
        };
    }

    function send(kind, payload) {
        if (!huddle) return;
        post(BASE + '/' + huddle.id + '/signal', { kind: kind, payload: payload }).catch(function () {});
    }

    var polling = false;

    function pollSignals() {
        if (!huddle || polling) return;
        polling = true;

        get(BASE + '/' + huddle.id + '/signals?since=' + since)
            .then(function (data) {
                canControl = !!data.canControl;
                paintControl();

                if (data.status === 'declined') {
                    stop('They said no this time.');
                    return;
                }

                if (data.status === 'ended') {
                    stop('The huddle has ended.');
                    return;
                }

                (data.signals || []).forEach(function (signal) {
                    since = Math.max(since, signal.id);
                    handleSignal(signal);
                });
            })
            .catch(function () {})
            .then(function () { polling = false; });
    }

    function handleSignal(signal) {
        try {
            var body = JSON.parse(signal.payload);

            if (signal.kind === 'offer') {
                ensurePeer();
                // One offer gets one answer. Seeing it again is the poll
                // overlapping itself, not a new question.
                if (peer.signalingState !== 'stable') return;
                peer.setRemoteDescription(new RTCSessionDescription(body))
                    .then(function () { return peer.createAnswer(); })
                    .then(function (answer) { return peer.setLocalDescription(answer); })
                    .then(function () { send('answer', JSON.stringify(peer.localDescription)); });
                return;
            }

            if (signal.kind === 'ice' && peer) {
                peer.addIceCandidate(new RTCIceCandidate(body)).catch(function () {});
            }
        } catch (e) { /* a signal that will not parse is one to skip */ }
    }

    function paintControl() {
        var pill = document.getElementById('hdControlPill');
        pill.textContent = canControl ? 'You can click' : 'Watching only';
        pill.classList.toggle('is-control', canControl);
        surface.classList.toggle('can-control', canControl);
        surface.title = canControl
            ? 'Click and type here to work in their CRM'
            : 'They have not handed over the mouse';
    }

    /* ---- working in it -------------------------------------------------- */

    /*
     * Where a point on this screen is on theirs.
     *
     * The picture is letterboxed inside the box it is shown in, so the padding
     * has to come off before the fraction means anything. A point outside the
     * picture is not a point on their screen, and is dropped.
     */
    function toTheirScreen(e) {
        var box = video.getBoundingClientRect();
        var vw = video.videoWidth || 16;
        var vh = video.videoHeight || 9;
        if (!box.width || !box.height) return null;

        var scale = Math.min(box.width / vw, box.height / vh);
        var shownWidth = vw * scale;
        var shownHeight = vh * scale;
        var left = box.left + (box.width - shownWidth) / 2;
        var top = box.top + (box.height - shownHeight) / 2;

        var x = (e.clientX - left) / shownWidth;
        var y = (e.clientY - top) / shownHeight;

        if (x < 0 || x > 1 || y < 0 || y > 1) return null;
        return { x: x, y: y };
    }

    function tell(action) {
        if (!channel || channel.readyState !== 'open') return;
        try { channel.send(JSON.stringify(action)); } catch (e) { /* closed under us */ }
    }

    var lastCursor = 0;
    surface.addEventListener('mousemove', function (e) {
        if (!canControl) return;

        var now = Date.now();
        if (now - lastCursor < 60) return;   // a pointer does not need 60 a second
        lastCursor = now;

        var point = toTheirScreen(e);
        if (point) tell({ t: 'cursor', x: point.x, y: point.y });
    });

    /*
     * One click is one click.
     *
     * A hurried hand clicks twice, and a browser turns that into two clicks and
     * a double click — which opened the same dialog on their screen twice. The
     * second one in the same spot is dropped, and a double click is not sent at
     * all: nothing in the CRM is opened by one.
     */
    var lastSent = { x: -1, y: -1, at: 0 };

    surface.addEventListener('click', function (e) {
        if (!canControl) return;

        var point = toTheirScreen(e);
        if (!point) return;

        var now = Date.now();
        if (now - lastSent.at < 400 &&
            Math.abs(point.x - lastSent.x) < 0.004 &&
            Math.abs(point.y - lastSent.y) < 0.004) return;

        lastSent = { x: point.x, y: point.y, at: now };
        tell({ t: 'click', x: point.x, y: point.y });
        surface.focus();
    });

    surface.addEventListener('wheel', function (e) {
        if (!canControl) return;
        e.preventDefault();
        tell({ t: 'scroll', dy: e.deltaY, dx: e.deltaX });
    }, { passive: false });

    surface.addEventListener('keydown', function (e) {
        if (!canControl) return;

        // Let the browser's own shortcuts alone; this is for typing.
        if (e.ctrlKey || e.metaKey || e.altKey) return;
        if (e.key.length > 1 && ['Enter', 'Backspace', 'Tab'].indexOf(e.key) < 0) return;

        e.preventDefault();
        tell({ t: 'key', key: e.key });
    });

    /* ---- leaving -------------------------------------------------------- */

    document.getElementById('hdLeave').addEventListener('click', function () { leave(true); });

    function leave(tellThem) {
        var id = huddle && huddle.id;
        stop('You left the huddle.');
        if (id && tellThem) post(BASE + '/' + id + '/end', {}).catch(function () {});
    }

    function stop(why) {
        if (signalTimer) { clearInterval(signalTimer); signalTimer = null; }
        if (channel) { try { channel.close(); } catch (e) {} channel = null; }
        if (peer) { try { peer.close(); } catch (e) {} peer = null; }

        huddle = null;
        canControl = false;
        video.srcObject = null;
        video.hidden = true;
        surface.hidden = true;
        bar.hidden = true;
        idle.hidden = false;
        idle.innerHTML = '<i class="fas fa-display"></i>' +
            '<strong>' + escapeHtml(why || 'Nobody is sharing with you yet') + '</strong>' +
            '<p>Pick somebody on the right and ask. When they say yes, their CRM appears here.</p>';

        refreshPeople();
    }

    window.addEventListener('beforeunload', function () { if (huddle) leave(true); });

    /* ---- who is at their desk ------------------------------------------ */

    function refreshPeople() {
        get(PEOPLE)
            .then(function (data) {
                var online = 0;

                (data.people || []).forEach(function (person) {
                    if (person.online) online += 1;

                    var row = document.querySelector('.hd-person[data-id="' + person.id + '"]');
                    if (!row) return;

                    row.querySelector('.hd-dot').classList.toggle('is-on', person.online);

                    var button = row.querySelector('.hd-ask');
                    // Somebody mid-ask keeps their "Asked" until it is answered.
                    if (button && !huddle) {
                        button.disabled = !person.online;
                        button.innerHTML = '<i class="fas fa-hand"></i> Ask';
                    }
                });

                document.getElementById('hdOnlineCount').textContent = online + ' at their desk';

                // Somebody accepted while this page was sitting idle.
                if (!huddle && data.live && data.live.role === 'guest' && data.live.status === 'active') {
                    huddle = data.live;
                    document.getElementById('hdWho').textContent = huddle.with || '';
                    startWatchingForAnswer();
                }
            })
            .catch(function () {});
    }

    setInterval(refreshPeople, 8000);
    refreshPeople();

    // Already in one when the page opened.
    @if($live && $live['role'] === 'guest' && $live['status'] === 'active')
        huddle = @json($live);
        document.getElementById('hdWho').textContent = huddle.with || '';
        startWatchingForAnswer();
    @endif
})();
</script>
