{{--
    Setting up a fingerprint or a face for the clock.

    The browser does the reading; this is only where somebody says yes to it
    once. What the laptop sends back is a key, and the finger itself never
    leaves the laptop — which is worth saying on the page, because "register
    your fingerprint" sounds like something is being collected.
--}}
<div class="card" id="dkCard">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-fingerprint"></i> Fingerprint &amp; face</div>
        <span class="chip" id="dkState" hidden></span>
    </div>

    <p class="field-hint" style="margin: 0 0 1rem; max-width: 60ch;">
        Clock in with the fingerprint reader or face camera your computer already
        has. Nothing about your finger or face is sent anywhere — your device
        checks it is you and sends only a signature, which is what stops anybody
        clocking in on your behalf.
    </p>

    <div id="dkList" class="dk-list"></div>

    <div class="dk-do">
        <button type="button" class="btn btn-primary btn-sm" id="dkAdd">
            <i class="fas fa-plus"></i> Set up this device
        </button>
        <span class="field-hint" id="dkSays"></span>
    </div>
</div>

@push('styles')
<style>
    .dk-list:empty { display: none; }
    .dk-row {
        display: flex; align-items: center; gap: 10px;
        padding: 0.55rem 0.1rem; border-bottom: 1px solid var(--border); font-size: 0.86rem;
    }
    .dk-row:last-child { border-bottom: none; }
    .dk-row .dk-what { flex: 1; min-width: 0; }
    .dk-row .dk-what b { display: block; font-weight: 600; }
    .dk-row .dk-what span { font-size: 0.74rem; color: var(--text-muted); }
    .dk-do { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-top: 1rem; }
    .dk-none { font-size: 0.82rem; color: var(--text-soft); padding: 0.5rem 0.1rem; }
</style>
@endpush

@push('scripts')
<script src="@versioned('js/device-keys.js')"></script>
<script>
/* The page around it: what is set up, and adding one more. */
(function () {
    if (!document.getElementById('dkCard') || !window.DeviceKeys) return;

    var list = document.getElementById('dkList');
    var says = document.getElementById('dkSays');
    var state = document.getElementById('dkState');

    function esc(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function paint(data) {
        var devices = data.devices || [];

        state.hidden = !data.required;
        state.textContent = 'Required at the clock';

        if (!devices.length) {
            list.innerHTML = '<div class="dk-none">Nothing set up yet.</div>';
            return;
        }

        list.innerHTML = devices.map(function (d) {
            return '<div class="dk-row" data-id="' + d.id + '">' +
                   '<i class="fas fa-fingerprint" style="color: var(--success);"></i>' +
                   '<span class="dk-what"><b>' + esc(d.label) + '</b>' +
                   '<span>Added ' + esc(d.added) + (d.lastUsed ? ' · last used ' + esc(d.lastUsed) : ' · not used yet') + '</span></span>' +
                   '<button type="button" class="btn btn-ghost btn-sm dk-forget" title="Forget this device" style="color: var(--danger);">' +
                   '<i class="fas fa-trash-alt"></i></button></div>';
        }).join('');
    }

    function load() {
        DeviceKeys.list().then(paint).catch(function () {
            list.innerHTML = '<div class="dk-none">Could not read these.</div>';
        });
    }

    document.getElementById('dkAdd').addEventListener('click', function () {
        var button = this;

        if (!DeviceKeys.supported()) {
            says.textContent = 'This browser or device has no fingerprint or face reader to offer.';
            return;
        }

        button.disabled = true;
        says.textContent = 'Ask your device…';

        DeviceKeys.setUp(prompt('What should this device be called?', 'My laptop') || 'This device')
            .then(function () {
                says.textContent = 'Set up. You can clock in with it now.';
                load();
            })
            .catch(function (e) {
                says.textContent = e && e.message ? e.message : 'That did not go through.';
            })
            .then(function () { button.disabled = false; });
    });

    list.addEventListener('click', function (e) {
        var go = e.target.closest('.dk-forget');
        if (!go) return;

        var row = go.closest('.dk-row');
        if (!confirm('Forget this device? You will not be able to clock in with it.')) return;

        DeviceKeys.forget(row.dataset.id).then(load).catch(function () {
            says.textContent = 'Could not forget it.';
        });
    });

    load();
})();
</script>
@endpush
