{{--
    The pool, and what this closer has already picked up.

    An agent hands a lead over and it lands here. Whoever is free claims it —
    first come, first served — and from then on it is theirs to move along. The
    board is the part of the page a closer actually works from, so it sits above
    everything else.

    Needs $board from LeadPipeline, plus $categories and $states for the labels.
--}}
@php
    $me = auth()->user();
    $canClaim = $me->isCloser() || $me->isAdmin();
@endphp

<style>
    /* ---- what it came to ----------------------------------------------
       A card used to end at "Won" and leave the figure two screens away on
       the deal. The figure is the reason the lead was handed over at all,
       so it belongs on the card, and clicking it opens the deal behind it. */
    .pipe-worth {
        display: flex; align-items: center; gap: 8px;
        margin: 0.5rem 0 0.2rem; padding: 0.45rem 0.6rem;
        border-radius: 10px; text-decoration: none;
        background: rgba(16, 185, 129, 0.08);
        border: 1px solid rgba(16, 185, 129, 0.2);
        color: var(--text-main); font-size: 0.82rem;
    }
    .pipe-worth:hover { border-color: var(--success); }
    .pipe-worth i { color: var(--success); }
    .pipe-worth b { font-variant-numeric: tabular-nums; font-size: 0.95rem; }
    .pipe-worth-state {
        padding: 1px 7px; border-radius: var(--radius-pill);
        font-size: 0.62rem; font-weight: 800; letter-spacing: 0.04em;
    }
    .pipe-worth-state.is-won { background: rgba(16,185,129,0.16); color: var(--success); }
    .pipe-worth-state.is-pending { background: rgba(245,158,11,0.16); color: var(--warning); }
    .pipe-worth-state.is-lost { background: rgba(239,68,68,0.14); color: var(--danger); }
    .pipe-worth-go { margin-left: auto; font-size: 0.72rem; color: var(--text-muted); }

    .pipe { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; align-items: start; }
    @media (max-width: 1100px) { .pipe { grid-template-columns: 1fr; } }

    .pipe-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 18px; padding: 1.3rem; }
    .pipe-head { display: flex; justify-content: space-between; align-items: center; gap: 10px;
                 border-bottom: 1px solid var(--border); padding-bottom: 0.9rem; margin-bottom: 1rem; }
    .pipe-head h3 { font-size: 1.02rem; font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 8px; margin: 0; }
    .pipe-count { background: var(--bg-body); border: 1px solid var(--border); border-radius: 999px;
                  padding: 2px 11px; font-size: 0.78rem; font-weight: 700; color: var(--text-muted); }

    .pipe-list { display: flex; flex-direction: column; gap: 10px; max-height: 420px; overflow-y: auto; padding-right: 4px; }
    .pipe-item { border: 1px solid var(--border); border-left: 3px solid var(--primary); border-radius: 12px;
                 padding: 0.75rem 0.9rem; background: var(--bg-body); }
    .pipe-item.is-mine { border-left-color: #10b981; }
    .pipe-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
    .pipe-name { font-weight: 700; font-size: 0.9rem; color: var(--text-main); }
    .pipe-meta { font-size: 0.74rem; color: var(--text-muted); margin-top: 3px; line-height: 1.5; }
    .pipe-tag { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 0.68rem; font-weight: 700;
                background: rgba(99,102,241,0.12); color: #6366f1; margin-right: 5px; }
    .pipe-stage { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 0.7rem; font-weight: 700;
                  background: rgba(245,158,11,0.14); color: #b45309; }
    .pipe-actions { display: flex; gap: 6px; align-items: center; margin-top: 9px; flex-wrap: wrap; }
    .pipe-actions select { height: 32px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-card);
                           color: var(--text-main); font-family: inherit; font-size: 0.78rem; padding: 0 0.5rem; }
    .pipe-btn { border: none; border-radius: 8px; padding: 0.42rem 0.85rem; font-size: 0.78rem; font-weight: 700;
                cursor: pointer; font-family: inherit; }
    .pipe-btn.claim { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #fff; }
    .pipe-btn.ghost { background: var(--bg-card); border: 1px solid var(--border); color: var(--text-muted); }
    .pipe-btn.ghost:hover { color: var(--text-main); }
    .pipe-empty { text-align: center; padding: 1.8rem 1rem; color: var(--text-muted); font-size: 0.85rem; }
    .pipe-wait { font-size: 0.7rem; color: var(--danger); font-weight: 700; }

    /* A card is something you can open, so it behaves like one. */
    .pipe-item { cursor: pointer; transition: border-color var(--fast) var(--ease), box-shadow var(--fast) var(--ease); }
    .pipe-item:hover { border-color: var(--border-strong); box-shadow: var(--shadow-sm); }
    .pipe-item.is-open { border-color: var(--primary); box-shadow: 0 0 0 3px var(--ring); }
    /* The buttons and the dropdown inside a card do their own thing. */
    .pipe-item form, .pipe-item .pipe-actions { cursor: default; }

    .pipe-open-hint { font-size: 0.7rem; color: var(--text-soft); display: flex; align-items: center; gap: 4px; }

    /* ---- the panel ---- */
    .ld-scrim {
        position: fixed; inset: 0; z-index: 1490;
        background: rgba(15, 23, 42, 0.45); backdrop-filter: blur(2px);
        opacity: 0; pointer-events: none; transition: opacity var(--base) var(--ease);
    }
    .ld-scrim.is-open { opacity: 1; pointer-events: auto; }

    .ld-panel {
        position: fixed; top: 0; right: 0; bottom: 0; z-index: 1491;
        width: min(460px, 94vw);
        background: var(--bg-card); border-left: 1px solid var(--border);
        box-shadow: var(--shadow-lg);
        display: flex; flex-direction: column;
        transform: translateX(100%);
        transition: transform var(--slow) var(--ease);
    }
    .ld-panel.is-open { transform: none; }

    .ld-head {
        padding: 1.1rem 1.25rem; border-bottom: 1px solid var(--border);
        display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;
    }
    .ld-head h3 { margin: 0 0 5px; font-size: 1.05rem; font-weight: 700; line-height: 1.3; }
    .ld-head .ld-sub { font-size: 0.78rem; color: var(--text-muted); }

    .ld-body { padding: 1.1rem 1.25rem 1.4rem; overflow-y: auto; flex: 1; }
    .ld-group + .ld-group { margin-top: 1.4rem; }
    .ld-group h4 {
        margin: 0 0 0.6rem; font-size: 0.68rem; font-weight: 700;
        letter-spacing: 0.07em; text-transform: uppercase; color: var(--text-soft);
    }
    .ld-rows { display: flex; flex-direction: column; gap: 0.55rem; }
    .ld-row { display: grid; grid-template-columns: 112px minmax(0, 1fr); gap: 10px; align-items: baseline; }
    .ld-row dt { font-size: 0.78rem; color: var(--text-muted); }
    .ld-row dd { margin: 0; font-size: 0.85rem; color: var(--text-main); word-break: break-word; }
    .ld-row dd a { color: var(--primary); text-decoration: none; font-weight: 600; }
    .ld-row dd a:hover { text-decoration: underline; }
    .ld-row dd.is-empty { color: var(--text-soft); }

    .ld-note {
        background: var(--bg-subtle); border-radius: var(--radius-sm);
        padding: 0.7rem 0.85rem; font-size: 0.85rem; line-height: 1.55;
        color: var(--text-main); white-space: pre-wrap;
    }

    .ld-foot {
        padding: 0.9rem 1.25rem; border-top: 1px solid var(--border);
        background: var(--bg-card); display: flex; gap: 8px; align-items: center; flex-wrap: wrap;
    }
    .ld-foot .ld-spacer { flex: 1; }

    @media (max-width: 560px) { .ld-row { grid-template-columns: 1fr; gap: 2px; } }
</style>

<div class="pipe">
    {{-- ---------- waiting for anybody ---------- --}}
    <div class="pipe-card">
        <div class="pipe-head">
            <h3><i class="fas fa-inbox" style="color: var(--primary);"></i> In the pool</h3>
            <span class="pipe-count" id="poolCount">{{ $board['waiting'] }} waiting</span>
        </div>

        <div class="pipe-list">
            @forelse($board['pool'] as $item)
                <div class="pipe-item" data-id="{{ $item->id }}" role="button" tabindex="0"
                     aria-label="Open {{ $item->displayName() }}">
                    {{-- Everything the agent wrote down, for the panel to show. It
                         rides along with the card rather than costing a request. --}}
                    @php
                        $detail = [
                            'id' => $item->id,
                            'title' => $item->displayName(),
                            'stage' => $item->stageLabel(),
                            'business' => $item->business_name,
                            'customer' => $item->customer_name,
                            'owner' => $item->owner_name,
                            'trade' => $item->category?->name,
                            'place' => $item->place(),
                            'address' => $item->address,
                            'phone' => $item->phone,
                            'personalPhone' => $item->personal_number,
                            'email' => $item->email,
                            'personalEmail' => $item->personal_email,
                            'yelp' => $item->yelp_link,
                            'google' => $item->google_link,
                            'notes' => $item->notes,
                            'agent' => $item->agent?->display_name,
                            'closer' => $item->closer?->display_name,
                            'handedOver' => $item->created_at->format('d M Y, h:i A'),
                            'waiting' => $item->created_at->diffForHumans(null, true),
                            'scheduled' => $item->scheduled_at?->format('d M Y, h:i A'),
                            'claimed' => $item->claimed_at?->diffForHumans(),
                            'status' => $item->status,
                            'proposalSent' => (bool) $item->is_proposal_sent,
                            'converted' => (bool) $item->is_converted,
                            'dealId' => $item->deal_id,
                            'dealUrl' => $item->deal ? route('admin.deals', ['search' => $item->deal->name]) : null,
                            'mine' => false,
                        ];
                    @endphp
                    <script type="application/json" class="pipe-detail">@json($detail)</script>
                    <div class="pipe-top">
                        <div>
                            <div class="pipe-name">{{ $item->displayName() }}</div>
                            <div class="pipe-meta">
                                @if($item->category)<span class="pipe-tag">{{ $item->category->name }}</span>@endif
                                {{ $item->place() ?: 'Place not set' }}
                            </div>
                            <div class="pipe-meta">
                                From {{ $item->agent?->display_name ?? 'an agent' }}
                                @if($item->scheduled_at) &middot; call at {{ $item->scheduled_at->format('d M, h:i A') }} @endif
                            </div>
                        </div>
                        <span class="pipe-wait">{{ $item->created_at->diffForHumans(null, true) }} waiting</span>
                    </div>

                    @if($canClaim)
                        <div class="pipe-actions">
                            <form action="{{ route('admin.transferred_leads.claim', $item->id) }}" method="POST" style="margin: 0;" data-act="claim">
                                @csrf
                                <button type="submit" class="pipe-btn claim"><i class="fas fa-hand"></i> Claim it</button>
                            </form>
                            <span class="pipe-meta">{{ $item->phone ?: 'no number' }}</span>
                            <span class="ld-spacer" style="flex: 1;"></span>
                            <span class="pipe-open-hint"><i class="fas fa-up-right-and-down-left-from-center"></i> open</span>
                        </div>
                    @endif
                </div>
            @empty
                <div class="pipe-empty">Nothing waiting. Every lead has somebody on it.</div>
            @endforelse
        </div>
    </div>

    {{-- ---------- what this closer is working on ---------- --}}
    <div class="pipe-card">
        <div class="pipe-head">
            <h3><i class="fas fa-user-tie" style="color: #10b981;"></i> On my desk</h3>
            <span class="pipe-count">{{ $board['mine']->count() }} open</span>
        </div>

        <div class="pipe-list">
            @forelse($board['mine'] as $item)
                <div class="pipe-item is-mine" data-id="{{ $item->id }}" role="button" tabindex="0"
                     aria-label="Open {{ $item->displayName() }}">
                    {{-- Everything the agent wrote down, for the panel to show. It
                         rides along with the card rather than costing a request. --}}
                    @php
                        $detail = [
                            'id' => $item->id,
                            'title' => $item->displayName(),
                            'stage' => $item->stageLabel(),
                            'business' => $item->business_name,
                            'customer' => $item->customer_name,
                            'owner' => $item->owner_name,
                            'trade' => $item->category?->name,
                            'place' => $item->place(),
                            'address' => $item->address,
                            'phone' => $item->phone,
                            'personalPhone' => $item->personal_number,
                            'email' => $item->email,
                            'personalEmail' => $item->personal_email,
                            'yelp' => $item->yelp_link,
                            'google' => $item->google_link,
                            'notes' => $item->notes,
                            'agent' => $item->agent?->display_name,
                            'closer' => $item->closer?->display_name,
                            'handedOver' => $item->created_at->format('d M Y, h:i A'),
                            'waiting' => $item->created_at->diffForHumans(null, true),
                            'scheduled' => $item->scheduled_at?->format('d M Y, h:i A'),
                            'claimed' => $item->claimed_at?->diffForHumans(),
                            'status' => $item->status,
                            'proposalSent' => (bool) $item->is_proposal_sent,
                            'converted' => (bool) $item->is_converted,
                            'dealId' => $item->deal_id,
                            'dealUrl' => $item->deal ? route('admin.deals', ['search' => $item->deal->name]) : null,
                            'mine' => true,
                        ];
                    @endphp
                    <script type="application/json" class="pipe-detail">@json($detail)</script>
                    <div class="pipe-top">
                        <div>
                            <div class="pipe-name">{{ $item->displayName() }}</div>
                            <div class="pipe-meta">
                                @if($item->category)<span class="pipe-tag">{{ $item->category->name }}</span>@endif
                                {{ $item->place() ?: 'Place not set' }}
                                @if($item->phone) &middot; {{ $item->phone }} @endif
                            </div>
                        </div>
                        <span class="pipe-stage">{{ $item->stageLabel() }}</span>
                    </div>

                    @if($item->deal)
                        {{-- What it came to. The whole point of handing a lead
                             over is this number, and it used to live two screens
                             away on the deal. --}}
                        <a href="{{ route('admin.deals', ['search' => $item->deal->name]) }}" class="pipe-worth">
                            <i class="fas fa-sack-dollar"></i>
                            <b>${{ number_format((float) $item->deal->value, 2) }}</b>
                            <span class="pipe-worth-state is-{{ strtolower($item->deal->status) }}">{{ $item->deal->status }}</span>
                            <span class="pipe-worth-go">Deal #{{ $item->deal->id }}</span>
                        </a>
                    @endif

                    <div class="pipe-actions">
                        <form action="{{ route('admin.transferred_leads.stage', $item->id) }}" method="POST"
                              style="margin: 0; display: flex; gap: 6px; align-items: center;" data-act="stage"
                              onsubmit="return askTheFigure(this);">
                            @csrf
                            <input type="hidden" name="value">
                            <input type="hidden" name="note">
                            <select name="stage" onchange="this.form.requestSubmit()">
                                <option value="">Move it to…</option>
                                @foreach(\App\Models\TransferredLead::STAGES as $key => $label)
                                    @continue(in_array($key, ['pool', 'claimed'], true) || $key === $item->stage)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </form>

                        <form action="{{ route('admin.transferred_leads.release', $item->id) }}" method="POST" style="margin: 0;" data-act="release">
                            @csrf
                            <button type="submit" class="pipe-btn ghost" title="Put it back for somebody else">Put back</button>
                        </form>

                        <span class="ld-spacer" style="flex: 1;"></span>
                        <span class="pipe-open-hint"><i class="fas fa-up-right-and-down-left-from-center"></i> open</span>
                    </div>
                </div>
            @empty
                <div class="pipe-empty">Nothing on your desk. Claim one from the pool.</div>
            @endforelse
        </div>
    </div>
</div>

{{-- ---------- the lead, opened ---------- --}}
<div class="ld-scrim" id="ldScrim" hidden></div>

<aside class="ld-panel" id="ldPanel" role="dialog" aria-modal="true" aria-labelledby="ldTitle" hidden>
    <div class="ld-head">
        <div>
            <h3 id="ldTitle">—</h3>
            <div class="ld-sub" id="ldSub"></div>
        </div>
        <button type="button" class="btn btn-ghost btn-icon" id="ldClose" title="Close"><i class="fas fa-xmark"></i></button>
    </div>

    <div class="ld-body" id="ldBody"></div>

    {{-- The same actions as the card, pressed through the card's own forms. --}}
    <div class="ld-foot" id="ldFoot"></div>
</aside>

<script>
/*
 * The board's cards, opened.
 *
 * Each card carries its lead as JSON, so opening one is a read from the page
 * rather than a request. Nothing here performs an action: the buttons at the
 * bottom submit the form that is already on the card, which is what keeps the
 * routes, the CSRF tokens and the permission checks exactly as they were.
 */
(function () {
    var panel = document.getElementById('ldPanel');
    var scrim = document.getElementById('ldScrim');
    if (!panel || !scrim) return;

    var titleEl = document.getElementById('ldTitle');
    var subEl   = document.getElementById('ldSub');
    var bodyEl  = document.getElementById('ldBody');
    var footEl  = document.getElementById('ldFoot');

    var openCard = null;
    var lastFocus = null;

    // The lead the panel was showing survives the reload that claiming or
    // moving a lead causes, so you come back to the lead you were working on.
    var REOPEN = 'crm.pipeOpenLead';

    function esc(value) {
        return String(value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function row(label, value, options) {
        options = options || {};
        var shown;

        if (value === null || value === undefined || value === '') {
            shown = '<dd class="is-empty">—</dd>';
        } else if (options.tel) {
            shown = '<dd><a href="tel:' + esc(String(value).replace(/[^0-9+]/g, '')) + '">' + esc(value) + '</a></dd>';
        } else if (options.mail) {
            shown = '<dd><a href="mailto:' + esc(value) + '">' + esc(value) + '</a></dd>';
        } else if (options.link) {
            shown = '<dd><a href="' + esc(value) + '" target="_blank" rel="noopener">' + esc(options.linkText || value) + '</a></dd>';
        } else {
            shown = '<dd>' + esc(value) + '</dd>';
        }

        return '<div class="ld-row"><dt>' + esc(label) + '</dt>' + shown + '</div>';
    }

    function group(heading, rows) {
        var filled = rows.join('');
        return '<div class="ld-group"><h4>' + esc(heading) + '</h4><dl class="ld-rows">' + filled + '</dl></div>';
    }

    function render(d) {
        titleEl.textContent = d.title || 'Lead';

        var bits = [];
        if (d.trade) bits.push(d.trade);
        if (d.place) bits.push(d.place);
        subEl.textContent = bits.join(' · ');

        var html = '';

        html += group('Who to call', [
            row('Contact', d.customer),
            row('Owner', d.owner),
            row('Phone', d.phone, { tel: true }),
            row('Other number', d.personalPhone, { tel: true }),
            row('Email', d.email, { mail: true }),
            row('Other email', d.personalEmail, { mail: true }),
        ]);

        html += group('The business', [
            row('Business', d.business),
            row('Trade', d.trade),
            row('Where', d.place),
            row('Address', d.address),
            row('Yelp', d.yelp, { link: true, linkText: 'Open on Yelp' }),
            row('Maps', d.google, { link: true, linkText: 'Open in Maps' }),
        ]);

        html += group('The handover', [
            row('From', d.agent),
            row('Handed over', d.handedOver),
            row('Waiting', d.waiting ? d.waiting + ' so far' : null),
            row('Call booked', d.scheduled),
            row('Picked up', d.claimed),
            row('With', d.closer),
        ]);

        var where = [
            row('Stage', d.stage),
            row('Status', d.status),
            row('Proposal', d.proposalSent ? 'Sent' : 'Not sent yet'),
            row('Converted', d.converted ? 'Yes' : 'Not yet'),
        ];
        if (d.dealId) {
            where.push(row('Deal', d.dealUrl, { link: true, linkText: 'Deal #' + d.dealId }));
        }
        html += group('Where it is', where);

        html += '<div class="ld-group"><h4>Call notes</h4>' +
                (d.notes ? '<div class="ld-note">' + esc(d.notes) + '</div>'
                         : '<div class="ld-row"><dd class="is-empty">Nothing written down yet.</dd></div>') +
                '</div>';

        bodyEl.innerHTML = html;
        bodyEl.scrollTop = 0;
    }

    /* The footer mirrors whatever the card itself offers. */
    function buildFoot(card, d) {
        footEl.innerHTML = '';

        var claim = card.querySelector('form[data-act="claim"]');
        if (claim) {
            var take = document.createElement('button');
            take.type = 'button';
            take.className = 'btn btn-success';
            take.innerHTML = '<i class="fas fa-hand"></i> Claim it';
            take.addEventListener('click', function () { remember(d.id); claim.requestSubmit(); });
            footEl.appendChild(take);
        }

        var stage = card.querySelector('form[data-act="stage"]');
        if (stage) {
            var source = stage.querySelector('select[name="stage"]');
            var move = document.createElement('select');
            move.className = 'control';
            move.style.width = 'auto';
            move.innerHTML = source ? source.innerHTML : '';
            move.addEventListener('change', function () {
                if (!move.value || !source) return;
                remember(d.id);
                source.value = move.value;
                stage.requestSubmit();
            });
            footEl.appendChild(move);
        }

        var spacer = document.createElement('span');
        spacer.className = 'ld-spacer';
        footEl.appendChild(spacer);

        if (d.phone) {
            var call = document.createElement('a');
            call.className = 'btn btn-primary';
            call.href = 'tel:' + String(d.phone).replace(/[^0-9+]/g, '');
            call.innerHTML = '<i class="fas fa-phone"></i> Call';
            footEl.appendChild(call);
        }

        var release = card.querySelector('form[data-act="release"]');
        if (release) {
            var back = document.createElement('button');
            back.type = 'button';
            back.className = 'btn btn-ghost';
            back.title = 'Put it back for somebody else';
            back.textContent = 'Put back';
            back.addEventListener('click', function () { release.requestSubmit(); });
            footEl.appendChild(back);
        }
    }

    function remember(id) {
        try { sessionStorage.setItem(REOPEN, String(id)); } catch (e) { /* private window */ }
    }

    function open(card) {
        var holder = card.querySelector('script.pipe-detail');
        if (!holder) return;

        var detail;
        try { detail = JSON.parse(holder.textContent); } catch (e) { return; }

        if (openCard) openCard.classList.remove('is-open');
        openCard = card;
        card.classList.add('is-open');

        render(detail);
        buildFoot(card, detail);

        lastFocus = document.activeElement;
        panel.hidden = false;
        scrim.hidden = false;
        // One frame, so the transition has a state to move from.
        requestAnimationFrame(function () {
            panel.classList.add('is-open');
            scrim.classList.add('is-open');
        });
        document.getElementById('ldClose').focus();
    }

    function close() {
        panel.classList.remove('is-open');
        scrim.classList.remove('is-open');
        if (openCard) { openCard.classList.remove('is-open'); openCard = null; }

        window.setTimeout(function () {
            if (!panel.classList.contains('is-open')) { panel.hidden = true; scrim.hidden = true; }
        }, 340);

        if (lastFocus && lastFocus.focus) lastFocus.focus();
    }

    document.addEventListener('click', function (e) {
        var card = e.target.closest('.pipe-item');
        if (!card) return;
        // The buttons and the dropdown on the card keep doing their own job.
        if (e.target.closest('form, button, select, a, input, label')) return;
        open(card);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !panel.hidden) { close(); return; }

        if ((e.key === 'Enter' || e.key === ' ') && document.activeElement &&
            document.activeElement.classList && document.activeElement.classList.contains('pipe-item')) {
            e.preventDefault();
            open(document.activeElement);
        }
    });

    document.getElementById('ldClose').addEventListener('click', close);
    scrim.addEventListener('click', close);

    // Coming back from a claim or a move: the lead you were on opens again.
    try {
        var wanted = sessionStorage.getItem(REOPEN);
        if (wanted) {
            sessionStorage.removeItem(REOPEN);
            var again = document.querySelector('.pipe-item[data-id="' + wanted + '"]');
            if (again) {
                again.scrollIntoView({ block: 'nearest' });
                open(again);
            }
        }
    } catch (e) { /* private window */ }
})();
</script>

<script>
/*
 * Winning and losing are the two moves worth stopping for.
 *
 * A win without its figure is a deal at zero that somebody has to remember to
 * go back to, and a loss without a reason teaches nobody anything. Every other
 * move goes straight through — this only steps in for those two.
 */
function askTheFigure(form) {
    var stage = form.querySelector('select[name="stage"]');
    var value = form.querySelector('input[name="value"]');
    var note = form.querySelector('input[name="note"]');

    if (!stage || !stage.value) return false;

    if (stage.value === 'won') {
        var said = window.prompt('Won — what is it worth? (US dollars)\n\nLeave it blank to fill the figure in on the deal later.', '');

        // Cancel means they did not mean to win it.
        if (said === null) { stage.value = ''; return false; }

        said = said.replace(/[^0-9.]/g, '');

        if (said !== '' && !(parseFloat(said) >= 0)) {
            alert('That is not an amount. Try something like 2500');
            stage.value = '';
            return false;
        }

        value.value = said;
    }

    if (stage.value === 'lost') {
        var why = window.prompt('Lost — why? This is what the agent will see.', '');

        if (why === null) { stage.value = ''; return false; }

        note.value = why;
    }

    return true;
}
</script>
