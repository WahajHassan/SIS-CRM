<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Who is looking. Per-user client-side preferences (sidebar order, card
         order, which quick guides have been seen) are keyed on this so two
         accounts sharing a browser do not inherit each other's layout. --}}
    <script>window.CRM_USER_ID = {{ (int) (auth()->id() ?? 0) }};</script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <title>Admin Dashboard | Systematic IT Solutions</title>
    <!-- Favicon -->
    @if(auth()->check() && auth()->user()->company && auth()->user()->company->favicon && file_exists(public_path(auth()->user()->company->favicon)))
        <link rel="icon" type="image/x-icon" href="{{ url('file/' . auth()->user()->company->favicon) }}">
    @else
        <link rel="icon" type="image/x-icon" href="{{ asset('img/favicon.png') }}">
    @endif
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        /* Display preferences, applied before the first paint so the page never
           flashes the wrong theme or text size on its way in. */
        (function () {
            try {
                var d = document.documentElement;
                if (localStorage.getItem('theme') === 'dark')      d.classList.add('dark-mode');
                if (localStorage.getItem('a11yMotion') === 'off')  d.classList.add('reduce-motion');
                if (localStorage.getItem('a11yText') === 'large')  d.classList.add('large-text');
            } catch (e) { /* storage blocked; defaults are fine */ }
        })();
    </script>
    <style>
        :root {
            @if(auth()->check() && auth()->user()->company && auth()->user()->company->theme_color)
                --primary: {{ auth()->user()->company->theme_color }};
                --primary-gradient: linear-gradient(135deg, {{ auth()->user()->company->theme_color }} 0%, #e93b6e 100%);
            @else
                --primary: #f99d4e;
                --primary-gradient: linear-gradient(135deg, #f99d4e 0%, #e93b6e 100%);
            @endif
            --secondary: #64748b;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --bg-body: #f8fafc;
            --bg-card: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --sidebar-width: 260px;
            --sidebar-rail: 76px;   /* the menu with its labels folded away */
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        /* The hidden attribute only means display:none in the browser's own
           stylesheet, so any component rule that sets display (flex, grid…)
           silently overrides it and the element stays on screen. The voice
           recording bar and the notepad editor were both always visible because
           of this. Make the attribute mean what it says. */
        [hidden] { display: none !important; }

        body { 
            font-family: 'Outfit', sans-serif; 
            background: var(--bg-body); 
            color: var(--text-main); 
            display: flex; 
            min-height: 100vh; 
            overflow-x: hidden; 
            transition: background 0.4s ease;
        }

        /* Sidebar Styling (Pulse UI) */
        .sidebar { 
            width: var(--sidebar-width); 
            background: var(--sidebar-bg); 
            border-right: 1px solid var(--border); 
            position: fixed; 
            height: 100vh; 
            display: flex; 
            flex-direction: column; 
            z-index: 100; 
            transition: all 0.3s ease; 
        }
        .logo-area { padding: 1.3rem 1.2rem; border-bottom: 1px solid var(--border); text-align: center; flex-shrink: 0; }
        .logo-area img { height: 38px; }

        .nav-links { padding: 0.9rem 0.7rem; flex-grow: 1; overflow-y: auto; }

        /* ===== Sidebar drag-to-reorder =====
         * Deliberately restrained: the sidebar's colours, spacing and type are
         * unchanged, and these rules only apply while a row is actually held. */

        /* Rows are draggable, so suppress the text/callout selection a press-and-
         * drag would otherwise produce. Links stay clickable. */
        .nav-links > .nav-item,
        .nav-links > .nav-item-group > .nav-item {
            -webkit-user-select: none;
            user-select: none;
            -webkit-touch-callout: none;
        }
        /* Touch: let a vertical swipe scroll the sidebar, and only the long-press
         * (Sortable's delay) start a drag. */
        .nav-links > .nav-item,
        .nav-links > .nav-item-group { touch-action: pan-y; }

        body.nav-sorting { cursor: grabbing; }
        body.nav-sorting .nav-links > .nav-item,
        body.nav-sorting .nav-links > .nav-item-group { cursor: grabbing; }

        /* The gap left behind: reads as "it will land here" rather than as a
         * ghost of the row, so the drop position is unambiguous. */
        .nav-links .nav-sort-ghost {
            position: relative;
            opacity: 1 !important;
            /* The slot must read as an empty space. Without this the active row's
             * gradient and glow keep painting while it is being dragged, and the
             * "drop here" outline arrives as a solid coloured bar instead. */
            background: transparent !important;
            box-shadow: none !important;
            transform: none !important;
        }
        .nav-links .nav-sort-ghost > * { visibility: hidden; }
        .nav-links .nav-sort-ghost::after {
            content: '';
            position: absolute;
            inset: 2px 0;
            border: 1px dashed var(--primary);
            border-radius: 14px;
            background: rgba(249, 157, 78, 0.06);
        }
        /* A dropdown's open sub-links would make that gap enormous, so collapse
         * the group to just its header height while it is the drop target. */
        .nav-links .nav-sort-ghost.nav-item-group .sub-nav-links { display: none !important; }

        /* The row travelling with the pointer.
         * NOT scoped under .nav-links: the clone is appended to <body>, so an
         * ancestor selector would silently miss it and leave it translucent with
         * the row underneath showing through. */
        .nav-sort-drag {
            opacity: 1 !important;
            will-change: transform;
            width: calc(var(--sidebar-width) - 2rem);
            box-shadow: 0 14px 34px rgba(0, 0, 0, 0.22);
            border-radius: 14px;
            background: var(--sidebar-bg, var(--bg-card));
            pointer-events: none;
        }
        /* A group travels as just its header — dragging an open dropdown around
         * with all of its children attached is unwieldy. */
        .nav-sort-drag.nav-item-group .sub-nav-links { display: none !important; }

        /* Chosen but not yet moved — a touch of lift so the grab registers. */
        .nav-links .nav-sort-chosen { transform: scale(1.015); }

        /* Someone who prefers less motion still gets the reordering, without the
         * shuffle animation. */
        @media (prefers-reduced-motion: reduce) {
            .nav-links .nav-sort-chosen { transform: none; }
        }

        /* ===== Overview metric cards: same interaction, same restraint =====
         * The card design itself is untouched; these rules only apply while a
         * card is actually being held. */
        .stat-card-grid > .card {
            -webkit-user-select: none;
            user-select: none;
            touch-action: pan-y;
        }
        body.stat-sorting { cursor: grabbing; }
        body.stat-sorting .stat-card-grid > .card { cursor: grabbing; }

        /* The space the card will drop into. The card's own surface is hidden so
         * the outline reads as an empty slot rather than as a second card. */
        .stat-card-grid .stat-sort-ghost {
            position: relative;
            opacity: 1 !important;
            background: transparent !important;
            border-color: transparent !important;
            box-shadow: none !important;
            transform: none !important;
        }
        .stat-card-grid .stat-sort-ghost > * { visibility: hidden; }
        .stat-card-grid .stat-sort-ghost::after {
            content: '';
            position: absolute;
            inset: 0;
            border: 1px dashed var(--primary);
            border-radius: 20px;
            background: rgba(249, 157, 78, 0.06);
        }

        /* The card travelling with the pointer. Not scoped under .stat-card-grid:
         * the clone is appended to <body>, so an ancestor selector would miss it
         * and leave it translucent with the card underneath showing through. */
        .stat-sort-drag {
            opacity: 1 !important;
            will-change: transform;
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.22) !important;
            pointer-events: none;
        }
        .stat-card-grid .stat-sort-chosen { transform: scale(1.02); }

        @media (prefers-reduced-motion: reduce) {
            .stat-card-grid .stat-sort-chosen { transform: none; }
        }

        /* ===== Quick Guide =====
         * Built from the dashboard's own tokens — card surface, border, radius,
         * accent — so it inherits the theme, dark mode included, rather than
         * introducing a second visual language. */
        .qg-root { position: fixed; inset: 0; z-index: 3000; pointer-events: none; }
        /* Deliberately no scroll lock on <body>: the guide has to be able to
         * scroll a step's element into view, and on narrow screens some header
         * controls sit off to the right. The spotlight tracks scrolling instead. */

        /* The spotlight is one element with an enormous spread shadow: the shadow
         * dims the whole page and the element's own box stays clear, which gives
         * a real cut-out that follows the target and animates cheaply. */
        .qg-spot {
            position: absolute;
            box-shadow: 0 0 0 9999px rgba(8, 12, 22, 0.62);
            border-radius: 16px;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.25s ease,
                        top 0.3s cubic-bezier(0.22, 1, 0.36, 1),
                        left 0.3s cubic-bezier(0.22, 1, 0.36, 1),
                        width 0.3s cubic-bezier(0.22, 1, 0.36, 1),
                        height 0.3s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .qg-root.qg-open .qg-spot { opacity: 1; }
        html.dark-mode .qg-spot { box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.72); }

        .qg-tip {
            position: absolute;
            background: var(--bg-card);
            color: var(--text-main);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1.1rem 1.2rem 1rem;
            box-shadow: 0 18px 44px rgba(0, 0, 0, 0.25);
            pointer-events: auto;
            opacity: 0;
            transform: translateY(6px);
            transition: opacity 0.22s ease, transform 0.22s ease,
                        top 0.3s cubic-bezier(0.22, 1, 0.36, 1),
                        left 0.3s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .qg-root.qg-open .qg-tip { opacity: 1; transform: none; }

        .qg-close {
            position: absolute; top: 8px; right: 10px;
            background: none; border: none; cursor: pointer;
            font-size: 1.25rem; line-height: 1; color: var(--text-muted);
        }
        .qg-close:hover { color: var(--text-main); }

        .qg-step-count {
            font-size: 0.72rem; font-weight: 700; letter-spacing: 0.04em;
            text-transform: uppercase; color: var(--primary); margin-bottom: 6px;
        }
        .qg-title { font-size: 1rem; font-weight: 700; margin-bottom: 6px; }
        .qg-text { font-size: 0.87rem; line-height: 1.55; color: var(--text-muted); }

        .qg-actions {
            display: flex; align-items: center; justify-content: space-between;
            gap: 10px; margin-top: 1rem;
        }
        .qg-nav { display: flex; gap: 8px; margin-left: auto; }
        .qg-skip, .qg-back, .qg-next {
            border-radius: 10px; font-size: 0.82rem; font-weight: 600;
            padding: 0.45rem 0.9rem; cursor: pointer; border: 1px solid transparent;
            font-family: inherit;
        }
        .qg-skip { background: none; color: var(--text-muted); }
        .qg-skip:hover { color: var(--text-main); }
        .qg-back { background: none; border-color: var(--border); color: var(--text-main); }
        .qg-back:hover { background: rgba(127, 127, 127, 0.08); }
        .qg-next { background: var(--primary-gradient); color: #fff; border: none; }
        .qg-next:hover { filter: brightness(1.05); }

        /* Header trigger, styled off the existing bell so it sits with it. */
        .qg-open-btn {
            background: none; border: none; cursor: pointer;
            color: var(--text-muted); font-size: 1.05rem;
            width: var(--control-h); height: var(--control-h); border-radius: var(--radius-sm);
            display: inline-flex; align-items: center; justify-content: center;
            transition: all 0.2s ease;
        }
        .qg-open-btn:hover { color: var(--primary); background: rgba(249, 157, 78, 0.1); }

        @media (max-width: 640px) {
            .qg-tip { padding: 1rem; }
            .qg-title { font-size: 0.95rem; }
        }
        @media (prefers-reduced-motion: reduce) {
            .qg-spot, .qg-tip { transition: opacity 0.15s ease; }
        }

        /* The stat grids are declared inline as repeat(4, 1fr), which below about
         * a laptop width squeezes the cards to unreadable slivers — and a card
         * that cannot be read cannot sensibly be dragged either. Wrapping them
         * instead leaves the desktop layout exactly as it was; !important is
         * needed only because the columns are an inline style. */
        @media (max-width: 1100px) {
            .stat-card-grid { grid-template-columns: repeat(2, 1fr) !important; }
        }
        @media (max-width: 640px) {
            .stat-card-grid { grid-template-columns: 1fr !important; }
        }
        /*
         * A sidebar row.
         *
         * The page somebody is on should be obvious at a glance without being
         * the loudest thing on screen: a tinted panel, the brand colour on the
         * text and icon, and a short bar down the left edge. A full gradient
         * slab drew the eye away from the content it was introducing.
         */
        .nav-item {
            position: relative;
            display: flex; align-items: center;
            padding: 0.62rem 0.85rem;
            color: var(--text-muted);
            text-decoration: none;
            border-radius: var(--radius-sm);
            margin-bottom: 0.18rem;
            font-weight: 500; font-size: 0.875rem;
            transition: background-color var(--fast) var(--ease), color var(--fast) var(--ease);
        }
        .nav-item i {
            margin-right: 11px; font-size: 0.95rem; width: 18px; text-align: center;
            color: var(--text-soft);
            transition: color var(--fast) var(--ease), transform var(--fast) var(--ease);
        }
        .nav-item:hover { background: var(--bg-subtle); color: var(--text-main); }
        .nav-item:hover i { color: var(--text-main); transform: translateX(1px); }

        .nav-item.active {
            background: var(--primary-soft);
            color: var(--primary);
            font-weight: 600;
        }
        .nav-item.active i { color: var(--primary); }
        .nav-item.active::before {
            content: '';
            position: absolute; left: 0; top: 50%; transform: translateY(-50%);
            width: 3px; height: 18px; border-radius: 0 3px 3px 0;
            background: var(--primary);
        }
        
        .status-badge.won { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .status-badge.lost { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .status-badge.pending { background: rgba(59, 130, 246, 0.1); color: var(--info); }

        html.dark-mode .nav-item:hover { background: rgba(255,255,255,0.04); }
        html.dark-mode .nav-item.active { background: var(--primary-soft); color: var(--primary); }

        html.dark-mode .page-title h1 { color: var(--text-main); }
        html.dark-mode .card-title { color: var(--text-main); }
        html.dark-mode table th { background: transparent; color: var(--text-muted); border-bottom: 1px solid var(--border); }
        html.dark-mode table tr:nth-child(even) { background: transparent; }
        html.dark-mode table tr:hover { background: rgba(255,255,255,0.02); }
        html.dark-mode input, html.dark-mode select, html.dark-mode textarea {
            background-color: #060913 !important;
            color: var(--text-main) !important;
            border-color: var(--border) !important;
        }
        html.dark-mode .btn-ghost { color: var(--text-muted); }
        html.dark-mode .btn-ghost:hover { background: rgba(255,255,255,0.05); color: var(--text-main); }
        html.dark-mode .badge { background: rgba(255,255,255,0.05) !important; }
        
        /*
         * A header control now, not a floating bubble. As a fixed button in the
         * bottom-right corner it overlapped whatever the page put there — the
         * chat composer's send button, and the Talk with Agent panel — which is
         * what it kept being reported for. Sitting in the header it is beside the
         * other controls and on top of nothing. Sized to match the 36px rush-hour
         * input and buttons next to it.
         */
        .theme-toggle {
            background: var(--bg-card);
            border: 1px solid var(--border);
            font-size: 1rem;
            cursor: pointer;
            color: var(--text-muted);
            transition: all 0.25s ease;
            width: var(--control-h);
            height: var(--control-h);
            border-radius: var(--radius-sm);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-sizing: border-box;
        }
        .theme-toggle:hover { color: var(--primary); border-color: var(--primary); background: rgba(249, 157, 78, 0.1); }
        /* Notepad trigger carries its label, so it is wider than the icon-only
           controls beside it but keeps their height and border. */
        .theme-toggle.np-header-btn {
            width: auto; padding: 0 0.75rem; gap: 7px;
            font-family: inherit; font-size: 0.82rem; font-weight: 600;
            color: var(--text-main); white-space: nowrap;
        }
        .theme-toggle.np-header-btn i { color: var(--primary); }
        .theme-toggle[aria-expanded="true"] { color: var(--primary); border-color: var(--primary); background: rgba(249, 157, 78, 0.1); }

        /* ===== Accessibility menu =====
         * Reuses .notif-dropdown for the panel itself so it matches the
         * notification and profile menus already in this header. Type is the
         * dashboard's own Outfit throughout — deliberately not a second family. */
        .a11y-menu {
            top: 48px; right: auto; left: 0;
            width: 320px;
            border-radius: 18px;
        }
        .a11y-menu .notif-header { padding: 1rem 1.15rem; }
        .a11y-menu .notif-header strong { font-size: 1rem; letter-spacing: -0.01em; }

        .a11y-row {
            width: 100%;
            display: flex; align-items: center; gap: 14px;
            padding: 1rem 1.15rem;
            background: none; border: none; border-bottom: 1px solid var(--border);
            cursor: pointer; text-align: left;
            font-family: inherit; color: var(--text-main);
            transition: background 0.2s ease;
        }
        .a11y-row:last-child { border-bottom: none; }
        .a11y-row:hover { background: rgba(0, 0, 0, 0.03); }
        html.dark-mode .a11y-row:hover { background: rgba(255, 255, 255, 0.04); }

        .a11y-ico {
            width: 40px; height: 40px; border-radius: 12px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            background: rgba(249, 157, 78, 0.12); color: var(--primary); font-size: 1.05rem;
        }
        .a11y-label {
            display: flex; flex-direction: column; gap: 3px;
            font-size: 0.95rem; font-weight: 600; line-height: 1.25;
        }
        .a11y-label small {
            font-size: 0.8rem; font-weight: 400; color: var(--text-muted); line-height: 1.35;
        }

        /* A real switch rather than a word, so the control looks like the thing it
         * is. aria-checked on the row is what actually conveys state to a screen
         * reader; this is the visual half. */
        .a11y-switch {
            margin-left: auto; flex-shrink: 0;
            width: 46px; height: 26px; border-radius: 999px;
            background: rgba(127, 127, 127, 0.28);
            position: relative;
            transition: background 0.25s ease;
        }
        .a11y-switch::after {
            content: '';
            position: absolute; top: 3px; left: 3px;
            width: 20px; height: 20px; border-radius: 50%;
            background: #fff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
            transition: transform 0.25s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .a11y-row.is-on .a11y-switch { background: var(--primary-gradient); }
        .a11y-row.is-on .a11y-switch::after { transform: translateX(20px); }

        /* ===== Notepad =====
         * A window over the dashboard: dragged by its bar, resized from the
         * corner, minimised to a tab on the right edge. Built from the same
         * card surface, border and radius as everything else. */
        .np-window {
            position: fixed;
            z-index: 2600;              /* over the page, under modals (3000+) */
            display: none;
            flex-direction: column;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            /* Depth plus a faint accent halo, so the notepad reads as its own
             * surface sitting over the dashboard rather than part of the page. */
            box-shadow:
                0 24px 60px rgba(0, 0, 0, 0.22),
                0 0 0 1px rgba(249, 157, 78, 0.22),
                0 0 38px rgba(249, 157, 78, 0.22);
            overflow: hidden;
            font-family: 'Outfit', sans-serif;
        }
        html.dark-mode .np-window {
            box-shadow:
                0 24px 60px rgba(0, 0, 0, 0.55),
                0 0 0 1px rgba(249, 157, 78, 0.28),
                0 0 44px rgba(249, 157, 78, 0.18);
        }
        /* A faint warm wash at the top of the editor ties the glow to the inside. */
        .np-pane { background: linear-gradient(180deg, rgba(249, 157, 78, 0.05) 0%, transparent 140px); }
        .np-window.np-open { display: flex; }
        /* No transition while dragging, or the window lags behind the pointer; and
           its own layer, so moving it does not repaint the large glow each frame. */
        .np-window.np-active { transition: none !important; will-change: transform; }

        .np-bar {
            display: flex; align-items: center; justify-content: space-between;
            gap: 10px; padding: 0.7rem 0.9rem;
            border-bottom: 1px solid var(--border);
            cursor: grab; user-select: none; flex-shrink: 0;
            background: rgba(127, 127, 127, 0.04);
        }
        .np-window.np-active .np-bar { cursor: grabbing; }
        .np-bar-title { font-weight: 700; font-size: 0.92rem; color: var(--text-main); }
        .np-bar-title i { color: var(--primary); margin-right: 7px; }
        .np-bar-actions { display: flex; gap: 2px; }

        .np-btn {
            width: 30px; height: 30px; border-radius: 8px;
            background: none; border: none; cursor: pointer;
            color: var(--text-muted); font-size: 0.95rem; line-height: 1;
            display: inline-flex; align-items: center; justify-content: center;
            transition: color 0.2s ease, background 0.2s ease;
        }
        .np-btn:hover { color: var(--primary); background: rgba(249, 157, 78, 0.12); }
        .np-close:hover { color: var(--danger); background: rgba(239, 68, 68, 0.12); }

        .np-body-wrap { display: flex; flex: 1; min-height: 0; }

        .np-list {
            width: 148px; flex-shrink: 0; overflow-y: auto;
            border-right: 1px solid var(--border); padding: 0.5rem;
        }
        .np-item {
            width: 100%; display: flex; align-items: center; gap: 6px;
            padding: 0.5rem 0.55rem; margin-bottom: 4px;
            border: none; border-radius: 9px; background: none; cursor: pointer;
            font-family: inherit; font-size: 0.82rem; color: var(--text-muted);
            text-align: left; transition: background 0.2s ease, color 0.2s ease;
        }
        .np-item:hover { background: rgba(127, 127, 127, 0.1); color: var(--text-main); }
        .np-item.is-active { background: var(--primary-gradient); color: #fff; font-weight: 600; }
        .np-item-title { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .np-item-del { opacity: 0; font-size: 1rem; line-height: 1; padding: 0 2px; }
        .np-item:hover .np-item-del { opacity: 0.6; }
        .np-item-del:hover { opacity: 1 !important; }
        .np-empty { padding: 0.6rem; font-size: 0.78rem; color: var(--text-muted); }

        .np-pane { flex: 1; min-width: 0; display: flex; flex-direction: column; }
        .np-blank { margin: auto; font-size: 0.85rem; color: var(--text-muted); padding: 1rem; text-align: center; }
        .np-editor { display: flex; flex-direction: column; flex: 1; min-height: 0; }

        .np-title-input {
            border: none; border-bottom: 1px solid var(--border); background: none;
            padding: 0.7rem 0.9rem; font-family: inherit; font-size: 0.95rem;
            font-weight: 600; color: var(--text-main); outline: none;
        }
        .np-body {
            flex: 1; min-height: 0; resize: none; border: none; background: none;
            padding: 0.8rem 0.9rem; font-family: inherit; font-size: 0.88rem;
            line-height: 1.6; color: var(--text-main); outline: none;
        }
        .np-title-input::placeholder, .np-body::placeholder { color: var(--text-muted); }

        .np-foot {
            flex-shrink: 0; padding: 0.35rem 0.9rem; min-height: 26px;
            border-top: 1px solid var(--border);
            font-size: 0.72rem; color: var(--text-muted);
        }

        .np-resize {
            position: absolute; right: 0; bottom: 0; width: 18px; height: 18px;
            cursor: nwse-resize;
            background: linear-gradient(135deg, transparent 50%, var(--border) 50%, var(--border) 62%, transparent 62%, transparent 74%, var(--border) 74%, var(--border) 86%, transparent 86%);
        }

        .np-bar-sep { width: 1px; height: 18px; background: var(--border); margin: 0 4px; align-self: center; }
        .np-list-toggle[aria-pressed="true"] { color: var(--primary); background: rgba(249, 157, 78, 0.12); }

        /* List hidden: the editor takes the whole window. */
        .np-window.np-list-hidden .np-list { display: none; }

        /* ---- Docked to the right edge, the mirror of the left sidebar ---- */
        .np-dock-resize { display: none; }
        .np-window.np-docked.np-open:not(.np-minimized) {
            top: 0 !important; right: 0 !important; bottom: 0 !important;
            left: auto !important;
            width: var(--np-dock-width, 360px) !important;
            height: 100vh !important;
            border-radius: 0;
            border-top: none; border-right: none; border-bottom: none;
            box-shadow:
                -12px 0 40px rgba(0, 0, 0, 0.10),
                inset 1px 0 0 rgba(249, 157, 78, 0.28);
            z-index: 1400;               /* level with the left sidebar, under dropdowns */
        }
        .np-window.np-docked .np-bar { cursor: default; padding: 1.1rem 0.9rem; }
        .np-window.np-docked .np-resize { display: none; }
        .np-window.np-docked.np-open:not(.np-minimized) .np-dock-resize {
            display: block;
            position: absolute; left: 0; top: 0; bottom: 0; width: 6px;
            cursor: ew-resize; z-index: 2;
        }
        .np-window.np-docked .np-dock-resize:hover { background: rgba(249, 157, 78, 0.35); }

        /* The page narrows to fit beside the docked notepad instead of sliding
           under it — same mechanism the left sidebar uses with margin-left. */
        /* The assistant orb is pinned bottom-right, which is now inside the docked
           notepad; move it to the page's new right edge so it covers nothing. */
        body.np-docked-layout .ai-orb {
            right: calc(var(--np-dock-width, 360px) + 24px) !important;
            transition: right 0.3s ease, transform 0.25s ease;
        }
        /* With the notepad docked the header has ~360px less room. Its controls do
           not shrink, so without wrapping the right-hand end — help, notifications
           and the profile menu with Log out — slid underneath the panel and could
           not be clicked. Letting the group wrap keeps every control reachable. */
        body.np-docked-layout .header { flex-wrap: wrap; row-gap: 14px; }
        body.np-docked-layout .header > div:last-child {
            flex-wrap: wrap; justify-content: flex-end; row-gap: 10px;
            margin-left: auto; min-width: 0;
        }
        body.np-docked-layout .main-content {
            margin-right: var(--np-dock-width, 360px);
            /* Wide tables (Sales Team, Users, Deals) do not shrink, so in the
               narrowed page their last columns — including the edit and delete
               actions — ran on underneath the docked panel with no way to reach
               them. Scrolling horizontally inside the page keeps them reachable.
               Trade-off: while docked, the Leads page's sticky filter card scrolls
               with the page instead of sticking. */
            overflow-x: auto;
            transition: margin-left 0.3s ease, margin-right 0.3s ease;
        }

        /* Minimised: a tab parked against the right edge, faded until pointed at. */
        .np-tab { display: none; }
        .np-window.np-minimized {
            left: auto !important; right: 0 !important;
            top: 50% !important; transform: translateY(-50%);
            width: auto !important; height: auto !important;
            border-radius: 12px 0 0 12px;
            border-right: none;
            opacity: 0.45;
            transition: opacity 0.25s ease;
            overflow: visible;
        }
        .np-window.np-minimized:hover { opacity: 1; }
        .np-window.np-minimized > .np-bar,
        .np-window.np-minimized > .np-body-wrap,
        .np-window.np-minimized > .np-foot,
        .np-window.np-minimized > .np-resize { display: none; }
        .np-window.np-minimized .np-tab {
            display: flex; align-items: center; gap: 8px;
            padding: 0.7rem 0.9rem; cursor: pointer;
            font-size: 0.85rem; font-weight: 600; color: var(--text-main);
            writing-mode: vertical-rl;
        }
        .np-window.np-minimized .np-tab i { color: var(--primary); }

        @media (max-width: 640px) {
            body.np-docked-layout .main-content { margin-right: 0; }
            .np-window.np-docked.np-open:not(.np-minimized) { width: calc(100vw - 16px) !important; }
            /* Too little room to float on a phone: fill the screen instead. */
            .np-window.np-open:not(.np-minimized) {
                left: 8px !important; top: 8px !important;
                width: calc(100vw - 16px) !important;
                height: calc(100vh - 16px) !important;
            }
            .np-list { width: 116px; }
            .np-resize { display: none; }
        }

        /* --- what the preferences actually do --- */

        /* Reduce motion: kill transitions and animations everywhere, including
         * the drag reordering and the quick guide's spotlight. */
        html.reduce-motion *,
        html.reduce-motion *::before,
        html.reduce-motion *::after {
            animation-duration: 0.001ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.001ms !important;
            scroll-behavior: auto !important;
        }

        /* Larger text: everything sized in rem scales from the root. */
        html.large-text { font-size: 118%; }


        /*
         * AI orb — a small, always-on ambient presence in the corner of every page,
         * built the same way the login mascot's eyes were: pure CSS animation, not a
         * looping video clip, so it never stutters and costs nothing to keep running.
         * Sits directly above the theme toggle, the one spot on the page guaranteed
         * free of real content at every screen size.
         *
         * The colour motion is a genuinely rotating conic-gradient layer, spun with
         * `transform: rotate()` rather than animated `background-position`. The
         * earlier version scaled the gradient with background-size and panned it —
         * a trick that works for linear/tiling gradients, but a conic-gradient isn't
         * a repeating pattern, so panning it left the sweep looking like two flat
         * colour halves with a hard seam instead of a smooth blend. Rotating the
         * whole layer (oversized so its corners never show a gap) is the standard
         * way to animate a conic-gradient and loops perfectly, with no jump at the
         * seam.
         *
         * A blurred colour bloom sits behind and beyond the shape (what reads as
         * "the thing is emitting light"), and a glossy specular highlight sits
         * inside it — the same trick that makes a flat CSS circle look like a lit
         * sphere instead of a painted disc. The shape's corner radii wobble gently
         * too, kept close to circular so it reads as a breathing liquid surface
         * rather than a lopsided blob.
         */
        .ai-orb-wrap { position: relative; cursor: pointer; }

        .ai-orb-glow {
            position: absolute;
            inset: -55%;
            z-index: 0;
            border-radius: 50%;
            background: radial-gradient(circle,
                rgba(139,92,246,0.65) 0%,
                rgba(59,130,246,0.45) 32%,
                rgba(236,72,153,0.3) 55%,
                transparent 74%);
            filter: blur(14px);
            animation: aiOrbPulse 1.7s ease-in-out infinite;
            /* Own layer: the blur is painted once and then only scaled/faded. */
            will-change: transform, opacity;
            pointer-events: none;
        }

        .ai-orb-shape {
            position: relative;
            z-index: 1;
            width: 100%; height: 100%;
            overflow: hidden;
            box-shadow:
                inset -6px -8px 16px rgba(0,0,0,0.22),
                inset 5px 6px 14px rgba(255,255,255,0.5);
            /* A gentle breathing scale instead of morphing border-radius: a changing
               radius has to be repainted every frame, a transform does not. The
               spinning colours inside still give it plenty of movement. */
            border-radius: 50%;
            animation: aiOrbBreathe 5s ease-in-out infinite;
            will-change: transform;
        }
        .ai-orb-spin {
            /* oversized (140% in every direction) and centred, so rotating it never
               exposes a corner of flat background behind the visible circle */
            position: absolute;
            top: -20%; left: -20%; right: -20%; bottom: -20%;
            background: conic-gradient(from 0deg at 50% 50%, #7c3aed, #3b82f6, #06b6d4, #ec4899, #f97316, #7c3aed);
            animation: aiOrbSpin 6s linear infinite;
            will-change: transform;
        }
        .ai-orb-shape::before {
            /* the glossy specular highlight — a bright spot near one edge, exactly
               like the light-source reflection on a physical glossy sphere */
            content: "";
            position: absolute;
            z-index: 2;
            top: 10%; left: 16%;
            width: 46%; height: 40%;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,0.95), rgba(255,255,255,0) 72%);
            filter: blur(1px);
            animation: aiOrbHighlight 4s ease-in-out infinite alternate;
            will-change: transform;
        }

        /* The larger orb inside the Talk with Agent panel is rendered even while
           the panel is closed (it is only slid off screen), so its animations kept
           running for nothing. Paused until the panel opens. */
        .ai-panel:not(.open) .ai-orb-hero-wrap,
        .ai-panel:not(.open) .ai-orb-hero-wrap *,
        .ai-panel:not(.open) .ai-orb-hero-wrap *::before { animation-play-state: paused !important; }

        .ai-orb {
            position: fixed !important;
            bottom: 24px !important;   /* was 88px, clearing the theme button that used to float here */
            right: 24px !important;
            width: 56px;
            height: 56px;
            z-index: 1490;
            transition: transform 0.25s ease;
        }
        .ai-orb:hover { transform: scale(1.12); }

        .ai-orb-hero-wrap { width: 172px; height: 172px; margin: 0 auto; }

        @keyframes aiOrbPulse {
            0%, 100% { opacity: 0.6; transform: scale(0.92); }
            50%      { opacity: 1;   transform: scale(1.14); }
        }
        @keyframes aiOrbSpin {
            to { transform: rotate(360deg); }
        }
        @keyframes aiOrbBreathe {
            0%, 100% { transform: scale(1); }
            50%      { transform: scale(1.05); }
        }
        @keyframes aiOrbHighlight {
            0%   { transform: translate(0, 0) scale(1); }
            100% { transform: translate(10%, 8%) scale(1.2); }
        }
        @media (prefers-reduced-motion: reduce) {
            .ai-orb-shape, .ai-orb-shape::before, .ai-orb-spin, .ai-orb-glow {
                animation: none; border-radius: 50%;
            }
        }

        /* ============ Talk with Agent side panel ============ */
        .ai-panel-overlay {
            position: fixed; inset: 0; z-index: 1495;
            background: rgba(8,12,22,0.45);
            opacity: 0; pointer-events: none;
            transition: opacity 0.3s ease;
        }
        /* The blur is applied only while the panel is open. The overlay covers the
           whole window at opacity 0 the rest of the time, and a backdrop-filter
           there made the browser re-blur the entire page on every frame. */
        .ai-panel-overlay.open { opacity: 1; pointer-events: auto; backdrop-filter: blur(2px); }

        .ai-panel {
            position: fixed; top: 0; right: 0; bottom: 0;
            width: min(400px, 92vw);
            z-index: 1496;
            background: var(--bg-card);
            border-left: 1px solid var(--border);
            box-shadow: -20px 0 50px rgba(0,0,0,0.25);
            display: flex; flex-direction: column;
            transform: translateX(100%);
            transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .ai-panel.open { transform: translateX(0); }

        .ai-panel-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 1.2rem 1.4rem;
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }
        .ai-panel-header span { font-weight: 700; font-size: 1.02rem; color: var(--text-main); }
        .ai-panel-header button {
            background: none; border: none; cursor: pointer; font-size: 1.2rem;
            color: var(--text-muted); line-height: 1;
            width: 30px; height: 30px; border-radius: 8px;
            display: inline-flex; align-items: center; justify-content: center;
            transition: color 0.2s ease, background 0.2s ease;
        }
        .ai-panel-header button:hover { color: var(--primary); background: rgba(249, 157, 78, 0.1); }
        #aiPanelGuide { font-size: 1rem; }

        .ai-panel-stage {
            flex-grow: 1;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 1.2rem;
            padding: 1.5rem;
        }
        .ai-panel-stage p { color: var(--text-muted); font-size: 0.85rem; text-align: center; }

        .ai-panel-chat {
            flex-shrink: 0;
            border-top: 1px solid var(--border);
            padding: 1rem 1.2rem;
        }
        .ai-chat-messages {
            max-height: 160px; overflow-y: auto;
            margin-bottom: 0.7rem;
            display: flex; flex-direction: column; gap: 6px;
        }
        .ai-chat-bubble {
            align-self: flex-end;
            background: var(--primary-gradient);
            color: #fff;
            padding: 0.5rem 0.85rem;
            border-radius: 14px 14px 3px 14px;
            font-size: 0.85rem;
            max-width: 80%;
            word-break: break-word;
        }
        /*
         * Once the first message is sent the panel stops being a splash screen and
         * becomes a thread: the orb and its "Listening…" prompt have done their job,
         * so they give their space to the messages instead of pinning them into a
         * 160px strip at the bottom. JS adds .has-messages on the first send.
         */
        .ai-panel.has-messages .ai-panel-stage { display: none; }
        .ai-panel.has-messages .ai-panel-chat {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;          /* lets the message list scroll inside the flex column */
            border-top: none;
        }
        .ai-panel.has-messages .ai-chat-messages {
            flex-grow: 1;
            max-height: none;
            justify-content: flex-start;
            padding-right: 2px;
        }

        .ai-panel-chat-row { display: flex; gap: 8px; }
        .ai-panel-chat-row input {
            flex: 1; padding: 0.65rem 0.9rem; border-radius: 12px;
            border: 1px solid var(--border); background: var(--bg-body); color: var(--text-main);
            font-size: 0.88rem; outline: none;
        }
        .ai-panel-chat-row input:focus { border-color: var(--primary); }
        .ai-panel-chat-row button {
            width: 42px; height: 42px; border-radius: 12px; border: none;
            background: var(--primary-gradient); color: #fff; cursor: pointer;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        html.dark-mode .theme-toggle:hover { background: #1a2235; }

        html.dark-mode .logo-for-light { display: none !important; }
        html.dark-mode .logo-for-dark { display: inline-block !important; }
        html:not(.dark-mode) .logo-for-light { display: inline-block !important; }
        html:not(.dark-mode) .logo-for-dark { display: none !important; }

        /* Fix native calendar icon in dark mode */
        html.dark-mode input[type="date"]::-webkit-calendar-picker-indicator,
        html.dark-mode input[type="month"]::-webkit-calendar-picker-indicator,
        html.dark-mode input[type="time"]::-webkit-calendar-picker-indicator,
        html.dark-mode input[type="datetime-local"]::-webkit-calendar-picker-indicator {
            filter: invert(1) brightness(1.5);
            cursor: pointer;
        }

        /* Main Content */
        .main-content { margin-left: var(--sidebar-width); flex-grow: 1; padding: 2.5rem; transition: margin-left 0.3s ease; min-width: 0; }
        .header { display: flex; justify-content: space-between; align-items: center; gap: 1.25rem; margin-bottom: 1.9rem; }
        /* A long screen name shortens rather than wrapping and pushing the row
           of controls into two lines. */
        .header .page-title { min-width: 0; }
        .header .page-title h1 {
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            font-size: 1.4rem; letter-spacing: -0.02em;
        }
        @media (max-width: 1500px) { .header .page-title h1 { font-size: 1.2rem; } }
        .page-title h1 { font-size: 1.8rem; font-weight: 700; color: var(--text-main); letter-spacing: -0.5px; }

        /* Global UI Elements (Pulse UI Cards) */
        /* =====================================================================
         * The design system.
         *
         * Everything in the CRM is built from the same handful of tokens, so a
         * change here is a change everywhere — which is the point. Screens set
         * their own layout; they do not set their own colours, radii, shadows or
         * type sizes.
         *
         * Light and dark are written separately rather than one being an
         * inversion of the other: an inverted shadow is a glow, and inverted
         * borders are either invisible or shouting.
         * ===================================================================== */
        :root {
            /* Surfaces, from the page upwards */
            --bg-body: #F5F7FA;
            --bg-card: #FFFFFF;
            --bg-subtle: #F1F4F9;          /* inputs, quiet fills, hovered rows */
            --bg-raised: #FFFFFF;          /* things that sit above a card */
            --sidebar-bg: #FFFFFF;

            /* Ink */
            --text-main: #0F172A;
            --text-muted: #64748B;
            --text-soft: #94A3B8;

            /* Lines */
            --border: #E6EAF1;
            --border-strong: #D4DCE7;

            /* The shape of things */
            --radius-xs: 8px;
            --radius-sm: 10px;
            --radius: 14px;
            --radius-lg: 18px;
            --radius-pill: 999px;

            /* Depth. Four steps, and no more. */
            --shadow-xs: 0 1px 2px rgba(15, 23, 42, 0.04);
            --shadow-sm: 0 1px 3px rgba(15, 23, 42, 0.05), 0 1px 2px rgba(15, 23, 42, 0.03);
            --shadow-md: 0 6px 16px -4px rgba(15, 23, 42, 0.08), 0 2px 6px -2px rgba(15, 23, 42, 0.04);
            --shadow-lg: 0 20px 40px -12px rgba(15, 23, 42, 0.18);

            /* Motion. Fast enough not to be waited on. */
            --ease: cubic-bezier(0.22, 1, 0.36, 1);
            --fast: 0.14s;
            --base: 0.22s;
            --slow: 0.34s;

            /* Every control in the header is this tall, so the row reads as one
               bar rather than as eight things that each chose their own size. */
            --control-h: 34px;

            --ring: color-mix(in srgb, var(--primary) 28%, transparent);
            --primary-soft: color-mix(in srgb, var(--primary) 12%, transparent);
        }

        html.dark-mode {
            --bg-body: #0A0E17;
            --bg-card: #121827;
            --bg-subtle: #1A2234;
            --bg-raised: #171E2F;
            --sidebar-bg: #0D1220;

            --text-main: #F1F5F9;
            --text-muted: #94A3B8;
            --text-soft: #64748B;

            --border: rgba(148, 163, 184, 0.14);
            --border-strong: rgba(148, 163, 184, 0.24);

            /* In the dark, depth comes from a darker shadow and a lighter edge
               rather than from a blur nobody can see. */
            --shadow-xs: 0 1px 2px rgba(0, 0, 0, 0.3);
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.35);
            --shadow-md: 0 8px 20px -6px rgba(0, 0, 0, 0.5);
            --shadow-lg: 0 24px 48px -12px rgba(0, 0, 0, 0.65);
        }

        /* The theme change itself is animated, but only the few properties that
           should cross-fade — animating "all" on every element is what makes a
           theme switch feel slow. */
        body, .card, .sidebar, .nav-item, table, th, td,
        input, select, textarea, .btn, .badge {
            transition: background-color var(--base) var(--ease),
                        border-color var(--base) var(--ease),
                        color var(--base) var(--ease);
        }

        /* Anybody who has asked for less movement gets less movement. */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
        html.reduce-motion *, html.reduce-motion *::before, html.reduce-motion *::after {
            animation-duration: 0.01ms !important;
            transition-duration: 0.01ms !important;
        }

        /* ---- type ---------------------------------------------------------- */
        body { font-size: 0.925rem; line-height: 1.55; -webkit-font-smoothing: antialiased; }
        h1, h2, h3, h4 { letter-spacing: -0.015em; color: var(--text-main); }

        /* ---- the keyboard gets the same treatment as the mouse ------------- */
        a:focus-visible, button:focus-visible, input:focus-visible,
        select:focus-visible, textarea:focus-visible, [tabindex]:focus-visible {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
            border-radius: var(--radius-xs);
        }

        /* ---- cards --------------------------------------------------------- */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.4rem;
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.25rem;
            transition: border-color var(--base) var(--ease),
                        box-shadow var(--base) var(--ease),
                        transform var(--base) var(--ease);
        }
        html.dark-mode .card { background: var(--bg-card); border-color: var(--border); box-shadow: var(--shadow-sm); }

        /* A card lifts a little under the pointer. A dialog does not: the pointer
           is over a dialog the whole time it is being used, and a box that rises
           and settles on every crossing reads as a glitch. */
        .card:hover { box-shadow: var(--shadow-md); border-color: var(--border-strong); }
        html.dark-mode .card:hover { box-shadow: var(--shadow-md); border-color: var(--border-strong); }
        [style*="position: fixed"] > .card,
        [style*="position:fixed"] > .card { box-shadow: var(--shadow-lg); }
        [style*="position: fixed"] > .card:hover,
        [style*="position:fixed"] > .card:hover { transform: none; box-shadow: var(--shadow-lg); }

        .card-header {
            display: flex; justify-content: space-between; align-items: center;
            gap: 12px; margin-bottom: 1.1rem; padding-bottom: 0.9rem;
            border-bottom: 1px solid var(--border);
        }
        .card-title {
            font-size: 1rem; font-weight: 700; color: var(--text-main);
            letter-spacing: -0.01em; display: flex; align-items: center; gap: 9px;
        }

        /* ---- buttons ------------------------------------------------------- */
        .btn {
            padding: 0.58rem 1.05rem;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 0.86rem;
            font-family: inherit;
            cursor: pointer;
            border: 1px solid transparent;
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            text-decoration: none; white-space: nowrap;
            transition: background-color var(--fast) var(--ease), border-color var(--fast) var(--ease),
                        box-shadow var(--fast) var(--ease), transform var(--fast) var(--ease),
                        color var(--fast) var(--ease);
        }
        .btn:active { transform: translateY(1px); }

        .btn-primary { background: var(--primary-gradient); color: #fff; box-shadow: var(--shadow-sm); }
        .btn-primary:hover { box-shadow: var(--shadow-md); filter: brightness(1.04); }

        .btn-ghost { background: var(--bg-card); color: var(--text-muted); border-color: var(--border); }
        .btn-ghost:hover { background: var(--bg-subtle); color: var(--text-main); border-color: var(--border-strong); }

        /* Toolbar-sized. The header is a row of actions, not a row of banners. */
        .btn-compact { height: 34px; padding: 0 0.8rem; font-size: 0.82rem; border-radius: var(--radius-sm); }

        /* ---- badges -------------------------------------------------------- */
        .badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 0.24rem 0.6rem;
            border-radius: var(--radius-pill);
            font-size: 0.7rem; font-weight: 700;
            letter-spacing: 0.03em; text-transform: uppercase; white-space: nowrap;
            background: var(--bg-subtle); color: var(--text-muted);
            border: 1px solid transparent;
        }
        html.dark-mode .badge { background: var(--bg-subtle) !important; }

        /* ---- tables -------------------------------------------------------- */
        table { width: 100%; border-collapse: separate; border-spacing: 0; }
        th {
            text-align: left; padding: 0.75rem 0.9rem;
            color: var(--text-soft); font-size: 0.68rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.06em;
            border-bottom: 1px solid var(--border);
            white-space: nowrap; background: transparent;
        }
        td {
            padding: 0.85rem 0.9rem; vertical-align: middle;
            border-bottom: 1px solid var(--border);
            font-size: 0.875rem; color: var(--text-main);
            transition: background-color var(--fast) var(--ease);
        }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover td { background: var(--bg-subtle); }

        /* ---- the things people type into ----------------------------------- */
        input[type=text], input[type=email], input[type=password], input[type=number],
        input[type=search], input[type=tel], input[type=url], input[type=date],
        input[type=datetime-local], input[type=time], input[type=month], select, textarea {
            font-family: inherit;
            font-size: 0.875rem;
            color: var(--text-main);
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            transition: border-color var(--fast) var(--ease), box-shadow var(--fast) var(--ease),
                        background-color var(--fast) var(--ease);
        }
        input:hover:not([disabled]), select:hover:not([disabled]), textarea:hover:not([disabled]) {
            border-color: var(--border-strong);
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--ring);
        }
        input::placeholder, textarea::placeholder { color: var(--text-soft); }

        /* A file picker draws its own button, and the browser's is a white slab
           that stays white in the dark. */
        input[type=file] {
            font-family: inherit; font-size: 0.82rem; color: var(--text-muted);
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: var(--radius-sm); padding: 0.4rem 0.6rem; width: 100%;
        }
        input[type=file]::file-selector-button {
            font-family: inherit; font-size: 0.78rem; font-weight: 600;
            margin-right: 10px; padding: 0.3rem 0.7rem; cursor: pointer;
            color: var(--text-main); background: var(--bg-subtle);
            border: 1px solid var(--border); border-radius: var(--radius-xs);
            transition: background-color var(--fast) var(--ease), border-color var(--fast) var(--ease);
        }
        input[type=file]::file-selector-button:hover { border-color: var(--primary); color: var(--primary); }
        input[disabled], select[disabled], textarea[disabled] { background: var(--bg-subtle); cursor: not-allowed; opacity: 0.75; }

        /* ---- alerts -------------------------------------------------------- */
        .alert {
            padding: 0.8rem 1rem; border-radius: var(--radius);
            margin-bottom: 1.25rem; display: flex; align-items: center; gap: 10px;
            font-weight: 500; font-size: 0.875rem; border: 1px solid transparent;
        }
        .alert-success { background: rgba(16,185,129,0.1); color: var(--success); border-color: rgba(16,185,129,0.22); }

        /* ---- pagination ---------------------------------------------------- */
        .pagination { display: flex; list-style: none; gap: 6px; margin-top: 1.25rem; justify-content: flex-end; align-items: center; }
        .page-item { margin: 0; }
        .page-link {
            display: flex; align-items: center; justify-content: center;
            min-width: 34px; height: 34px; padding: 0 10px;
            border-radius: var(--radius-sm); font-size: 0.85rem; font-weight: 600;
            color: var(--text-muted); background: var(--bg-card);
            border: 1px solid var(--border); text-decoration: none;
            transition: all var(--fast) var(--ease);
        }
        .page-link:hover { border-color: var(--primary); color: var(--primary); background: var(--bg-subtle); }
        .page-item.active .page-link { background: var(--primary-gradient); color: #fff; border-color: transparent; box-shadow: var(--shadow-sm); }
        .page-item.disabled .page-link { opacity: 0.45; pointer-events: none; }

        /* ---- the page itself ----------------------------------------------- */
        /* The side panels wait off the right edge until they are opened, which
           on a phone makes the document as wide as the panel's hiding place and
           lets the whole page slide sideways. Clipping — rather than scrolling —
           stops that without turning the page into a scroll container, so
           sticky headers and sticky columns keep working. */
        html { overflow-x: clip; }
        body { overflow-x: clip; }

        .main-content { padding: 1.75rem 2rem 3rem; }
        @media (max-width: 760px) { .main-content { padding: 1rem 1rem 2.5rem; } }

        /* Content arrives rather than appearing. Once, on load, and never in a
           way that delays anything being usable. */
        @keyframes dsRise { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
        /* "backwards", not "both": a forwards fill would leave the transform
           applied for good, and an element with a transform is a stacking
           context — which traps the header's dropdowns under the rows below it. */
        .main-content > * { animation: dsRise var(--slow) var(--ease) backwards; }
        /* The header holds the menus that open over the page, so it is lifted
           out of the row order rather than relying on where it happens to sit. */
        .main-content > .header { position: relative; z-index: 60; }

        /* ---- the figures at the top of a screen ----------------------------- */
        .kpi { display: flex; flex-direction: column; gap: 0.55rem; padding: 1.15rem 1.25rem; }
        .kpi-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
        .kpi-label {
            font-size: 0.72rem; font-weight: 700; color: var(--text-soft);
            text-transform: uppercase; letter-spacing: 0.06em;
        }
        .kpi-value {
            font-size: 1.75rem; font-weight: 700; color: var(--text-main);
            letter-spacing: -0.025em; line-height: 1.1; font-variant-numeric: tabular-nums;
        }
        .kpi-foot { display: flex; align-items: center; gap: 7px; font-size: 0.76rem; color: var(--text-muted); }

        /* The icon is a quiet tile, not a badge competing with the number. */
        .kpi-icon {
            width: 34px; height: 34px; flex: 0 0 34px; border-radius: var(--radius-sm);
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 0.82rem; background: var(--bg-subtle); color: var(--text-muted);
            transition: transform var(--base) var(--ease), background-color var(--base) var(--ease);
        }
        .card:hover .kpi-icon { transform: scale(1.06); }
        .kpi-icon.is-primary { background: var(--primary-soft); color: var(--primary); }
        .kpi-icon.is-success { background: rgba(16,185,129,0.12); color: var(--success); }
        .kpi-icon.is-info { background: rgba(59,130,246,0.12); color: var(--info); }
        .kpi-icon.is-warning { background: rgba(245,158,11,0.14); color: var(--warning); }
        .kpi-icon.is-violet { background: rgba(99,102,241,0.12); color: #6366F1; }
        .kpi-icon.is-danger { background: rgba(239,68,68,0.12); color: var(--danger); }

        /* A trend is a direction before it is a number. */
        .kpi-trend {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 0.1rem 0.45rem; border-radius: var(--radius-pill);
            font-size: 0.72rem; font-weight: 700; font-variant-numeric: tabular-nums;
            background: var(--bg-subtle); color: var(--text-muted);
        }
        .kpi-trend.is-up { background: rgba(16,185,129,0.12); color: var(--success); }
        .kpi-trend.is-down { background: rgba(239,68,68,0.1); color: var(--danger); }

        /* ---- the heading above a group of cards ----------------------------- */
        .section-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 1rem; }
        .section-head h2 {
            font-size: 1.02rem; font-weight: 700; color: var(--text-main);
            display: flex; align-items: center; gap: 9px; letter-spacing: -0.01em;
        }
        .section-head h2 i { color: var(--primary); font-size: 0.92rem; }
        .section-sub { font-size: 0.78rem; color: var(--text-muted); }


        /* =====================================================================
         * The pieces every screen is built from.
         *
         * Each of these replaces something the screens were drawing themselves
         * with inline styles — a page title here, a filter row there, each one a
         * slightly different grey. One definition each, so a screen says what a
         * thing is and the system says what it looks like.
         * ===================================================================== */

        /* ---- the top of a screen ------------------------------------------- */
        .page-head {
            display: flex; align-items: flex-end; justify-content: space-between;
            gap: 1rem; flex-wrap: wrap; margin-bottom: 1.4rem;
        }
        .page-head h1 {
            font-size: 1.45rem; font-weight: 700; line-height: 1.2;
            display: flex; align-items: center; gap: 10px; margin: 0;
        }
        .page-head h1 i { color: var(--primary); font-size: 1.1rem; }
        .page-head .page-sub { color: var(--text-muted); font-size: 0.84rem; margin-top: 4px; }
        .page-head .page-actions { display: flex; align-items: center; gap: 9px; flex-wrap: wrap; }
        @media (max-width: 700px) {
            .page-head h1 { font-size: 1.2rem; }
            .page-head .page-actions { width: 100%; }
            .page-head .page-actions > * { flex: 1; }
        }

        /* ---- the row of things that narrow a list -------------------------- */
        .filter-bar {
            display: flex; align-items: flex-end; gap: 10px; flex-wrap: wrap;
            padding: 0.9rem 1rem; margin-bottom: 1.1rem;
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: var(--radius); box-shadow: var(--shadow-xs);
        }
        .filter-bar .filter-field { display: flex; flex-direction: column; gap: 5px; min-width: 150px; flex: 1 1 150px; }
        .filter-bar .filter-field > label,
        .field > label {
            font-size: 0.68rem; font-weight: 700; letter-spacing: 0.06em;
            text-transform: uppercase; color: var(--text-soft);
        }
        .filter-bar .filter-go { display: flex; gap: 8px; flex: 0 0 auto; }

        /* ---- the things people type into, as a labelled pair --------------- */
        .field { display: flex; flex-direction: column; gap: 6px; }
        .field-row { display: grid; gap: 0.9rem; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); }
        .control,
        .filter-bar input, .filter-bar select,
        .field input, .field select, .field textarea {
            width: 100%; padding: 0.55rem 0.7rem; height: auto;
        }
        .field textarea, textarea.control { min-height: 86px; resize: vertical; line-height: 1.5; }
        .field-hint { font-size: 0.74rem; color: var(--text-soft); }
        .field-req { color: var(--danger); }

        /* ---- a table in a box of its own ---------------------------------- */
        .table-wrap {
            border: 1px solid var(--border); border-radius: var(--radius);
            overflow: auto; background: var(--bg-card);
        }
        .table-wrap > table { margin: 0; }
        .table-wrap th { position: sticky; top: 0; z-index: 1; background: var(--bg-card); }
        /* A table already inside a card does not need a second border. */
        .card > .table-wrap { border: none; border-radius: 0; }

        /* ---- nothing here yet --------------------------------------------- */
        .empty-state {
            padding: 2.6rem 1.5rem; text-align: center; color: var(--text-muted);
        }
        .empty-state > i {
            font-size: 1.6rem; color: var(--text-soft); margin-bottom: 0.8rem;
            display: block;
        }
        .empty-state strong { display: block; color: var(--text-main); font-size: 0.95rem; margin-bottom: 4px; }
        .empty-state p { font-size: 0.83rem; margin: 0 auto; max-width: 42ch; }

        /* ---- small standing labels ---------------------------------------- */
        .chip {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 0.28rem 0.62rem; border-radius: var(--radius-pill);
            font-size: 0.76rem; font-weight: 600;
            background: var(--bg-subtle); color: var(--text-muted);
            border: 1px solid var(--border); white-space: nowrap;
        }
        .chip.is-primary { background: var(--primary-soft); color: var(--primary); border-color: transparent; }

        /* Badges, by what they mean rather than by a colour written out again
           on every screen that needs one. */
        .badge-success { background: rgba(16,185,129,0.12) !important; color: var(--success); }
        .badge-danger  { background: rgba(239,68,68,0.12) !important;  color: var(--danger); }
        .badge-warning { background: rgba(245,158,11,0.14) !important; color: var(--warning); }
        .badge-info    { background: rgba(59,130,246,0.12) !important; color: var(--info); }
        .badge-primary { background: var(--primary-soft) !important;   color: var(--primary); }

        /* ---- buttons, the rest of the set --------------------------------- */
        .btn-soft { background: var(--bg-subtle); color: var(--text-main); border-color: var(--border); }
        .btn-soft:hover { border-color: var(--border-strong); background: var(--bg-card); }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-danger:hover { filter: brightness(1.06); box-shadow: var(--shadow-md); }
        .btn-success { background: var(--success); color: #fff; }
        .btn-success:hover { filter: brightness(1.06); box-shadow: var(--shadow-md); }
        .btn-warning { background: var(--warning); color: #fff; }
        .btn-warning:hover { filter: brightness(1.06); box-shadow: var(--shadow-md); }
        .btn-info { background: var(--info); color: #fff; }
        .btn-info:hover { filter: brightness(1.06); box-shadow: var(--shadow-md); }
        .btn-muted { background: var(--text-soft); color: #fff; }
        .btn-sm { padding: 0.36rem 0.7rem; font-size: 0.78rem; border-radius: var(--radius-xs); }
        .btn-icon { width: var(--control-h); height: var(--control-h); padding: 0; border-radius: var(--radius-sm); }
        .btn[disabled], .btn.is-busy { opacity: 0.6; pointer-events: none; }

        /* ---- calling the floor --------------------------------------------- */
        .fm-wrap { position: relative; display: inline-flex; }
        .fm-caret { font-size: 0.6rem; opacity: 0.75; margin-left: 2px; }

        .fm-menu {
            position: absolute; top: 100%; left: 0; z-index: 1200;
            min-width: 232px; padding: 5px; margin-top: 6px;
            background: var(--bg-raised); border: 1px solid var(--border);
            border-radius: var(--radius); box-shadow: var(--shadow-lg);
            opacity: 0; visibility: hidden; transform: translateY(-6px);
            transition: opacity var(--fast) var(--ease), transform var(--fast) var(--ease), visibility var(--fast);
        }
        /* The gap between button and menu is bridged, or the menu shuts while
           the pointer is crossing it. */
        .fm-menu::before { content: ''; position: absolute; left: 0; right: 0; top: -8px; height: 8px; }
        .fm-wrap:hover .fm-menu,
        .fm-wrap:focus-within .fm-menu { opacity: 1; visibility: visible; transform: none; }

        .fm-item {
            display: flex; align-items: center; gap: 10px; width: 100%;
            padding: 0.5rem 0.6rem; border: none; background: none; cursor: pointer;
            border-radius: var(--radius-xs); font-family: inherit; font-size: 0.82rem;
            color: var(--text-main); text-align: left; text-decoration: none;
            transition: background-color var(--fast) var(--ease);
        }
        .fm-item:hover { background: var(--bg-subtle); }
        .fm-item i:first-child { width: 17px; text-align: center; }
        .fm-item .fm-mins { margin-left: auto; font-size: 0.72rem; color: var(--text-soft); }
        .fm-item.is-on { background: var(--primary-soft); color: var(--primary); }

        .fm-setup {
            border-top: 1px solid var(--border); margin-top: 4px; padding-top: 0.6rem;
            color: var(--text-muted); font-size: 0.78rem;
        }
        .fm-waiting { padding: 0.6rem; font-size: 0.78rem; color: var(--text-soft); }

        /* ---- a person's face ----------------------------------------------- */
        .face {
            position: relative; display: inline-flex; flex-shrink: 0;
            width: var(--face-size, 34px); height: var(--face-size, 34px);
        }
        .face-pic, .face-letter {
            width: 100%; height: 100%; border-radius: var(--face-round, 10px);
            display: flex; align-items: center; justify-content: center;
            object-fit: cover; background: var(--bg-subtle);
        }
        /* The picture covers the letter rather than replacing it, so a picture
           that fails to load simply uncovers the letter underneath. */
        .face-pic { position: absolute; inset: 0; }
        .face-letter {
            color: #fff; font-weight: 700;
            font-size: calc(var(--face-size, 34px) * 0.42); line-height: 1;
        }
        /* The role, small enough to sit on the corner without covering a face. */
        .face-badge {
            position: absolute; left: 50%; bottom: -7px; transform: translateX(-50%);
            padding: 1px 6px; border-radius: var(--radius-pill);
            font-size: 0.56rem; font-weight: 800; letter-spacing: 0.04em;
            text-transform: uppercase; color: #fff; white-space: nowrap;
            border: 2px solid var(--bg-card);
        }

        /*
         * The tick.
         *
         * On the face itself where there is only a face, and beside the name
         * where there is a name — the same mark, the same colour, in whichever
         * of the two places the eye is already looking. It is drawn in the
         * CRM's own blue rather than the brand orange, so it reads as a fact
         * about the person and not as a highlight somebody chose.
         */
        .face-tick {
            position: absolute; right: -3px; bottom: -3px;
            width: calc(var(--face-size, 34px) * 0.38);
            height: calc(var(--face-size, 34px) * 0.38);
            min-width: 13px; min-height: 13px;
            border-radius: 50%;
            background: var(--info);
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: calc(var(--face-size, 34px) * 0.2);
            border: 2px solid var(--bg-card);
            box-sizing: content-box;
        }
        .person-tick {
            color: var(--info);
            font-size: 0.82em;
            margin-left: 4px;
            vertical-align: baseline;
        }

        /* ---- a person in a list: a face, a name, and whatever follows ------ */
        .person { display: inline-flex; align-items: center; gap: 10px; min-width: 0; }
        .person-who { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
        .person-name {
            font-weight: 600; color: var(--text-main); font-size: 0.875rem;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .person-name a { color: inherit; text-decoration: none; }
        .person-name a:hover { color: var(--primary); }
        .person-sub {
            font-size: 0.74rem; color: var(--text-muted);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        /* ---- a person, in one line ---------------------------------------- */
        .avatar {
            width: 34px; height: 34px; border-radius: var(--radius-pill);
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 0.78rem; font-weight: 700; color: #fff; flex-shrink: 0;
            background: var(--primary-gradient); object-fit: cover;
        }
        .avatar-sm { width: 26px; height: 26px; font-size: 0.66rem; }
        .person { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .person-name { font-weight: 600; color: var(--text-main); }
        .person-meta { font-size: 0.75rem; color: var(--text-muted); }

        /* ---- switching between views of the same thing -------------------- */
        .tabs {
            display: flex; gap: 2px; padding: 3px; margin-bottom: 1.1rem;
            background: var(--bg-subtle); border-radius: var(--radius-sm);
            width: fit-content; max-width: 100%; overflow-x: auto;
        }
        .tabs a, .tabs button {
            padding: 0.44rem 0.9rem; border-radius: var(--radius-xs); border: none;
            background: none; cursor: pointer; font-family: inherit;
            font-size: 0.82rem; font-weight: 600; color: var(--text-muted);
            text-decoration: none; white-space: nowrap;
            transition: background-color var(--fast) var(--ease), color var(--fast) var(--ease);
        }
        .tabs a:hover, .tabs button:hover { color: var(--text-main); }
        .tabs a.is-active, .tabs button.is-active {
            background: var(--bg-card); color: var(--text-main); box-shadow: var(--shadow-xs);
        }

        /* ---- dialogs ------------------------------------------------------- */
        .modal-overlay { transition: opacity var(--base) var(--ease); }
        .modal-content {
            border-radius: var(--radius-lg); box-shadow: var(--shadow-lg);
            animation: dsPop var(--base) var(--ease) backwards;
        }
        @keyframes dsPop { from { opacity: 0; transform: translateY(10px) scale(0.985); } to { opacity: 1; transform: none; } }

        /* ---- waiting ------------------------------------------------------- */
        @keyframes dsShimmer { from { background-position: -200% 0; } to { background-position: 200% 0; } }
        .skeleton {
            border-radius: var(--radius-sm);
            background: linear-gradient(90deg, var(--bg-subtle) 25%, var(--bg-card) 50%, var(--bg-subtle) 75%);
            background-size: 200% 100%;
            animation: dsShimmer 1.4s linear infinite;
        }

        /* ---- scrollbars, quietly ------------------------------------------- */
        * { scrollbar-width: thin; scrollbar-color: var(--border-strong) transparent; }
        *::-webkit-scrollbar { width: 9px; height: 9px; }
        *::-webkit-scrollbar-track { background: transparent; }
        *::-webkit-scrollbar-thumb { background: var(--border-strong); border-radius: var(--radius-pill); border: 2px solid transparent; background-clip: content-box; }
        *::-webkit-scrollbar-thumb:hover { background: var(--text-soft); background-clip: content-box; }

        /* =====================================================================
         * The menu opens and closes.
         *
         * One button, two behaviours, and the screen width decides which — so
         * there is a single control to learn and nothing to choose.
         *
         * On a laptop the menu narrows to a rail of icons, which hands a wide
         * table back its 180px. On a phone the menu is not on the page at all
         * until it is asked for: it slides in over the content. The old
         * behaviour kept a 62px strip permanently, which spent a sixth of a
         * phone's width on icons too small to read and left the content in a
         * column narrower than the tables it had to hold.
         *
         * Which mode is in force is a class on <body>, set by the script at the
         * end of the page; these rules only react to it.
         * ===================================================================== */
        .nav-toggle {
            display: inline-flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            width: var(--control-h); height: var(--control-h);
            border: 1px solid var(--border); border-radius: var(--radius-sm);
            background: var(--bg-card); color: var(--text-muted);
            font-size: 0.95rem; cursor: pointer;
            transition: background-color var(--fast) var(--ease),
                        color var(--fast) var(--ease),
                        border-color var(--fast) var(--ease);
        }
        .nav-toggle:hover { background: var(--primary-soft); color: var(--primary); border-color: var(--primary); }
        .nav-toggle:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }
        .header .page-title { display: flex; align-items: center; gap: 0.7rem; }
        /* The screen's name shortens before the controls do, but it should not
           shorten to "Floor ..." — below this it stops giving ground and the
           row of controls scrolls instead. */
        .header .page-title h1 { min-width: 11ch; }

        /* ---- the rail: icons, and the sub-menus folded away with them ------- */
        body.nav-rail .sidebar { width: var(--sidebar-rail); }
        body.nav-rail .main-content { margin-left: var(--sidebar-rail); }
        body.nav-rail .sidebar .nav-item > span,
        body.nav-rail .sidebar .sub-nav-links { display: none !important; }
        /* The second icon in a group row is its chevron, and a chevron beside a
           label that is not being shown points at nothing. */
        body.nav-rail .sidebar .nav-item > i:nth-of-type(2) { display: none !important; }
        body.nav-rail .sidebar .nav-item { justify-content: center; padding-left: 0; padding-right: 0; }
        body.nav-rail .sidebar .nav-item i { margin-right: 0; }
        body.nav-rail .logo-area { padding: 1rem 0.4rem; overflow: hidden; }
        body.nav-rail .logo-area img { height: 28px; width: auto; max-width: 100%; object-fit: contain; }
        /* Two 42px buttons side by side are wider than the rail, so in a rail
           they queue up instead of hanging off the edge of it. */
        /* !important because the row carries its direction as an inline style,
           which an ordinary class rule cannot outrank. */
        body.nav-rail .sidebar-foot {
            flex-direction: column !important;
            align-items: center; gap: 8px;
            padding: 0.9rem 0.4rem !important;
        }

        /* A logo is drawn at whatever width its height gives it, and some are
           wide. Left unchecked one runs past the edge of a narrowed menu. */
        .logo-area img { max-width: 100%; object-fit: contain; }
        .logo-area { overflow: hidden; }

        /* ---- the sheet: off the page until asked for ------------------------ */
        .nav-scrim {
            display: none;
            position: fixed; inset: 0;
            background: rgba(15, 23, 42, 0.46);
            z-index: 1500;
            opacity: 0; pointer-events: none;
            transition: opacity var(--base) var(--ease);
        }
        body.nav-sheet .nav-scrim { display: block; }
        body.nav-sheet.nav-open .nav-scrim { opacity: 1; pointer-events: auto; }

        body.nav-sheet .sidebar {
            width: min(82vw, 290px);
            transform: translateX(-102%);
            z-index: 1600;
            transition: transform var(--base) var(--ease);
            padding-bottom: env(safe-area-inset-bottom, 0px);
        }
        body.nav-sheet.nav-open .sidebar {
            transform: none;
            box-shadow: 18px 0 48px rgba(0, 0, 0, 0.3);
        }
        /* The page keeps its full width whether the menu is out or not. */
        body.nav-sheet .main-content { margin-left: 0; }
        /* The sheet sits over the page, so the page behind it must not scroll
           away underneath a finger meant for the menu. */
        body.nav-sheet.nav-open { overflow: hidden; }

        /* A finger is not a cursor: the rows in the sheet are given room. */
        body.nav-sheet .sidebar .nav-item { padding: 0.72rem 0.9rem; font-size: 0.92rem; }
        body.nav-sheet .sidebar .nav-item i { font-size: 1rem; }

        html.reduce-motion .sidebar,
        html.reduce-motion .nav-scrim { transition: none !important; }
        @media (prefers-reduced-motion: reduce) {
            .sidebar, .nav-scrim { transition: none !important; }
        }

        @media (max-width: 900px) {
            /* The way back to the menu travels with the page rather than being
               scrolled off the top of it. */
            .main-content > .header {
                position: sticky; top: 0; z-index: 60;
                margin: -1rem -1rem 1.2rem;
                padding: 0.65rem 1rem;
                background: var(--bg-body);
                border-bottom: 1px solid var(--border);
            }
            body.is-impersonating .main-content > .header { top: 46px; }

            /* Safari zooms the whole page in when a field it is about to focus
               has text under 16px. Nothing else about the field changes. */
            input[type=text], input[type=email], input[type=password], input[type=number],
            input[type=search], input[type=tel], input[type=url], input[type=date],
            input[type=datetime-local], input[type=time], input[type=month],
            select, textarea { font-size: 16px; }

            /* Room for the rounded corners and the home indicator. */
            .main-content {
                padding-left: max(1rem, env(safe-area-inset-left));
                padding-right: max(1rem, env(safe-area-inset-right));
                padding-bottom: calc(2.5rem + env(safe-area-inset-bottom, 0px));
            }
        }

        /* =====================================================================
         * A phone, and what a laptop layout does to one.
         *
         * The companion to js/mobile-fit.js: that moves things, this sets what
         * they are allowed to be. Between them the rule is the same — nothing
         * on a page may be wider than the screen it is on, except a table,
         * which may be as wide as it likes inside a box that scrolls.
         * ===================================================================== */
        @media (max-width: 900px) {
            /*
             * The automatic minimum width.
             *
             * A grid or flex item is, by default, never allowed to be narrower
             * than its content — so one long row inside a card widens the card,
             * the track it sits in, and the page. Written with :where() so it
             * carries no specificity at all: any rule that deliberately sets a
             * min-width still wins, and so does an inline style.
             */
            .main-content :where(*) { min-width: 0; }

            /* Where a table that had nowhere to go now scrolls. */
            .mf-scroll {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                /* A scroll box is also a clip box, so a dropdown opened from a
                   row would be cut off. Vertically there is nothing to clip. */
                overflow-y: visible;
            }
            .mf-scroll > table { margin: 0; }

            /* Column headings were 0.68rem, which is 10.9px — under the size
               most people can read at arm's length on a phone. */
            body.mf-on th { font-size: 0.78rem; }
            body.mf-on .filter-bar .filter-field > label,
            body.mf-on .field > label { font-size: 0.74rem; }
            body.mf-on .field-hint,
            body.mf-on .tl-note,
            body.mf-on .tl-trade,
            body.mf-on .pipe-tag { font-size: 0.76rem; }

            /* A card's own padding is a bigger share of a small screen, and two
               cards side by side are two half-cards. */
            body.mf-on .stat-card-grid,
            body.mf-on .kpi-grid { grid-template-columns: 1fr; }

            /* The assistant's halo is drawn well outside the orb itself, which
               on a phone puts it past the right edge of the screen. It comes
               in, and the halo comes in with it. */
            .ai-orb {
                /* Far enough in that the halo, which is drawn about 27px wider
                   than the orb on every side, stays on the screen too. */
                right: 30px !important;
                bottom: calc(24px + env(safe-area-inset-bottom, 0px)) !important;
            }
            .ai-orb-glow { inset: -22%; }
        }

        /* ---- the header on a phone ------------------------------------------
         * Eight controls do not fit beside a title. They go behind one button
         * and come back underneath it, as a block somebody can read, rather
         * than a row that scrolls sideways past things nobody knew were there.
         */
        .hdr-more { display: none; }

        @media (max-width: 760px) {
            body.mf-on .main-content > .header { display: block; }
            body.mf-on .main-content > .header > .page-title { gap: 0.6rem; }
            body.mf-on .hdr-more { display: inline-flex; margin-left: auto; }

            /* !important on both: the row carries display:flex as an inline
               style, which no class rule can outrank. */
            body.mf-on .main-content > .header > div:last-child { display: none !important; }
            body.mf-on.hdr-open .main-content > .header > div:last-child {
                display: flex !important;
                flex-wrap: wrap;
                justify-content: flex-start;
                overflow: visible;
                margin: 0.8rem 0 0;
                padding: 0;
                gap: 8px;
                animation: dsRise var(--base) var(--ease);
            }
            /* Each control gets a whole finger's worth of room rather than
               being squeezed to fit a row. */
            body.mf-on.hdr-open .main-content > .header > div:last-child > * { flex: 0 0 auto; }
        }

        @media (max-width: 640px) {
            /* A dialog written for a laptop is taller than a phone; let it
               scroll inside itself rather than running off the bottom. */
            .modal-overlay { padding: 1rem; }
            .modal-content {
                max-width: 100%; padding: 1.25rem;
                max-height: calc(100vh - 2rem);
                overflow-y: auto; -webkit-overflow-scrolling: touch;
            }
        }

        @media (max-width: 760px) {
            /* The screen's name on one line, its controls on the next, scrolled
               sideways rather than clipped or stacked eight rows deep. */
            .header { flex-direction: column; align-items: stretch; gap: 0.75rem; margin-bottom: 1.2rem; }
            .header .page-title h1 { font-size: 1.05rem; }
            .header > div:last-child {
                overflow-x: auto; overflow-y: hidden;
                flex-wrap: nowrap; justify-content: flex-start;
                padding-bottom: 4px; margin: 0 -1rem; padding-left: 1rem; padding-right: 1rem;
                scrollbar-width: none;
            }
            .header > div:last-child::-webkit-scrollbar { display: none; }
            .header > div:last-child > * { flex: 0 0 auto; }

            /* A card's padding is a bigger share of a small screen. */
            .card { padding: 1rem; border-radius: var(--radius); }
            .kpi-value { font-size: 1.5rem; }

            /* A wide table scrolls inside its box instead of the page sliding. */
            .table-wrap, .table-container { -webkit-overflow-scrolling: touch; }
        }

        /* Global scrollbar. This used to be the same orange-to-pink gradient as an
           active sidebar item, on every scrollable box on the page including the
           sidebar itself — so a scrollbar sitting near the real active link looked
           like a second one. Kept subtle here; the gradient stays for things that are
           actually meant to draw the eye. */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }

        /* Notifications */
        .notif-bell { position: relative; background: none; border: none; font-size: 1.05rem; cursor: pointer; color: var(--text-muted); width: var(--control-h); height: var(--control-h); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; transition: all var(--base) var(--ease); }
        .notif-bell:hover { background: rgba(0,0,0,0.05); color: var(--primary); }
        html.dark-mode .notif-bell:hover { background: rgba(255,255,255,0.1); }
        .notif-badge { position: absolute; top: 5px; right: 5px; background: var(--danger); color: white; font-size: 0.6rem; font-weight: bold; border-radius: 50%; min-width: 16px; height: 16px; display: flex; align-items: center; justify-content: center; display: none; }
        
        .notif-dropdown { position: absolute; top: 70px; right: 2rem; width: 350px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); display: none; z-index: 1000; overflow: hidden; }
        .notif-dropdown.show { display: block; animation: slideDown 0.3s ease; }
        .notif-header { padding: 1rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .notif-list { max-height: 400px; overflow-y: auto; }
        .notif-item { padding: 1rem; border-bottom: 1px solid var(--border); cursor: pointer; transition: 0.2s; display: flex; gap: 12px; }
        .notif-item:hover { background: rgba(0,0,0,0.02); }
        html.dark-mode .notif-item:hover { background: rgba(255,255,255,0.02); }
        .notif-item.unread { background: rgba(239, 68, 68, 0.05); border-left: 4px solid var(--danger); }
        .notif-item.read { background: rgba(16, 185, 129, 0.05); border-left: 4px solid var(--success); }
        .notif-icon { width: 40px; height: 40px; border-radius: 10px; background: rgba(59, 130, 246, 0.1); color: var(--info); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
        
        /* Modal */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(5px); z-index: 2000; display: none; align-items: center; justify-content: center; }
        .modal-overlay.show { display: flex; animation: fadeIn 0.3s; }
        .modal-content { background: var(--bg-card); border-radius: 20px; width: 100%; max-width: 500px; padding: 2rem; position: relative; border: 1px solid var(--border); }
        .close-modal { position: absolute; top: 1.5rem; right: 1.5rem; background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--text-muted); }
        
        /* Toastr custom */
        #toastContainer { position: fixed; bottom: 30px; right: 30px; z-index: 9999; display: flex; flex-direction: column; gap: 12px; pointer-events: none; align-items: flex-end; }
        .sys-toast { background: var(--bg-card); border-left: 4px solid var(--primary); padding: 1rem 1.5rem; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); display: flex; gap: 15px; align-items: center; transform: translateX(120%); transition: 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55); color: var(--text-main); width: max-content; min-width: 300px; max-width: 400px; pointer-events: auto; position: relative; }
        .sys-toast.show { transform: translateX(0); }
        .sys-toast.toast-success { border-left-color: var(--success); }
        .sys-toast.toast-error { border-left-color: var(--danger); }
        .sys-toast.toast-success i { color: var(--success); }
        .sys-toast.toast-error i { color: var(--danger); }
        .sys-toast.toast-info i { color: var(--primary); }
        .sys-toast .close-toast { margin-left: auto; cursor: pointer; color: var(--text-muted); font-size: 1.2rem; background: none; border: none; padding: 0; line-height: 1; transition: 0.2s; align-self: flex-start; }
        .sys-toast .close-toast:hover { color: var(--danger); transform: scale(1.1); }
        
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        /* Fix Browser Autofill Background */
        input:-webkit-autofill,
        input:-webkit-autofill:hover, 
        input:-webkit-autofill:focus, 
        input:-webkit-autofill:active,
        select:-webkit-autofill,
        select:-webkit-autofill:hover,
        select:-webkit-autofill:focus {
            -webkit-box-shadow: 0 0 0 30px var(--card) inset !important;
            -webkit-text-fill-color: var(--text-main) !important;
            transition: background-color 5000s ease-in-out 0s;
            border-color: var(--border) !important;
        }
        
        /* Impersonation adjustments */
        body.is-impersonating {
            padding-top: 46px;
        }
        body.is-impersonating .sidebar {
            top: 46px;
            height: calc(100vh - 46px);
        }
    </style>

    <script>
    /*
     * One look for every chart in the CRM.
     *
     * ApexCharts reads window.Apex as the defaults for every chart made after
     * it is set, so the thirty-odd charts scattered across the app pick this up
     * without any of them being touched. Each chart still decides what it is
     * and what data it draws; this decides how it reads.
     *
     * Colours come from the stylesheet rather than being repeated here, so a
     * chart follows the theme the same way everything else does.
     */
    (function () {
        if (!window.ApexCharts) return;

        function token(name, fallback) {
            var value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
            return value || fallback;
        }

        function palette() {
            var primary = token('--primary', '#f99d4e');

            // A set that stays legible on both backgrounds and keeps the brand
            // colour first, so a single-series chart is on-brand by default.
            return [primary, '#6366F1', '#10B981', '#0EA5E9', '#F59E0B', '#EC4899', '#14B8A6', '#8B5CF6'];
        }

        function defaults() {
            var dark = document.documentElement.classList.contains('dark-mode');
            var ink = token('--text-muted', '#64748b');
            var line = dark ? 'rgba(148,163,184,0.14)' : '#EDF1F6';

            return {
                chart: {
                    fontFamily: "'Outfit', sans-serif",
                    foreColor: ink,
                    toolbar: { show: false },
                    zoom: { enabled: false },
                    background: 'transparent',
                    animations: {
                        enabled: true,
                        easing: 'easeinout',
                        speed: 650,
                        animateGradually: { enabled: true, delay: 90 },
                        dynamicAnimation: { enabled: true, speed: 320 },
                    },
                    dropShadow: { enabled: false },
                },
                theme: { mode: dark ? 'dark' : 'light' },
                colors: palette(),
                grid: {
                    borderColor: line,
                    strokeDashArray: 4,
                    xaxis: { lines: { show: false } },
                    padding: { left: 6, right: 6, top: 0 },
                },
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: 2.5, lineCap: 'round' },
                markers: { size: 0, hover: { size: 5 } },
                legend: {
                    position: 'top',
                    horizontalAlign: 'right',
                    fontSize: '12px',
                    fontWeight: 600,
                    markers: { radius: 12, width: 9, height: 9 },
                    itemMargin: { horizontal: 10 },
                },
                plotOptions: {
                    bar: { borderRadius: 6, borderRadiusApplication: 'end', columnWidth: '45%' },
                },
                tooltip: {
                    theme: dark ? 'dark' : 'light',
                    style: { fontSize: '12px', fontFamily: "'Outfit', sans-serif" },
                    marker: { show: true },
                },
                xaxis: {
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                    labels: { style: { fontSize: '11.5px', fontWeight: 500 } },
                    tooltip: { enabled: false },
                },
                yaxis: {
                    labels: { style: { fontSize: '11.5px', fontWeight: 500 } },
                },
                states: {
                    hover: { filter: { type: 'lighten', value: 0.06 } },
                    active: { filter: { type: 'darken', value: 0.1 } },
                },
            };
        }

        window.Apex = defaults();

        /*
         * Charts already on screen when the theme changes.
         *
         * Apex keeps what it has made in _chartInstances; walking that is what
         * lets a chart follow the theme without every page having to remember
         * to redraw its own.
         */
        window.crmRethemeCharts = function () {
            var next = defaults();
            window.Apex = next;

            var instances = (window.Apex && window.Apex._chartInstances) || ApexCharts._chartInstances || [];

            [].forEach.call(instances, function (entry) {
                var chart = entry && (entry.chart || entry);
                if (!chart || !chart.updateOptions) return;

                try {
                    chart.updateOptions({
                        theme: next.theme,
                        chart: { foreColor: next.chart.foreColor },
                        grid: { borderColor: next.grid.borderColor },
                        tooltip: { theme: next.tooltip.theme },
                    }, false, false);
                } catch (e) { /* a chart that will not retheme is not worth a broken page */ }
            });
        };

        // The theme is switched by adding or removing a class on <html>.
        new MutationObserver(function (changes) {
            changes.forEach(function (change) {
                if (change.attributeName === 'class') window.crmRethemeCharts();
            });
        }).observe(document.documentElement, { attributes: true });
    })();
    </script>

    <style>
        /* The music player: a corner of the screen, big enough to pick a song. */
        .music-player { position: fixed; right: 18px; bottom: 18px; width: 400px; z-index: 2500;
                        background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px;
                        box-shadow: 0 18px 44px rgba(0,0,0,0.22); overflow: hidden;
                        transition: width 0.2s ease; }
        .music-player[hidden] { display: none !important; }
        .music-bar { display: flex; align-items: center; justify-content: space-between; gap: 10px;
                     padding: 0.55rem 0.5rem 0.55rem 0.8rem; font-size: 0.82rem; font-weight: 600;
                     color: var(--text-main); border-bottom: 1px solid var(--border); }
        .music-title { display: inline-flex; align-items: center; gap: 7px; white-space: nowrap; }
        .music-playing { font-size: 0.7rem; font-weight: 600; color: #1db954; display: none; }
        .music-actions { display: flex; gap: 2px; }
        .music-actions button { border: none; background: transparent; color: var(--text-muted);
                                cursor: pointer; padding: 4px 7px; border-radius: 7px; font-size: 0.82rem; }
        .music-actions button:hover { color: var(--text-main); background: var(--bg-body); }
        .music-frame { padding: 0.6rem; }

        .music-pick { display: grid; grid-template-columns: 1fr auto; gap: 6px;
                      padding: 0.6rem 0.6rem 0; align-items: center; }
        .music-pick[hidden] { display: none; }
        .music-pick input { height: 34px; padding: 0 0.6rem; border-radius: 9px; font-size: 0.82rem;
                            border: 1px solid var(--border); background: var(--bg-body); color: var(--text-main);
                            font-family: inherit; min-width: 0; }
        .music-pick button { height: 34px; padding: 0 0.9rem; border-radius: 9px; border: none; cursor: pointer;
                             background: #1db954; color: #fff; font-weight: 700; font-size: 0.8rem; }
        .music-pick small { grid-column: 1 / -1; font-size: 0.7rem; color: var(--text-muted); line-height: 1.4; }
        .music-pick small.is-bad { color: var(--danger); }

        /*
         * Tucked away, the frame is clipped rather than hidden: a display:none
         * iframe is torn down by the browser and the song stops with it, which is
         * the opposite of what tucking it away is for.
         */
        .music-player.is-collapsed { width: 230px; }
        .music-player.is-collapsed .music-bar { border-bottom: none; }
        .music-player.is-collapsed .music-playing { display: inline; }
        .music-player.is-collapsed .music-frame { height: 0; padding: 0; overflow: hidden; }

        @media (max-width: 620px) {
            .music-player { right: 10px; left: 10px; width: auto; }
            .music-player.is-collapsed { width: auto; left: auto; }
        }
    </style>

    @stack('styles')
</head>
<body @if(session()->has('impersonate_original_id')) class="is-impersonating" @endif>
    @if(session()->has('impersonate_original_id'))
    <div style="background: rgba(245, 158, 11, 0.85); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); color: #fff; text-align: center; padding: 10px; font-weight: bold; position: fixed; top: 0; left: 0; right: 0; z-index: 9999; display: flex; justify-content: center; align-items: center; gap: 15px; height: 46px; border-bottom: 1px solid rgba(255,255,255,0.2);">
        <span><i class="fas fa-user-secret"></i> You are currently impersonating <strong>{{ auth()->user()->name }}</strong>.</span>
        <a href="{{ route('admin.impersonate.leave') }}" class="btn btn-primary" style="padding: 0.3rem 0.8rem; font-size: 0.85rem; border-radius: 6px; background: #000; border: none; color: #fff; text-decoration: none;">Leave Impersonation</a>
    </div>
    @endif
    @if(auth()->check() && auth()->user()->todayAttendance() && auth()->user()->todayAttendance()->status === 'on_break')
        @php
            $activeBreak = auth()->user()->todayAttendance()->breaks()->whereNull('break_end')->first();
            $breakType = $activeBreak ? $activeBreak->type : 'short_1';
            $breakStart = $activeBreak ? $activeBreak->break_start->toIso8601String() : now()->toIso8601String();
            
            $company = auth()->user()->company;
            $b1_duration = 2400; // default 40 mins
            $b1_name = '1st Half Break';
            if ($company && $company->break_1_start && $company->break_1_end) {
                // Determine duration handling crossing midnight if necessary, but standard times assume same day
                $start1 = \Carbon\Carbon::parse($company->break_1_start);
                $end1 = \Carbon\Carbon::parse($company->break_1_end);
                if ($end1->lessThan($start1)) $end1->addDay();
                $b1_duration = $start1->diffInSeconds($end1);
                $b1_name = $company->break_1_name ?: '1st Half Break';
            }
            $b2_duration = 1800; // default 30 mins
            $b2_name = '2nd Half Break';
            if ($company && $company->break_2_start && $company->break_2_end) {
                $start2 = \Carbon\Carbon::parse($company->break_2_start);
                $end2 = \Carbon\Carbon::parse($company->break_2_end);
                if ($end2->lessThan($start2)) $end2->addDay();
                $b2_duration = $start2->diffInSeconds($end2);
                $b2_name = $company->break_2_name ?: '2nd Half Break';
            }
            
            $b1_mins = round($b1_duration / 60);
            $b2_mins = round($b2_duration / 60);
            
            $previousElapsedSeconds = 0;
            if ($activeBreak && in_array($breakType, ['half_1', 'half_2'])) {
                $previousBreaks = auth()->user()->todayAttendance()->breaks()
                    ->where('type', $breakType)
                    ->whereNotNull('break_end')
                    ->get();
                foreach ($previousBreaks as $pb) {
                    $previousElapsedSeconds += \Carbon\Carbon::parse($pb->break_start)->diffInSeconds(\Carbon\Carbon::parse($pb->break_end));
                }
            }
            
            $breakLabel = [
                'short_1' => '1st Short Break (5 Mins Max)',
                'half_1' => "$b1_name ($b1_mins Mins Max)",
                'short_2' => '2nd Short Break (5 Mins Max)',
                'half_2' => "$b2_name ($b2_mins Mins Max)",
                'short_3' => '3rd Temporary Break (5 Mins Max)'
            ][$breakType] ?? 'On Break';
        @endphp
        <div id="break-lock-overlay" style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 999999; background: rgba(15, 23, 42, 0.96); backdrop-filter: blur(15px); display: flex; flex-direction: column; align-items: center; justify-content: center; color: white; font-family: 'Outfit', sans-serif; pointer-events: auto;">
            <div style="text-align: center; max-width: 500px; width: 90%; padding: 2.5rem; border-radius: 30px; background: rgba(30, 41, 59, 0.6); border: 1px solid rgba(255, 255, 255, 0.05); box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
                <div style="font-size: 3rem; color: var(--primary); margin-bottom: 1.5rem; animation: pulse 2s infinite;"><i class="fas fa-coffee"></i></div>
                <h2 style="font-size: 1.8rem; font-weight: 700; margin-bottom: 0.5rem; letter-spacing: 0.5px;">{{ $breakLabel }}</h2>
                <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 2rem;">Your CRM is currently locked. Enjoy your break!</p>
                
                <!-- Circular Countdown Timer -->
                <div style="position: relative; width: 200px; height: 200px; margin: 0 auto 2.5rem; display: flex; align-items: center; justify-content: center;">
                    <svg viewBox="0 0 100 100" style="transform: rotate(-90deg); width: 200px; height: 200px;">
                        <circle cx="50" cy="50" r="45" stroke="rgba(255,255,255,0.03)" stroke-width="5" fill="transparent"></circle>
                        <circle id="break-circle-bar" cx="50" cy="50" r="45" stroke="var(--primary)" stroke-width="5" fill="transparent" stroke-dasharray="283" stroke-dashoffset="0" stroke-linecap="round" style="transition: stroke-dashoffset 1s linear, stroke 1s ease;"></circle>
                    </svg>
                    <div style="position: absolute; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                        <span id="break-timer-text" style="font-size: 2.8rem; font-weight: 800; font-variant-numeric: tabular-nums; line-height: 1;">00:00</span>
                        <span id="break-overtime-label" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--danger); margin-top: 5px; display: none;">Overtime</span>
                    </div>
                </div>

                @if(!in_array($breakType, ['half_1', 'half_2']))
                <form action="{{ route('admin.attendance.end-break') }}" method="POST" style="margin: 0;">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-lg" style="background: var(--primary-gradient); padding: 0.8rem 2.5rem; font-size: 1rem; border-radius: 50px; font-weight: 700; border: none; box-shadow: 0 10px 20px rgba(249,157,78,0.3); cursor: pointer; transition: 0.3s; width: 100%;">
                        <i class="fas fa-play"></i> End Break & Resume
                    </button>
                </form>
                @else
                <div style="font-size: 1.1rem; color: var(--warning); font-weight: 600; background: rgba(245, 158, 11, 0.1); padding: 1rem; border-radius: 12px; border: 1px solid rgba(245, 158, 11, 0.2); text-align: center;">
                    <i class="fas fa-lock"></i> Waiting for Admin to Resume Session
                </div>
                @endif
            </div>
        </div>

        <script>
            (function() {
                const breakType = "{{ $breakType }}";
                const breakStart = new Date("{{ $breakStart }}");
                const previousElapsedSeconds = parseInt("{{ $previousElapsedSeconds ?? 0 }}", 10);
                const maxDurations = { 
                    short_1: 300, 
                    half_1: {{ $b1_duration }}, 
                    short_2: 300, 
                    half_2: {{ $b2_duration }}, 
                    short_3: 300 
                };
                const limitSeconds = maxDurations[breakType] || 300;
                
                const timerText = document.getElementById('break-timer-text');
                const circleBar = document.getElementById('break-circle-bar');
                const overtimeLabel = document.getElementById('break-overtime-label');

                function updateTimer() {
                    const elapsed = Math.floor((new Date() - breakStart) / 1000) + previousElapsedSeconds;
                    const remaining = limitSeconds - elapsed;

                    if (remaining >= 0) {
                        const mins = Math.floor(remaining / 60);
                        const secs = remaining % 60;
                        timerText.textContent = String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
                        
                        // Circle stroke dash offset calculation (circumference = 283)
                        const offset = (remaining / limitSeconds) * 283;
                        circleBar.style.strokeDashoffset = 283 - offset;
                        circleBar.style.stroke = "var(--primary)";
                        overtimeLabel.style.display = 'none';
                        timerText.style.color = "#ffffff";
                    } else {
                        // Overtime
                        const absRemaining = Math.abs(remaining);
                        const mins = Math.floor(absRemaining / 60);
                        const secs = absRemaining % 60;
                        timerText.textContent = '-' + String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
                        
                        circleBar.style.strokeDashoffset = 283;
                        circleBar.style.stroke = "var(--danger)";
                        overtimeLabel.style.display = 'inline-block';
                        timerText.style.color = "var(--danger)";
                    }
                }

                updateTimer();
                setInterval(updateTimer, 1000);
            })();
        </script>
    @endif
    <!-- Sidebar -->
    <aside class="sidebar" id="crmSidebar">
        <div class="logo-area">
            <a href="{{ route('admin.dashboard') }}" style="text-decoration: none; display: inline-block; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                @if(auth()->check() && auth()->user()->company)
                    @if(auth()->user()->company->logo && file_exists(public_path(auth()->user()->company->logo)))
                        <img src="{{ url('file/' . auth()->user()->company->logo) }}" alt="{{ auth()->user()->company->name }} Logo" class="logo-for-light" style="max-height: 45px;">
                    @endif
                    
                    @if(auth()->user()->company->dark_logo && file_exists(public_path(auth()->user()->company->dark_logo)))
                        <img src="{{ url('file/' . auth()->user()->company->dark_logo) }}" alt="{{ auth()->user()->company->name }} Dark Logo" class="logo-for-dark" style="max-height: 45px; display: none;">
                    @elseif(auth()->user()->company->logo && file_exists(public_path(auth()->user()->company->logo)))
                        <img src="{{ url('file/' . auth()->user()->company->logo) }}" alt="{{ auth()->user()->company->name }} Logo" class="logo-for-dark" style="max-height: 45px; display: none;">
                    @endif
                    
                    @if(!auth()->user()->company->logo && !auth()->user()->company->dark_logo)
                        {{-- No logo uploaded for this company, so fall back to the marks
                             that ship with the app rather than the bare company name.
                             Two files because one logo cannot sit on both backgrounds:
                             the wordmark is black in logo.webp and white in
                             whitelogo.png. The .logo-for-light / .logo-for-dark rules in
                             the stylesheet swap them with the theme. A company that
                             uploads its own logo still overrides this. --}}
                        <img src="{{ asset('uploads/company_logos/logo.webp') }}"
                             alt="{{ auth()->user()->company->name }}"
                             class="logo-for-light" style="max-height: 45px;">
                        <img src="{{ asset('uploads/company_logos/whitelogo.png') }}"
                             alt="{{ auth()->user()->company->name }}"
                             class="logo-for-dark" style="max-height: 45px; display: none;">
                    @endif
                @else
                    <h2 style="font-size: 1.3rem; font-weight: 700; color: var(--primary); line-height: 1.2; text-align: center;">
                        CRM Dashboard
                    </h2>
                @endif
            </a>
        </div>
        @php
            /* Every link below is shown only when the signed in user has that section
               switched on. The same list is what CheckSectionAccess enforces, so a
               hidden link is genuinely closed rather than merely out of sight. */
            $me = auth()->user();
        @endphp
        <nav class="nav-links">
            <!-- 1. Overview -->
            @if($me->canAccess('overview'))
            <a href="{{ route('admin.dashboard') }}" class="nav-item {{ Request::is('admin') ? 'active' : '' }}">
                <i class="fas fa-grid-2"></i> <span>Overview</span>
            </a>
            @endif

            <!-- 2. Communication -->
            @if($me->canAccess('emails') || $me->canAccess('email_templates'))
            <div class="nav-item-group" style="margin-bottom: 0.5rem;">
                @php $isEmailGroupActive = request()->is('admin/email*'); @endphp
                <a href="javascript:void(0)" class="nav-item {{ $isEmailGroupActive ? 'active' : '' }}" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? 'block' : 'none';">
                    <i class="fas fa-envelope"></i> <span>Communications</span>
                    <i class="fas fa-chevron-down" style="margin-left: auto; font-size: 0.8rem;"></i>
                </a>
                <div class="sub-nav-links" style="display: {{ $isEmailGroupActive ? 'block' : 'none' }}; margin-left: 1.5rem; border-left: 2px solid var(--border); padding-left: 1rem; margin-top: 0.5rem; margin-bottom: 1rem;">
                    @if($me->canAccess('emails'))
                    <a href="{{ route('admin.emails.inbox') }}" class="nav-item {{ request()->routeIs('admin.emails.inbox') ? 'active' : '' }}" style="padding: 0.5rem 1rem; font-size: 0.9rem; margin-bottom: 0;">
                        <i class="fas fa-inbox" style="font-size: 0.9rem;"></i> <span>Inbox & History</span>
                    </a>
                    @endif
                    @if($me->canAccess('email_templates'))
                    <a href="{{ route('admin.email_templates') }}" class="nav-item {{ request()->routeIs('admin.email_templates') ? 'active' : '' }}" style="padding: 0.5rem 1rem; font-size: 0.9rem; margin-bottom: 0;">
                        <i class="fas fa-file-alt" style="font-size: 0.9rem;"></i> <span>Email Templates</span>
                    </a>
                    @endif
                </div>
            </div>
            @endif

            <!-- 3. Sales => (Sales History, Sales Team, Dispatched, Pending, On Hold) -->
            @php
                $canSeeSalesHistoryTeam = $me->canAccess('sales_history');
                $canSeeSalesTech = $me->canAccess('clients');
                $isSalesGroupActive = request()->routeIs('admin.history', 'admin.team') || request()->is('*/dispatched', '*/pending', '*/sales-on-hold');
            @endphp
            @if($canSeeSalesHistoryTeam || $canSeeSalesTech)
            <div class="nav-item-group" style="margin-bottom: 0.5rem;">
                <a href="javascript:void(0)" class="nav-item {{ $isSalesGroupActive ? 'active' : '' }}" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? 'block' : 'none';">
                    <i class="fas fa-chart-line"></i> <span>Sales</span>
                    <i class="fas fa-chevron-down" style="margin-left: auto; font-size: 0.8rem;"></i>
                </a>
                <div class="sub-nav-links" style="display: {{ $isSalesGroupActive ? 'block' : 'none' }}; margin-left: 1.5rem; border-left: 2px solid var(--border); padding-left: 1rem; margin-top: 0.5rem; margin-bottom: 1rem;">
                    
                    @if($canSeeSalesHistoryTeam)
                    <a href="{{ route('admin.history') }}" class="nav-item {{ request()->routeIs('admin.history') ? 'active' : '' }}" style="padding: 0.5rem 1rem; font-size: 0.9rem; margin-bottom: 0;">
                        <i class="fas fa-history" style="font-size: 0.9rem;"></i> <span>Sales History</span>
                    </a>
                    <a href="{{ route('admin.team') }}" class="nav-item {{ request()->routeIs('admin.team') ? 'active' : '' }}" style="padding: 0.5rem 1rem; font-size: 0.9rem; margin-bottom: 0;">
                        <i class="fas fa-users" style="font-size: 0.9rem;"></i> <span>Sales Team</span>
                    </a>
                    @endif

                    @if($canSeeSalesTech)
                    <a href="{{ route('support.clients', 'dispatched') }}" class="nav-item {{ request()->is('*/dispatched') ? 'active' : '' }}" style="padding: 0.5rem 1rem; font-size: 0.9rem; margin-bottom: 0;">
                        <i class="fas fa-truck" style="font-size: 0.9rem;"></i> <span>Dispatched Sales</span>
                    </a>
                    <a href="{{ route('support.clients', 'pending') }}" class="nav-item {{ request()->is('*/pending') ? 'active' : '' }}" style="padding: 0.5rem 1rem; font-size: 0.9rem; margin-bottom: 0;">
                        <i class="fas fa-clock" style="font-size: 0.9rem;"></i> <span>Pending Sales</span>
                    </a>
                    <a href="{{ route('support.clients', 'sales-on-hold') }}" class="nav-item {{ request()->is('*/sales-on-hold') ? 'active' : '' }}" style="padding: 0.5rem 1rem; font-size: 0.9rem; margin-bottom: 0;">
                        <i class="fas fa-pause-circle" style="font-size: 0.9rem;"></i> <span>On Hold Clients</span>
                    </a>
                    @endif
                </div>
            </div>
            @endif

            <!-- 5. Team Chat -->
            @if($me->canAccess('viewrec'))
            <a href="{{ route('admin.viewrec') }}" class="nav-item {{ request()->routeIs('admin.viewrec*') ? 'active' : '' }}">
                <i class="fas fa-user-check" style="width: 20px; text-align: center;"></i> <span>Viewrec</span>
            </a>
            @endif

            @if($me->canAccess('training'))
            <a href="{{ route('admin.training') }}" class="nav-item {{ request()->routeIs('admin.training*') ? 'active' : '' }}">
                <i class="fas fa-graduation-cap" style="width: 20px; text-align: center;"></i> <span>Training</span>
            </a>
            @endif

            @if($me->canAccess('wallet'))
            <a href="{{ route('admin.wallet') }}" class="nav-item {{ request()->routeIs('admin.wallet') ? 'active' : '' }}">
                <i class="fas fa-wallet" style="width: 20px; text-align: center;"></i> <span>My Wallet</span>
            </a>
            @endif

            @if($me->canAccess('moments'))
            <a href="{{ route('admin.moments.settings') }}" class="nav-item {{ request()->routeIs('admin.moments.settings') ? 'active' : '' }}">
                <i class="fas fa-bullhorn" style="width: 20px; text-align: center;"></i> <span>Floor Decorum</span>
            </a>
            @endif

            @if($me->canAccess('netflix'))
            <a href="{{ route('admin.netflix') }}" class="nav-item {{ request()->routeIs('admin.netflix*') ? 'active' : '' }}">
                <i class="fas fa-film" style="width: 20px; text-align: center;"></i> <span>Netflix</span>
            </a>
            @endif

            @if($me->canAccess('games'))
            <a href="{{ route('admin.games') }}" class="nav-item {{ request()->routeIs('admin.games*') ? 'active' : '' }}">
                <i class="fas fa-gamepad" style="width: 20px; text-align: center;"></i> <span>Games</span>
            </a>
            @endif

            @if($me->canAccess('huddle'))
            <a href="{{ route('admin.huddle') }}" class="nav-item {{ request()->routeIs('admin.huddle*') ? 'active' : '' }}">
                <i class="fas fa-display" style="width: 20px; text-align: center;"></i> <span>Quick Huddle</span>
            </a>
            @endif

            @if($me->canAccess('chat'))
            <a href="{{ route('chat.index') }}" class="nav-item {{ request()->routeIs('chat.*') ? 'active' : '' }}" style="position: relative;">
                <i class="fas fa-comments" style="width: 20px; text-align: center;"></i> <span>Team Chat</span>
                <div id="globalChatBadge" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); background: var(--danger); color: white; font-size: 0.7rem; font-weight: bold; border-radius: 50%; min-width: 18px; height: 18px; display: none; align-items: center; justify-content: center; box-shadow: 0 2px 5px rgba(239,68,68,0.4);"></div>
            </a>
            @endif

            <!-- 6. Client's Queries -->
            @if($me->canAccess('clients'))
            @php
                $isClientsGroupActive = request()->is('*/active', '*/on-hold', '*/charge-back', '*/case-studies', '*/deactivated', '*/win-back') || request()->routeIs('support.sheet');
            @endphp
            <div class="nav-item-group" style="margin-bottom: 0.5rem;">
                <a href="javascript:void(0)" class="nav-item {{ $isClientsGroupActive ? 'active' : '' }}" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? 'block' : 'none';">
                    <i class="fas fa-users-cog"></i> <span>Client's Queries</span>
                    <i class="fas fa-chevron-down" style="margin-left: auto; font-size: 0.8rem;"></i>
                </a>
                <div class="sub-nav-links" style="display: {{ $isClientsGroupActive ? 'block' : 'none' }}; margin-left: 1.5rem; border-left: 2px solid var(--border); padding-left: 1rem; margin-top: 0.5rem; margin-bottom: 1rem;">
                    <a href="javascript:void(0)" class="nav-item" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? 'block' : 'none';" style="padding: 0.5rem 1rem; font-size: 0.9rem; margin-bottom: 0;">
                        <i class="fas fa-folder-open" style="font-size: 0.9rem;"></i> <span>Clients</span>
                        <i class="fas fa-chevron-down" style="margin-left: auto; font-size: 0.7rem;"></i>
                    </a>
                    <div style="display: {{ $isClientsGroupActive ? 'block' : 'none' }}; margin-left: 1rem; padding-left: 0.5rem; margin-top: 0.3rem;">
                        <a href="{{ route('support.clients', 'active') }}" class="nav-item {{ request()->is('*/active') ? 'active' : '' }}" style="padding: 0.4rem 1rem; font-size: 0.85rem; margin-bottom: 0;">Active Customers</a>
                        <a href="{{ route('support.clients', 'on-hold') }}" class="nav-item {{ request()->is('*/on-hold') ? 'active' : '' }}" style="padding: 0.4rem 1rem; font-size: 0.85rem; margin-bottom: 0;">On Hold Clients</a>
                        <a href="{{ route('support.clients', 'charge-back') }}" class="nav-item {{ request()->is('*/charge-back') ? 'active' : '' }}" style="padding: 0.4rem 1rem; font-size: 0.85rem; margin-bottom: 0;">Charge back Clients</a>
                        <a href="{{ route('support.clients', 'case-studies') }}" class="nav-item {{ request()->is('*/case-studies') ? 'active' : '' }}" style="padding: 0.4rem 1rem; font-size: 0.85rem; margin-bottom: 0;">Case studies</a>
                        <a href="{{ route('support.clients', 'deactivated') }}" class="nav-item {{ request()->is('*/deactivated') ? 'active' : '' }}" style="padding: 0.4rem 1rem; font-size: 0.85rem; margin-bottom: 0;">Deactivated Customers</a>
                        <a href="{{ route('support.clients', 'win-back') }}" class="nav-item {{ request()->is('*/win-back') ? 'active' : '' }}" style="padding: 0.4rem 1rem; font-size: 0.85rem; margin-bottom: 0;">Win back Customers</a>
                        <a href="{{ route('support.sheet') }}" class="nav-item {{ request()->routeIs('support.sheet') ? 'active' : '' }}" style="padding: 0.4rem 1rem; font-size: 0.85rem; margin-bottom: 0;">
                            <i class="fas fa-file-excel" style="margin-right: 8px;"></i> Google Sheet View
                        </a>
                    </div>
                </div>
            </div>
            @endif

            <!-- 7. Attendance -->
            @if($me->canAccess('attendance'))
            <a href="{{ route('admin.attendance') }}" class="nav-item {{ Request::is('admin/attendance*') ? 'active' : '' }}">
                <i class="fas fa-clock"></i> <span>Attendance</span>
            </a>
            @endif

            <!-- 7b. HR. One group so the personnel pages sit together, next to the
                 attendance they are all worked out from.

                 Not for the owner: HR runs the personnel side and has its own
                 dashboard for it. The owner account looks after the system —
                 companies, users, settings — and these screens only made that
                 list longer without being theirs to work. Any of them is still
                 reachable by address if it is ever needed. -->
            @if(!$me->isSuperAdmin() && ($me->canAccess('hr_dashboard') || $me->canAccess('hr_employees') || $me->canAccess('hr_leaves') || $me->canAccess('hr_performance') || $me->canAccess('hr_reports') || $me->canAccess('hr_work_record') || $me->canAccess('hr_settings')))
            @php $isHrGroupActive = Request::is('admin/hr*'); @endphp
            <div class="nav-item-group" style="margin-bottom: 0.5rem;">
                <a href="javascript:void(0)" class="nav-item {{ $isHrGroupActive ? 'active' : '' }}" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? 'block' : 'none';">
                    <i class="fas fa-id-card"></i> <span>HR</span>
                    <i class="fas fa-chevron-down" style="margin-left: auto; font-size: 0.8rem;"></i>
                </a>
                <div class="sub-nav-links" style="display: {{ $isHrGroupActive ? 'block' : 'none' }}; margin-left: 1.5rem; border-left: 2px solid var(--border); padding-left: 1rem; margin-top: 0.5rem; margin-bottom: 1rem;">
                    @if($me->canAccess('hr_dashboard'))
                    <a href="{{ route('admin.hr.dashboard') }}" class="nav-item {{ Request::is('admin/hr/dashboard*') ? 'active' : '' }}" style="padding: 0.5rem 1rem; font-size: 0.9rem; margin-bottom: 0;">
                        <i class="fas fa-gauge-high" style="font-size: 0.9rem;"></i> <span>HR Dashboard</span>
                    </a>
                    @endif
                    @if($me->canAccess('hr_employees'))
                    <a href="{{ route('admin.hr.employees') }}" class="nav-item {{ Request::is('admin/hr/employees*') ? 'active' : '' }}" style="padding: 0.5rem 1rem; font-size: 0.9rem; margin-bottom: 0;">
                        <i class="fas fa-users" style="font-size: 0.9rem;"></i> <span>Employee Records</span>
                    </a>
                    @endif
                    @if($me->canAccess('hr_leaves'))
                    <a href="{{ route('admin.hr.leaves') }}" class="nav-item {{ Request::is('admin/hr/leaves*') ? 'active' : '' }}" style="padding: 0.5rem 1rem; font-size: 0.9rem; margin-bottom: 0;">
                        <i class="fas fa-calendar-check" style="font-size: 0.9rem;"></i> <span>Leave Management</span>
                    </a>
                    @endif
                    @if($me->canAccess('hr_performance'))
                    <a href="{{ route('admin.hr.performance') }}" class="nav-item {{ Request::is('admin/hr/performance*') ? 'active' : '' }}" style="padding: 0.5rem 1rem; font-size: 0.9rem; margin-bottom: 0;">
                        <i class="fas fa-bullseye" style="font-size: 0.9rem;"></i> <span>JD &amp; KPIs</span>
                    </a>
                    @endif
                    @if($me->canAccess('hr_work_record'))
                    <a href="{{ route('admin.hr.work_record') }}" class="nav-item {{ Request::is('admin/hr/work-record*') ? 'active' : '' }}" style="padding: 0.5rem 1rem; font-size: 0.9rem; margin-bottom: 0;">
                        <i class="fas fa-chart-column" style="font-size: 0.9rem;"></i> <span>Work Record</span>
                    </a>
                    @endif
                    @if($me->canAccess('hr_reports'))
                    <a href="{{ route('admin.hr.reports') }}" class="nav-item {{ Request::is('admin/hr/reports*') ? 'active' : '' }}" style="padding: 0.5rem 1rem; font-size: 0.9rem; margin-bottom: 0;">
                        <i class="fas fa-file-lines" style="font-size: 0.9rem;"></i> <span>Reports</span>
                    </a>
                    @endif
                    @if($me->canAccess('hr_settings'))
                    <a href="{{ route('admin.hr.settings') }}" class="nav-item {{ Request::is('admin/hr/settings*') ? 'active' : '' }}" style="padding: 0.5rem 1rem; font-size: 0.9rem; margin-bottom: 0;">
                        <i class="fas fa-sliders" style="font-size: 0.9rem;"></i> <span>HR Settings</span>
                    </a>
                    @endif
                </div>
            </div>
            @endif

            <!-- 7c. Everyone's own HR pages: leave and targets. No permission needed.
                 Left off the owner account, which is a system login rather than a
                 member of staff: it is not on the employee list, not on the salary
                 sheet and has no leave quota, so it has no use for these either. -->
            @unless($me->isSuperAdmin())
            <a href="{{ route('admin.hr.my_attendance') }}" class="nav-item {{ Request::is('admin/my-attendance') ? 'active' : '' }}">
                <i class="fas fa-calendar-days"></i> <span>My Attendance</span>
            </a>
            <a href="{{ route('admin.hr.my_leaves') }}" class="nav-item {{ Request::is('admin/my-leaves') ? 'active' : '' }}">
                <i class="fas fa-plane-departure"></i> <span>My Leave</span>
            </a>
            <a href="{{ route('admin.hr.my_performance') }}" class="nav-item {{ Request::is('admin/my-targets') ? 'active' : '' }}">
                <i class="fas fa-bullseye"></i> <span>My Targets</span>
            </a>
            @endunless

            <!-- 8. Deals & Activity -->
            @if($me->canAccess('deals'))
            <a href="{{ route('admin.deals') }}" class="nav-item {{ request()->routeIs('admin.deals') ? 'active' : '' }}">
                <i class="fas fa-handshake"></i> <span>Deals & Activity</span>
            </a>
            @endif

            <!-- Leads Management (unspecified by user, but belongs near Deals/Transferred leads) -->
            @if($me->canAccess('leads'))
            <a href="{{ route('admin.leads') }}" class="nav-item {{ Request::is('admin/leads*') ? 'active' : '' }}">
                <i class="fas fa-user-plus"></i> <span>Leads Management</span>
            </a>
            @endif

            <!-- 9. Transferred Leads -->
            @if($me->canAccess('transferred_leads'))
            <a href="{{ route('admin.transferred_leads') }}" class="nav-item {{ request()->routeIs('admin.transferred_leads') ? 'active' : '' }}">
                <i class="fas fa-exchange-alt"></i> <span>Transferred Leads</span>
            </a>
            @endif

            <!-- 10. Support Dashboard -->
            @if($me->canAccess('support'))
            <a href="{{ route('support.dashboard') }}" class="nav-item {{ (request()->routeIs('support.dashboard') || request()->routeIs('support.show')) ? 'active' : '' }}">
                <i class="fas fa-headset"></i> <span>Support Dashboard</span>
            </a>
            @endif

            <!-- 11. Scoreboard Settings => (News Headlines, Business Categories, States) -->
            @if($me->canAccess('scoreboard_settings') || $me->canAccess('scoreboard_display'))
            @php $isScoreboardSettingsActive = Request::is('admin/settings*', 'admin/headlines*', 'admin/categories*', 'admin/usa_states*'); @endphp
            <div class="nav-item-group" style="margin-bottom: 0.5rem;">
                <a href="javascript:void(0)" class="nav-item {{ $isScoreboardSettingsActive ? 'active' : '' }}" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? 'block' : 'none';">
                    <i class="fas fa-cogs"></i> <span>Scoreboard Settings</span>
                    <i class="fas fa-chevron-down" style="margin-left: auto; font-size: 0.8rem;"></i>
                </a>
                <div class="sub-nav-links" style="display: {{ $isScoreboardSettingsActive ? 'block' : 'none' }}; margin-left: 1.5rem; border-left: 2px solid var(--border); padding-left: 1rem; margin-top: 0.5rem; margin-bottom: 1rem;">
                    @if($me->canAccess('scoreboard_display'))
                    <a href="{{ route('admin.settings') }}" class="nav-item {{ Request::is('admin/settings*') ? 'active' : '' }}" style="padding: 0.5rem 1rem; font-size: 0.9rem; margin-bottom: 0;">
                        <i class="fas fa-tv" style="font-size: 0.9rem;"></i> <span>Scoreboard Display</span>
                    </a>
                    @endif
                    @if($me->canAccess('scoreboard_settings'))
                    <a href="{{ route('admin.headlines') }}" class="nav-item {{ Request::is('admin/headlines*') ? 'active' : '' }}" style="padding: 0.5rem 1rem; font-size: 0.9rem; margin-bottom: 0;">
                        <i class="fas fa-newspaper" style="font-size: 0.9rem;"></i> <span>News Headlines</span>
                    </a>
                    <a href="{{ route('admin.categories') }}" class="nav-item {{ Request::is('admin/categories*') ? 'active' : '' }}" style="padding: 0.5rem 1rem; font-size: 0.9rem; margin-bottom: 0;">
                        <i class="fas fa-building" style="font-size: 0.9rem;"></i> <span>Business Categories</span>
                    </a>
                    <a href="{{ route('admin.usa_states') }}" class="nav-item {{ request()->routeIs('admin.usa_states') ? 'active' : '' }}" style="padding: 0.5rem 1rem; font-size: 0.9rem; margin-bottom: 0;">
                        <i class="fas fa-map-marker-alt" style="font-size: 0.9rem;"></i> <span>States</span>
                    </a>
                    @endif
                </div>
            </div>
            @endif

            <!-- Remaining Admin Links -->
            @if($me->canAccess('users'))
            <a href="{{ route('admin.users') }}" class="nav-item {{ Request::is('admin/users*') ? 'active' : '' }}">
                <i class="fas fa-user-shield"></i> <span>User Management</span>
            </a>
            <a href="{{ route('admin.bin') }}" class="nav-item {{ Request::is('admin/recycle-bin*') ? 'active' : '' }}">
                <i class="fas fa-trash-arrow-up"></i> <span>Recycle Bin</span>
            </a>
            @endif

            <a href="{{ route('admin.my_profile') }}" class="nav-item {{ request()->routeIs('admin.my_profile') ? 'active' : '' }}" style="margin-top: 10px; border-top: 1px solid var(--border); padding-top: 15px; border-radius: 0;">
                <i class="fas fa-user-circle"></i> <span>My Profile</span>
            </a>

            @if($me->isSuperAdmin())
                <a href="{{ route('admin.companies') }}" class="nav-item {{ request()->routeIs('admin.companies') ? 'active' : '' }}">
                    <i class="fas fa-building" style="width: 25px;"></i> <span>Companies</span>
                </a>
                <a href="{{ route('admin.logs') }}" class="nav-item {{ request()->routeIs('admin.logs') ? 'active' : '' }}">
                    <i class="fas fa-list-alt" style="width: 25px;"></i> <span>System Logs</span>
                </a>
                <a href="{{ route('admin.settings.global') }}" class="nav-item {{ request()->routeIs('admin.settings.global') ? 'active' : '' }}">
                    <i class="fas fa-globe" style="width: 25px;"></i> <span>Global Settings</span>
                </a>
            @endif
        </nav>

        {{-- Fitting a laptop's layout onto a phone: folding grids that cannot
             afford their columns, giving wide tables somewhere to scroll, and
             putting the header's controls behind one button. Does nothing at
             all above the phone width. --}}
        <script src="@versioned('js/mobile-fit.js')" defer></script>

        {{-- The menu button.
             Early, and inline, because the mode it picks decides the sidebar's
             width: left until DOMContentLoaded the full menu paints first and
             then jumps to a rail, which is the same flash the drag-to-reorder
             script below is placed early to avoid. --}}
        <script>
        (function () {
            var body = document.body;

            /* Below this a phone has no width to spare, so the menu stops being
               part of the page and becomes something that slides over it. */
            var SHEET_UNDER = 900;
            /* And above this there is room for the labels, so the menu starts
               open unless this person has said otherwise. */
            var ROOMY_OVER = 1280;

            var REMEMBERED = 'crm.nav';

            function wanted() {
                try { return localStorage.getItem(REMEMBERED); } catch (e) { return null; }
            }
            function remember(value) {
                try { localStorage.setItem(REMEMBERED, value); } catch (e) { /* private window */ }
            }

            function isSheet() { return window.innerWidth <= SHEET_UNDER; }

            /* What the menu should look like at this width, before anybody has
               touched the button. A laptop that cannot show labels comfortably
               starts as a rail; a wide screen starts open. */
            function settle() {
                var sheet = isSheet();

                body.classList.toggle('nav-sheet', sheet);

                if (sheet) {
                    // A sheet is never a rail: when it is out it shows labels.
                    body.classList.remove('nav-rail');
                } else {
                    body.classList.remove('nav-open');
                    var choice = wanted();
                    var rail = choice ? choice === 'rail' : window.innerWidth <= ROOMY_OVER;
                    body.classList.toggle('nav-rail', rail);
                }

                speak();
            }

            /* Screen readers need to be told which of the two the button is
               doing, and whether the thing it controls is showing. */
            function speak() {
                var button = document.getElementById('navToggle');
                if (!button) return;

                var sheet = isSheet();
                var showing = sheet ? body.classList.contains('nav-open')
                                    : !body.classList.contains('nav-rail');

                button.setAttribute('aria-expanded', showing ? 'true' : 'false');
                button.setAttribute('aria-label', showing ? 'Hide menu' : 'Show menu');
                button.title = showing ? 'Hide menu' : 'Show menu';

                var icon = button.querySelector('i');
                if (icon) {
                    icon.className = sheet
                        ? (showing ? 'fas fa-xmark' : 'fas fa-bars')
                        : (showing ? 'fas fa-bars' : 'fas fa-bars-staggered');
                }
            }

            function open()  { body.classList.add('nav-open'); speak(); }
            function close() { body.classList.remove('nav-open'); speak(); }

            function toggle() {
                if (isSheet()) {
                    body.classList.contains('nav-open') ? close() : open();
                    return;
                }

                var rail = body.classList.toggle('nav-rail');
                remember(rail ? 'rail' : 'full');
                speak();

                // Charts measure themselves against the room they have, and the
                // room just changed.
                window.dispatchEvent(new Event('resize'));
            }

            settle();

            document.addEventListener('DOMContentLoaded', function () {
                var button = document.getElementById('navToggle');
                var scrim = document.getElementById('navScrim');
                var sidebar = document.getElementById('crmSidebar');

                if (button) button.addEventListener('click', toggle);
                if (scrim) scrim.addEventListener('click', close);

                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape' && body.classList.contains('nav-open')) close();
                });

                /* Following a link takes the page away anyway; the menu closing
                   first means the new page is not behind a sheet while it loads.
                   The group headers are not links — they open their sub-menu —
                   so they are left alone. */
                if (sidebar) {
                    sidebar.addEventListener('click', function (e) {
                        if (!body.classList.contains('nav-open')) return;

                        var link = e.target.closest('a[href]');
                        if (link && link.getAttribute('href').indexOf('javascript:') !== 0) close();
                    });

                    /* Pushing the sheet back off the side, which is what a phone
                       expects of anything that slid in from one. Ignored while a
                       row is being dragged to a new place. */
                    var from = null;
                    sidebar.addEventListener('touchstart', function (e) {
                        if (!body.classList.contains('nav-open') || body.classList.contains('nav-sorting')) return;
                        from = e.touches[0].clientX;
                    }, { passive: true });

                    sidebar.addEventListener('touchend', function (e) {
                        if (from === null) return;

                        var travelled = e.changedTouches[0].clientX - from;
                        from = null;

                        if (travelled < -55) close();
                    }, { passive: true });
                }

                /* A phone rotated, or a window dragged across a breakpoint. */
                var settling;
                window.addEventListener('resize', function () {
                    clearTimeout(settling);
                    settling = setTimeout(settle, 120);
                });
            });
        })();
        </script>

        {{-- Sidebar drag-to-reorder.
             Loaded here rather than with the other scripts at the end of the body
             so the saved order is applied while the page is still parsing: run it
             on DOMContentLoaded instead and the menu visibly jumps into place
             after first paint. --}}
        <script src="@versioned('js/sortable.min.js')"></script>
        <script src="@versioned('js/sortable-prefs.js')"></script>
        <script src="@versioned('js/sidebar-sort.js')"></script>
        <script>
            (function () {
                var nav = document.querySelector('.sidebar .nav-links');
                if (nav && window.SidebarSort) {
                    SidebarSort.init(nav, window.CRM_USER_ID);
                }
            })();
        </script>

        <div class="sidebar-foot" style="padding: 1.2rem; border-top: 1px solid var(--border); flex-shrink: 0; display: flex; flex-direction: row; gap: 12px; justify-content: center;">
            <a href="{{ url('/') }}" target="_blank" title="View Scoreboard" style="display: flex; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: 10px; background: rgba(249, 157, 78, 0.1); color: var(--primary); text-decoration: none; font-size: 1.1rem; transition: all 0.2s ease; border: 1px solid rgba(249, 157, 78, 0.2);" 
onmouseover="this.style.background='var(--primary-gradient)'; this.style.color='white'; this.style.borderColor='transparent'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(249, 157, 78, 0.3)';" onmouseout="this.style.background='rgba(249, 157, 78, 0.1)'; this.style.color='var(--primary)'; this.style.borderColor='rgba(249, 157, 78, 0.2)'; this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                <i class="fas fa-external-link-alt"></i>
            </a>
            <form action="{{ route('logout') }}" method="POST" style="margin: 0; display: flex;">
                @csrf
                <button type="submit" title="Logout" style="display: flex; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: 10px; background: rgba(239, 68, 68, 0.08); color: var(--danger); font-size: 1.1rem; transition: all 0.2s ease; border: 1px solid rgba(239, 68, 68, 0.15); cursor: pointer;" onmouseover="this.style.background='var(--danger)'; this.style.color='white'; this.style.borderColor='var(--danger)'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(239, 68, 68, 0.3)';" onmouseout="this.style.background='rgba(239, 68, 68, 0.08)'; this.style.color='var(--danger)'; this.style.borderColor='rgba(239, 68, 68, 0.15)'; this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                    <i class="fas fa-sign-out-alt"></i>
                </button>
            </form>
        </div>
    </aside>

    {{-- Behind the menu when it is out over a phone screen. Tapping it is the
         obvious way back to the page, and it also stops a tap landing on a
         button underneath that nobody can see. --}}
    <div class="nav-scrim" id="navScrim" aria-hidden="true"></div>

    <main class="main-content">
        <header class="header">
            <div class="page-title">
                <button type="button" class="nav-toggle" id="navToggle"
                        aria-controls="crmSidebar" aria-expanded="true"
                        aria-label="Menu" title="Menu">
                    <i class="fas fa-bars" aria-hidden="true"></i>
                </button>
                <h1>@yield('title', 'Admin Dashboard')</h1>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                {{-- Accessibility menu. The theme switch used to float over the
                     bottom-right corner, where it sat on top of the chat send
                     button and the agent panel; it lives in here now, alongside
                     the other display preferences. Outside the admin/closer check
                     below, so every role has it. --}}
                <div style="position: relative;" id="a11yWrap">
                    <button type="button" id="a11yBtn" class="theme-toggle"
                            title="Accessibility &amp; display" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-universal-access"></i>
                    </button>

                    <div id="a11yMenu" class="notif-dropdown a11y-menu" role="menu" aria-label="Accessibility and display">
                        <div class="notif-header">
                            <strong style="font-size: 0.92rem;">Accessibility</strong>
                        </div>

                        <button type="button" class="a11y-row" id="themeToggle" role="menuitemcheckbox">
                            <span class="a11y-ico"><i class="fas fa-moon"></i></span>
                            <span class="a11y-label">
                                Dark mode
                                <small>Easier on the eyes in low light</small>
                            </span>
                            <span class="a11y-switch" aria-hidden="true"></span>
                        </button>

                        <button type="button" class="a11y-row" id="a11yMotion" role="menuitemcheckbox">
                            <span class="a11y-ico"><i class="fas fa-wind"></i></span>
                            <span class="a11y-label">
                                Reduce motion
                                <small>Turn off sliding and fading effects</small>
                            </span>
                            <span class="a11y-switch" aria-hidden="true"></span>
                        </button>

                        <button type="button" class="a11y-row" id="a11yText" role="menuitemcheckbox">
                            <span class="a11y-ico"><i class="fas fa-text-height"></i></span>
                            <span class="a11y-label">
                                Larger text
                                <small>Increase text size across the dashboard</small>
                            </span>
                            <span class="a11y-switch" aria-hidden="true"></span>
                        </button>

                        {{-- Music belongs with the other comfort settings rather
                             than as another icon in the header. Off by default:
                             the player only loads once somebody asks for it. --}}
                        <button type="button" class="a11y-row" id="a11yMusic" role="menuitemcheckbox">
                            <span class="a11y-ico"><i class="fab fa-spotify"></i></span>
                            <span class="a11y-label">
                                Music
                                <small>A small Spotify player while you work</small>
                            </span>
                            <span class="a11y-switch" aria-hidden="true"></span>
                        </button>
                    </div>
                </div>

                {{-- Opens the floating notepad. Its own window, so it can be
                     dragged over the dashboard and left open while working. --}}
                <button type="button" id="notepadBtn" class="theme-toggle np-header-btn"
                        title="Open your notepad" aria-label="Open your notepad">
                    <i class="fas fa-note-sticky"></i><span>Notepad</span>
                </button>

                @if(auth()->user()->isAdmin() || auth()->user()->isCloser())
                <input type="text" id="rushHourMsg" placeholder="Custom msg..." style="width: 140px; height: 34px; padding: 0 10px;">

                {{-- The first moment is the button; the rest drop out of it on
                     hover. Pressing the button still starts rush hour, so the
                     gesture everybody already knows is unchanged. --}}
                <div class="fm-wrap" id="fmWrap">
                    <button id="toggleRushHourBtn" class="btn btn-primary btn-compact">
                        <i class="fas fa-rocket"></i> <span id="fmLead">Start Rush Hour</span>
                        <i class="fas fa-chevron-down fm-caret"></i>
                    </button>

                    <div class="fm-menu" id="fmMenu" role="menu" aria-label="Call the floor">
                        <div class="fm-list" id="fmList">
                            <div class="fm-waiting">Loading…</div>
                        </div>

                        @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.moments.settings') }}" class="fm-item fm-setup">
                            <i class="fas fa-sliders"></i>
                            <span>Set these up</span>
                        </a>
                        @endif
                    </div>
                </div>
                @endif
                @if(auth()->user()->isAdmin())
                <button class="btn btn-ghost btn-compact" onclick="document.getElementById('notifModal').classList.add('show')">
                    <i class="fas fa-bullhorn"></i> Announcement
                </button>
                @endif
                
                @include('admin.partials.timezone_map')

                {{-- Replays the current section's quick guide. Hidden by the
                     script when the page has nothing worth pointing at. --}}
                <button type="button" id="quickGuideBtn" class="qg-open-btn"
                        title="Quick guide for this page" aria-label="Quick guide for this page">
                    <i class="fas fa-question-circle"></i>
                </button>

                <div style="position: relative;">
                    <button class="notif-bell" id="notifBtn" title="Notifications">
                        <i class="fas fa-bell"></i>
                        <span class="notif-badge" id="notifBadge">0</span>
                    </button>
                </div>
                <div id="userProfileBtn" style="position: relative; display: flex; align-items: center; gap: 15px; cursor: pointer;" onmouseover="document.getElementById('userAvatar').style.transform='scale(1.1)'; document.getElementById('userAvatar').style.boxShadow='0 4px 12px rgba(249,157,78,0.4)'" onmouseout="document.getElementById('userAvatar').style.transform='scale(1)'; document.getElementById('userAvatar').style.boxShadow='none'">
                    <div style="text-align: right;">
                        <div style="font-weight: 600; font-size: 0.9rem;">{{ auth()->user() ? auth()->user()->display_name : 'Administrator' }}</div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">{{ auth()->user() ? strtoupper(auth()->user()->role) : 'Active Session' }}</div>
                    </div>
                    <div id="userAvatar" style="transition: transform 0.3s, box-shadow 0.3s; border-radius: 12px;">
                        <x-face :user="auth()->user()" :size="40" />
                    </div>
                    
                    <!-- User Dropdown -->
                    <div id="userDropdown" class="notif-dropdown" style="width: 200px; right: 0; top: 50px;">
                        <a href="{{ route('admin.my_profile') }}" class="notif-item" style="text-decoration: none; color: var(--text-main); font-size: 0.9rem; align-items: center;">
                            <i class="fas fa-user-circle"></i> My Profile
                        </a>
                        <form action="{{ route('logout') }}" method="POST" style="margin: 0;">
                            @csrf
                            <button type="submit" class="notif-item" style="width: 100%; text-align: left; background: none; border: none; color: var(--danger); cursor: pointer; font-family: inherit; font-size: 0.9rem; align-items: center;">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        @if(session('success'))
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    if (typeof showToast === 'function') {
                        showToast('Success', '{!! addslashes(session("success")) !!}', 'success');
                    }
                });
            </script>
        @endif

        @if(session('error'))
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    if (typeof showToast === 'function') {
                        showToast('Error', '{!! addslashes(session("error")) !!}', 'error');
                    }
                });
            </script>
        @endif

        @if($errors->any())
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    if (typeof showToast === 'function') {
                        let errMsg = '';
                        @foreach($errors->all() as $error)
                            errMsg += '{!! addslashes($error) !!}<br>';
                        @endforeach
                        showToast('Error', errMsg, 'error');
                    }
                });
            </script>
        @endif

        @if(auth()->check() && (auth()->user()->isCloser() || auth()->user()->isAdmin() || auth()->user()->isEditor()))
            <!-- Break Requests Section (Moved from dashboard for global access) -->
            <div id="pending-break-requests-card" class="card" style="display: none; margin-bottom: 2rem; border-left: 4px solid var(--primary); background: var(--bg-card); padding: 1.5rem;">
                <div class="card-title" style="font-size: 1.1rem; font-weight: 700; color: var(--text-main); margin-bottom: 1rem; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-exclamation-circle" style="color: var(--primary);"></i> Pending Break Appeals
                </div>
                <div id="pending-break-requests-list" style="display: flex; flex-direction: column; gap: 10px;">
                    <!-- Will be populated dynamically via JS -->
                </div>
            </div>

            <script>
                (function() {
                    const listContainer = document.getElementById('pending-break-requests-list');
                    const cardContainer = document.getElementById('pending-break-requests-card');

                    let seenBreakRequests = new Set();
                    let isInitialBreakFetch = true;
                    
                    function fetchPendingBreaks() {
                        fetch('{{ route("admin.attendance.pending-appeals") }}?t=' + Date.now(), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                            .then(res => res.json())
                            .then(data => {
                                if (data.requests && data.requests.length > 0) {
                                    cardContainer.style.display = 'block';
                                    listContainer.innerHTML = '';
                                    
                                    // Track active IDs in this fetch
                                    let currentIds = new Set();
                                    
                                    data.requests.forEach(req => {
                                        currentIds.add(req.id);
                                        if (!isInitialBreakFetch && !seenBreakRequests.has(req.id)) {
                                            if (typeof showToast === 'function') {
                                                showToast('Pending Break Appeal', `<strong>${req.agent_name}</strong> is requesting to go on <strong>${req.type_label}</strong>`, 'error');
                                            }
                                        }
                                        seenBreakRequests.add(req.id);
                                        const row = document.createElement('div');
                                        row.style.display = 'flex';
                                        row.style.justifyContent = 'space-between';
                                        row.style.alignItems = 'center';
                                        row.style.padding = '0.8rem 1rem';
                                        row.style.background = 'rgba(249, 157, 78, 0.05)';
                                        row.style.borderRadius = '12px';
                                        row.style.border = '1px solid rgba(249, 157, 78, 0.1)';
                                        
                                        row.innerHTML = `
                                            <div>
                                                <strong style="color: var(--text-main);">${req.agent_name}</strong> 
                                                <span class="badge" style="background: rgba(99,102,241,0.1); color: var(--primary); font-size: 0.7rem; margin-left: 10px;">${req.role}</span>
                                                <span class="badge" style="background: rgba(236,72,153,0.1); color: #db2777; font-size: 0.7rem; margin-left: 5px;">${req.type_label}</span>
                                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 3px;">Requested at: ${new Date(req.created_at).toLocaleTimeString()}</div>
                                            </div>
                                            <div style="display: flex; gap: 8px;">
                                                <button onclick="processBreakAppeal(${req.id}, 'approve', this)" class="btn" style="padding: 0.4rem 1rem; font-size: 0.8rem; border-radius: 8px; font-weight: 700; background: var(--success); color: white; border: none; cursor: pointer;">
                                                    <i class="fas fa-check"></i> Allow
                                                </button>
                                                <button onclick="processBreakAppeal(${req.id}, 'decline', this)" class="btn" style="padding: 0.4rem 1rem; font-size: 0.8rem; border-radius: 8px; font-weight: 700; background: var(--danger); color: white; border: none; cursor: pointer;">
                                                    <i class="fas fa-times"></i> Decline
                                                </button>
                                            </div>
                                        `;
                                        listContainer.appendChild(row);
                                    });
                                    
                                    // Clean up seen requests that are no longer pending
                                    for (let id of seenBreakRequests) {
                                        if (!currentIds.has(id)) {
                                            seenBreakRequests.delete(id);
                                        }
                                    }
                                } else {
                                    cardContainer.style.display = 'none';
                                    seenBreakRequests.clear();
                                }
                                isInitialBreakFetch = false;
                            })
                            .catch(err => console.error("Error fetching break appeals", err));
                    }

                    window.processBreakAppeal = function(id, action, btn) {
                        btn.disabled = true;
                        const originalHtml = btn.innerHTML;
                        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                        
                        fetch('{{ url("admin/attendance/appeal") }}/' + id + '/' + action, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            }
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                fetchPendingBreaks();
                                if (typeof showToast === 'function') {
                                    showToast('Success', 'Appeal processed successfully.');
                                }
                            } else {
                                if (typeof showToast === 'function') {
                                    showToast('Error', 'Failed to process appeal.');
                                } else {
                                    alert('Failed to process appeal.');
                                }
                                btn.disabled = false;
                                btn.innerHTML = originalHtml;
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            if (typeof showToast === 'function') {
                                showToast('Error', 'An error occurred.');
                            } else {
                                alert('An error occurred.');
                            }
                            btn.disabled = false;
                            btn.innerHTML = originalHtml;
                        });
                    };

                    // Poll every 15 seconds
                    setInterval(fetchPendingBreaks, 15000);
                    // Initial fetch
                    fetchPendingBreaks();
                })();
            </script>
        @endif

        @yield('content')
    </main>

    <!-- Notification Dropdown -->
    <div class="notif-dropdown" id="notifDropdown">
        <div class="notif-header">
            <strong style="font-size: 1rem;">Notifications</strong>
            <button onclick="markAllAsRead()" style="background:none; border:none; color:var(--primary); font-size:0.8rem; cursor:pointer;">Mark all as read</button>
        </div>
        <div class="notif-list" id="notifList">
            <!-- Notifications will be populated here via JS -->
        </div>
    </div>

    <!-- Send Announcement Modal -->
    <div class="modal-overlay" id="notifModal">
        <div class="modal-content">
            <button class="close-modal" onclick="document.getElementById('notifModal').classList.remove('show')"><i class="fas fa-times"></i></button>
            <h2 style="margin-bottom: 1rem;">Send Announcement</h2>
            <form action="{{ route('admin.notifications.send') }}" method="POST">
                @csrf
                <div style="margin-bottom: 1rem;">
                    <label style="display:block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 5px;">Title</label>
                    <input type="text" name="title" class="form-control" style="width:100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border);" required placeholder="e.g. New Feature Added!">
                </div>
                <div style="margin-bottom: 1rem;">
                    <label style="display:block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 5px;">Message</label>
                    <textarea name="message" class="form-control" rows="4" style="width:100%; padding: 0.6rem; border-radius: 8px; border: 1px solid var(--border);" required placeholder="Details about the update..."></textarea>
                </div>
                <div style="text-align: right;">
                    <button type="button" class="btn btn-ghost" onclick="document.getElementById('notifModal').classList.remove('show')" style="color: var(--text-main); border: 1px solid var(--border);">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send to All</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteConfirmModal">
        <div class="modal-content" style="max-width: 400px; text-align: center;">
            <button class="close-modal" onclick="document.getElementById('deleteConfirmModal').classList.remove('show')"><i class="fas fa-times"></i></button>
            <div style="font-size: 3rem; color: var(--danger); margin-bottom: 1rem;"><i class="fas fa-exclamation-triangle"></i></div>
            <h2 style="margin-bottom: 0.5rem; font-size: 1.5rem;">Recall Announcement?</h2>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 1.5rem;">This will permanently delete this notification from all users' accounts. This action cannot be undone.</p>
            <div style="display: flex; gap: 10px; justify-content: center;">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('deleteConfirmModal').classList.remove('show')" style="flex: 1; color: var(--text-main); border: 1px solid var(--border);">Cancel</button>
                <button type="button" class="btn" id="confirmDeleteBtn" style="flex: 1; background: var(--danger); color: white;"><i class="fas fa-trash-alt"></i> Delete</button>
            </div>
        </div>
    </div>

    <!-- Rush Hour Confirmation Modal -->
    <div class="modal-overlay" id="rhConfirmModal">
        <div class="modal-content" style="max-width: 450px; text-align: center;">
            <button class="close-modal" onclick="document.getElementById('rhConfirmModal').classList.remove('show')"><i class="fas fa-times"></i></button>
            <div id="rhModalIcon" style="font-size: 3rem; color: var(--primary); margin-bottom: 1rem;"><i class="fas fa-rocket"></i></div>
            <h2 id="rhModalTitle" style="margin-bottom: 0.5rem; font-size: 1.5rem;">Start Rush Hour?</h2>
            <p id="rhModalDesc" style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 1.5rem;">Are you sure you want to start a 1-Hour Rush Hour? This will trigger an animation on the scoreboard.</p>
            <div style="display: flex; gap: 10px; justify-content: center;">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('rhConfirmModal').classList.remove('show')" style="flex: 1; color: var(--text-main); border: 1px solid var(--border);">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmRhBtn" style="flex: 1;"><i class="fas fa-check"></i> Yes, Proceed</button>
            </div>
        </div>
    </div>

    <!-- Clock Out Confirmation Modal -->
    <div class="modal-overlay" id="clockOutConfirmModal">
        <div class="modal-content" style="max-width: 400px; text-align: center;">
            <button class="close-modal" onclick="document.getElementById('clockOutConfirmModal').classList.remove('show')"><i class="fas fa-times"></i></button>
            <div style="font-size: 3rem; color: var(--danger); margin-bottom: 1rem;"><i class="fas fa-sign-out-alt"></i></div>
            <h2 style="margin-bottom: 0.5rem; font-size: 1.5rem;">Clock Out?</h2>
            <p id="clockOutModalDesc" style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 1.5rem;">Are you sure you want to clock out for the day?</p>
            <div style="display: flex; gap: 10px; justify-content: center;">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('clockOutConfirmModal').classList.remove('show')" style="flex: 1; color: var(--text-main); border: 1px solid var(--border);">Cancel</button>
                <button type="button" class="btn" id="confirmClockOutBtn" style="flex: 1; background: var(--danger); color: white;"><i class="fas fa-sign-out-alt"></i> Clock Out</button>
            </div>
        </div>
    </div>

    <!-- System Toast Container -->
    <div id="toastContainer"></div>

    {{-- One place to look for anything, on every screen. --}}
    @include('admin.partials.ask_bar')

    <div class="ai-orb ai-orb-wrap" id="aiOrbTrigger" title="Talk with Agent">
        <div class="ai-orb-glow"></div>
        <div class="ai-orb-shape"><div class="ai-orb-spin"></div></div>
    </div>

    <div class="ai-panel-overlay" id="aiPanelOverlay"></div>
    <div class="ai-panel" id="aiPanel">
        <div class="ai-panel-header">
            <span><i class="fas fa-headset" style="margin-right: 8px; color: var(--primary);"></i>Talk with Agent</span>
            <div style="display: flex; align-items: center; gap: 4px;">
                {{-- The header's help button is behind this panel while it is
                     open, so the assistant's own guide is replayed from here. --}}
                <button type="button" id="aiPanelGuide" title="Quick guide"
                        aria-label="Quick guide for the assistant">
                    <i class="fas fa-question-circle"></i>
                </button>
                <button type="button" id="aiPanelClose">&times;</button>
            </div>
        </div>
        <div class="ai-panel-stage">
            <div class="ai-orb-wrap ai-orb-hero-wrap">
                <div class="ai-orb-glow"></div>
                <div class="ai-orb-shape"><div class="ai-orb-spin"></div></div>
            </div>
            <p>Listening — type a message below to get started.</p>
        </div>
        <div class="ai-panel-chat">
            <div class="ai-chat-messages" id="aiChatMessages"></div>
            <div class="ai-panel-chat-row">
                <input type="text" id="aiChatInput" placeholder="Type a message..." autocomplete="off">
                <button type="button" id="aiChatSend"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>

    <script>
        /*
         * Talk with Agent panel — UI only, no backend behind it yet. Sending a
         * message just drops it into the thread as a sent bubble; nothing is posted
         * anywhere and nothing replies. Wiring it to a real agent/AI backend is a
         * separate piece of work once there is an endpoint to call.
         */
        (function () {
            var trigger = document.getElementById('aiOrbTrigger');
            var panel = document.getElementById('aiPanel');
            var overlay = document.getElementById('aiPanelOverlay');
            var closeBtn = document.getElementById('aiPanelClose');
            var input = document.getElementById('aiChatInput');
            var sendBtn = document.getElementById('aiChatSend');
            var messages = document.getElementById('aiChatMessages');

            if (!trigger || !panel) return;

            function openPanel() {
                panel.classList.add('open');
                overlay.classList.add('open');
                setTimeout(function () { input.focus(); }, 300);
            }
            function closePanel() {
                panel.classList.remove('open');
                overlay.classList.remove('open');
            }

            trigger.addEventListener('click', openPanel);
            closeBtn.addEventListener('click', closePanel);
            overlay.addEventListener('click', closePanel);

            function sendMessage() {
                var text = input.value.trim();
                if (!text) return;
                // First message turns the splash screen into a thread; see the
                // .has-messages rules in the stylesheet above.
                panel.classList.add('has-messages');
                var bubble = document.createElement('div');
                bubble.className = 'ai-chat-bubble';
                bubble.textContent = text;
                messages.appendChild(bubble);
                messages.scrollTop = messages.scrollHeight;
                input.value = '';
            }
            sendBtn.addEventListener('click', sendMessage);
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') sendMessage();
            });
        })();
    </script>

    @stack('scripts')
    <script>
        function confirmDelete(event, form, message) {
            event.preventDefault();
            Swal.fire({
                title: 'Are you sure?',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete it!',
                background: document.documentElement.classList.contains('dark-mode') ? '#1e293b' : '#ffffff',
                color: document.documentElement.classList.contains('dark-mode') ? '#f8fafc' : '#1e293b',
                customClass: {
                    popup: 'swal-custom-popup'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        }
    </script>

    <script>
        /*
         * Modal behaviour: the popup itself never moves, only its own content does.
         *
         * Every modal on this page (edit user, add user, rush hour, notifications,
         * and more) opens by setting an element's style.display directly. The dialog
         * box is centred and capped at a fixed max-height with its own scrollbar
         * (see the inline styles on each one), so this script only has two jobs:
         *
         *  1. Lock the page behind it. Without this the mouse wheel over the dimmed
         *     backdrop scrolled the sidebar/table underneath instead of the dialog —
         *     background and dialog moving independently is what read as "broken".
         *  2. Reset the dialog's own scroll to the top the moment it opens. Each
         *     per-row modal stays in the DOM the whole time (only display toggles),
         *     so without this, reopening one that had been scrolled down to reach the
         *     toggles near the bottom would resume there instead of at the title.
         *
         * Rather than editing every modal's open/close handler individually, this
         * watches the whole page for any fixed-position, modal-shaped element.
         */
        (function () {
            var wasOpen = new WeakMap();

            function modalElements() {
                // "Modal" appears anywhere in the id: editUserModal_7, resetPasswordModal_7,
                // addUserModal, notifModal, and so on all match, whatever number is stuck
                // on the end of the per-row ones.
                return Array.from(document.querySelectorAll('[id*="Modal"], .modal-overlay'))
                    .filter(function (el) {
                        return window.getComputedStyle(el).position === 'fixed';
                    });
            }

            function sync() {
                var anyOpen = false;

                modalElements().forEach(function (el) {
                    var isOpen = window.getComputedStyle(el).display !== 'none';

                    if (isOpen && !wasOpen.get(el)) {
                        // The scrollable element is the dialog box itself (.card) when
                        // there is one; a couple of older modals scroll on the outer
                        // wrapper instead, so fall back to that.
                        var dialog = el.querySelector('.card') || el;
                        dialog.scrollTop = 0;
                    }

                    wasOpen.set(el, isOpen);
                    if (isOpen) anyOpen = true;
                });

                document.body.style.overflow = anyOpen ? 'hidden' : '';
            }

            new MutationObserver(sync).observe(document.body, {
                attributes: true,
                attributeFilter: ['style'],
                subtree: true,
            });

            sync();
        })();
    </script>
    {{-- Quick Guide: a short spotlight tour of whatever section this page is.
         The engine and the step data are separate files, so adding a guide for
         another section is a data change, not a code change. --}}
    @php
        $guideSection = null;
        if (request()->routeIs('admin.dashboard'))                                  $guideSection = 'overview';
        elseif (request()->routeIs('admin.team', 'admin.history'))                  $guideSection = 'sales';
        elseif (request()->routeIs('admin.users'))                                  $guideSection = 'users';
        elseif (request()->routeIs('admin.emails.inbox', 'admin.email_templates'))  $guideSection = 'communications';
        elseif (request()->routeIs('admin.leads'))                                  $guideSection = 'leads';
        elseif (request()->routeIs('admin.deals'))                                  $guideSection = 'deals';
        elseif (request()->routeIs('admin.attendance'))                             $guideSection = 'attendance';
        elseif (request()->routeIs('admin.hr.employees'))                           $guideSection = 'hr-employees';
        elseif (request()->routeIs('admin.hr.employee'))                            $guideSection = 'hr-employee-file';
        elseif (request()->routeIs('admin.hr.leaves'))                              $guideSection = 'hr-leaves';
        elseif (request()->routeIs('admin.hr.performance'))                         $guideSection = 'hr-performance';
        elseif (request()->routeIs('admin.hr.settings'))                            $guideSection = 'hr-settings';
        elseif (request()->routeIs('admin.hr.dashboard'))                           $guideSection = 'hr-dashboard';
        elseif (request()->routeIs('admin.hr.reports'))                             $guideSection = 'hr-reports';
        elseif (request()->routeIs('admin.hr.my_attendance'))                       $guideSection = 'my-attendance';
        elseif (request()->routeIs('support.dashboard', 'support.show'))            $guideSection = 'support';
        elseif (request()->routeIs('support.clients'))                              $guideSection = 'queries';
    @endphp
    {{-- The player. It sits in a corner, can be tucked away, and is only given
         an address once it is switched on — an iframe that is never opened
         costs nothing. --}}
    <div id="musicPlayer" class="music-player" hidden>
        <div class="music-bar">
            <span class="music-title">
                <i class="fab fa-spotify" style="color: #1db954;"></i> Music
                <span class="music-playing">still playing</span>
            </span>
            <span class="music-actions">
                <button type="button" id="musicPick" title="Play something else" aria-label="Play something else">
                    <i class="fas fa-link"></i>
                </button>
                <button type="button" id="musicCollapse" title="Tuck it away, keep playing"
                        aria-label="Tuck the player away and keep playing">
                    <i class="fas fa-chevron-down"></i>
                </button>
                <button type="button" id="musicClose" title="Close the player" aria-label="Close the player">
                    <i class="fas fa-xmark"></i>
                </button>
            </span>
        </div>

        {{-- Paste anything from Spotify — a playlist, an album, one song, an
             artist, a podcast — and it plays here rather than in another tab. --}}
        <form class="music-pick" id="musicPickForm" hidden onsubmit="return false;">
            <input type="text" id="musicUrl" placeholder="Paste a Spotify link" autocomplete="off" spellcheck="false">
            <button type="submit" id="musicUrlGo">Play</button>
            <small id="musicHint">Open a playlist in Spotify, Share &rarr; Copy link, paste it here.</small>
        </form>

        <div class="music-frame">
            <iframe id="musicFrame" title="Spotify player" style="border-radius: 12px" src="" width="100%" height="420"
                    frameborder="0" allowfullscreen=""
                    allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"
                    loading="lazy"></iframe>
        </div>
    </div>

    {{-- Notepad. Notes are per user and stored server-side, so they survive a
         reload; only the window's position and size are kept per device. --}}
    <script>window.CRM_NOTES_URL = @json(url('notes'));</script>
    <script src="@versioned('js/notepad.js')"></script>
    <script>
        (function () {
            if (!window.Notepad) return;
            Notepad.init();
            var b = document.getElementById('notepadBtn');
            if (b) b.addEventListener('click', function () { Notepad.toggle(); });
        })();
    </script>

    <script>
    /*
     * Figures count up to themselves the first time they are seen.
     *
     * The number in the markup is what the server rendered and is what stays on
     * screen; this only animates the way it arrives, and only once. Anybody who
     * has asked for less movement gets the number straight away.
     */
    (function () {
        var still = window.matchMedia('(prefers-reduced-motion: reduce)').matches
            || document.documentElement.classList.contains('reduce-motion');

        if (still || !window.IntersectionObserver) return;

        function run(el) {
            var target = Number(el.dataset.count);
            if (!isFinite(target) || target <= 0) return;

            var prefix = el.dataset.prefix || '';
            var suffix = el.dataset.suffix || '';
            var final = el.textContent;
            var started = null;
            var ms = 900;

            function frame(now) {
                started = started || now;
                var done = Math.min(1, (now - started) / ms);

                // Fast at first and easing out, so the last digits settle.
                var eased = 1 - Math.pow(1 - done, 3);

                if (done === 1) {
                    el.textContent = final;                 // exactly what was rendered
                    return;
                }

                el.textContent = prefix + Math.round(target * eased).toLocaleString() + suffix;
                requestAnimationFrame(frame);
            }

            requestAnimationFrame(frame);
        }

        var seen = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;

                seen.unobserve(entry.target);
                run(entry.target);
            });
        }, { threshold: 0.4 });

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-count]').forEach(function (el) { seen.observe(el); });
        });
    })();
    </script>

    {{-- Being asked for your screen has to reach you wherever you are, so the
         host's half of Quick Huddle rides along on every page. It does nothing
         until somebody asks. --}}
    @include('admin.huddle._host')

    <script src="@versioned('js/quick-guide.js')"></script>
    <script src="@versioned('js/quick-guide-steps.js')"></script>
    <script>
        (function () {
            var section = @json($guideSection);
            var btn = document.getElementById('quickGuideBtn');
            if (!window.QuickGuide) return;

            // Nothing to point at on this page: hide the trigger rather than
            // offering a guide that would open empty.
            if (!section || !QuickGuide.has(section)) {
                if (btn) btn.style.display = 'none';
                return;
            }

            if (btn) {
                btn.addEventListener('click', function () { QuickGuide.start(section); });
            }

            // The charts draw after load and the cards restore their saved order
            // on the way in; measuring before that settles would spotlight the
            // wrong rectangle.
            window.setTimeout(function () { QuickGuide.autoStart(section); }, 700);
        })();

        /*
         * The assistant guide is tied to the panel rather than to a page: its
         * steps point at elements that only exist once the panel is open, so it
         * runs the first time someone opens it. After that the help button
         * replays it whenever the panel is open, instead of the page's guide.
         */
        (function () {
            var orb = document.getElementById('aiOrbTrigger');
            var panel = document.getElementById('aiPanel');
            if (!orb || !panel || !window.QuickGuide) return;

            orb.addEventListener('click', function () {
                window.setTimeout(function () {
                    if (panel.classList.contains('open')) QuickGuide.autoStart('assistant');
                }, 450);   // after the panel's slide-in settles
            });

            var replay = document.getElementById('aiPanelGuide');
            if (replay) {
                replay.addEventListener('click', function () { QuickGuide.start('assistant'); });
            }

        })();
    </script>
