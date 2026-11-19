/*
 * Floating notepad.
 *
 * A window that lives over the dashboard: drag it by its title bar, resize it
 * from the bottom-right corner, minimise it to a tab on the right edge, or close
 * it. Notes are per user and stored server-side (see NoteController), so they
 * survive a reload, a different browser and a different machine.
 *
 * The one rule everything here is built around: nothing the user types may be
 * lost. Typing saves on a short debounce, and closing, minimising, switching
 * note, hiding the tab or leaving the page all flush any pending edit first —
 * the last of those with sendBeacon, because a normal request is not guaranteed
 * to survive the page going away.
 *
 * Window geometry (position, size, minimised) is per-device chrome rather than
 * user data, so it lives in localStorage; the notes themselves never do.
 */
(function (window, document) {
    'use strict';

    var SAVE_DEBOUNCE_MS = 700;
    var GEOM_KEY_PREFIX = 'crm.notepadGeom.';
    var MIN_W = 300, MIN_H = 240;

    /* Docked width limits. Wide enough to write in, never so wide the dashboard
     * beside it becomes unusable. */
    var DOCK_MIN_W = 280, DOCK_DEFAULT_W = 360, DOCK_MAX_RATIO = 0.5;

    var state = {
        root: null,
        notes: [],
        activeId: null,
        dirty: false,
        saveTimer: null,
        loaded: false,
        docked: false,
        dockWidth: DOCK_DEFAULT_W
    };

    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function base() {
        // Set by the layout; the app can live under a subfolder (…/crm).
        return window.CRM_NOTES_URL || '/notes';
    }

    function api(method, path, body) {
        return fetch(base() + path, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: body ? JSON.stringify(body) : undefined
        }).then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.status === 204 ? null : r.json();
        });
    }

    // ------------------------------------------------------------- geometry

    function geomKey() {
        return GEOM_KEY_PREFIX + (window.CRM_USER_ID == null ? 'anon' : window.CRM_USER_ID);
    }

    function readGeom() {
        try {
            var raw = window.localStorage.getItem(geomKey());
            return raw ? JSON.parse(raw) : null;
        } catch (e) { return null; }
    }

    function writeGeom() {
        if (!state.root) return;
        try {
            var r = state.root;
            // The floating size is read from the inline style, not offsetWidth:
            // while docked or minimised the box on screen is a different shape,
            // and saving that would lose the size the floating window had.
            window.localStorage.setItem(geomKey(), JSON.stringify({
                left: parseInt(r.style.left, 10) || 0,
                top: parseInt(r.style.top, 10) || 0,
                width: parseInt(r.style.width, 10) || r.offsetWidth,
                height: parseInt(r.style.height, 10) || r.offsetHeight,
                minimized: r.classList.contains('np-minimized'),
                open: r.classList.contains('np-open'),
                listHidden: r.classList.contains('np-list-hidden'),
                docked: state.docked,
                dockWidth: state.dockWidth
            }));
        } catch (e) { /* geometry is a convenience, not data */ }
    }

    /* Keep the window on screen — after a resize of the browser, or a restore of
     * geometry saved on a larger monitor, it must not sit off the edge. */
    function clampToViewport() {
        var r = state.root;
        if (!r || r.classList.contains('np-minimized')) return;
        if (state.docked) { applyDockWidth(); return; }
        // A closed window is display:none and measures 0x0; clamping that would
        // shrink the saved size to the minimum the next time it opens.
        if (!r.classList.contains('np-open')) return;

        var w = Math.min(r.offsetWidth, window.innerWidth - 16);
        var h = Math.min(r.offsetHeight, window.innerHeight - 16);
        r.style.width = Math.max(MIN_W, w) + 'px';
        r.style.height = Math.max(MIN_H, h) + 'px';

        var left = parseInt(r.style.left, 10) || 0;
        var top = parseInt(r.style.top, 10) || 0;
        left = Math.max(8, Math.min(window.innerWidth - r.offsetWidth - 8, left));
        top = Math.max(8, Math.min(window.innerHeight - r.offsetHeight - 8, top));
        r.style.left = left + 'px';
        r.style.top = top + 'px';
    }

    // ---------------------------------------------------------------- saving

    function currentNote() {
        for (var i = 0; i < state.notes.length; i++) {
            if (state.notes[i].id === state.activeId) return state.notes[i];
        }
        return null;
    }

    function setStatus(text) {
        var el = state.root && state.root.querySelector('.np-status');
        if (el) el.textContent = text || '';
    }

    function scheduleSave() {
        state.dirty = true;
        setStatus('Saving…');
        window.clearTimeout(state.saveTimer);
        state.saveTimer = window.setTimeout(flush, SAVE_DEBOUNCE_MS);
    }

    function readEditor() {
        var r = state.root;
        return {
            title: r.querySelector('.np-title-input').value,
            body: r.querySelector('.np-body').value
        };
    }

    function flush() {
        window.clearTimeout(state.saveTimer);
        if (!state.dirty || !state.activeId) return Promise.resolve();

        var note = currentNote();
        var payload = readEditor();
        state.dirty = false;

        if (note) { note.title = payload.title; note.body = payload.body; }
        renderList();

        return api('PUT', '/' + state.activeId, payload)
            .then(function () { setStatus('Saved'); })
            .catch(function () {
                // Put the flag back so the next keystroke (or close) retries.
                state.dirty = true;
                setStatus('Not saved — will retry');
            });
    }

    /*
     * Last-ditch save when the page is going away. A normal fetch is routinely
     * cancelled at that point; sendBeacon is queued by the browser and delivered
     * regardless. Laravel needs the method override and the token in the body
     * because a beacon cannot carry custom headers.
     */
    function flushBeacon() {
        if (!state.dirty || !state.activeId || !navigator.sendBeacon) return;

        var payload = readEditor();
        var form = new FormData();
        form.append('_method', 'PUT');
        form.append('_token', csrf());
        form.append('title', payload.title);
        form.append('body', payload.body);

        try {
            navigator.sendBeacon(base() + '/' + state.activeId, form);
            state.dirty = false;
        } catch (e) { /* nothing further we can do at this point */ }
    }

    // --------------------------------------------------------------- rendering

    function renderList() {
        var list = state.root.querySelector('.np-list');
        list.innerHTML = '';

        if (!state.notes.length) {
            var empty = document.createElement('div');
            empty.className = 'np-empty';
            empty.textContent = 'No notes yet.';
            list.appendChild(empty);
            return;
        }

        state.notes.forEach(function (n) {
            var item = document.createElement('button');
            item.type = 'button';
            item.className = 'np-item' + (n.id === state.activeId ? ' is-active' : '');
            item.dataset.id = n.id;

            var name = document.createElement('span');
            name.className = 'np-item-title';
            name.textContent = (n.title && n.title.trim()) ||
                               (n.body ? n.body.replace(/\s+/g, ' ').trim().slice(0, 40) : '') ||
                               'Untitled note';

            var del = document.createElement('span');
            del.className = 'np-item-del';
            del.title = 'Delete note';
            del.innerHTML = '&times;';

            item.appendChild(name);
            item.appendChild(del);
            list.appendChild(item);
        });
    }

    function openNote(id) {
        flush();
        state.activeId = id;
        var n = currentNote();
        var r = state.root;
        r.querySelector('.np-title-input').value = (n && n.title) || '';
        r.querySelector('.np-body').value = (n && n.body) || '';
        r.querySelector('.np-editor').hidden = !n;
        r.querySelector('.np-blank').hidden = !!n;
        setStatus('');
        renderList();
    }

    function load() {
        return api('GET', '').then(function (data) {
            state.notes = (data && data.notes) || [];
            state.loaded = true;
            if (!state.activeId && state.notes.length) state.activeId = state.notes[0].id;
            renderList();
            openNote(state.activeId);
        }).catch(function () {
            setStatus('Could not load notes');
        });
    }

    function addNote() {
        return flush().then(function () {
            return api('POST', '', { title: '', body: '' });
        }).then(function (data) {
            var n = data.note;
            state.notes.unshift(n);
            state.activeId = n.id;
            renderList();
            openNote(n.id);
            state.root.querySelector('.np-title-input').focus();
        }).catch(function () { setStatus('Could not create note'); });
    }

    function deleteNote(id) {
        var idx = -1;
        state.notes.forEach(function (n, i) { if (n.id === id) idx = i; });
        if (idx === -1) return;

        state.notes.splice(idx, 1);
        if (state.activeId === id) {
            state.activeId = state.notes.length ? state.notes[Math.max(0, idx - 1)].id : null;
            state.dirty = false;
        }
        renderList();
        openNote(state.activeId);
        api('DELETE', '/' + id).catch(function () { setStatus('Could not delete'); });
    }

    // ------------------------------------------------------------------ chrome

    function build() {
        var r = document.createElement('div');
        r.className = 'np-window';
        r.id = 'notepadWindow';
        r.setAttribute('role', 'dialog');
        r.setAttribute('aria-label', 'Notepad');
        r.innerHTML =
            '<div class="np-bar">' +
                '<span class="np-bar-title"><i class="fas fa-note-sticky"></i> Notepad</span>' +
                '<div class="np-bar-actions">' +
                    '<button type="button" class="np-btn np-list-toggle" title="Hide notes list" aria-label="Hide notes list"><i class="fas fa-table-columns"></i></button>' +
                    '<button type="button" class="np-btn np-dock" title="Dock to the right side" aria-label="Dock to the right side"><i class="fas fa-right-to-bracket"></i></button>' +
                    '<span class="np-bar-sep"></span>' +
                    '<button type="button" class="np-btn np-add" title="New note" aria-label="New note"><i class="fas fa-plus"></i></button>' +
                    '<button type="button" class="np-btn np-min" title="Minimise" aria-label="Minimise"><i class="fas fa-minus"></i></button>' +
                    '<button type="button" class="np-btn np-close" title="Close" aria-label="Close">&times;</button>' +
                '</div>' +
            '</div>' +
            '<div class="np-body-wrap">' +
                '<div class="np-list"></div>' +
                '<div class="np-pane">' +
                    '<div class="np-blank" hidden>Create a note to start writing.</div>' +
                    '<div class="np-editor" hidden>' +
                        '<input type="text" class="np-title-input" placeholder="Title" maxlength="200">' +
                        '<textarea class="np-body" placeholder="Start typing…" spellcheck="true"></textarea>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="np-foot"><span class="np-status"></span></div>' +
            '<div class="np-resize" title="Resize"></div>' +
            '<div class="np-dock-resize" title="Drag to change width"></div>' +
            '<div class="np-tab" title="Open notepad"><i class="fas fa-note-sticky"></i><span>Notepad</span></div>';

        document.body.appendChild(r);
        return r;
    }

    function show() {
        var r = state.root;
        r.classList.add('np-open');
        r.classList.remove('np-minimized');
        if (state.docked) layoutDocked(true);
        clampToViewport();
        writeGeom();
        if (!state.loaded) load();
    }

    function minimize() {
        flush();
        state.root.classList.add('np-minimized');
        // A parked tab takes no room, so the page gets its full width back.
        if (state.docked) layoutDocked(false);
        writeGeom();
    }

    function close() {
        // Save before the window goes away, never after.
        flush();
        state.root.classList.remove('np-open', 'np-minimized');
        if (state.docked) layoutDocked(false);
        writeGeom();
    }

    // ------------------------------------------------------- notes list toggle

    function setListHidden(hidden) {
        var r = state.root;
        r.classList.toggle('np-list-hidden', hidden);
        var b = r.querySelector('.np-list-toggle');
        var label = hidden ? 'Show notes list' : 'Hide notes list';
        b.title = label;
        b.setAttribute('aria-label', label);
        b.setAttribute('aria-pressed', hidden ? 'true' : 'false');
        writeGeom();
    }

    // ------------------------------------------------------------------ docking

    /*
     * Docked, the notepad stops floating and becomes a panel fixed to the right
     * edge, the mirror of the navigation sidebar on the left. The page does not
     * slide underneath it: body gets a class that gives .main-content a right
     * margin of the same width, so the dashboard narrows to fit beside it.
     */
    function maxDockWidth() {
        return Math.max(DOCK_MIN_W, Math.floor(window.innerWidth * DOCK_MAX_RATIO));
    }

    function applyDockWidth() {
        state.dockWidth = Math.max(DOCK_MIN_W, Math.min(maxDockWidth(), state.dockWidth));
        document.documentElement.style.setProperty('--np-dock-width', state.dockWidth + 'px');
    }

    /* The charts size themselves from their container on load and on window
     * resize only, so a width change from docking has to be announced, after the
     * margin transition has finished. */
    function announceResize() {
        window.setTimeout(function () {
            window.dispatchEvent(new Event('resize'));
        }, 340);
    }

    function layoutDocked(on) {
        document.body.classList.toggle('np-docked-layout', on);
        announceResize();
    }

    function setDocked(on) {
        var r = state.root;
        state.docked = on;
        r.classList.toggle('np-docked', on);

        var b = r.querySelector('.np-dock');
        var icon = b.querySelector('i');
        var label = on ? 'Undock (float again)' : 'Dock to the right side';
        b.title = label;
        b.setAttribute('aria-label', label);
        icon.className = on ? 'fas fa-up-right-from-square' : 'fas fa-right-to-bracket';

        if (on) applyDockWidth();

        var visible = r.classList.contains('np-open') && !r.classList.contains('np-minimized');
        layoutDocked(on && visible);

        if (!on) clampToViewport();
        writeGeom();
    }

    function toggle() {
        var r = state.root;
        if (!r.classList.contains('np-open')) return show();
        if (r.classList.contains('np-minimized')) return show();
        minimize();
    }

    // Dragging by the title bar, and resizing from the corner. Pointer events so
    // mouse, trackpad and touch all work from one path.
    function makeMovable() {
        var r = state.root;
        var bar = r.querySelector('.np-bar');
        var grip = r.querySelector('.np-resize');
        var dockGrip = r.querySelector('.np-dock-resize');
        var mode = null, sx = 0, sy = 0, sl = 0, st = 0, sw = 0, sh = 0, sdw = 0;
        // Latest pointer position, applied at most once per animation frame.
        var px = 0, py = 0, frame = 0, dragLeft = 0, dragTop = 0;

        function down(e, which) {
            if (e.target.closest('.np-btn')) return;   // buttons are not a drag handle
            // A docked panel is fixed in place; only its width can change.
            if (state.docked && which !== 'dockresize') return;
            mode = which;
            sdw = state.dockWidth;
            px = e.clientX; py = e.clientY;
            sx = e.clientX; sy = e.clientY;
            sl = parseInt(r.style.left, 10) || 0;
            st = parseInt(r.style.top, 10) || 0;
            dragLeft = sl; dragTop = st;
            sw = r.offsetWidth; sh = r.offsetHeight;
            r.classList.add('np-active');
            document.addEventListener('pointermove', move);
            document.addEventListener('pointerup', up);
            e.preventDefault();
        }

        /*
         * Pointer events can arrive several times per frame. Rather than touching
         * the layout on each one, remember the latest position and apply it once
         * per animation frame. While dragging, the window is moved with a transform
         * (the compositor slides the existing layer, nothing is repainted) and its
         * left/top are only written when the drag ends.
         */
        function move(e) {
            if (!mode) return;
            px = e.clientX; py = e.clientY;
            if (!frame) frame = window.requestAnimationFrame(apply);
        }

        function apply() {
            frame = 0;
            if (!mode) return;
            var dx = px - sx, dy = py - sy;

            if (mode === 'dockresize') {
                // Handle is on the left edge, so dragging left makes it wider.
                state.dockWidth = sdw - dx;
                applyDockWidth();
                return;
            }

            if (mode === 'drag') {
                // Clamped so the window can never be dragged out of reach.
                dragLeft = Math.max(8, Math.min(window.innerWidth - r.offsetWidth - 8, sl + dx));
                dragTop  = Math.max(8, Math.min(window.innerHeight - r.offsetHeight - 8, st + dy));
                r.style.transform = 'translate3d(' + (dragLeft - sl) + 'px,' + (dragTop - st) + 'px,0)';
            } else {
                r.style.width  = Math.max(MIN_W, Math.min(window.innerWidth - sl - 8, sw + dx)) + 'px';
                r.style.height = Math.max(MIN_H, Math.min(window.innerHeight - st - 8, sh + dy)) + 'px';
            }
        }

        function up() {
            if (frame) { window.cancelAnimationFrame(frame); frame = 0; apply(); }
            if (mode === 'drag') {
                // Commit the final position and drop the temporary transform.
                r.style.left = dragLeft + 'px';
                r.style.top = dragTop + 'px';
                r.style.transform = '';
            }
            if (mode === 'dockresize') announceResize();
            mode = null;
            r.classList.remove('np-active');
            document.removeEventListener('pointermove', move);
            document.removeEventListener('pointerup', up);
            writeGeom();
        }

        bar.addEventListener('pointerdown', function (e) { down(e, 'drag'); });
        grip.addEventListener('pointerdown', function (e) { down(e, 'resize'); });
        dockGrip.addEventListener('pointerdown', function (e) { down(e, 'dockresize'); });
    }

    function wire() {
        var r = state.root;

        r.querySelector('.np-add').addEventListener('click', addNote);
        r.querySelector('.np-list-toggle').addEventListener('click', function () {
            setListHidden(!r.classList.contains('np-list-hidden'));
        });
        r.querySelector('.np-dock').addEventListener('click', function () {
            setDocked(!state.docked);
        });
        r.querySelector('.np-min').addEventListener('click', minimize);
        r.querySelector('.np-close').addEventListener('click', close);
        r.querySelector('.np-tab').addEventListener('click', show);

        r.querySelector('.np-title-input').addEventListener('input', scheduleSave);
        r.querySelector('.np-body').addEventListener('input', scheduleSave);

        r.querySelector('.np-list').addEventListener('click', function (e) {
            var item = e.target.closest('.np-item');
            if (!item) return;
            var id = parseInt(item.dataset.id, 10);
            if (e.target.closest('.np-item-del')) {
                deleteNote(id);
            } else if (id !== state.activeId) {
                openNote(id);
            }
        });

        window.addEventListener('resize', clampToViewport);

        // Every way out of the editor flushes first.
        window.addEventListener('beforeunload', flushBeacon);
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'hidden') flushBeacon();
        });
    }

    function init() {
        if (state.root) return;
        state.root = build();
        makeMovable();
        wire();

        var g = readGeom();
        var r = state.root;
        r.style.width  = ((g && g.width)  || 460) + 'px';
        r.style.height = ((g && g.height) || 380) + 'px';
        r.style.left = ((g && typeof g.left === 'number') ? g.left
                        : Math.max(8, window.innerWidth - 500)) + 'px';
        r.style.top  = ((g && typeof g.top === 'number') ? g.top : 96) + 'px';

        if (g && typeof g.dockWidth === 'number') state.dockWidth = g.dockWidth;

        // Open/minimised first. The two setters below save geometry as they go,
        // and saving before this would record the notepad as closed and lose it
        // on the next load.
        if (g && g.open) {
            r.classList.add('np-open');
            if (g.minimized) r.classList.add('np-minimized');
            load();
        }
        if (g && g.listHidden) setListHidden(true);
        // Docking last, so it knows whether the page needs to make room.
        if (g && g.docked) setDocked(true);
        clampToViewport();
    }

    window.Notepad = {
        init: init,
        open: function () { init(); show(); },
        toggle: function () { init(); toggle(); },
        close: close,
        flush: flush,
        dock: function (on) { init(); setDocked(on !== false); },
        isDocked: function () { return state.docked; }
    };
})(window, document);
