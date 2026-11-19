<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Systematic IT Solutions</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon-sis.png') }}">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #f99d4e;
            --primary-dark: #e88c3d;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --error: #ef4444;
            --navy: #070c17;
            --navy-2: #0d1526;
            --glow: #7dd3fc;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        html, body { height: 100%; }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
            background:
                radial-gradient(circle at 22% 15%, rgba(125,211,252,0.10) 0%, transparent 50%),
                radial-gradient(circle at 80% 85%, rgba(125,211,252,0.07) 0%, transparent 50%),
                linear-gradient(165deg, var(--navy-2) 0%, var(--navy) 100%);
        }

        /* faint studio grid across the whole page now, not just half of it */
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
            background-size: 44px 44px;
            mask-image: radial-gradient(circle at 50% 35%, black 0%, transparent 72%);
            pointer-events: none;
        }

        .decor-circle {
            position: fixed;
            border-radius: 50%;
            background: var(--glow);
            opacity: 0.05;
            pointer-events: none;
        }
        .circle-1 { width: 420px; height: 420px; top: -220px; right: -160px; }
        .circle-2 { width: 320px; height: 320px; bottom: -180px; left: -140px; }

        /* ============ Stage: robot behind, card floating in front ============ */
        .stage {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 3.5rem 1.5rem 3rem;
            width: 100%;
        }

        .tagline {
            text-align: center;
            margin-bottom: 0.6rem;
        }
        .tagline h2 {
            color: #e2e8f0;
            font-size: 1.05rem;
            font-weight: 600;
        }
        .tagline p {
            color: #5b6b85;
            font-size: 0.8rem;
            margin-top: 0.25rem;
        }

        .robot-stage {
            position: relative;
            width: 260px;
            height: 300px;
            z-index: 1;
            transition: transform 0.5s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .robot-stage.password-active {
            transform: scale(1.1) translateY(20px);
        }

        .robot-shadow {
            position: absolute;
            bottom: 14px;
            left: 50%;
            width: 140px;
            height: 20px;
            transform: translateX(-50%);
            background: radial-gradient(ellipse at center, rgba(0,0,0,0.6) 0%, transparent 72%);
            filter: blur(2px);
        }

        .robot {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .robot-head-wrap {
            position: relative;
            width: 200px;
            height: 150px;
            margin-top: 14px;
        }

        .robot-ear {
            position: absolute;
            top: 38%;
            width: 19px;
            height: 44px;
            border-radius: 10px;
            background: linear-gradient(180deg, #eef2f6 0%, #c7ced8 55%, #aab2c0 100%);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.5), 0 4px 10px rgba(0,0,0,0.35);
        }
        .robot-ear-left { left: -13px; }
        .robot-ear-right { right: -13px; }

        .robot-head {
            position: absolute;
            inset: 0;
            border-radius: 56px 56px 60px 60px;
            background: linear-gradient(160deg, #ffffff 0%, #eef1f5 38%, #d7dce3 78%, #c3c9d3 100%);
            box-shadow:
                inset 0 3px 6px rgba(255,255,255,0.9),
                inset 0 -14px 22px rgba(120,130,150,0.28),
                0 18px 30px -10px rgba(0,0,0,0.6);
        }
        .robot-head::after {
            content: "";
            position: absolute;
            top: 8%;
            left: 10%;
            width: 34%;
            height: 22%;
            border-radius: 50%;
            background: linear-gradient(160deg, rgba(255,255,255,0.75), rgba(255,255,255,0));
            filter: blur(2px);
        }

        .robot-visor {
            position: absolute;
            left: 9%;
            right: 9%;
            top: 26%;
            bottom: 23%;
            border-radius: 32px;
            background: radial-gradient(120% 140% at 30% 20%, #1c2333 0%, #060a12 65%, #030509 100%);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.06), inset 0 4px 14px rgba(0,0,0,0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16%;
            overflow: hidden;
        }
        .robot-visor::before {
            content: "";
            position: absolute;
            top: -30%;
            left: -20%;
            width: 55%;
            height: 160%;
            background: linear-gradient(120deg, rgba(255,255,255,0.09), rgba(255,255,255,0) 70%);
            transform: rotate(8deg);
            pointer-events: none;
        }

        .eye { position: relative; width: 32px; height: 32px; }
        .eye-open {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: radial-gradient(circle at 35% 30%, #eafcff 0%, var(--glow) 45%, #2596d1 100%);
            box-shadow: 0 0 10px 3px rgba(125,211,252,0.55), 0 0 26px 8px rgba(96,165,250,0.28);
            transition: opacity 0.16s ease;
            will-change: transform;
        }
        .eye-closed {
            position: absolute;
            left: 2px; right: 2px; top: 46%;
            height: 5px;
            border-radius: 4px;
            background: linear-gradient(90deg, rgba(125,211,252,0.15), var(--glow) 50%, rgba(125,211,252,0.15));
            box-shadow: 0 0 8px 2px rgba(125,211,252,0.4);
            opacity: 0;
            transition: opacity 0.16s ease;
        }
        .robot.eyes-closed .eye-open, .robot.blink-once .eye-open { opacity: 0; }
        .robot.eyes-closed .eye-closed, .robot.blink-once .eye-closed { opacity: 1; }

        .robot-neck {
            width: 32px; height: 24px; margin-top: -6px;
            background: linear-gradient(180deg, #c9cfd8, #a9b0bc);
            border-radius: 6px;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.25);
        }

        .robot-base {
            position: relative;
            width: 160px; height: 44px; margin-top: -4px;
            border-radius: 50% / 60%;
            background: linear-gradient(180deg, #f2f4f7 0%, #d3d8e0 55%, #b7bdc8 100%);
            box-shadow:
                inset 0 2px 4px rgba(255,255,255,0.8),
                inset 0 -8px 14px rgba(110,118,135,0.35),
                0 12px 20px -6px rgba(0,0,0,0.55);
            display: flex; align-items: flex-end; justify-content: center;
            padding-bottom: 9px;
        }
        .robot-base-light {
            width: 44px; height: 6px; border-radius: 4px;
            background: var(--glow);
            box-shadow: 0 0 10px 3px rgba(125,211,252,0.7);
        }

        .robot-aura {
            position: absolute;
            width: 300px; height: 300px; border-radius: 50%;
            background: radial-gradient(circle, rgba(96,165,250,0.16) 0%, transparent 70%);
            top: 46%; left: 50%;
            transform: translate(-50%, -54%);
            animation: auraPulse 4.5s ease-in-out infinite;
            z-index: -1;
        }
        .password-active .robot-aura { background: radial-gradient(circle, rgba(96,165,250,0.28) 0%, transparent 70%); }
        @keyframes auraPulse {
            0%, 100% { opacity: 0.55; transform: translate(-50%, -54%) scale(0.96); }
            50% { opacity: 1; transform: translate(-50%, -54%) scale(1.05); }
        }

        /* ============ The card, overlapping up into the robot's base ============ */
        .login-card-wrap {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 400px;
            margin-top: -152px;
            transition: transform 0.45s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .login-card-wrap.password-active {
            transform: translateY(-58px);
        }

        .login-card {
            background: rgba(17, 25, 43, 0.72);
            backdrop-filter: blur(22px);
            -webkit-backdrop-filter: blur(22px);
            border: 1px solid rgba(255,255,255,0.09);
            padding: 2.3rem 2.1rem 2.1rem;
            border-radius: 26px;
            box-shadow: 0 25px 60px -15px rgba(0,0,0,0.65), 0 0 0 1px rgba(125,211,252,0.04);
            text-align: center;
        }

        .logo { max-width: 230px; max-height: 60px; margin-bottom: 1.4rem; }

        /* Shown only when no logo has been uploaded — a plain wordmark, not a
           generated avatar square, so it reads as intentional either way. */
        .logo-word {
            margin-bottom: 1.6rem;
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            background: linear-gradient(135deg, #f8fafc 0%, #94a3b8 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .logo-word span { display: inline-block; padding-bottom: 0.6rem; border-bottom: 2px solid rgba(125,211,252,0.35); }

        h1 { font-size: 1.4rem; color: #f1f5f9; margin-bottom: 0.35rem; font-weight: 700; }
        p.subtitle { color: #7c8aa5; font-size: 0.85rem; margin-bottom: 1.8rem; }

        .form-group { text-align: left; margin-bottom: 1.3rem; }

        label { display: block; font-size: 0.8rem; font-weight: 600; color: #c3cbdc; margin-bottom: 7px; }

        .input-wrapper { position: relative; }
        /* Direct children only: the icon inside the show-password button is a
           descendant, and would otherwise be pulled to the left of the field. */
        .input-wrapper > i {
            position: absolute; left: 15px; top: 50%; transform: translateY(-50%);
            color: #64748b; font-size: 0.95rem;
        }

        .pw-toggle {
            position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
            background: transparent; border: 0; color: #64748b; cursor: pointer;
            padding: 8px 10px; border-radius: 9px; font-size: 0.95rem; line-height: 1;
            transition: color 0.2s ease, background 0.2s ease;
        }
        .pw-toggle:hover { color: #f1f5f9; background: rgba(255,255,255,0.06); }
        .pw-toggle:focus-visible { outline: 2px solid var(--glow); outline-offset: 2px; }
        #passwordField { padding-right: 46px; }

        input {
            width: 100%;
            padding: 12px 15px 12px 44px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.1);
            outline: none;
            transition: all 0.25s ease;
            font-size: 0.92rem;
            background: rgba(255,255,255,0.04);
            color: #f1f5f9;
        }
        input::placeholder { color: #4b5872; }

        input:focus {
            border-color: var(--glow);
            background: rgba(255,255,255,0.07);
            box-shadow: 0 0 0 4px rgba(125,211,252,0.12);
        }

        .btn-login {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.98rem;
            cursor: pointer;
            transition: all 0.25s ease;
            display: flex; align-items: center; justify-content: center; gap: 10px;
            box-shadow: 0 10px 20px -6px rgba(249, 157, 78, 0.45);
            margin-top: 0.4rem;
        }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 14px 24px -6px rgba(249, 157, 78, 0.55); }

        .error-message {
            background: rgba(239,68,68,0.12);
            color: #fca5a5;
            padding: 10px;
            border-radius: 10px;
            font-size: 0.83rem;
            margin-bottom: 1.4rem;
            display: flex; align-items: center; gap: 8px;
            border: 1px solid rgba(239,68,68,0.25);
            text-align: left;
        }

        @media (max-width: 480px) {
            .robot-stage { width: 210px; height: 250px; transform-origin: bottom center; }
            .robot-head-wrap { width: 160px; height: 120px; }
            .login-card-wrap { margin-top: -120px; }
            .login-card { padding: 1.9rem 1.5rem 1.7rem; border-radius: 22px; }
        }

        @media (prefers-reduced-motion: reduce) {
            .robot-aura { animation: none; }
            .eye-open, .robot-stage, .login-card-wrap { transition: none; }
        }
    </style>
</head>
<body>
    <div class="decor-circle circle-1"></div>
    <div class="decor-circle circle-2"></div>

    <div class="stage">
        <div class="tagline">
            <h2>Your CRM, watched over.</h2>
            <p>Sign in to pick up right where you left off.</p>
        </div>

        <div class="robot-stage" id="robotStage">
            <div class="robot-aura"></div>
            <div class="robot-shadow"></div>
            <div class="robot" id="robotMascot">
                <div class="robot-head-wrap">
                    <div class="robot-ear robot-ear-left"></div>
                    <div class="robot-ear robot-ear-right"></div>
                    <div class="robot-head"></div>
                    <div class="robot-visor" id="robotVisor">
                        <div class="eye eye-left">
                            <div class="eye-open"></div>
                            <div class="eye-closed"></div>
                        </div>
                        <div class="eye eye-right">
                            <div class="eye-open"></div>
                            <div class="eye-closed"></div>
                        </div>
                    </div>
                </div>
                <div class="robot-neck"></div>
                <div class="robot-base">
                    <div class="robot-base-light"></div>
                </div>
            </div>
        </div>

        <div class="login-card-wrap" id="loginCardWrap">
            <div class="login-card">
                @php
                    $primaryCompany = \App\Models\Company::first();
                    $companyName = $primaryCompany->name ?? 'Systematic IT Solutions';

                    /*
                     * The logo, in the order that gives the best-looking card.
                     *
                     * This card is dark glass, so a logo drawn for dark comes
                     * first. The company's own dark_logo column is read now —
                     * it is what Settings > Branding writes, and this screen
                     * used to ignore it and look only for a file named
                     * *dark_logo* on disk, which nothing has created since that
                     * column existed.
                     *
                     * The last image is the white wordmark that ships with the
                     * CRM. The sidebar has always fallen back to it, and this
                     * page had no image fallback at all — which is why a company
                     * that has not uploaded anything saw its logo in the sidebar
                     * and its name as plain text here.
                     */
                    $onDisk = function (?string $path): ?string {
                        return $path && file_exists(public_path($path)) ? $path : null;
                    };

                    $legacyDark = glob(public_path('uploads/company_logos/*dark_logo*'));

                    $logo = $onDisk($primaryCompany?->dark_logo)
                        ?? (!empty($legacyDark) ? 'uploads/company_logos/' . basename($legacyDark[0]) : null)
                        ?? $onDisk($primaryCompany?->logo)
                        ?? $onDisk('uploads/company_logos/whitelogo.png');
                @endphp
                @if($logo)
                    {{-- Shown as uploaded: a real logo can be any colour, so it is
                         never force-inverted the way a generated placeholder was. --}}
                    <img src="{{ url('file/' . $logo) }}" alt="{{ $companyName }}" class="logo">
                @else
                    {{-- Nothing to show at all. A wordmark built from the company's
                         own name reads as deliberate on this dark card; the old
                         fallback called an external avatar service and rendered as a
                         flat orange square that looked like a broken image. --}}
                    <div class="logo-word"><span>{{ $companyName }}</span></div>
                @endif
                <h1>Welcome Back</h1>
                <p class="subtitle">Sign in to your account</p>

                @if($errors->any())
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    {{ $errors->first() }}
                </div>
                @endif

                <form action="{{ route('login.post') }}" method="POST">
                    @csrf
                    <div class="form-group">
                        <label>Email Address or Username</label>
                        <div class="input-wrapper">
                            <i class="far fa-user"></i>
                            <input type="text" name="login" id="loginField" placeholder="Email or username" required value="{{ old('login') }}">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 0.6rem;">
                        <label>Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="password" id="passwordField" placeholder="••••••••" required>
                            <button type="button" class="pw-toggle" id="pwToggle" onclick="togglePassword()"
                                    aria-label="Show password" aria-pressed="false" title="Show password">
                                <i class="far fa-eye" id="pwToggleIcon"></i>
                            </button>
                        </div>
                    </div>

                    {{-- The way back in without waiting for an administrator. --}}
                    <div style="text-align: right; margin-bottom: 1.1rem;">
                        <a href="{{ route('password.request') }}"
                           style="font-size: 0.82rem; color: var(--primary); text-decoration: none;">
                            Forgotten your password?
                        </a>
                    </div>

                    <button type="submit" class="btn-login">
                        Sign In <i class="fas fa-arrow-right"></i>
                    </button>
                </form>

                {{-- Quiet, but present: a customer deciding whether to use this
                     reads these before they have an account to sign into. --}}
                <div style="margin-top: 1.6rem; text-align: center; font-size: 0.78rem; color: rgba(255,255,255,0.45);">
                    <a href="{{ route('legal.privacy') }}" style="color: inherit; text-decoration: none;">Privacy</a>
                    <span style="margin: 0 8px;">·</span>
                    <a href="{{ route('legal.terms') }}" style="color: inherit; text-decoration: none;">Terms</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        /*
         * Show the password while typing it.
         *
         * The cursor is put back where it was: switching the input's type moves it
         * to the end, which is exactly the wrong thing to do to somebody who
         * revealed the field to fix one character in the middle.
         */
        function togglePassword() {
            var field = document.getElementById('passwordField');
            var button = document.getElementById('pwToggle');
            var icon = document.getElementById('pwToggleIcon');
            if (!field) return;

            var showing = field.type === 'text';
            var at = field.selectionStart;

            field.type = showing ? 'password' : 'text';
            icon.className = showing ? 'far fa-eye' : 'far fa-eye-slash';
            button.setAttribute('aria-pressed', showing ? 'false' : 'true');
            button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
            button.title = showing ? 'Show password' : 'Hide password';

            field.focus();
            try { field.setSelectionRange(at, at); } catch (e) { /* not supported */ }
        }

        /*
         * Robot mascot: eyes genuinely track the cursor (no pre-rendered animation to
         * loop or glitch), close while the password field is focused, and the whole
         * composition shifts when that happens — the card rises and the robot leans
         * in slightly, so its face reads clearly above the card while a password is
         * being typed instead of staying half hidden behind it.
         */
        (function () {
            var robot = document.getElementById('robotMascot');
            var robotStage = document.getElementById('robotStage');
            var cardWrap = document.getElementById('loginCardWrap');
            var visor = document.getElementById('robotVisor');
            var eyes = document.querySelectorAll('.eye-open');
            var passwordField = document.getElementById('passwordField');

            if (!robot || !visor || !eyes.length) return;

            // Generous range so the eyes clearly, visibly track the cursor rather
            // than a barely-there flicker — tuned to stay inside the visor glass at
            // full deflection (the visor has roughly 37px of horizontal and 22px of
            // vertical clearance around the eyes at rest, so this stays inside it).
            var maxOffsetX = 20;
            var maxOffsetY = 14;
            var targetX = 0, targetY = 0, currentX = 0, currentY = 0;
            var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            function updateTarget(clientX, clientY) {
                var rect = visor.getBoundingClientRect();
                var cx = rect.left + rect.width / 2;
                var cy = rect.top + rect.height / 2;
                var dx = clientX - cx;
                var dy = clientY - cy;
                var dist = Math.sqrt(dx * dx + dy * dy) || 1;
                // Reaches full deflection by ~190px away, so normal cursor movement
                // around the form is enough to swing the eyes clearly side to side.
                var pull = Math.min(1, dist / 190);
                targetX = (dx / dist) * maxOffsetX * pull;
                targetY = (dy / dist) * maxOffsetY * pull;
            }

            document.addEventListener('mousemove', function (e) {
                updateTarget(e.clientX, e.clientY);
            });

            function frame() {
                var ease = reduceMotion ? 1 : 0.16;
                currentX += (targetX - currentX) * ease;
                currentY += (targetY - currentY) * ease;
                for (var i = 0; i < eyes.length; i++) {
                    eyes[i].style.transform = 'translate(' + currentX.toFixed(2) + 'px,' + currentY.toFixed(2) + 'px)';
                }
                requestAnimationFrame(frame);
            }
            frame();

            if (passwordField) {
                passwordField.addEventListener('focus', function () {
                    robot.classList.add('eyes-closed');
                    robotStage.classList.add('password-active');
                    cardWrap.classList.add('password-active');
                });
                passwordField.addEventListener('blur', function () {
                    robot.classList.remove('eyes-closed');
                    robotStage.classList.remove('password-active');
                    cardWrap.classList.remove('password-active');
                });
            }

            // Idle blink every few seconds so the robot reads as alive, not static.
            if (!reduceMotion) {
                (function scheduleBlink() {
                    var delay = 2800 + Math.random() * 3200;
                    setTimeout(function () {
                        if (document.activeElement !== passwordField) {
                            robot.classList.add('blink-once');
                            setTimeout(function () { robot.classList.remove('blink-once'); }, 160);
                        }
                        scheduleBlink();
                    }, delay);
                })();
            }
        })();
    </script>
</body>
</html>
