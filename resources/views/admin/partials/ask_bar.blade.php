{{--
    The bar along the bottom of every screen.

    One place to look for anything: a customer on the phone says "Gutter Co" and
    nobody should have to know first whether Gutter Co is a lead, a handover or
    a deal. It searches all of them at once, and only the ones this person is
    allowed to see — the endpoint decides that, not this page.

    The microphone is the browser's own dictation. No audio leaves the machine
    and nothing is recorded; it is the same engine the phone keyboard uses, so
    it simply types what was said into the box.
--}}
<div class="ask-wrap" id="askWrap">
    <div class="ask-results" id="askResults" hidden></div>

    <div class="ask-bar">
        <button type="button" class="ask-plus" id="askPlus" title="Create something" aria-label="Create something">
            <i class="fas fa-plus"></i>
        </button>

        <input type="text" id="askInput" class="ask-input" autocomplete="off" spellcheck="false"
               placeholder="Ask anything or search…"
               aria-label="Search the CRM" aria-expanded="false" aria-controls="askResults">

        <kbd class="ask-key" aria-hidden="true">/</kbd>

        <button type="button" class="ask-mic" id="askMic" title="Speak instead of typing" aria-label="Speak instead of typing">
            <i class="fas fa-microphone"></i>
        </button>

        <button type="button" class="ask-go" id="askGo" title="Search" aria-label="Search">
            <i class="fas fa-arrow-up"></i>
        </button>
    </div>
</div>

<div class="ask-menu" id="askMenu" hidden role="menu" aria-label="Create something">
    @if($me->canAccess('leads'))
        <a href="{{ route('admin.leads') }}" class="ask-menu-item" role="menuitem">
            <i class="fas fa-user-plus"></i> <span>Add a lead</span>
        </a>
    @endif
    @if($me->canAccess('transferred_leads'))
        <a href="{{ route('admin.transferred_leads') }}" class="ask-menu-item" role="menuitem">
            <i class="fas fa-arrow-right-arrow-left"></i> <span>Hand a lead over</span>
        </a>
    @endif
    @if($me->canAccess('deals'))
        <a href="{{ route('admin.deals') }}" class="ask-menu-item" role="menuitem">
            <i class="fas fa-file-signature"></i> <span>Add a deal</span>
        </a>
    @endif
    @if($me->canAccess('users'))
        <a href="{{ route('admin.users') }}" class="ask-menu-item" role="menuitem">
            <i class="fas fa-user-shield"></i> <span>Add a person</span>
        </a>
    @endif
    @if($me->canAccess('announcements') || $me->isAdmin())
        <button type="button" class="ask-menu-item" role="menuitem"
                onclick="document.getElementById('askMenu').hidden = true; document.getElementById('notifModal')?.classList.add('show');">
            <i class="fas fa-bullhorn"></i> <span>Send an announcement</span>
        </button>
    @endif
</div>

{{-- Inline rather than @push('styles'): this partial is included near the end
     of the body, long after the layout has already rendered its styles stack,
     so anything pushed from here would be thrown away. --}}
