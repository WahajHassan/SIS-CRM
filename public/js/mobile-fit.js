/*
 * Making the screens fit a phone.
 *
 * Three things break a page on a 390px screen, and none of them is anybody's
 * mistake — they are all what the browser does by default on a layout written
 * for a laptop:
 *
 *   1. A grid keeps its columns. Two columns of a table each 500px wide do not
 *      become one column on a narrow screen; they become two 500px columns and
 *      the page is 1000px wide.
 *   2. A wide table holds its card open. The card has nowhere to put 1,300px
 *      of columns, so it simply becomes 1,300px wide and takes the page with
 *      it.
 *   3. The row of buttons beside the screen's name is eight controls long.
 *
 * This fixes those three where they are, on the page, rather than in each of
 * the forty screens. Nothing here runs above the phone width, and nothing here
 * changes what anything does — only how wide it is allowed to be.
 */
(function () {
    'use strict';

    var PHONE_UNDER = 900;

    /* How narrow a grid column may get before one column is better than two. */
    var TOO_NARROW = 150;

    function onAPhone() { return window.innerWidth <= PHONE_UNDER; }

    /* ---- 1. grids that cannot afford their columns ----------------------- */

    /*
     * A grid is left alone unless it is actually in trouble, which happens in
     * two different ways.
     *
     * Several columns squeezed into a phone: each one ends up too narrow to
     * read, so one column is better than three.
     *
     * Or a single column that is too wide for the screen, which is what
     * `minmax(450px, 1fr)` does when there are only 358px to give it: auto-fit
     * drops to one column, but the floor of 450px still holds, and the page is
     * 450px wide from then on.
     */
    function foldGrids(root) {
        root.querySelectorAll('*').forEach(function (el) {
            if (el.dataset.mfGrid) return;

            var style = getComputedStyle(el);
            if (style.display !== 'grid' && style.display !== 'inline-grid') return;

            var tracks = style.gridTemplateColumns.split(' ')
                .filter(Boolean).map(parseFloat).filter(function (n) { return !isNaN(n); });
            if (!tracks.length) return;

            var narrowest = Math.min.apply(null, tracks);
            var widest = Math.max.apply(null, tracks);
            var room = el.clientWidth;

            var crowded = tracks.length > 1 && (narrowest < TOO_NARROW || el.scrollWidth > room + 2);
            var tooWide = room > 0 && widest > room + 2;

            if (crowded || tooWide) {
                // Remembered, so putting it back is exact rather than a guess.
                el.dataset.mfGrid = el.style.gridTemplateColumns || '';
                el.style.gridTemplateColumns = '1fr';
            }
        });
    }

    /* ---- 3b. rows that will not go round the corner ---------------------- */

    /*
     * A row of things laid out side by side keeps them side by side however
     * little room there is, and the last one runs off the edge. If a row
     * cannot hold what is in it, it is allowed to go onto a second line.
     *
     * Rows that scroll sideways on purpose, like the header's controls, are
     * left alone: being longer than the box is the whole point of those.
     */
    function wrapRows(root) {
        root.querySelectorAll('*').forEach(function (el) {
            if (el.dataset.mfWrap) return;

            var style = getComputedStyle(el);
            if (style.display !== 'flex' && style.display !== 'inline-flex') return;
            if (style.flexWrap !== 'nowrap') return;
            if (style.overflowX === 'auto' || style.overflowX === 'scroll') return;
            if (style.flexDirection.indexOf('column') === 0) return;

            if (spillsOut(el)) {
                el.dataset.mfWrap = el.style.flexWrap || '';
                el.style.flexWrap = 'wrap';
            }
        });
    }

    /*
     * Is anything in this row past its right-hand edge?
     *
     * scrollWidth would be the obvious question, but it answers "no" for a
     * child that is clipping its own content — an input with a number too long
     * for it reports no overflow of its own while still sticking out of the
     * row it is in. Measuring the children says what is actually on screen.
     */
    function spillsOut(el) {
        if (el.scrollWidth > el.clientWidth + 2) return true;

        var edge = el.getBoundingClientRect().right;

        for (var i = 0; i < el.children.length; i++) {
            var kid = el.children[i];
            if (getComputedStyle(kid).position === 'absolute') continue;
            if (kid.getBoundingClientRect().right > edge + 2) return true;
        }

        return false;
    }

    function unwrapRows(root) {
        root.querySelectorAll('[data-mf-wrap]').forEach(function (el) {
            el.style.flexWrap = el.dataset.mfWrap;
            delete el.dataset.mfWrap;
        });
    }

    /* ---- 4. text too small to read on a phone ---------------------------- */

    /*
     * Plenty of labels are set at 0.62rem or 0.68rem, which is fine at a desk
     * and about 10px on a phone — below what most people can read at arm's
     * length. Only the leaves are touched, and only the ones that are actually
     * under the floor, so the type keeps its order; everything simply stops
     * below a size nobody can use.
     */
    var FLOOR = 11.5;

    function liftSmallText(root) {
        root.querySelectorAll('*').forEach(function (el) {
            if (el.children.length) return;
            if (el.dataset.mfText) return;

            var text = (el.textContent || '').trim();
            if (text.length < 2) return;

            var size = parseFloat(getComputedStyle(el).fontSize);
            if (!(size < FLOOR - 0.1)) return;

            el.dataset.mfText = el.style.fontSize || '';
            el.style.fontSize = FLOOR + 'px';
        });
    }

    function dropSmallText(root) {
        root.querySelectorAll('[data-mf-text]').forEach(function (el) {
            el.style.fontSize = el.dataset.mfText;
            delete el.dataset.mfText;
        });
    }

    function unfoldGrids(root) {
        root.querySelectorAll('[data-mf-grid]').forEach(function (el) {
            el.style.gridTemplateColumns = el.dataset.mfGrid;
            delete el.dataset.mfGrid;
        });
    }

    /* ---- 2. tables wider than the screen --------------------------------- */

    /*
     * A table that already sits in something that scrolls sideways is fine, and
     * most of them do. The rest get a box of their own to scroll inside, so the
     * columns stay full width and legible and it is the table that moves, not
     * the page.
     *
     * The box goes around the table and inside whatever held it, so anything
     * looking for the table from the card above still finds it.
     */
    function alreadyScrolls(el) {
        for (var p = el.parentElement; p && p !== document.body; p = p.parentElement) {
            var o = getComputedStyle(p).overflowX;
            if (o === 'auto' || o === 'scroll') return true;
        }
        return false;
    }

    function boxTables(root) {
        root.querySelectorAll('table').forEach(function (table) {
            if (table.parentElement && table.parentElement.classList.contains('mf-scroll')) return;
            if (alreadyScrolls(table)) return;

            var box = document.createElement('div');
            box.className = 'mf-scroll';

            table.parentNode.insertBefore(box, table);
            box.appendChild(table);
        });
    }

    /* ---- 3. the row of controls beside the screen's name ----------------- */

    /*
     * Eight controls do not fit beside a title on a phone, and a row that
     * scrolls sideways hides half of them behind a gesture nobody thinks to
     * make. They go behind one button instead, and come back as a block.
     */
    function addMoreButton() {
        if (document.getElementById('hdrMore')) return;

        var title = document.querySelector('.main-content > .header > .page-title');
        var strip = document.querySelector('.main-content > .header > div:last-child');
        if (!title || !strip || strip === title) return;

        var button = document.createElement('button');
        button.type = 'button';
        button.id = 'hdrMore';
        button.className = 'nav-toggle hdr-more';
        button.setAttribute('aria-controls', 'hdrStrip');
        button.setAttribute('aria-expanded', 'false');
        button.setAttribute('aria-label', 'More actions');
        button.title = 'More actions';
        button.innerHTML = '<i class="fas fa-ellipsis" aria-hidden="true"></i>';

        strip.id = 'hdrStrip';

        button.addEventListener('click', function () {
            var open = document.body.classList.toggle('hdr-open');
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        title.appendChild(button);
    }

    /* ---- putting it to work ---------------------------------------------- */

    var fitting = false;

    function fit() {
        if (fitting) return;
        fitting = true;

        var root = document.querySelector('.main-content');
        if (!root) { fitting = false; return; }

        if (onAPhone()) {
            document.body.classList.add('mf-on');
            addMoreButton();
            boxTables(root);
            foldGrids(root);
            wrapRows(root);
            liftSmallText(root);
        } else {
            document.body.classList.remove('mf-on', 'hdr-open');
            unfoldGrids(root);
            unwrapRows(root);
            dropSmallText(root);
            // The scroll boxes are left in place: they do nothing at all on a
            // wide screen, and taking them out would move the tables twice.
        }

        fitting = false;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fit);
    } else {
        fit();
    }

    // Charts and tables arrive after their data does, and a phone gets turned
    // sideways. Both want another look.
    var again;
    function later() {
        clearTimeout(again);
        again = setTimeout(fit, 150);
    }

    window.addEventListener('resize', later);
    window.addEventListener('load', later);
})();
