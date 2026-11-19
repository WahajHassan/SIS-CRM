/*
 * Drag-to-reorder for the Overview metric cards.
 *
 * Thin wrapper over sortable-prefs.js — the same engine the sidebar uses, so
 * there is one drag implementation in the app rather than two. All that differs
 * is which container, how a card is identified, and where the layout is stored.
 *
 * Every .stat-card-grid on the page is passed in together and each keeps its own
 * list in the saved layout, but a card stays in the row it was rendered in. The
 * rows are not interchangeable — the owner's top row is two columns beside the
 * announcements board while the summary row is four across the page — so a card
 * dragged from one to the other left a gap in one row and an orphan in the
 * other. Within a row, dragging reorders as before.
 *
 * Cards are identified by their visible label rather than an added attribute, so
 * the Blade markup needs no per-card edits and a card the current user's role
 * does not render simply does not participate.
 */
(function (window, document) {
    'use strict';

    var STORAGE_PREFIX = 'crm.statCards.';

    /*
     * A card's label is the first small muted line inside it ("Total Revenue",
     * "Deals Closed", …) — the heading, not the number, which changes daily and
     * would make the key useless as an identity.
     */
    function keyFor(card) {
        var label = card.querySelector('div');
        var text = '';

        // Walk the leading divs until one has actual text of its own; the outer
        // wrapper in some cards is a flex row holding the label and an icon.
        var candidates = card.querySelectorAll('div');
        for (var i = 0; i < candidates.length; i++) {
            var own = candidates[i].childNodes;
            var direct = '';
            for (var j = 0; j < own.length; j++) {
                if (own[j].nodeType === 3) direct += own[j].textContent;
            }
            direct = direct.replace(/\s+/g, ' ').trim();
            if (direct) { text = direct; break; }
        }

        if (!text && label) text = label.textContent;
        return window.SortablePrefs.slugify(text);
    }

    function init(userId) {
        if (!window.SortablePrefs || !window.Sortable) return;

        var grids = Array.prototype.slice.call(document.querySelectorAll('.stat-card-grid'));
        if (!grids.length) return;

        window.StatCardsSort.instance = window.SortablePrefs.create({
            containers: grids,
            itemSelector: '.card',
            keyFor: keyFor,
            storageKey: STORAGE_PREFIX + (userId || 'anon'),
            groupName: 'stat-cards',
            crossContainer: false,

            ghostClass: 'stat-sort-ghost',
            chosenClass: 'stat-sort-chosen',
            dragClass: 'stat-sort-drag',
            draggingBodyClass: 'stat-sorting',

            sortableOptions: {
                /* Anything interactive inside a card stays interactive: a press
                 * that starts on a control is that control's, not a drag. */
                filter: 'a, button, input, select, textarea, .apexcharts-canvas',
                preventOnFilter: false
            }
        });
    }

    window.StatCardsSort = {
        init: init,
        instance: null,
        reset: function (userId) {
            try {
                window.localStorage.removeItem(STORAGE_PREFIX + (userId || 'anon'));
            } catch (e) { /* nothing to clear */ }
        }
    };
})(window, document);
