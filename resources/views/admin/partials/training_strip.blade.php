{{--
    The newest training, across the top of the dashboard.

    The team is expected to watch these, so they go where the team already
    looks — above everything else — rather than behind a menu they would have to
    remember to open. Clicking one opens it in the training room; a new one
    arrives here on its own.

    Needs $trainingStrip from the dashboard.
--}}
@php
    $me = auth()->user();
@endphp

<style>
    .ts-wrap { margin-bottom: 1.5rem; }
    .ts-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.8rem; }
    .ts-head h2 { font-size: 1.05rem; font-weight: 700; color: var(--text-main); margin: 0;
                  display: flex; align-items: center; gap: 8px; }
    .ts-head a { font-size: 0.8rem; font-weight: 600; color: var(--primary); text-decoration: none; }

    .ts-row { display: flex; gap: 1rem; overflow-x: auto; padding-bottom: 6px; scroll-snap-type: x proximity; }
    .ts-row::-webkit-scrollbar { height: 6px; }
    .ts-row::-webkit-scrollbar-thumb { background: var(--border); border-radius: 999px; }

    .ts-card { flex: 0 0 clamp(230px, 24%, 290px); scroll-snap-align: start; text-decoration: none;
               background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px;
               overflow: hidden; transition: border-color 0.15s ease, transform 0.15s ease; }
    .ts-card:hover { border-color: var(--primary); transform: translateY(-2px); }

    .ts-thumb { height: 92px; display: flex; align-items: center; justify-content: center;
                font-size: 1.6rem; color: #fff; position: relative; }
    .ts-thumb.video { background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); }
    .ts-thumb.voice { background: linear-gradient(135deg, #ec4899 0%, #db2777 100%); }
    .ts-thumb.text  { background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); }
    .ts-new { position: absolute; top: 8px; right: 8px; background: rgba(255,255,255,0.92); color: #111;
              font-size: 0.62rem; font-weight: 800; letter-spacing: 0.04em; padding: 2px 7px; border-radius: 999px; }

    .ts-body { padding: 0.75rem 0.9rem; }
    .ts-body b { display: block; font-size: 0.86rem; color: var(--text-main); line-height: 1.4;
                 overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
    .ts-meta { font-size: 0.7rem; color: var(--text-muted); margin-top: 6px; display: flex; gap: 8px; flex-wrap: wrap; }
    .ts-seen { color: #10b981; font-weight: 700; }

    .ts-empty { border: 1px dashed var(--border); border-radius: 16px; padding: 1.2rem;
                text-align: center; color: var(--text-muted); font-size: 0.85rem; }
</style>

<div class="ts-wrap" id="trainingStrip">
    <div class="ts-head">
        <h2><i class="fas fa-graduation-cap" style="color: var(--primary);"></i> Training</h2>
        <a href="{{ route('admin.training') }}">All training <i class="fas fa-arrow-right" style="font-size: 0.7rem;"></i></a>
    </div>

    <div class="ts-row" id="trainingRow">
        @forelse($trainingStrip as $training)
            <a class="ts-card" href="{{ route('admin.training', ['open' => $training->id]) }}" data-id="{{ $training->id }}">
                <div class="ts-thumb {{ $training->kind }}">
                    <i class="fas {{ $training->kind === 'video' ? 'fa-play' : ($training->kind === 'voice' ? 'fa-microphone' : 'fa-align-left') }}"></i>
                    @unless($training->watchedBy($me))
                        <span class="ts-new">NEW</span>
                    @endunless
                </div>
                <div class="ts-body">
                    <b>{{ $training->title }}</b>
                    <div class="ts-meta">
                        <span>{{ $training->author->display_name ?? 'Management' }}</span>
                        <span>&middot; {{ $training->created_at->diffForHumans() }}</span>
                        @if($training->watchedBy($me))
                            <span class="ts-seen"><i class="fas fa-check"></i> watched</span>
                        @endif
                    </div>
                </div>
            </a>
        @empty
            <div class="ts-empty" id="trainingEmpty" style="flex: 1;">
                No training up yet. Anything posted will show here.
            </div>
        @endforelse
    </div>
</div>

<script>
/*
 * Training posted while somebody is sitting on the dashboard turns up here
 * without them reloading, the same way announcements do.
 */
(function () {
    var row = document.getElementById('trainingRow');
    if (!row) return;

    var known = new Set([].map.call(row.querySelectorAll('.ts-card'), function (el) { return String(el.dataset.id); }));
    var firstPass = true;

    function card(t) {
        var icon = t.kind === 'video' ? 'fa-play' : (t.kind === 'voice' ? 'fa-microphone' : 'fa-align-left');
        var safe = function (v) { var d = document.createElement('div'); d.textContent = v == null ? '' : v; return d.innerHTML; };

        return '<a class="ts-card" href="{{ route('admin.training') }}?open=' + t.id + '" data-id="' + t.id + '">' +
            '<div class="ts-thumb ' + safe(t.kind) + '"><i class="fas ' + icon + '"></i>' +
                (t.watched ? '' : '<span class="ts-new">NEW</span>') +
            '</div>' +
            '<div class="ts-body"><b>' + safe(t.title) + '</b>' +
                '<div class="ts-meta"><span>' + safe(t.author) + '</span><span>&middot; ' + safe(t.ago) + '</span></div>' +
            '</div></a>';
    }

    function poll() {
        fetch('{{ route('admin.training') }}', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
        .then(function (res) { return res.ok ? res.json() : null; })
        .then(function (data) {
            if (!data || !data.trainings) return;

            data.trainings.slice().reverse().forEach(function (t) {
                if (known.has(String(t.id))) return;
                known.add(String(t.id));

                if (firstPass) return;                  // already on the page

                var empty = document.getElementById('trainingEmpty');
                if (empty) empty.remove();

                row.insertAdjacentHTML('afterbegin', card(t));

                if (typeof showToast === 'function') {
                    showToast('New training', t.title, 'info');
                }
            });

            firstPass = false;
        })
        .catch(function () { /* the next tick will try again */ });
    }

    poll();
    setInterval(poll, 10000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
})();
</script>
