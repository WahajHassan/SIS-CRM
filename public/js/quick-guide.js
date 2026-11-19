/*
 * Quick Guide — a short spotlight walkthrough of the section the user is on.
 *
 * The engine is deliberately dumb about content: it takes a list of steps, each
 * naming a real element already on the page, and walks through them. Adding a
 * guide for another section means adding data to quick-guide-steps.js, not
 * writing another component.
 *
 * A step that cannot find its element is dropped before the guide starts. That
 * is what keeps the guide honest under this app's permission system: a user who
 * cannot see Deals never gets a step pointing at a Deals button, and a guide
 * left with nothing to show simply does not open.
 *
 * Completion is remembered per user and per section, so a guide opens by itself
 * once and thereafter only when asked for.
 *
 * No dependencies. The styling uses the dashboard's own CSS variables so it
 * inherits the theme, including dark mode, without knowing anything about it.
 */
(function (window, document) {
    'use strict';

    var STORAGE_PREFIX = 'crm.guideDone.';
    var PAD = 6;              // breathing room around the spotlit element
    var GAP = 14;             // distance from spotlight to the popover
    var TIP_W = 320;

    var registry = {};
    var state = null;

    // ---------------------------------------------------------------- storage

    function storageKey(userId) {
        return STORAGE_PREFIX + (userId == null ? 'anon' : userId);
    }

    function readDone(userId) {
        try {
            var raw = window.localStorage.getItem(storageKey(userId));
            var parsed = raw ? JSON.parse(raw) : null;
            return (parsed && typeof parsed === 'object') ? parsed : {};
        } catch (e) {
            return {};
        }
    }

    function markDone(userId, section) {
        try {
            var done = readDone(userId);
            done[section] = true;
            window.localStorage.setItem(storageKey(userId), JSON.stringify(done));
        } catch (e) {
            /* If we cannot remember, the guide reappears next visit. Harmless. */
        }
    }

    // ------------------------------------------------------------ step lookup

    /*
     * Resolve a step to a live element. `sel` is a CSS selector; when `match` is
     * also given, the first element whose text contains it wins — that is how a
     * button is targeted by its label without adding ids to the Blade templates.
     * (`match`, not `text`: `text` is the step's own wording.)
     */
    function resolve(step) {
        var nodes;
        try {
            nodes = document.querySelectorAll(step.sel);
        } catch (e) {
            return null;
        }
        if (!nodes.length) return null;

        var list = Array.prototype.slice.call(nodes);
        if (step.match) {
            var needle = step.match.toLowerCase();
            list = list.filter(function (el) {
                return (el.textContent || '').toLowerCase().indexOf(needle) !== -1;
            });
        }

        // Only ever point at something the user can actually see.
        for (var i = 0; i < list.length; i++) {
            var el = list[i];
            var r = el.getBoundingClientRect();
            if (r.width > 0 && r.height > 0 && el.offsetParent !== null) return el;
        }
        return null;
    }

    function usableSteps(section) {
        var steps = registry[section] && registry[section].steps;
        if (!steps) return [];
        return steps.filter(function (s) { return resolve(s) !== null; });
    }

    // --------------------------------------------------------------------- UI

    function buildChrome() {
        var root = document.createElement('div');
        root.className = 'qg-root';
        root.setAttribute('role', 'dialog');
        root.setAttribute('aria-modal', 'true');
        root.setAttribute('aria-label', 'Quick guide');
        root.innerHTML =
            '<div class="qg-spot" aria-hidden="true"></div>' +
            '<div class="qg-tip">' +
                '<button type="button" class="qg-close" aria-label="Close guide">&times;</button>' +
                '<div class="qg-step-count"></div>' +
                '<div class="qg-title"></div>' +
                '<div class="qg-text"></div>' +
                '<div class="qg-actions">' +
                    '<button type="button" class="qg-skip">Skip</button>' +
                    '<div class="qg-nav">' +
                        '<button type="button" class="qg-back">Back</button>' +
                        '<button type="button" class="qg-next">Next</button>' +
                    '</div>' +
                '</div>' +
            '</div>';
        document.body.appendChild(root);
        return root;
    }

    function place(step, el) {
        var spot = state.root.querySelector('.qg-spot');
        var tip = state.root.querySelector('.qg-tip');

        var r = el.getBoundingClientRect();
        var top = r.top - PAD, left = r.left - PAD;
        var w = r.width + PAD * 2, h = r.height + PAD * 2;

        spot.style.top = top + 'px';
        spot.style.left = left + 'px';
        spot.style.width = w + 'px';
        spot.style.height = h + 'px';
        spot.style.borderRadius = (step.radius || 16) + 'px';

        // Measure the popover, then choose a side that keeps it on screen.
        var tw = Math.min(TIP_W, window.innerWidth - 24);
        tip.style.width = tw + 'px';
        var th = tip.offsetHeight || 160;

        var below = top + h + GAP;
        var above = top - GAP - th;
        var tipTop, tipLeft;

        if (below + th <= window.innerHeight - 12) {
            tipTop = below;                       // preferred: under the element
        } else if (above >= 12) {
            tipTop = above;                       // otherwise above it
        } else {
            tipTop = Math.max(12, (window.innerHeight - th) / 2);  // last resort
        }

        tipLeft = left + w / 2 - tw / 2;
        tipLeft = Math.max(12, Math.min(window.innerWidth - tw - 12, tipLeft));

        tip.style.top = tipTop + 'px';
        tip.style.left = tipLeft + 'px';
    }

    function render() {
        var step = state.steps[state.index];
        var el = resolve(step);

        if (!el) {                    // vanished mid-guide (a modal closed, say)
            return next();
        }

        var root = state.root;
        root.querySelector('.qg-step-count').textContent =
            'Step ' + (state.index + 1) + ' of ' + state.steps.length;
        root.querySelector('.qg-title').textContent = step.title || '';
        root.querySelector('.qg-text').textContent = step.text || '';

        var back = root.querySelector('.qg-back');
        var nextBtn = root.querySelector('.qg-next');
        back.style.display = state.index === 0 ? 'none' : '';
        nextBtn.textContent = state.index === state.steps.length - 1 ? 'Done' : 'Next';
        root.querySelector('.qg-skip').style.display =
            state.index === state.steps.length - 1 ? 'none' : '';

        // Bring the element into view first, then position against where it
        // actually ended up.
        var r = el.getBoundingClientRect();
        var offscreen = r.top < 80 || r.bottom > window.innerHeight - 80 ||
                        r.left < 8 || r.right > window.innerWidth - 8;
        if (offscreen) {
            // inline as well as block: this dashboard is wider than a phone, so a
            // header control can be off to the side rather than above or below.
            el.scrollIntoView({
                behavior: state.reduceMotion ? 'auto' : 'smooth',
                block: 'center',
                inline: 'center'
            });
        }

        state.current = el;

        /*
         * Place immediately, then again as the smooth scroll settles. A single
         * delayed measurement is fragile: how long the scroll takes depends on
         * the distance, and on a narrow screen a header control can be several
         * hundred pixels away horizontally, so the one measurement lands
         * mid-flight and the spotlight stops short of the element.
         */
        place(step, el);
        [80, 200, 380, 620].forEach(function (ms) {
            window.setTimeout(function () {
                if (state && state.current === el) place(step, el);
            }, ms);
        });
    }

    // ---------------------------------------------------------------- control

    function next() {
        if (!state) return;
        if (state.index >= state.steps.length - 1) return finish(true);
        state.index++;
        render();
    }

    function back() {
        if (!state || state.index === 0) return;
        state.index--;
        render();
    }

    function finish(completed) {
        if (!state) return;
        var section = state.section, userId = state.userId;

        window.removeEventListener('resize', state.onReflow);
        window.removeEventListener('scroll', state.onReflow, true);
        document.removeEventListener('keydown', state.onKey, true);

        state.root.classList.remove('qg-open');
        var root = state.root;
        window.setTimeout(function () {
            if (root && root.parentNode) root.parentNode.removeChild(root);
        }, 220);

        document.body.classList.remove('qg-active');
        state = null;

        // Skipping counts as done: the point is not to nag, and the guide can
        // always be reopened from the header.
        markDone(userId, section);
    }

    function start(section, userId, opts) {
        if (state) return false;
        if (!registry[section]) return false;

        var steps = usableSteps(section);
        if (!steps.length) return false;

        state = {
            section: section,
            userId: userId,
            steps: steps,
            index: 0,
            root: buildChrome(),
            current: null,
            reduceMotion: window.matchMedia &&
                window.matchMedia('(prefers-reduced-motion: reduce)').matches
        };

        state.onReflow = function () {
            if (state && state.current) place(state.steps[state.index], state.current);
        };
        state.onKey = function (e) {
            if (!state) return;
            if (e.key === 'Escape') { e.preventDefault(); finish(false); }
            else if (e.key === 'ArrowRight' || e.key === 'Enter') { e.preventDefault(); next(); }
            else if (e.key === 'ArrowLeft') { e.preventDefault(); back(); }
        };

        var root = state.root;
        root.querySelector('.qg-next').addEventListener('click', next);
        root.querySelector('.qg-back').addEventListener('click', back);
        root.querySelector('.qg-skip').addEventListener('click', function () { finish(false); });
        root.querySelector('.qg-close').addEventListener('click', function () { finish(false); });

        window.addEventListener('resize', state.onReflow);
        window.addEventListener('scroll', state.onReflow, true);
        document.addEventListener('keydown', state.onKey, true);

        document.body.classList.add('qg-active');
        // Next frame, so the opening transition has a starting state to run from.
        window.requestAnimationFrame(function () {
            if (!state) return;
            state.root.classList.add('qg-open');
            render();
        });

        return true;
    }

    // ------------------------------------------------------------------- wire

    window.QuickGuide = {
        register: function (section, config) { registry[section] = config; },

        has: function (section) {
            return !!registry[section] && usableSteps(section).length > 0;
        },

        /* Manual replay: always runs, whether or not it has been seen. */
        start: function (section, userId) {
            return start(section, userId == null ? window.CRM_USER_ID : userId);
        },

        /* First visit only. */
        autoStart: function (section, userId) {
            if (userId == null) userId = window.CRM_USER_ID;
            if (readDone(userId)[section]) return false;
            return start(section, userId);
        },

        isDone: function (section, userId) {
            return !!readDone(userId == null ? window.CRM_USER_ID : userId)[section];
        },

        /* So a user can ask to see the guides again. */
        resetAll: function (userId) {
            try {
                window.localStorage.removeItem(
                    storageKey(userId == null ? window.CRM_USER_ID : userId));
            } catch (e) { /* nothing to clear */ }
        }
    };
})(window, document);
