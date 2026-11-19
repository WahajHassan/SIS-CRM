/*
 * The ask bar.
 *
 * Typing searches the CRM as you go; Enter opens the first thing it found. The
 * microphone is the browser's own dictation — the same engine the phone
 * keyboard uses — so nothing is recorded and no audio leaves the machine.
 *
 * Two things it is careful about. It waits for a pause before asking the server,
 * because a search box that fires on every keystroke sends eight requests for a
 * word nobody finished typing. And every answer carries the term it was for, so
 * a slow reply for "gut" can never overwrite the results for "gutter".
 */
(function () {
    'use strict';

    var WAIT_FOR_PAUSE = 220;
    var ENOUGH_TO_SEARCH = 2;

    var wrap = document.getElementById('askWrap');
    var input = document.getElementById('askInput');
    var results = document.getElementById('askResults');
    var mic = document.getElementById('askMic');
    var go = document.getElementById('askGo');
    var plus = document.getElementById('askPlus');
    var menu = document.getElementById('askMenu');

    if (!wrap || !input || !results) return;

    var pause = null;
    var askedFor = '';
    var picked = -1;

    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /* ---- looking ---------------------------------------------------------- */

    function look() {
        var term = input.value.trim();

        if (term.length < ENOUGH_TO_SEARCH) {
            close();
            return;
        }

        askedFor = term;

        fetch('/admin/search?q=' + encodeURIComponent(term), {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (found) {
                // An answer for something they have since typed past is stale.
                if (!found || found.term !== askedFor) return;

                draw(found);
            })
            .catch(function () {
                show('<div class="ask-note">Could not reach the CRM just now.</div>');
            });
    }

    function draw(found) {
        if (!found.groups.length) {
            show('<div class="ask-note">Nothing found for &ldquo;' + esc(found.term) + '&rdquo;.</div>');
            return;
        }

        var html = found.groups.map(function (group) {
            return '<div class="ask-group-label"><i class="fas ' + esc(group.icon) + '"></i>' + esc(group.label) + '</div>'
                + group.items.map(function (item) {
                    return '<a class="ask-hit" href="' + esc(item.url) + '">'
                        + '<i class="fas ' + esc(group.icon) + '"></i>'
                        + '<span class="ask-hit-text">'
                        + '<b>' + esc(item.title) + '</b>'
                        + (item.detail ? '<span>' + esc(item.detail) + '</span>' : '')
                        + '</span></a>';
                }).join('');
        }).join('');

        show(html);
    }

    function show(html) {
        results.innerHTML = html;
        results.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        picked = -1;
    }

    function close() {
        results.hidden = true;
        results.innerHTML = '';
        input.setAttribute('aria-expanded', 'false');
        picked = -1;
    }

    /* ---- moving through what it found -------------------------------------- */

    function hits() {
        return [].slice.call(results.querySelectorAll('.ask-hit'));
    }

    function move(by) {
        var all = hits();
        if (!all.length) return;

        all.forEach(function (el) { el.classList.remove('is-picked'); });

        picked = (picked + by + all.length) % all.length;
        all[picked].classList.add('is-picked');
        all[picked].scrollIntoView({ block: 'nearest' });
    }

    function open() {
        var all = hits();
        var one = picked >= 0 ? all[picked] : all[0];

        if (one) window.location.href = one.getAttribute('href');
    }

    /* ---- typing ------------------------------------------------------------ */

    input.addEventListener('input', function () {
        // The shortcut badge is only useful while the box is empty.
        wrap.classList.toggle('has-text', input.value.length > 0);

        clearTimeout(pause);
        pause = setTimeout(look, WAIT_FOR_PAUSE);
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
        else if (e.key === 'Enter') { e.preventDefault(); open(); }
        else if (e.key === 'Escape') { close(); input.blur(); }
    });

    input.addEventListener('focus', function () {
        if (input.value.trim().length >= ENOUGH_TO_SEARCH && results.hidden) look();
    });

    if (go) go.addEventListener('click', function () {
        if (results.hidden) look();
        else open();
    });

    document.addEventListener('click', function (e) {
        if (!wrap.contains(e.target)) close();
        if (menu && !menu.hidden && !menu.contains(e.target) && e.target !== plus && !plus.contains(e.target)) {
            shutMenu();
        }
    });

    /*
     * Anywhere on the page, / jumps into the bar — the shortcut every search
     * box has. Not while somebody is already typing into something else.
     */
    document.addEventListener('keydown', function (e) {
        var typingElsewhere = /^(input|textarea|select)$/i.test((e.target.tagName || ''))
            || e.target.isContentEditable;

        if (e.key === '/' && !typingElsewhere) {
            e.preventDefault();
            input.focus();
            input.select();
        }

        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            input.focus();
            input.select();
        }
    });

    /* ---- the create menu ---------------------------------------------------- */

    function shutMenu() {
        if (!menu) return;
        menu.hidden = true;
        plus.classList.remove('is-open');
        plus.setAttribute('aria-expanded', 'false');
    }

    if (plus && menu) {
        plus.setAttribute('aria-expanded', 'false');

        plus.addEventListener('click', function (e) {
            e.stopPropagation();

            if (!menu.hidden) { shutMenu(); return; }

            // Opened against the button rather than positioned in CSS, because
            // the bar is centred and the menu has to stay on the screen at any
            // width.
            var at = plus.getBoundingClientRect();
            menu.hidden = false;

            var box = menu.getBoundingClientRect();
            var left = Math.max(12, Math.min(at.left, window.innerWidth - box.width - 12));

            menu.style.left = left + 'px';
            menu.style.top = Math.max(12, at.top - box.height - 10) + 'px';

            plus.classList.add('is-open');
            plus.setAttribute('aria-expanded', 'true');
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') shutMenu();
        });
    }

    /* ---- speaking instead of typing ------------------------------------------ */

    var Dictation = window.SpeechRecognition || window.webkitSpeechRecognition;

    if (!mic) return;

    if (!Dictation) {
        // Firefox has no dictation. Saying so is better than a button that
        // looks the same and does nothing.
        mic.title = 'Your browser cannot do dictation — try Chrome or Edge';
        mic.addEventListener('click', function () {
            mic.title = 'Your browser cannot do dictation — try Chrome or Edge';
        });
        mic.style.opacity = '0.45';
        return;
    }

    var listener = new Dictation();
    listener.continuous = false;
    listener.interimResults = true;
    listener.lang = document.documentElement.lang || 'en-US';

    var listening = false;

    listener.onresult = function (e) {
        var said = '';

        for (var i = e.resultIndex; i < e.results.length; i++) {
            said += e.results[i][0].transcript;
        }

        input.value = said;

        // Search on the final text only; the interim guesses change too fast to
        // be worth a request each.
        if (e.results[e.results.length - 1].isFinal) {
            clearTimeout(pause);
            look();
        }
    };

    listener.onend = function () {
        listening = false;
        mic.classList.remove('is-listening');
    };

    listener.onerror = function (e) {
        listening = false;
        mic.classList.remove('is-listening');

        if (e.error === 'not-allowed' || e.error === 'service-not-allowed') {
            mic.title = 'The microphone is blocked for this site';
        }
    };

    mic.addEventListener('click', function () {
        if (listening) { listener.stop(); return; }

        try {
            listener.start();
            listening = true;
            mic.classList.add('is-listening');
            input.focus();
        } catch (err) {
            // start() throws if it is already running; nothing to do.
        }
    });
})();
