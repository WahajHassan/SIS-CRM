{{--
    The day's prayer times, where the person is.

    The browser is asked once where that is and the answer is kept, so nobody is
    asked again every morning. Until they say yes there is a button and nothing
    else — a panel of dashes waiting on a permission is worse than no panel.

    The times are worked out in the page. Nothing is fetched, which means they
    still appear when some website is having a bad day, and nobody's location
    leaves their own browser.
--}}
<div class="card pt-card" id="ptCard" style="margin-bottom: 0;">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-mosque"></i> Prayer times</div>
        <button type="button" class="btn btn-ghost btn-sm" id="ptSettings" title="Method and school" hidden>
            <i class="fas fa-sliders"></i>
        </button>
    </div>

    {{-- Before they have said where they are. --}}
    <div class="pt-ask" id="ptAsk">
        <i class="fas fa-location-dot"></i>
        <p>Times depend on where you are. Your browser can tell this page, and nothing leaves it.</p>
        <button type="button" class="btn btn-primary btn-sm" id="ptAllow">
            <i class="fas fa-location-crosshairs"></i> Use my location
        </button>
        <p class="pt-small" id="ptAskWhy"></p>
    </div>

    {{-- Once it knows. --}}
    <div id="ptTimes" hidden>
        <div class="pt-next" id="ptNext">
            <span>Next</span>
            <b id="ptNextName">—</b>
            <span class="pt-when" id="ptNextWhen">—</span>
        </div>

        <div class="pt-list" id="ptList"></div>

        <div class="pt-foot">
            <span id="ptWhere">—</span>
            <span class="pt-spacer"></span>
            <span id="ptMethod">—</span>
        </div>
    </div>

    {{-- The two things people disagree about. --}}
    <div class="pt-prefs" id="ptPrefs" hidden>
        <label class="field">
            <span>Method</span>
            <select id="ptMethodPick" class="control"></select>
        </label>
        <label class="field">
            <span>Asr</span>
            <select id="ptSchoolPick" class="control">
                <option value="standard">Standard (Shafi'i, Maliki, Hanbali)</option>
                <option value="hanafi">Hanafi</option>
            </select>
        </label>
        <button type="button" class="btn btn-ghost btn-sm" id="ptPrefsDone">Done</button>
    </div>
</div>

@push('styles')
<style>
    .pt-card .card-header { margin-bottom: 0.9rem; padding-bottom: 0.8rem; }

    .pt-ask { text-align: center; padding: 0.6rem 0.4rem 0.2rem; }
    .pt-ask > i { font-size: 1.5rem; color: var(--text-soft); display: block; margin-bottom: 0.7rem; }
    .pt-ask p { margin: 0 auto 0.9rem; font-size: 0.8rem; color: var(--text-muted); line-height: 1.6; max-width: 34ch; }
    .pt-small { font-size: 0.74rem !important; color: var(--text-soft) !important; margin: 0.7rem 0 0 !important; }

    .pt-next {
        display: flex; align-items: baseline; gap: 8px;
        padding: 0.6rem 0.8rem; margin-bottom: 0.8rem;
        background: var(--primary-soft); border-radius: var(--radius-sm);
    }
    .pt-next > span:first-child {
        font-size: 0.64rem; font-weight: 700; letter-spacing: 0.07em;
        text-transform: uppercase; color: var(--primary);
    }
    .pt-next b { font-size: 0.95rem; color: var(--text-main); }
    .pt-next .pt-when { margin-left: auto; font-size: 0.8rem; color: var(--text-muted); font-variant-numeric: tabular-nums; }

    .pt-row {
        display: flex; align-items: center; gap: 10px;
        padding: 0.42rem 0.2rem; font-size: 0.85rem;
        border-bottom: 1px solid var(--border);
    }
    .pt-row:last-child { border-bottom: none; }
    .pt-row .pt-name { flex: 1; color: var(--text-muted); }
    .pt-row .pt-at { font-weight: 600; font-variant-numeric: tabular-nums; }
    .pt-row.is-next .pt-name, .pt-row.is-next .pt-at { color: var(--primary); font-weight: 700; }
    .pt-row.is-gone { opacity: 0.45; }

    .pt-foot {
        display: flex; align-items: center; gap: 8px; margin-top: 0.8rem;
        padding-top: 0.7rem; border-top: 1px solid var(--border);
        font-size: 0.72rem; color: var(--text-soft);
    }
    .pt-foot .pt-spacer { flex: 1; }

    .pt-prefs { display: flex; flex-direction: column; gap: 0.8rem; }
    .pt-prefs .field span { font-size: 0.68rem; font-weight: 700; letter-spacing: 0.06em;
                            text-transform: uppercase; color: var(--text-soft); }
</style>
@endpush

@push('scripts')
<script src="@versioned('js/prayer-times.js')"></script>
<script>
/*
 * The panel around the sums.
 *
 * Where they are and how they count are kept in this browser, because neither
 * is the CRM's business: one is where somebody is sitting, and the other is a
 * matter they may hold an opinion about.
 */
(function () {
    var KEEP = 'crm.prayer';

    var card = document.getElementById('ptCard');
    if (!card || !window.PrayerTimes) return;

    var ask = document.getElementById('ptAsk');
    var panel = document.getElementById('ptTimes');
    var prefs = document.getElementById('ptPrefs');
    var listEl = document.getElementById('ptList');

    var ORDER = [
        ['fajr', 'Fajr'], ['sunrise', 'Sunrise'], ['dhuhr', 'Dhuhr'],
        ['asr', 'Asr'], ['maghrib', 'Maghrib'], ['isha', 'Isha'],
    ];

    function remembered() {
        try { return JSON.parse(localStorage.getItem(KEEP) || 'null'); } catch (e) { return null; }
    }

    function remember(what) {
        try { localStorage.setItem(KEEP, JSON.stringify(what)); } catch (e) {}
    }

    var kept = remembered();

    // The methods, from the library rather than written out again here.
    var picker = document.getElementById('ptMethodPick');
    Object.keys(PrayerTimes.METHODS).forEach(function (key) {
        var option = document.createElement('option');
        option.value = key;
        option.textContent = PrayerTimes.METHODS[key].name;
        picker.appendChild(option);
    });

    function paint() {
        if (!kept || kept.lat === undefined) return;

        var times = PrayerTimes.timesFor(new Date(), kept.lat, kept.lon, {
            method: kept.method || 'karachi',
            school: kept.school || 'standard',
        });

        var next = PrayerTimes.next(times);

        document.getElementById('ptNextName').textContent = next ? next.name : '—';
        document.getElementById('ptNextWhen').textContent = next
            ? PrayerTimes.spell(next.clock) + ' · ' + PrayerTimes.howLong(next.inMinutes)
            : '—';

        listEl.innerHTML = ORDER.map(function (pair) {
            var clock = times[pair[0]];
            var isNext = next && next.name === pair[1] && !next.tomorrow;
            var gone = clock && !isNext && (clock.h * 60 + clock.m) < (new Date().getHours() * 60 + new Date().getMinutes());

            return '<div class="pt-row' + (isNext ? ' is-next' : '') + (gone ? ' is-gone' : '') + '">' +
                   '<span class="pt-name">' + pair[1] + '</span>' +
                   '<span class="pt-at">' + PrayerTimes.spell(clock) + '</span></div>';
        }).join('');

        document.getElementById('ptWhere').textContent =
            kept.lat.toFixed(2) + '°, ' + kept.lon.toFixed(2) + '°';
        document.getElementById('ptMethod').textContent =
            times.method + (times.estimated ? ' · estimated' : '');

        ask.hidden = true;
        panel.hidden = false;
        document.getElementById('ptSettings').hidden = false;

        // The welcome line at the top of the page says the same thing.
        var strip = document.getElementById('wcPrayer');
        if (strip && next) {
            document.getElementById('wcPrayerName').textContent = next.name;
            document.getElementById('wcPrayerWhen').textContent =
                PrayerTimes.spell(next.clock) + ' · ' + PrayerTimes.howLong(next.inMinutes);
            strip.hidden = false;
        }
    }

    document.getElementById('ptAllow').addEventListener('click', function () {
        var why = document.getElementById('ptAskWhy');

        if (!navigator.geolocation) {
            why.textContent = 'This browser will not tell a page where it is.';
            return;
        }

        why.textContent = 'Asking your browser…';

        navigator.geolocation.getCurrentPosition(
            function (spot) {
                kept = Object.assign({}, kept, {
                    lat: spot.coords.latitude,
                    lon: spot.coords.longitude,
                });
                remember(kept);
                paint();
            },
            function (problem) {
                why.textContent = problem.code === 1
                    ? 'Your browser said no. Allow location for this site and try again.'
                    : 'Could not work out where you are. Try again in a moment.';
            },
            { enableHighAccuracy: false, timeout: 12000, maximumAge: 6 * 60 * 60 * 1000 }
        );
    });

    document.getElementById('ptSettings').addEventListener('click', function () {
        picker.value = (kept && kept.method) || 'karachi';
        document.getElementById('ptSchoolPick').value = (kept && kept.school) || 'standard';

        panel.hidden = true;
        prefs.hidden = false;
    });

    document.getElementById('ptPrefsDone').addEventListener('click', function () {
        kept = Object.assign({}, kept, {
            method: picker.value,
            school: document.getElementById('ptSchoolPick').value,
        });
        remember(kept);

        prefs.hidden = true;
        panel.hidden = false;
        paint();
    });

    if (kept && kept.lat !== undefined) paint();

    // The countdown is the point, so it has to keep counting.
    setInterval(function () { if (kept && kept.lat !== undefined) paint(); }, 30000);
})();
</script>
@endpush