<style>
    /* ---- the bar itself -------------------------------------------------- */
    .ask-wrap {
        position: fixed; left: 50%; bottom: 26px; transform: translateX(-50%);
        z-index: 1300; width: min(680px, calc(100vw - 2rem));
        display: flex; flex-direction: column; gap: 10px;
    }

    /*
     * The glow.
     *
     * This was a blurred pseudo-element underneath at first, and on a near-black
     * page a 26%-opacity blurred orange simply could not be seen — it was there
     * in the stylesheet and invisible on the screen. Coloured box-shadows paint
     * on the element itself, so there is no stacking context, no blur budget and
     * nothing to wash them out, and they can be laid up until the bar genuinely
     * sits in its own light.
     */

    /*
     * This sits over the page rather than in it, so it has to look like it is
     * floating — otherwise it reads as something that has come loose and landed
     * on the table underneath. The ring in the brand colour and the deep shadow
     * are what lift it; the blur behind it is what stops the content showing
     * through and muddying the text.
     */
    .ask-bar {
        display: flex; align-items: center; gap: 10px;
        padding: 9px;
        background: var(--bg-raised);
        border: 1px solid var(--border-strong);
        border-radius: var(--radius-pill);
        box-shadow:
            0 0 0 1px rgba(249, 157, 78, 0.22),
            0 10px 30px -6px rgba(249, 157, 78, 0.40),
            0 18px 44px rgba(15, 23, 42, 0.20),
            0 4px 12px rgba(15, 23, 42, 0.10);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        transition: border-color var(--fast) var(--ease),
                    box-shadow var(--fast) var(--ease),
                    transform var(--fast) var(--ease);
    }
    .ask-bar:hover { transform: translateY(-1px); }

    .ask-bar:focus-within {
        border-color: var(--primary);
        box-shadow:
            0 0 0 4px var(--primary-soft),
            0 14px 42px -4px rgba(249, 157, 78, 0.55),
            0 22px 54px rgba(15, 23, 42, 0.24);
    }

    /* On the dark theme the page behind is nearly black, so the bar has to be
       lighter than its surroundings or it disappears into them. */
    html.dark-mode .ask-bar {
        background: #1a2234;
        border-color: rgba(249, 157, 78, 0.30);
        box-shadow:
            0 0 0 1px rgba(249, 157, 78, 0.22),
            0 12px 36px -6px rgba(249, 157, 78, 0.45),
            0 26px 60px -10px rgba(233, 59, 110, 0.28),
            0 20px 52px rgba(0, 0, 0, 0.7);
    }
    html.dark-mode .ask-bar:focus-within {
        background: #1e2740;
        border-color: var(--primary);
        box-shadow:
            0 0 0 4px rgba(249, 157, 78, 0.16),
            0 16px 48px -4px rgba(249, 157, 78, 0.70),
            0 30px 70px -10px rgba(233, 59, 110, 0.40),
            0 24px 60px rgba(0, 0, 0, 0.75);
    }

    /*
     * And it breathes, slowly, while nothing is happening.
     *
     * Eight seconds, and only a few percent of the glow — enough that the eye
     * registers the bar as live rather than painted on, not enough to pull
     * attention off the work. It stops the moment somebody is actually using
     * it, because then the bar has their attention already.
     */
    @keyframes askBreathe {
        0%, 100% { filter: saturate(1) brightness(1); }
        50% { filter: saturate(1.25) brightness(1.1); }
    }
    .ask-bar { animation: askBreathe 8s ease-in-out infinite; }
    .ask-bar:hover, .ask-bar:focus-within { animation: none; }

    html.reduce-motion .ask-bar { animation: none; }
    @media (prefers-reduced-motion: reduce) { .ask-bar { animation: none; } }

    .ask-input {
        flex: 1; min-width: 0;
        border: none !important; background: transparent !important;
        padding: 0 2px !important; height: 40px;
        font-size: 0.98rem; color: var(--text-main);
        box-shadow: none !important; outline: none !important;
    }
    .ask-input::placeholder { color: var(--text-muted); opacity: 0.85; }

    /* The shortcut, shown in the bar itself rather than left to be discovered.
       It goes as soon as there is anything to read in the box. */
    .ask-key {
        flex-shrink: 0; font-size: 0.68rem; font-weight: 700;
        color: var(--text-soft); background: var(--bg-subtle);
        border: 1px solid var(--border); border-radius: 6px;
        padding: 2px 7px; line-height: 1.5;
    }
    .ask-wrap.has-text .ask-key { display: none; }
    @media (max-width: 640px) { .ask-key { display: none; } }

    /* The round buttons at either end. */
    .ask-plus, .ask-go {
        flex-shrink: 0; width: 42px; height: 42px; border: none;
        border-radius: 50%; cursor: pointer; color: #fff;
        /*
         * The brand colour itself, lit from the top left, rather than the full
         * brand gradient. That gradient runs orange to pink across its whole
         * length — on a 38px circle you only ever see the pink end of it, so
         * the buttons came out maroon when the brand reads as orange.
         */
        background-color: var(--primary);
        background-image: linear-gradient(140deg, rgba(255, 255, 255, 0.26) 0%, rgba(255, 255, 255, 0) 55%, rgba(0, 0, 0, 0.1) 100%);
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 1rem;
        box-shadow: 0 4px 12px rgba(249, 157, 78, 0.35);
        transition: transform var(--fast) var(--ease), filter var(--fast) var(--ease);
    }
    .ask-plus:hover, .ask-go:hover { transform: scale(1.06); filter: brightness(1.05); }
    .ask-plus:active, .ask-go:active { transform: scale(0.96); }
    .ask-plus.is-open { transform: rotate(45deg); }

    .ask-mic {
        flex-shrink: 0; width: 38px; height: 38px; border: none; cursor: pointer;
        border-radius: 50%; background: transparent; color: var(--text-muted);
        display: inline-flex; align-items: center; justify-content: center;
        transition: background-color var(--fast) var(--ease), color var(--fast) var(--ease);
    }
    .ask-mic:hover { background: var(--bg-subtle); color: var(--text-main); }

    /* Listening: the one moment the bar is doing something to the room. */
    .ask-mic.is-listening { background: var(--danger); color: #fff; animation: askPulse 1.2s ease-in-out infinite; }
    @keyframes askPulse {
        0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.5); }
        50% { box-shadow: 0 0 0 7px rgba(239, 68, 68, 0); }
    }

    /* ---- what it found ---------------------------------------------------- */
    .ask-results {
        background: var(--bg-raised);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        box-shadow: 0 16px 44px rgba(15, 23, 42, 0.18);
        max-height: min(52vh, 420px); overflow-y: auto;
        padding: 0.45rem;
    }
    html.dark-mode .ask-results { box-shadow: 0 18px 50px rgba(0, 0, 0, 0.55); }

    .ask-group-label {
        font-size: 0.66rem; font-weight: 800; letter-spacing: 0.09em;
        text-transform: uppercase; color: var(--text-soft);
        padding: 0.6rem 0.7rem 0.3rem; display: flex; align-items: center; gap: 7px;
    }

    .ask-hit {
        display: flex; align-items: center; gap: 11px;
        padding: 0.55rem 0.7rem; border-radius: var(--radius-sm);
        text-decoration: none; color: var(--text-main); cursor: pointer;
        border: none; background: none; width: 100%; text-align: left;
        font-family: inherit; font-size: 0.875rem;
    }
    .ask-hit:hover, .ask-hit.is-picked { background: var(--primary-soft); }
    .ask-hit i { color: var(--primary); width: 16px; text-align: center; flex-shrink: 0; }
    .ask-hit .ask-hit-text { min-width: 0; }
    .ask-hit b { display: block; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ask-hit span {
        display: block; font-size: 0.76rem; color: var(--text-muted);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }

    .ask-note { padding: 1.1rem 0.8rem; text-align: center; font-size: 0.85rem; color: var(--text-muted); }

    /* ---- the create menu --------------------------------------------------- */
    .ask-menu {
        position: fixed; z-index: 1350;
        background: var(--bg-raised); border: 1px solid var(--border);
        border-radius: var(--radius); box-shadow: 0 16px 40px rgba(15, 23, 42, 0.2);
        padding: 0.4rem; min-width: 220px;
    }
    .ask-menu-item {
        display: flex; align-items: center; gap: 10px; width: 100%;
        padding: 0.55rem 0.7rem; border-radius: var(--radius-sm);
        text-decoration: none; color: var(--text-main); font-size: 0.86rem;
        border: none; background: none; cursor: pointer; font-family: inherit; text-align: left;
    }
    .ask-menu-item:hover { background: var(--primary-soft); color: var(--primary); }
    .ask-menu-item i { width: 16px; text-align: center; color: var(--text-muted); }
    .ask-menu-item:hover i { color: var(--primary); }

    /* ---- out of the way of the things already down there ------------------- */
    /* The assistant orb lives bottom-right and the notepad docks to the right
       edge; the bar is centred, so they only meet on a narrow screen. */
    @media (max-width: 760px) {
        .ask-wrap { bottom: calc(14px + env(safe-area-inset-bottom, 0px)); width: calc(100vw - 1.5rem); }
        .ask-input { font-size: 16px; }   /* or Safari zooms the page on focus */
    }

    /*
     * Room at the foot of every page.
     *
     * The bar is fixed, so without this the last card on a screen sits
     * underneath it — which is what made it look like something that had fallen
     * onto the table rather than a part of the furniture.
     */
    .main-content { padding-bottom: 7rem; }
    @media (max-width: 760px) {
        .main-content { padding-bottom: calc(6.5rem + env(safe-area-inset-bottom, 0px)); }
    }

    /* Printing a payslip or a report should not print a search box. */
    @media print { .ask-wrap, .ask-menu { display: none !important; } }

    html.reduce-motion .ask-mic.is-listening { animation: none; }
    @media (prefers-reduced-motion: reduce) { .ask-mic.is-listening { animation: none; } }
</style>

@push('scripts')
<script src="@versioned('js/ask-bar.js')" defer></script>
@endpush
