{{--
    What an agent is working towards this month, at the top of their own
    Overview: whatever HR has set up — the target, the sales bounties, a streak,
    an allowance — each one filling as the month's sales land.

    They sit in one line that slides sideways rather than wrapping onto a second
    row: with five of them a wrapped row leaves one block stranded underneath,
    and HR can add more at any time.

    The sales figure is the one the rest of the CRM already counts, so these
    blocks cannot tell a different story from the targets page. The rules
    themselves are rows HR edits under HR Settings.
--}}
@php
    $incentive = (new \App\Services\IncentiveService())->summary(auth()->user());
@endphp

@if(count($incentive['blocks']))
<style>
    .inc-wrap { position: relative; margin-bottom: 2rem; }

    .inc-row { display: flex; gap: 1.25rem; overflow-x: auto; scroll-behavior: smooth;
               scroll-snap-type: x proximity; padding-bottom: 6px; }
    /* A thin rail rather than a chunky scrollbar: this is a strip of cards, not a document. */
    .inc-row::-webkit-scrollbar { height: 6px; }
    .inc-row::-webkit-scrollbar-track { background: transparent; }
    .inc-row::-webkit-scrollbar-thumb { background: var(--border); border-radius: 999px; }
    .inc-row:hover::-webkit-scrollbar-thumb { background: var(--text-muted); }

    .inc { position: relative; flex: 0 0 clamp(240px, 23%, 320px); scroll-snap-align: start;
           border: 1px solid var(--border); border-radius: 18px; padding: 1.15rem 1.25rem;
           background: var(--bg-card); display: flex; flex-direction: column; gap: 0.55rem; overflow: hidden; }
    .inc::before { content: ''; position: absolute; inset: 0 auto 0 0; width: 4px; background: var(--border); }
    .inc.is-done::before { background: var(--success); }
    .inc.is-close::before { background: var(--warning); }

    .inc-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
    .inc-label { font-size: 0.82rem; font-weight: 700; color: var(--text-main); line-height: 1.35; }
    .inc-note { font-size: 0.74rem; color: var(--text-muted); }
    .inc-badge { flex-shrink: 0; width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center;
                 justify-content: center; font-size: 0.72rem; background: rgba(100,116,139,0.14); color: var(--secondary); }
    .inc.is-done .inc-badge { background: rgba(16,185,129,0.15); color: var(--success); }

    .inc-figure { font-size: 1.45rem; font-weight: 800; color: var(--text-main); line-height: 1.1; }
    .inc-figure small { font-size: 0.78rem; font-weight: 600; color: var(--text-muted); }

    .inc-bar { height: 7px; border-radius: 999px; background: var(--bg-body); border: 1px solid var(--border); overflow: hidden; }
    .inc-bar i { display: block; height: 100%; background: var(--primary); transition: width 0.4s ease; }
    .inc.is-done .inc-bar i { background: var(--success); }

    .inc-foot { font-size: 0.73rem; color: var(--text-muted); margin-top: auto; }
    .inc-foot b { color: var(--text-main); }
    .inc.is-done .inc-foot b { color: var(--success); }

    .inc-streak { display: flex; gap: 6px; }
    .inc-streak span { flex: 1; text-align: center; font-size: 0.68rem; font-weight: 700; padding: 0.3rem 0;
                       border-radius: 8px; background: var(--bg-body); border: 1px solid var(--border); color: var(--text-muted); }
    .inc-streak span.met { background: rgba(16,185,129,0.14); border-color: transparent; color: var(--success); }

    /* The arrows only appear when there is something off the edge to reach. */
    .inc-arrow { position: absolute; top: 50%; transform: translateY(-50%); z-index: 2;
                 width: 34px; height: 34px; border-radius: 50%; border: 1px solid var(--border);
                 background: var(--bg-card); color: var(--text-main); cursor: pointer;
                 display: flex; align-items: center; justify-content: center; font-size: 0.8rem;
                 box-shadow: 0 4px 14px rgba(0,0,0,0.12); transition: opacity 0.2s ease; }
    .inc-arrow:hover { border-color: var(--primary); color: var(--primary); }
    .inc-arrow[hidden] { display: none; }
    .inc-arrow.prev { left: -10px; }
    .inc-arrow.next { right: -10px; }

    @media (max-width: 620px) { .inc { flex-basis: 78%; } }
</style>

<div class="inc-wrap">
    <button type="button" class="inc-arrow prev" id="incPrev" hidden aria-label="Previous" onclick="incScroll(-1)">
        <i class="fas fa-chevron-left"></i>
    </button>

    <div class="inc-row" id="incRow">
        @foreach($incentive['blocks'] as $block)
            <div class="inc {{ $block['achieved'] ? 'is-done' : ($block['progress'] >= 70 ? 'is-close' : '') }}">
                <div class="inc-top">
                    <div>
                        <div class="inc-label">{{ $block['label'] }}</div>
                        @if($block['note'])
                            <div class="inc-note">{{ $block['note'] }}</div>
                        @endif
                    </div>
                    <div class="inc-badge">
                        <i class="fas {{ $block['achieved'] ? 'fa-check' : $block['icon'] }}"></i>
                    </div>
                </div>

                <div class="inc-figure">
                    {{ $block['figure'] }}
                    @if($block['figure_note'])
                        <small>{{ $block['figure_note'] }}</small>
                    @endif
                </div>

                @if(!empty($block['months']))
                    {{-- A streak reads better as the months themselves than as a bar. --}}
                    <div class="inc-streak">
                        @foreach($block['months'] as $entry)
                            <span class="{{ $entry['met'] ? 'met' : '' }}"
                                  title="${{ number_format($entry['sales']) }} in {{ $entry['month']->format('F Y') }}">
                                {{ $entry['month']->format('M') }}
                            </span>
                        @endforeach
                    </div>
                @else
                    <div class="inc-bar"><i style="width: {{ $block['progress'] }}%;"></i></div>
                @endif

                <div class="inc-foot">
                    @if($block['foot_strong'])
                        <b>{{ $block['foot'] }}</b>
                    @else
                        {{ $block['foot'] }}
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <button type="button" class="inc-arrow next" id="incNext" hidden aria-label="Next" onclick="incScroll(1)">
        <i class="fas fa-chevron-right"></i>
    </button>
</div>

@push('scripts')
<script>
    (function () {
        var row = document.getElementById('incRow');
        var prev = document.getElementById('incPrev');
        var next = document.getElementById('incNext');
        if (!row) return;

        // One card's worth per press, whatever the card width works out to.
        window.incScroll = function (direction) {
            var card = row.querySelector('.inc');
            var step = card ? card.getBoundingClientRect().width + 20 : 260;
            row.scrollBy({ left: direction * step, behavior: 'smooth' });
        };

        function updateArrows() {
            var overflowing = row.scrollWidth - row.clientWidth > 4;
            prev.hidden = !overflowing || row.scrollLeft <= 4;
            next.hidden = !overflowing || row.scrollLeft >= row.scrollWidth - row.clientWidth - 4;
        }

        row.addEventListener('scroll', updateArrows, { passive: true });
        window.addEventListener('resize', updateArrows);
        updateArrows();
    })();
</script>
@endpush
@endif
