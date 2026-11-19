/*
 * Drag-to-reorder for the sidebar's top level.
 *
 * The sidebar is plain Blade: <nav class="nav-links"> holds a flat list of direct
 * children, each either an <a class="nav-item"> (Overview, Attendance, …) or a
 * <div class="nav-item-group"> wrapping a dropdown (Communications, Sales,
 * Client's Queries, Scoreboard Settings). Only those direct children move —
 * sub-links inside a group stay where they are, so dropdown contents, routing,
 * active states and permission checks are all untouched.
 *
 * The dragging, click-guarding and persistence all live in sortable-prefs.js,
 * which the Overview cards use too. What is sidebar-specific stays here: how a
 * row is identified, and which presses count as a reorder.
 *
 * Rows are identified by their visible label rather than an added data
 * attribute, so the Blade markup needs no per-item edits and items the current
 * user cannot see (never rendered) simply do not participate.
 *
 * Requires js/sortable.min.js and js/sortable-prefs.js to be loaded first.
 */
(function (window, document) {
    'use strict';

    var STORAGE_PREFIX = 'crm.sidebarOrder.';

    function storageKey(userId) {
        return STORAGE_PREFIX + (userId || 'anon');
    }

    /* A stable-enough identity for a row: its visible label. For a group that is
     * the label of the group's own header link, not its children. */
    function keyFor(el) {
        var labelSource = el.classList.contains('nav-item-group')
            ? el.querySelector('.nav-item')
            : el;
        if (!labelSource) return null;

        var span = labelSource.querySelector('span');
        return window.SortablePrefs.slugify(span ? span.textContent : labelSource.textContent);
    }

    function init(nav, userId) {
        if (!nav || !window.SortablePrefs || !window.Sortable) return;

        /*
         * Tag the top-level rows with a class of their own. This matters: a
         * dropdown's header is itself a .nav-item nested inside the
         * .nav-item-group, so an item selector of '.nav-item' resolves to that
         * header rather than to the group, and Sortable then tries to drag a link
         * out of its own group — dropdowns simply refuse to move. Marking only
         * the direct children removes the ambiguity.
         */
        Array.prototype.forEach.call(nav.children, function (el) {
            if (el.nodeType === 1 &&
                (el.classList.contains('nav-item') || el.classList.contains('nav-item-group'))) {
                el.classList.add('nav-sort-item');
            }
        });

        window.SidebarSort.instance = window.SortablePrefs.create({
            containers: [nav],
            itemSelector: '.nav-sort-item',
            keyFor: keyFor,
            storageKey: storageKey(userId),
            groupName: 'sidebar-nav',

            ghostClass: 'nav-sort-ghost',
            chosenClass: 'nav-sort-chosen',
            dragClass: 'nav-sort-drag',
            draggingBodyClass: 'nav-sorting',

            sortableOptions: {
                /* A press that starts on a sub-link inside an open dropdown is
                 * navigation, not a reorder. */
                filter: '.sub-nav-links',
                preventOnFilter: false
            }
        });
    }

    window.SidebarSort = {
        init: init,
        instance: null,
        /* Exposed so a saved order can be cleared from the console if it ever
         * needs resetting: SidebarSort.reset(<userId>). */
        reset: function (userId) {
            try {
                window.localStorage.removeItem(storageKey(userId));
            } catch (e) { /* nothing to clear */ }
        }
    };
})(window, document);