</body>
<script>
    const themeToggleBtn = document.getElementById('themeToggle');
    const themeIcon = themeToggleBtn.querySelector('i');

    function updateThemeIcon() {
        if (document.documentElement.classList.contains('dark-mode')) {
            themeIcon.classList.remove('fa-moon');
            themeIcon.classList.add('fa-sun');
        } else {
            themeIcon.classList.remove('fa-sun');
            themeIcon.classList.add('fa-moon');
        }
    }

    // Set initial icon
    updateThemeIcon();

    themeToggleBtn.addEventListener('click', () => {
        document.documentElement.classList.toggle('dark-mode');
        
        if (document.documentElement.classList.contains('dark-mode')) {
            localStorage.setItem('theme', 'dark');
        } else {
            localStorage.setItem('theme', 'light');
        }
        
        updateThemeIcon();
    });

    /*
     * Accessibility menu.
     *
     * The dark-mode handler above still owns the theme itself; this only opens
     * and closes the panel and keeps each row's On/Off pill in step. Reduce
     * motion and larger text are applied by adding a class to <html>, which the
     * stylesheet acts on, and are re-applied before paint on the next load by
     * the script in <head>.
     */
    (function () {
        var wrap   = document.getElementById('a11yWrap');
        var btn    = document.getElementById('a11yBtn');
        var menu   = document.getElementById('a11yMenu');
        if (!wrap || !btn || !menu) return;

        var rowTheme  = document.getElementById('themeToggle');
        var rowMotion = document.getElementById('a11yMotion');
        var rowText   = document.getElementById('a11yText');
        var rowMusic  = document.getElementById('a11yMusic');
        var root      = document.documentElement;

        function store(key, value) {
            try { localStorage.setItem(key, value); } catch (e) { /* not fatal */ }
        }

        function syncRows() {
            if (rowTheme)  rowTheme.classList.toggle('is-on',  root.classList.contains('dark-mode'));
            if (rowMotion) rowMotion.classList.toggle('is-on', root.classList.contains('reduce-motion'));
            if (rowText)   rowText.classList.toggle('is-on',   root.classList.contains('large-text'));
            var mp = document.getElementById('musicPlayer');
            if (rowMusic && mp) rowMusic.classList.toggle('is-on', !mp.hidden);
            [rowTheme, rowMotion, rowText, rowMusic].forEach(function (r) {
                if (r) r.setAttribute('aria-checked', r.classList.contains('is-on') ? 'true' : 'false');
            });
        }

        function open()  { menu.classList.add('show');    btn.setAttribute('aria-expanded', 'true');  syncRows(); }
        function close() { menu.classList.remove('show'); btn.setAttribute('aria-expanded', 'false'); }

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            menu.classList.contains('show') ? close() : open();
        });

        // Clicking a row should act and leave the menu open, so several
        // preferences can be changed in one visit.
        menu.addEventListener('click', function (e) { e.stopPropagation(); });
        document.addEventListener('click', close);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') close();
        });

        if (rowTheme) rowTheme.addEventListener('click', syncRows);   // runs after the theme handler

        if (rowMotion) {
            rowMotion.addEventListener('click', function () {
                var on = root.classList.toggle('reduce-motion');
                store('a11yMotion', on ? 'off' : 'on');   // 'off' = motion is off
                syncRows();
            });
        }

        if (rowText) {
            rowText.addEventListener('click', function () {
                var on = root.classList.toggle('large-text');
                store('a11yText', on ? 'large' : 'normal');
                syncRows();
            });
        }

        if (rowMusic) {
            rowMusic.addEventListener('click', function () {
                window.toggleMusicPlayer();
                syncRows();
            });
        }

        syncRows();
    })();

    /*
     * The music player.
     *
     * Spotify's own embed, given its address only when somebody switches it on,
     * so a page nobody wants music on never loads a third-party frame. Whether
     * it is open, where it was tucked to, and the playlist are remembered per
     * person on that device.
     */
    (function () {
        var player = document.getElementById('musicPlayer');
        if (!player) return;

        var frame = document.getElementById('musicFrame');
        var key = 'crm.music.' + (window.CRM_USER_ID || 0);
        var DEFAULT_PLAYLIST = 'https://open.spotify.com/embed/playlist/37i9dQZF1DX0XUsuxWHRQd';

        function state() {
            try { return JSON.parse(localStorage.getItem(key) || '{}'); } catch (e) { return {}; }
        }

        function save(patch) {
            try { localStorage.setItem(key, JSON.stringify(Object.assign(state(), patch))); } catch (e) { /* private mode */ }
        }

        /*
         * Spotify's own web player cannot be put in a frame — it answers with
         * frame-ancestors 'self' and the browser refuses. Its embed can, and the
         * embed takes any playlist, album, track, artist or show, so pasting a
         * link is how somebody brings their own music in here.
         *
         * Full songs rather than thirty-second previews need the listener to be
         * signed in to Spotify in this same browser; that is Spotify's rule, not
         * something this page can decide.
         */
        function embedUrl(input) {
            var value = (input || '').trim();
            if (!value) return null;

            // spotify:playlist:37i9... as well as the https link
            var uri = value.match(/^spotify:([a-z]+):([A-Za-z0-9]+)$/);
            if (uri) return 'https://open.spotify.com/embed/' + uri[1] + '/' + uri[2];

            var link = value.match(/open\.spotify\.com\/(?:intl-[a-z-]+\/)?(?:embed\/)?(playlist|album|track|artist|show|episode)\/([A-Za-z0-9]+)/);
            if (link) return 'https://open.spotify.com/embed/' + link[1] + '/' + link[2];

            return null;
        }

        function play(url, remember) {
            frame.src = url + (url.indexOf('?') === -1 ? '?' : '&') + 'utm_source=generator&theme=0';
            if (remember !== false) save({ url: url });
        }

        function load() {
            // getAttribute, not .src: an iframe with an empty src reports the
            // page's own address, which would read as already loaded.
            if (!frame.getAttribute('src')) {
                play(state().url || DEFAULT_PLAYLIST, false);
            }
        }

        window.toggleMusicPlayer = function (force) {
            var show = typeof force === 'boolean' ? force : player.hidden;

            if (show) {
                load();
                player.hidden = false;
            } else {
                player.hidden = true;
            }

            save({ open: show });
            return show;
        };

        document.getElementById('musicClose').addEventListener('click', function () {
            window.toggleMusicPlayer(false);
            // Stop whatever is playing: a closed player that keeps singing is a
            // bug report waiting to happen.
            frame.src = '';
            save({ open: false });
        });

        // ---- bringing your own music in ------------------------------------
        var pickForm = document.getElementById('musicPickForm');
        var urlInput = document.getElementById('musicUrl');
        var hint = document.getElementById('musicHint');

        document.getElementById('musicPick').addEventListener('click', function () {
            pickForm.hidden = !pickForm.hidden;
            if (!pickForm.hidden) {
                urlInput.value = state().url || '';
                urlInput.focus();
                urlInput.select();
            }
        });

        function applyUrl() {
            var url = embedUrl(urlInput.value);

            if (!url) {
                hint.textContent = 'That is not a Spotify link. It should look like open.spotify.com/playlist/...';
                hint.classList.add('is-bad');
                return;
            }

            hint.textContent = 'Playing it now. It will still be here next time.';
            hint.classList.remove('is-bad');
            play(url);
            pickForm.hidden = true;
        }

        document.getElementById('musicUrlGo').addEventListener('click', applyUrl);
        urlInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); applyUrl(); }
        });

        // A link pasted into the box plays straight away; nobody wants to paste
        // and then hunt for a button.
        urlInput.addEventListener('paste', function () {
            setTimeout(applyUrl, 0);
        });

        document.getElementById('musicCollapse').addEventListener('click', function () {
            var collapsed = player.classList.toggle('is-collapsed');
            this.querySelector('i').className = collapsed ? 'fas fa-chevron-up' : 'fas fa-chevron-down';
            save({ collapsed: collapsed });
        });

        var saved = state();
        if (saved.collapsed) {
            player.classList.add('is-collapsed');
            document.querySelector('#musicCollapse i').className = 'fas fa-chevron-up';
        }
        if (saved.open) {
            window.toggleMusicPlayer(true);
        }
    })();

    /*
     * The rest of what the floor gets called out for.
     *
     * Rush hour keeps its own button and its own confirmation, untouched —
     * everybody already knows that gesture. What hovering adds is the others:
     * a break, prayer time, lunch, and whatever else an admin has set up.
     */
    (function () {
        var wrap = document.getElementById('fmWrap');
        var list = document.getElementById('fmList');
        if (!wrap || !list) return;

        var TOKEN = '{{ csrf_token() }}';
        var MOMENTS = '{{ route('admin.moments') }}';
        var START = '{{ url('admin/moments') }}';

        function esc(value) {
            return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }

        function paint(data) {
            var moments = data.moments || [];

            if (!moments.length) {
                list.innerHTML = '<div class="fm-waiting">Nothing set up yet.</div>';
                return;
            }

            var running = data.running;

            list.innerHTML = moments.map(function (m) {
                var on = running && running.name === m.name;

                return '<button type="button" class="fm-item' + (on ? ' is-on' : '') + '" data-id="' + m.id +
                       '" data-name="' + esc(m.name) + '" data-mins="' + m.minutes + '">' +
                       '<i class="fas ' + esc(m.icon) + '" style="color: ' + esc(m.tone) + ';"></i>' +
                       '<span>' + esc(m.name) + '</span>' +
                       '<span class="fm-mins">' + m.minutes + ' min</span></button>';
            }).join('');
        }

        function load() {
            fetch(MOMENTS, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
                .then(paint)
                .catch(function () { list.innerHTML = '<div class="fm-waiting">Could not load these.</div>'; });
        }

        list.addEventListener('click', function (e) {
            var item = e.target.closest('.fm-item');
            if (!item || !item.dataset.id) return;

            var name = item.dataset.name;
            var mins = item.dataset.mins;

            // The same question rush hour asks, because it is the same kind of
            // thing: the whole floor is about to see it.
            var modal = document.getElementById('rhConfirmModal');
            if (modal) {
                document.getElementById('rhModalIcon').innerHTML = item.querySelector('i').outerHTML;
                document.getElementById('rhModalTitle').textContent = 'Start ' + name + '?';
                document.getElementById('rhModalDesc').textContent =
                    'The whole floor sees this for the next ' + mins + ' minutes.';

                var go = document.getElementById('confirmRhBtn');
                var fresh = go.cloneNode(true);       // off with rush hour's own handler
                fresh.className = 'btn btn-primary';
                fresh.style.background = '';
                fresh.innerHTML = '<i class="fas fa-play"></i> Start ' + name;
                go.parentNode.replaceChild(fresh, go);

                fresh.addEventListener('click', function once() {
                    modal.classList.remove('show');
                    begin(item.dataset.id, name);
                    // Rush hour's own handler goes back on the next page load;
                    // until then this button belongs to the moment that was picked.
                    fresh.removeEventListener('click', once);
                });

                modal.classList.add('show');
                return;
            }

            begin(item.dataset.id, name);
        });

        function begin(id, name) {
            var message = document.getElementById('rushHourMsg');

            fetch(START + '/' + id + '/start', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': TOKEN,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ message: message ? message.value : '' }),
            })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
            .then(function (data) {
                paint({ moments: null, running: data.running });
                load();

                if (typeof showToast === 'function' && data.running) {
                    showToast(name + ' started',
                              'The floor sees it until ' + new Date(data.running.endsAt).toLocaleTimeString() + '.',
                              'success');
                }
            })
            .catch(function () {
                if (typeof showToast === 'function') showToast('Not started', 'That did not go through.', 'error');
            });
        }

        // Only when somebody looks: a menu nobody opens costs nothing.
        var loaded = false;
        wrap.addEventListener('mouseenter', function () { if (!loaded) { loaded = true; load(); } });
        wrap.addEventListener('focusin', function () { if (!loaded) { loaded = true; load(); } });
    })();

    const toggleRushHourBtn = document.getElementById('toggleRushHourBtn');
    let isRushHourActive = false;
    let pendingRhAction = null;

    if (toggleRushHourBtn) {
        // Initial Check
        const statusUrl = '{{ auth()->user()->company ? route("scoreboard.rush-hour.status", ["company_slug" => auth()->user()->company->slug]) : "" }}';
        if (statusUrl) {
            fetch(statusUrl + '?t=' + Date.now(), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(data => {
                    if (data.active) {
                        setRushHourUIActive(true);
                    }
                })
                .catch(err => console.error(err));
        }

        toggleRushHourBtn.addEventListener('click', function() {
            const modal = document.getElementById('rhConfirmModal');
            const icon = document.getElementById('rhModalIcon');
            const title = document.getElementById('rhModalTitle');
            const desc = document.getElementById('rhModalDesc');
            const btn = document.getElementById('confirmRhBtn');

            if (!isRushHourActive) {
                pendingRhAction = 'start';
                icon.style.color = 'var(--primary)';
                icon.innerHTML = '<i class="fas fa-rocket"></i>';
                title.textContent = 'Start Rush Hour?';
                desc.textContent = 'Are you sure you want to start a 1-Hour Rush Hour? This will trigger a dynamic 3D animation on the scoreboard.';
                btn.className = 'btn btn-primary';
                btn.style.background = '';
                btn.innerHTML = '<i class="fas fa-rocket"></i> Start Now';
            } else {
                pendingRhAction = 'stop';
                icon.style.color = 'var(--danger)';
                icon.innerHTML = '<i class="fas fa-hand-paper"></i>';
                title.textContent = 'Stop Rush Hour?';
                desc.textContent = 'Are you sure you want to STOP the active Rush Hour? The scoreboard will return to normal immediately.';
                btn.className = 'btn';
                btn.style.background = 'var(--danger)';
                btn.style.color = 'white';
                btn.innerHTML = '<i class="fas fa-stop"></i> Stop Now';
            }
            modal.classList.add('show');
        });

        document.getElementById('confirmRhBtn').addEventListener('click', function() {
            const modal = document.getElementById('rhConfirmModal');
            modal.classList.remove('show');

            if (pendingRhAction === 'start') {
                toggleRushHourBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Starting...';
                toggleRushHourBtn.disabled = true;
                let customMsg = document.getElementById('rushHourMsg') ? document.getElementById('rushHourMsg').value : '';
                
                fetch('{{ route("admin.rush_hour.start") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ message: customMsg })
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        if(typeof showToast === 'function') showToast('Rush Hour Started', 'It will end at ' + new Date(data.end_time).toLocaleTimeString());
                        setRushHourUIActive(true);
                    } else {
                        if(typeof showToast === 'function') showToast('Error', 'Failed to start Rush Hour.');
                        setRushHourUIActive(false);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    if(typeof showToast === 'function') showToast('Error', 'An error occurred.');
                    setRushHourUIActive(false);
                });
            } else if (pendingRhAction === 'stop') {
                toggleRushHourBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Stopping...';
                toggleRushHourBtn.disabled = true;
                
                fetch('{{ route("admin.rush_hour.stop") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        if(typeof showToast === 'function') showToast('Rush Hour Stopped', 'Scoreboard returned to normal.');
                        setRushHourUIActive(false);
                    } else {
                        if(typeof showToast === 'function') showToast('Error', 'Failed to stop Rush Hour.');
                        setRushHourUIActive(true);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    if(typeof showToast === 'function') showToast('Error', 'An error occurred.');
                    setRushHourUIActive(true);
                });
            }
        });
    }

    function setRushHourUIActive(active) {
        isRushHourActive = active;
        toggleRushHourBtn.disabled = false;
        if (active) {
            toggleRushHourBtn.innerHTML = '<i class="fas fa-stop"></i> Stop Rush Hour';
            toggleRushHourBtn.style.background = '#ef4444';
            toggleRushHourBtn.style.color = '#fff';
        } else {
            toggleRushHourBtn.innerHTML = '<i class="fas fa-rocket"></i> Start Rush Hour';
            toggleRushHourBtn.style.background = ''; // reset to class default
            toggleRushHourBtn.style.color = '';
        }
    }
    // Notification JS Logic
    // Notification JS Logic
    const notifBtn = document.getElementById('notifBtn');
    const notifDropdown = document.getElementById('notifDropdown');
    const notifBadge = document.getElementById('notifBadge');
    const notifList = document.getElementById('notifList');
    
    if (notifBtn) {
        notifBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notifDropdown.classList.toggle('show');
        });
        
        document.addEventListener('click', function(e) {
            if (!notifBtn.contains(e.target) && !notifDropdown.contains(e.target)) {
                notifDropdown.classList.remove('show');
            }
        });
    }

    /*
     * Opening one.
     *
     * Every notification already knew where it came from — a lead, a leave
     * request, a huddle — and threw that away before it reached the page, so
     * the bell could tell somebody that something needed them and then leave
     * them to go and find it. It is marked read and followed in one tap now.
     */
    window.openNotification = function (id, url, isUnread) {
        if (isUnread) {
            try { markAsRead(id); } catch (e) { /* the page still goes */ }
        }

        if (url) {
            window.location.href = url;
        }
    };

    let seenNotificationIds = new Set();
    const userProfileBtn = document.getElementById('userProfileBtn');
    const userDropdown = document.getElementById('userDropdown');
    if (userProfileBtn) {
        userProfileBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.classList.toggle('show');
        });
        document.addEventListener('click', function(e) {
            if (!userProfileBtn.contains(e.target) && !userDropdown.contains(e.target)) {
                userDropdown.classList.remove('show');
            }
        });
    }
    let initialLoad = true;
    const isUserAdmin = {{ auth()->user()->isAdmin() ? 'true' : 'false' }};

    function fetchNotifications() {
        fetch('{{ route("notifications.fetch") }}?t=' + Date.now(), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
            .then(res => res.json())
            .then(data => {
                if (data.error) return;

                if (data.is_on_break && !document.getElementById('break-lock-overlay')) {
                    window.location.reload();
                    return;
                }

                if (!data.is_on_break && document.getElementById('break-lock-overlay')) {
                    window.location.reload();
                    return;
                }
                
                // Update badge
                if (data.unread_count > 0) {
                    notifBadge.style.display = 'flex';
                    notifBadge.textContent = data.unread_count > 9 ? '9+' : data.unread_count;
                } else {
                    notifBadge.style.display = 'none';
                }
                
                // Update list
                notifList.innerHTML = '';
                if (data.notifications.length === 0) {
                    notifList.innerHTML = '<div style="padding: 2rem; text-align: center; color: var(--text-muted); font-size: 0.9rem;">No notifications yet.</div>';
                } else {
                    data.notifications.forEach(n => {
                        let isUnread = n.read_at === null;
                        
                        // Check if it's a completely new notification we haven't seen before
                        if (!initialLoad && isUnread && !seenNotificationIds.has(n.id)) {
                            showToast(n.data.title, n.data.message);
                        }

                        seenNotificationIds.add(n.id);
                        
                        let item = document.createElement('div');
                        item.className = 'notif-item ' + (isUnread ? 'unread' : 'read');
                        item.style.position = 'relative';
                        
                        let html = `
                            <div class="notif-icon" onclick="markAsRead('${n.id}')" style="cursor: pointer; background: ${isUnread ? 'rgba(239,68,68,0.1)' : 'rgba(16,185,129,0.1)'}; color: ${isUnread ? 'var(--danger)' : 'var(--success)'};">
                                <i class="${n.data.icon || 'fas fa-info-circle'}"></i>
                            </div>
                            <div style="flex-grow: 1; padding-right: 20px; cursor: ${n.data.url || isUnread ? 'pointer' : 'default'};" onclick="openNotification('${n.id}', ${JSON.stringify(n.data.url || '')}, ${isUnread})">
                                <div style="font-weight: 600; font-size: 0.9rem; color: var(--text-main); display: flex; justify-content: space-between;">
                                    ${n.data.title}
                                    ${isUnread ? '<span style="font-size: 0.65rem; background: var(--danger); color: white; padding: 2px 6px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;"><i class="fas fa-circle" style="font-size: 0.4rem;"></i> Unread</span>' : '<span style="font-size: 0.65rem; background: var(--success); color: white; padding: 2px 6px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;"><i class="fas fa-check"></i> Read</span>'}
                                </div>
                                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 3px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">${n.data.message}</div>
                                <div style="font-size: 0.7rem; color: var(--primary); margin-top: 5px;">${new Date(n.created_at).toLocaleString()}</div>
                            </div>
                        `;
                        
                        if (isUserAdmin) {
                            html += `<button onclick="deleteGlobalNotification('${n.id}')" title="Recall Announcement" style="position:absolute; top: 1rem; right: 1rem; background:none; border:none; color: var(--danger); cursor:pointer; font-size: 1rem;"><i class="fas fa-trash-alt"></i></button>`;
                        }
                        
                        item.innerHTML = html;
                        notifList.appendChild(item);
                    });
                }
                initialLoad = false;
            })
            .catch(err => console.error(err));
    }

    function markAsRead(id) {
        fetch('{{ url("admin/notifications/read") }}/' + id, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        }).then(() => fetchNotifications());
    }

    let notificationToDelete = null;

    function deleteGlobalNotification(id) {
        notificationToDelete = id;
        document.getElementById('deleteConfirmModal').classList.add('show');
    }

    document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
        if (notificationToDelete) {
            let btn = this;
            let originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
            btn.disabled = true;

            fetch('{{ url("admin/notifications") }}/' + notificationToDelete, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }
            }).then(() => {
                document.getElementById('deleteConfirmModal').classList.remove('show');
                btn.innerHTML = originalHtml;
                btn.disabled = false;
                notificationToDelete = null;
                fetchNotifications();
            }).catch(err => {
                console.error(err);
                btn.innerHTML = originalHtml;
                btn.disabled = false;
                alert("An error occurred while deleting.");
            });
        }
    });

    function markAllAsRead() {
        fetch('{{ route("notifications.read_all") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        }).then(() => fetchNotifications());
    }

    // Ask for Notification permission on first user interaction
    document.body.addEventListener('click', function () {
        if ("Notification" in window && Notification.permission !== "granted" && Notification.permission !== "denied") {
            Notification.requestPermission();
        }
    }, { once: true });

    function playNotificationSound() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            [880, 1175].forEach((freq, i) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = freq;
                const start = ctx.currentTime + i * 0.11;
                gain.gain.setValueAtTime(0, start);
                gain.gain.linearRampToValueAtTime(0.18, start + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.001, start + 0.16);
                osc.connect(gain).connect(ctx.destination);
                osc.start(start);
                osc.stop(start + 0.18);
            });
        } catch (e) { /* audio not available */ }
    }

    function showToast(title, msg, type = 'info') {
        playNotificationSound();
        // Desktop notification if tab is inactive
        if (document.hidden && "Notification" in window && Notification.permission === "granted") {
            new Notification(title, {
                body: msg,
                icon: '{{ asset("img/favicon.png") }}'
            });
        }

        const container = document.getElementById('toastContainer');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = 'sys-toast';
        
        let iconHtml = '<i class="fas fa-bell" style="font-size: 1.8rem;"></i>';
        if (type === 'success') {
            toast.classList.add('toast-success');
            iconHtml = '<i class="fas fa-check-circle" style="font-size: 1.8rem;"></i>';
        } else if (type === 'error') {
            toast.classList.add('toast-error');
            iconHtml = '<i class="fas fa-exclamation-circle" style="font-size: 1.8rem;"></i>';
        } else {
            toast.classList.add('toast-info');
        }

        toast.innerHTML = `
            <div style="display:flex; align-items:center;">${iconHtml}</div>
            <div style="flex-grow:1; padding-right:15px; word-wrap: break-word; overflow-wrap: break-word; max-width: 320px;">
                <div style="font-weight: 700; font-size: 0.95rem; margin-bottom: 3px;">${title}</div>
                <div style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.4;">${msg}</div>
            </div>
            <button class="close-toast"><i class="fas fa-times"></i></button>
        `;

        container.appendChild(toast);

        // Slide in
        setTimeout(() => toast.classList.add('show'), 10);

        // Close button event
        toast.querySelector('.close-toast').addEventListener('click', () => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 500);
        });

        // Auto close after 10 seconds
        setTimeout(() => {
            if(toast.parentElement) {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 500);
            }
        }, 10000);
    }

    // Polling every 3 seconds for near-instant global breaks
    setInterval(fetchNotifications, 3000);
    // Initial fetch
    fetchNotifications();
    
    // Global chat polling for unread messages popup
    let previousChatCounts = {};
    let isInitialChatPoll = true;
    function pollGlobalChat() {
        if (!'{{ auth()->check() }}') return;
        if (window.location.pathname.includes('/chat')) return;
        
        fetch('{{ url('/chat/api/poll') }}', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
            .then(res => res.json())
            .then(convs => {
                let totalUnread = 0;
                convs.forEach(c => {
                    totalUnread += c.unread_count;
                    if (!isInitialChatPoll && c.unread_count > (previousChatCounts[c.id] || 0)) {
                        if (!window.location.pathname.includes('/chat')) {
                            showToast('New Chat Message', `You have unread messages in ${c.type === 'group' ? '#' : ''}${c.name}`, 'info');
                        }
                    }
                    previousChatCounts[c.id] = c.unread_count;
                });
                
                isInitialChatPoll = false;
                
                const chatBadge = document.getElementById('globalChatBadge');
                if (chatBadge) {
                    if (totalUnread > 0) {
                        chatBadge.style.display = 'inline-flex';
                        chatBadge.textContent = totalUnread > 9 ? '9+' : totalUnread;
                    } else {
                        chatBadge.style.display = 'none';
                    }
                }
            })
            .catch(e => console.log(e));
    }
    
    setInterval(pollGlobalChat, 4000);
    pollGlobalChat();

    let clockOutFormToSubmit = null;
    window.showClockOutConfirm = function(isOnBreak, formId) {
        const desc = isOnBreak 
            ? "Are you sure you want to clock out for the day? This will automatically end your break." 
            : "Are you sure you want to clock out for the day?";
        document.getElementById('clockOutModalDesc').textContent = desc;
        clockOutFormToSubmit = document.getElementById(formId);
        document.getElementById('clockOutConfirmModal').classList.add('show');
    }

    document.getElementById('confirmClockOutBtn').addEventListener('click', function() {
        if (!clockOutFormToSubmit) return;

        // requestSubmit fires a submit event; form.submit() does not, and the
        // dashboard listens for that event to clock out without reloading the
        // whole page. Older browsers fall back to the plain submit.
        if (typeof clockOutFormToSubmit.requestSubmit === 'function') {
            clockOutFormToSubmit.requestSubmit();
        } else {
            clockOutFormToSubmit.submit();
        }

        var modal = document.getElementById('clockOutConfirmModal');
        if (modal) modal.classList.remove('show');
    });

    @if(auth()->user()->isAdmin() || auth()->user()->isFloorManager())
    (function() {
        let notifiedOvertimes = {};
        
        function playBeep() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                
                function beep(time, freq1, freq2) {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(freq1, time);
                    osc.frequency.setValueAtTime(freq2, time + 0.1);
                    gain.gain.setValueAtTime(0.3, time);
                    gain.gain.exponentialRampToValueAtTime(0.01, time + 0.3);
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start(time);
                    osc.stop(time + 0.3);
                }
                
                beep(ctx.currentTime, 800, 600);
                beep(ctx.currentTime + 0.4, 800, 600);
                beep(ctx.currentTime + 0.8, 800, 600);
            } catch(e) {}
        }

        function checkOvertimes() {
            fetch('{{ route("admin.attendance.active-overtimes") }}', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(res => res.json())
                .then(data => {
                    if(data.success && data.overtimes.length > 0) {
                        let newOvertime = false;
                        let now = Date.now();
                        
                        data.overtimes.forEach(ot => {
                            // Alert if not notified, or if last notification was > 60 seconds ago
                            if (!notifiedOvertimes[ot.id] || (now - notifiedOvertimes[ot.id]) > 60000) {
                                notifiedOvertimes[ot.id] = now;
                                newOvertime = true;
                                
                                if (typeof showToast === 'function') {
                                    showToast('Break Overtime!', `<strong>${ot.agent_name}</strong> is in overtime for their ${ot.type}! (${ot.overtime_mins} mins over)`, 'error');
                                }
                            }
                        });
                        
                        if (newOvertime) {
                            playBeep();
                        }
                    }
                })
                .catch(err => console.error(err));
        }

        setInterval(checkOvertimes, 20000); // Check every 20 seconds
    })();
    @endif
</script>
</html>
