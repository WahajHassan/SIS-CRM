<script>
/*
 * The break-room games.
 *
 * Three of them, written here rather than fetched from anywhere: a game that
 * loads something from the internet is a game that stops working the day that
 * something moves. Each one is a small object with the same three doors —
 * start, stop, and a way to say the game is over with a number — so the page
 * around them does not need to know which one is being played.
 */
(function () {
    var GAMES = @json($games);
    var MINE = @json((object) $mine);
    var BOARDS = @json($boards->map ?? $boards);
    var SCORE_URL = @json(route('admin.games.score'));
    var TOKEN = document.querySelector('meta[name="csrf-token"]')
        ? document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        : '{{ csrf_token() }}';

    var shelf = document.getElementById('gmShelf');
    var open = document.getElementById('gmOpen');
    var holder = document.getElementById('gmBoardHolder');
    var scoreEl = document.getElementById('gmScore');
    var bestEl = document.getElementById('gmBest');
    var hintEl = document.getElementById('gmHint');
    var boardEl = document.getElementById('gmBoard');

    var playing = null;      // the game object that is running
    var current = null;      // its key

    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function setScore(n) { scoreEl.textContent = Number(n).toLocaleString(); }

    function paintBest() {
        var best = MINE[current];
        bestEl.textContent = (best === undefined || best === null) ? '—' : Number(best).toLocaleString();
    }

    function paintBoard(rows) {
        if (!rows || !rows.length) {
            boardEl.innerHTML = '<div class="empty-state" style="padding: 1.4rem 0.5rem;"><p>Nobody has played yet. Be first.</p></div>';
            return;
        }

        boardEl.innerHTML = rows.map(function (row, i) {
            return '<div class="gm-row' + (row.isMe ? ' is-me' : '') + '">' +
                   '<span class="gm-place">' + (i + 1) + '</span>' +
                   '<span class="gm-who">' + esc(row.name) + '</span>' +
                   '<span class="gm-pts">' + Number(row.score).toLocaleString() + '</span>' +
                   '</div>';
        }).join('');
    }

    /*
     * The end of a game.
     *
     * The server decides whether it was a best — the page is in no position to
     * know what anybody else has done since it loaded.
     */
    function finished(score, message) {
        hintEl.textContent = message || 'Game over.';

        // Nothing achieved is not a result worth putting on a board, and a
        // column of zeroes beside people's names is just noise.
        if (GAMES[current].higher_is_better && score <= 0) return;

        fetch(SCORE_URL, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': TOKEN,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ game: current, score: Math.max(0, Math.round(score)) }),
        })
        .then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
        .then(function (data) {
            MINE[current] = data.mine;
            paintBest();
            paintBoard(data.board);

            if (data.best) {
                hintEl.textContent = 'That is your best yet.';
                if (typeof showToast === 'function') {
                    showToast('New best', GAMES[current].name + ': ' + Number(data.mine).toLocaleString() + ' ' + GAMES[current].unit + '.', 'success');
                }
            }
        })
        .catch(function () { /* a score that will not save is not worth a broken page */ });
    }

    /* ==================================================================== */
    /* 2048                                                                 */
    /* ==================================================================== */

    function make2048() {
        var size = 4;
        var cells = [];
        var grid = [];
        var score = 0;
        var over = false;

        var board = document.createElement('div');
        board.className = 'g48';

        for (var i = 0; i < size * size; i++) {
            var cell = document.createElement('div');
            cell.className = 'g48-cell';
            board.appendChild(cell);
            cells.push(cell);
        }

        // The tile colours, warm as the number grows — the shape of the board
        // should be readable without reading any of the numbers.
        function paint() {
            for (var i = 0; i < cells.length; i++) {
                var value = grid[i];
                var cell = cells[i];

                cell.textContent = value ? value : '';
                cell.style.background = value ? tint(value) : '';
                cell.style.color = value > 4 ? '#fff' : '';
            }
        }

        function tint(value) {
            var steps = {
                2: 'rgba(249,157,78,0.14)', 4: 'rgba(249,157,78,0.26)',
                8: '#F6B26B', 16: '#F29D52', 32: '#EE8639', 64: '#E96F21',
                128: '#E35B18', 256: '#D94C14', 512: '#C93F11',
                1024: '#B8330E', 2048: '#10B981',
            };
            return steps[value] || '#0F766E';
        }

        function freeSpots() {
            var out = [];
            for (var i = 0; i < grid.length; i++) if (!grid[i]) out.push(i);
            return out;
        }

        function drop() {
            var spots = freeSpots();
            if (!spots.length) return;

            var at = spots[Math.floor(Math.random() * spots.length)];
            grid[at] = Math.random() < 0.9 ? 2 : 4;

            cells[at].classList.remove('is-new');
            void cells[at].offsetWidth;        // let the animation run again
            cells[at].classList.add('is-new');
        }

        /** One row or column, squashed towards the start. */
        function squash(line) {
            var kept = line.filter(function (n) { return n; });
            var out = [];

            for (var i = 0; i < kept.length; i++) {
                if (kept[i] === kept[i + 1]) {
                    out.push(kept[i] * 2);
                    score += kept[i] * 2;
                    i++;
                } else {
                    out.push(kept[i]);
                }
            }

            while (out.length < size) out.push(0);
            return out;
        }

        function lineOf(which, index) {
            var out = [];
            for (var i = 0; i < size; i++) {
                out.push(which === 'row' ? grid[index * size + i] : grid[i * size + index]);
            }
            return out;
        }

        function putLine(which, index, line) {
            for (var i = 0; i < size; i++) {
                if (which === 'row') grid[index * size + i] = line[i];
                else grid[i * size + index] = line[i];
            }
        }

        function move(direction) {
            if (over) return;

            var before = grid.join(',');
            var backwards = direction === 'right' || direction === 'down';
            var which = (direction === 'left' || direction === 'right') ? 'row' : 'col';

            for (var i = 0; i < size; i++) {
                var line = lineOf(which, i);
                if (backwards) line.reverse();

                line = squash(line);
                if (backwards) line.reverse();

                putLine(which, i, line);
            }

            if (grid.join(',') === before) return;   // nothing shifted, so no new tile

            drop();
            paint();
            setScore(score);

            if (stuck()) {
                over = true;
                finished(score, 'No moves left — ' + score.toLocaleString() + ' points.');
            }
        }

        function stuck() {
            if (freeSpots().length) return false;

            for (var r = 0; r < size; r++) {
                for (var c = 0; c < size; c++) {
                    var here = grid[r * size + c];
                    if (c + 1 < size && grid[r * size + c + 1] === here) return false;
                    if (r + 1 < size && grid[(r + 1) * size + c] === here) return false;
                }
            }
            return true;
        }

        var onKey = function (e) {
            var map = { ArrowLeft: 'left', ArrowRight: 'right', ArrowUp: 'up', ArrowDown: 'down',
                        a: 'left', d: 'right', w: 'up', s: 'down' };
            var direction = map[e.key];
            if (!direction) return;

            e.preventDefault();
            move(direction);
        };

        // A finger counts as the same four directions.
        var from = null;
        board.addEventListener('pointerdown', function (e) { from = { x: e.clientX, y: e.clientY }; });
        board.addEventListener('pointerup', function (e) {
            if (!from) return;

            var dx = e.clientX - from.x;
            var dy = e.clientY - from.y;
            from = null;

            if (Math.abs(dx) < 24 && Math.abs(dy) < 24) return;

            move(Math.abs(dx) > Math.abs(dy)
                ? (dx > 0 ? 'right' : 'left')
                : (dy > 0 ? 'down' : 'up'));
        });

        return {
            label: 'Score',
            hint: 'Arrow keys, or swipe. Two of the same number make one bigger one.',
            element: board,
            start: function () {
                grid = new Array(size * size).fill(0);
                score = 0;
                over = false;
                drop(); drop();
                paint();
                setScore(0);
                document.addEventListener('keydown', onKey);
            },
            stop: function () { document.removeEventListener('keydown', onKey); },
        };
    }

    /* ==================================================================== */
    /* Snake                                                                */
    /* ==================================================================== */

    function makeSnake() {
        var squares = 17;
        var canvas = document.createElement('canvas');
        canvas.className = 'gsnake';
        canvas.width = 510;
        canvas.height = 510;

        var ctx = canvas.getContext('2d');
        var step = canvas.width / squares;

        var snake, heading, next, apple, score, ticker, dead;

        function ink(name, fallback) {
            var value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
            return value || fallback;
        }

        function place() {
            var spot;
            do {
                spot = { x: Math.floor(Math.random() * squares), y: Math.floor(Math.random() * squares) };
            } while (snake.some(function (bit) { return bit.x === spot.x && bit.y === spot.y; }));
            return spot;
        }

        function draw() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            ctx.fillStyle = '#EF4444';
            round(apple.x * step + 3, apple.y * step + 3, step - 6, step - 6, 6);

            var primary = ink('--primary', '#F99D4E');
            for (var i = snake.length - 1; i >= 0; i--) {
                ctx.fillStyle = i === 0 ? primary : 'rgba(249,157,78,' + (0.45 + 0.4 * (1 - i / snake.length)) + ')';
                round(snake[i].x * step + 2, snake[i].y * step + 2, step - 4, step - 4, 5);
            }
        }

        function round(x, y, w, h, r) {
            ctx.beginPath();
            if (ctx.roundRect) ctx.roundRect(x, y, w, h, r);
            else ctx.rect(x, y, w, h);
            ctx.fill();
        }

        function tick() {
            heading = next;

            // It sits still until the first key. Starting at a wall-ward crawl
            // gave about a second to react, which is not a game, it is a trap.
            if (!heading.x && !heading.y) return;

            var head = { x: snake[0].x + heading.x, y: snake[0].y + heading.y };

            var bitSelf = snake.some(function (bit) { return bit.x === head.x && bit.y === head.y; });
            var offBoard = head.x < 0 || head.y < 0 || head.x >= squares || head.y >= squares;

            if (offBoard || bitSelf) {
                dead = true;
                clearInterval(ticker);
                finished(score, 'Caught — ' + score + ' points.');
                return;
            }

            snake.unshift(head);

            if (head.x === apple.x && head.y === apple.y) {
                score += 10;
                setScore(score);
                apple = place();
            } else {
                snake.pop();
            }

            draw();
        }

        function steer(x, y) {
            // No turning back on yourself: that is a crash, not a turn.
            if (heading.x === -x && heading.y === -y) return;
            next = { x: x, y: y };
            hintEl.textContent = 'Arrow keys, or swipe. Ten points an apple.';
        }

        var onKey = function (e) {
            var map = {
                ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1],
                a: [-1, 0], d: [1, 0], w: [0, -1], s: [0, 1],
            };
            var to = map[e.key];
            if (!to) return;

            e.preventDefault();
            steer(to[0], to[1]);
        };

        var from = null;
        canvas.addEventListener('pointerdown', function (e) { from = { x: e.clientX, y: e.clientY }; });
        canvas.addEventListener('pointerup', function (e) {
            if (!from) return;
            var dx = e.clientX - from.x, dy = e.clientY - from.y;
            from = null;
            if (Math.abs(dx) < 20 && Math.abs(dy) < 20) return;

            if (Math.abs(dx) > Math.abs(dy)) steer(dx > 0 ? 1 : -1, 0);
            else steer(0, dy > 0 ? 1 : -1);
        });

        return {
            label: 'Score',
            hint: 'Press an arrow key to set off. Ten points an apple.',
            element: canvas,
            start: function () {
                var middle = Math.floor(squares / 2);
                snake = [{ x: middle, y: middle }, { x: middle - 1, y: middle }, { x: middle - 2, y: middle }];
                // Not moving yet; the first key decides which way.
                heading = { x: 0, y: 0 };
                next = heading;
                score = 0;
                dead = false;
                apple = place();

                setScore(0);
                draw();

                document.addEventListener('keydown', onKey);
                if (ticker) clearInterval(ticker);
                ticker = setInterval(tick, 110);
            },
            stop: function () {
                document.removeEventListener('keydown', onKey);
                if (ticker) clearInterval(ticker);
            },
        };
    }

    /* ==================================================================== */
    /* Memory                                                               */
    /* ==================================================================== */

    function makeMemory() {
        var faces = ['🍀', '⚡', '🎯', '🚀', '🎧', '🧊', '🔔', '🌙'];
        var board = document.createElement('div');
        board.className = 'gmem';

        var buttons = [];
        var up = [];
        var moves = 0;
        var made = 0;
        var busy = false;

        function shuffled() {
            var deck = faces.concat(faces);
            for (var i = deck.length - 1; i > 0; i--) {
                var j = Math.floor(Math.random() * (i + 1));
                var swap = deck[i]; deck[i] = deck[j]; deck[j] = swap;
            }
            return deck;
        }

        function turn(button) {
            if (busy || button.classList.contains('is-up') || button.classList.contains('is-made')) return;

            button.classList.remove('is-down');
            button.classList.add('is-up');
            up.push(button);

            if (up.length < 2) return;

            moves += 1;
            setScore(moves);

            var a = up[0], b = up[1];

            if (a.dataset.face === b.dataset.face) {
                a.classList.remove('is-up'); b.classList.remove('is-up');
                a.classList.add('is-made'); b.classList.add('is-made');
                a.disabled = true; b.disabled = true;
                up = [];
                made += 1;

                if (made === faces.length) {
                    finished(moves, 'All of them, in ' + moves + ' moves.');
                }
                return;
            }

            // Long enough to have seen them, short enough not to wait.
            busy = true;
            setTimeout(function () {
                a.classList.remove('is-up'); b.classList.remove('is-up');
                a.classList.add('is-down'); b.classList.add('is-down');
                up = [];
                busy = false;
            }, 650);
        }

        return {
            label: 'Moves',
            hint: 'Find the eight pairs. Fewer moves is better.',
            element: board,
            start: function () {
                var deck = shuffled();
                board.innerHTML = '';
                buttons = [];
                up = [];
                moves = 0;
                made = 0;
                busy = false;
                setScore(0);

                deck.forEach(function (face) {
                    var button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'is-down';
                    button.dataset.face = face;
                    button.textContent = face;
                    button.addEventListener('click', function () { turn(button); });
                    board.appendChild(button);
                    buttons.push(button);
                });
            },
            stop: function () {},
        };
    }

    var MAKERS = { '2048': make2048, 'snake': makeSnake, 'memory': makeMemory };

    /* ==================================================================== */

    function openGame(key) {
        if (!MAKERS[key]) return;

        if (playing && playing.stop) playing.stop();

        current = key;
        playing = MAKERS[key]();

        document.getElementById('gmTitle').textContent = GAMES[key].name;
        document.getElementById('gmScoreLabel').textContent = playing.label;
        hintEl.textContent = playing.hint;

        holder.innerHTML = '';
        holder.appendChild(playing.element);

        paintBest();
        paintBoard((BOARDS[key] || []).map(function (row) {
            return { name: (row.user && (row.user.display_name || row.user.name)) || 'Somebody', score: row.score, isMe: false };
        }));

        shelf.hidden = true;
        open.hidden = false;
        document.getElementById('gmBack').hidden = false;

        playing.start();
    }

    function closeGame() {
        if (playing && playing.stop) playing.stop();
        playing = null;
        current = null;

        open.hidden = true;
        shelf.hidden = false;
        document.getElementById('gmBack').hidden = true;
    }

    shelf.addEventListener('click', function (e) {
        var card = e.target.closest('.gm-card');
        if (card) openGame(card.dataset.game);
    });

    shelf.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        var card = e.target.closest('.gm-card');
        if (!card) return;
        e.preventDefault();
        openGame(card.dataset.game);
    });

    document.getElementById('gmBack').addEventListener('click', closeGame);
    document.getElementById('gmRestart').addEventListener('click', function () {
        if (!playing) return;
        if (playing.stop) playing.stop();
        hintEl.textContent = playing.hint;
        playing.start();
    });

    // Leaving the page mid-game should not leave a timer running.
    window.addEventListener('beforeunload', function () { if (playing && playing.stop) playing.stop(); });
})();
</script>
