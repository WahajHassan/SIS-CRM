/*
 * Shared "drag to reorder, and remember it" behaviour.
 *
 * Both the sidebar navigation and the Overview metric cards need the same five
 * things: identify rows by a stable key, restore a saved order before first
 * paint, let the user drag them around, keep clicks from firing at the end of a
 * drag, and save the result. This module is that behaviour once; the callers
 * supply only what differs (which container, which children, where to store).
 *
 * Several containers can be passed together, sharing one storage entry that
 * holds a list per container. Whether an item may be dragged from one container
 * into another is the caller's choice: the sidebar wants it, so a link can move
 * between groups; the Overview cards do not, because the two rows of stats are
 * laid out differently and a card that crosses over leaves a hole in one row and
 * an odd one out in the other.
 *
 * Storage is localStorage, keyed per user. The app has a permissions system but
 * no user-preference store, and a cosmetic per-browser layout does not justify a
 * migration and an endpoint.
 *
 * Requires Sortable (js/sortable.min.js). Without it everything still renders and
 * works, just without dragging.
 */
(function (window, document) {
    'use strict';

    /* Pointer travel before a press counts as a drag rather than a click. */
    var DRAG_THRESHOLD_PX = 6;

    /* How long clicks stay blocked after a drag ends. Long enough to swallow the
     * click the pointer release generates, short enough to be imperceptible. */
    var CLICK_BLOCK_MS = 300;

    function readJson(key) {
        try {
            return JSON.parse(window.localStorage.getItem(key));
        } catch (e) {
            // Private mode, blocked storage, corrupted value.
            return null;
        }
    }

    function writeJson(key, value) {
        try {
            window.localStorage.setItem(key, JSON.stringify(value));
        } catch (e) {
            /* Not remembering the layout is not worth breaking the page for. */
        }
    }

    function slugify(text) {
        text = (text || '').replace(/\s+/g, ' ').trim().toLowerCase();
        return text ? text.replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') : null;
    }

    function childItems(container, selector) {
        return Array.prototype.filter.call(container.children, function (el) {
            return el.nodeType === 1 && el.matches(selector);
        });
    }

    function containerId(container, index) {
        return container.getAttribute('data-sort-group') || ('group-' + index);
    }

    function create(opts) {
        var containers = (opts.containers || []).filter(Boolean);
        if (!containers.length || !window.Sortable) return null;

        var itemSelector = opts.itemSelector;
        var keyFor = opts.keyFor;
        var storageKey = opts.storageKey;
        var groupName = opts.groupName || storageKey;

        // Default is to allow it, which is what the sidebar has always done.
        var crossContainer = opts.crossContainer !== false;

        assignKeys();
        applySaved();
        wire();

        return { save: save, applySaved: applySaved, reset: reset };

        /*
         * Freeze each item's key onto the element before anything is moved.
         *
         * Two things make this necessary. Labels are not guaranteed unique — the
         * Overview shows "Total Revenue" in both rows of stat cards — so a bare
         * slug would collide and one of the two would be treated as unknown.
         * And once items start moving, recomputing a de-duplicating suffix from
         * the live DOM would attach it to whichever card happens to come first,
         * so the same card would answer to different keys over time.
         *
         * Running this against the pristine server-rendered order solves both:
         * the markup arrives in the same order on every request, so every card
         * gets the same key every time, whatever the user has since dragged.
         */
        function assignKeys() {
            var seen = Object.create(null);
            containers.forEach(function (c) {
                childItems(c, itemSelector).forEach(function (el) {
                    if (el.dataset.spKey) return;
                    var base = keyFor(el);
                    if (!base) return;
                    seen[base] = (seen[base] || 0) + 1;
                    el.dataset.spKey = seen[base] > 1 ? base + '-' + seen[base] : base;
                });
            });
        }

        function itemKey(el) {
            return el.dataset.spKey || keyFor(el);
        }

        function layoutNow() {
            var out = {};
            containers.forEach(function (c, i) {
                out[containerId(c, i)] = childItems(c, itemSelector).map(itemKey).filter(Boolean);
            });
            return out;
        }

        function save() {
            writeJson(storageKey, layoutNow());
        }

        function reset() {
            try { window.localStorage.removeItem(storageKey); } catch (e) { /* nothing to clear */ }
        }

        /*
         * Rebuild each container as: the items the saved layout names, in that
         * order, followed by anything that container started with which the saved
         * layout has never heard of. That second half is what makes a newly added
         * card or menu entry show up for someone with an older saved layout,
         * rather than silently disappearing.
         */
        function applySaved() {
            var saved = readJson(storageKey);
            if (!saved || typeof saved !== 'object') return;

            var byKey = Object.create(null);
            var originalHome = [];

            containers.forEach(function (c, i) {
                var mine = [];
                childItems(c, itemSelector).forEach(function (el) {
                    var k = itemKey(el);
                    if (!k) return;
                    if (!(k in byKey)) byKey[k] = el;
                    mine.push(k);
                });
                originalHome[i] = mine;
            });

            var claimed = Object.create(null);
            var plan = containers.map(function (c, i) {
                var list = Array.isArray(saved[containerId(c, i)]) ? saved[containerId(c, i)] : [];
                var picked = [];

                // When items may not cross, a saved layout that has one in the
                // wrong container is ignored rather than obeyed — otherwise a
                // layout saved before the rule came in would still break it.
                var allowed = crossContainer ? null : originalHome[i];

                list.forEach(function (k) {
                    if (!byKey[k] || claimed[k]) return;
                    if (allowed && allowed.indexOf(k) === -1) return;
                    claimed[k] = true;
                    picked.push(byKey[k]);
                });

                return picked;
            });

            // Anything the saved layout never mentions stays in the container it
            // was rendered in, at the end.
            containers.forEach(function (c, i) {
                originalHome[i].forEach(function (k) {
                    if (byKey[k] && !claimed[k]) {
                        claimed[k] = true;
                        plan[i].push(byKey[k]);
                    }
                });
            });

            // One fragment per container, so each is laid out once.
            containers.forEach(function (c, i) {
                var frag = document.createDocumentFragment();
                plan[i].forEach(function (el) { frag.appendChild(el); });
                c.appendChild(frag);
            });
        }

        function wire() {
            var blockClicksUntil = 0;

            containers.forEach(function (container, index) {
                if (container.dataset.sortablePrefsReady) return;
                container.dataset.sortablePrefsReady = '1';

                /*
                 * A drag that ends on top of a link or a button would otherwise
                 * fire its click — navigating away or toggling something right
                 * after a reorder. Blocking clicks briefly in the capture phase
                 * removes that whole class of accident.
                 */
                container.addEventListener('click', function (e) {
                    if (Date.now() < blockClicksUntil) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                }, true);

                /*
                 * Links and images are natively draggable: press and move on one
                 * and the browser starts its own drag gesture, which swallows the
                 * pointer stream Sortable listens to. Turning that off is what
                 * lets a press on a link become a reorder.
                 */
                container.addEventListener('dragstart', function (e) { e.preventDefault(); });
                container.querySelectorAll('a, img').forEach(function (el) {
                    el.setAttribute('draggable', 'false');
                });

                var config = {
                    animation: 180,
                    easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
                    draggable: itemSelector,

                    group: crossContainer
                        ? { name: groupName, pull: true, put: true }
                        : { name: groupName + '-' + containerId(container, index), pull: false, put: false },

                    /* Threshold on mouse/trackpad; a short hold on touch, so a
                     * finger swipe still scrolls instead of grabbing something. */
                    touchStartThreshold: DRAG_THRESHOLD_PX,
                    delay: 120,
                    delayOnTouchOnly: true,

                    /*
                     * Sortable's own clone rather than the browser's native drag
                     * image: the native one is an opaque snapshot the page cannot
                     * style, so the lift and shadow would never show, and it
                     * behaves differently across browsers and input devices.
                     */
                    forceFallback: true,
                    fallbackOnBody: true,
                    fallbackTolerance: DRAG_THRESHOLD_PX,

                    ghostClass: opts.ghostClass || 'sp-ghost',
                    chosenClass: opts.chosenClass || 'sp-chosen',
                    dragClass: opts.dragClass || 'sp-drag',

                    scroll: true,
                    scrollSensitivity: 90,
                    scrollSpeed: 12,
                    bubbleScroll: true,

                    onStart: function () {
                        document.body.classList.add(opts.draggingBodyClass || 'sp-dragging');
                    },
                    onEnd: function () {
                        document.body.classList.remove(opts.draggingBodyClass || 'sp-dragging');
                        blockClicksUntil = Date.now() + CLICK_BLOCK_MS;
                        save();
                    }
                };

                Object.keys(opts.sortableOptions || {}).forEach(function (k) {
                    config[k] = opts.sortableOptions[k];
                });

                Sortable.create(container, config);
            });
        }
    }

    window.SortablePrefs = {
        create: create,
        slugify: slugify,
        childItems: childItems
    };
})(window, document);
