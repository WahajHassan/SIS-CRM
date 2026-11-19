<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Auto-refresh every 5 minutes (300 seconds) to fetch live data -->
    <meta http-equiv="refresh" content="60">
    <title>Sales Performance Scoreboard | Systematic IT Solutions</title>
    @if($company && $company->favicon && file_exists(public_path($company->favicon)))
        <link rel="icon" type="image/x-icon" href="{{ url('file/' . $company->favicon) }}">
    @else
        <link rel="icon" type="image/x-icon" href="{{ asset('img/favicon.png') }}">
    @endif
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Syncopate:wght@700&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script>
        const COMPANY_SLUG = "{{ $company->slug }}";
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.classList.add('dark-mode');
        }
    </script>
    <style>
        :root {
            @if($company && $company->theme_color)
                --primary: {{ $company->theme_color }};
                --brand-gradient: linear-gradient(135deg, {{ $company->theme_color }} 0%, #e93b6e 100%);
                --brand-soft: linear-gradient(135deg, {{ $company->theme_color }}1a 0%, rgba(233,59,110,0.06) 100%);
            @else
                --primary: #f99d4e;
                --brand-gradient: linear-gradient(135deg, #f99d4e 0%, #e93b6e 100%);
                --brand-soft: linear-gradient(135deg, rgba(249,157,78,0.10) 0%, rgba(233,59,110,0.06) 100%);
            @endif
            --secondary: #e93b6e;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --green: #10b981;
            --gold: #fbbf24;
            --blue: #3b82f6;
            --purple: #8b5cf6;
            --card: rgba(255,255,255,0.92);
            --glass: rgba(255,255,255,0.75);
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        html, body {
            font-family: 'Outfit', sans-serif;
            height: 100vh;
            overflow: hidden;
            color: var(--text-main);
            background: linear-gradient(-45deg, #f0f4f8, #e8edf5, #f0f0fa, #eef5f0);
            background-size: 400% 400%;
            animation: bgShift 20s ease infinite;
        }
        @keyframes bgShift {
            0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}
        }

        /* ===== RUSH HOUR OVERLAY (3D GSAP) ===== */
        #rush-hour-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: #050510; /* Very dark blue/black for space effect */
            z-index: 9999;
            display: none; flex-direction: column; align-items: center; justify-content: center;
            opacity: 0; transition: opacity 0.5s ease;
            color: white; overflow: hidden;
            perspective: 1200px; /* 3D Perspective */
        }
        #rush-hour-overlay.active {
            display: flex; opacity: 1;
        }
        .dashboard { transition: opacity 1s ease-in-out; }
        .dashboard.hidden { opacity: 0; pointer-events: none; }
        
        /* 3D Background Particles Container */
        .rh-bg-effects {
            position: absolute; top:0; left:0; width:100%; height:100%;
            z-index: 0; transform-style: preserve-3d;
        }
        .rh-particle {
            position: absolute;
            background: #ef4444;
            border-radius: 50%;
            box-shadow: 0 0 20px 5px rgba(239, 68, 68, 0.6);
            opacity: 0;
        }

        .rh-content { 
            z-index: 10; 
            text-align: center; 
            transform-style: preserve-3d;
        }
        
        .rh-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 8rem; font-weight: 900; line-height: 1;
            text-transform: uppercase; letter-spacing: 12px; margin-bottom: 15px;
            
            /* Gradient text outline / neon glow */
            background: linear-gradient(135deg, #ffffff 10%, #ff9999 50%, #ff3333 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            
            filter: drop-shadow(0px 0px 20px rgba(239, 68, 68, 0.7)) drop-shadow(0px 10px 30px rgba(239, 68, 68, 0.4));
            transform-style: preserve-3d;
            animation: rhGlowPulse 2.5s infinite alternate ease-in-out;
        }

        .rh-subtitle {
            font-family: 'Syncopate', sans-serif;
            font-size: 1.25rem; font-weight: 700; color: #cbd5e1; margin-bottom: 60px;
            text-transform: uppercase; letter-spacing: 12px;
            text-shadow: 0px 5px 15px rgba(0,0,0,0.5), 0px 0px 10px rgba(255,255,255,0.2);
            transform-style: preserve-3d;
        }

        @keyframes rhGlowPulse {
            0% {
                filter: drop-shadow(0px 0px 15px rgba(239, 68, 68, 0.5)) drop-shadow(0px 5px 15px rgba(239, 68, 68, 0.3));
                transform: scale(1);
            }
            100% {
                filter: drop-shadow(0px 0px 30px rgba(239, 68, 68, 0.9)) drop-shadow(0px 15px 45px rgba(239, 68, 68, 0.6));
                transform: scale(1.02);
            }
        }
        
        .rh-clock {
            display: flex; gap: 40px; justify-content: center;
            transform-style: preserve-3d;
            margin-top: 30px;
        }
        
        .rh-unit {
            position: relative;
            background: rgba(10, 10, 25, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 24px; 
            padding: 35px 25px; 
            width: 220px;
            height: 220px;
            backdrop-filter: blur(25px);
            box-shadow: 
                0 30px 60px rgba(0,0,0,0.8),
                inset 0 0 30px rgba(239, 68, 68, 0.1),
                inset 0 2px 10px rgba(255,255,255,0.05);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transform-style: preserve-3d;
            transition: all 0.3s ease;
        }

        .rh-unit::after {
            content: '';
            position: absolute;
            inset: -2px;
            border-radius: 26px;
            background: linear-gradient(135deg, rgba(239,68,68,0.5), rgba(249,157,78,0.2), rgba(239,68,68,0.5));
            z-index: -1;
            opacity: 0.4;
            transition: opacity 0.3s ease;
        }

        .rh-unit:hover::after {
            opacity: 0.8;
        }

        /* Circular Progress styling */
        .rh-circle-progress {
            position: absolute;
            top: 25px;
            width: 130px;
            height: 130px;
            z-index: 1;
            transform: rotate(-90deg);
        }

        .rh-circle-bg {
            fill: none;
            stroke: rgba(255, 255, 255, 0.03);
            stroke-width: 6;
        }

        .rh-circle-bar {
            fill: none;
            stroke: url(#rh-gradient);
            stroke-width: 6;
            stroke-linecap: round;
            stroke-dasharray: 283; /* 2 * PI * r = 2 * 3.14159 * 45 ≈ 283 */
            stroke-dashoffset: 283;
        }

        /* SVG Gradient definition inside overlay */
        .rh-svg-defs {
            position: absolute;
            width: 0;
            height: 0;
            pointer-events: none;
        }
        
        .rh-val { 
            font-size: 4.5rem; 
            font-weight: 950; 
            font-variant-numeric: tabular-nums; 
            line-height: 1; 
            color: #fff; 
            text-shadow: 
                0 0 20px rgba(255,255,255,0.5), 
                0 0 40px rgba(239,68,68,0.4),
                0 10px 20px rgba(0,0,0,0.8); 
            transform-style: preserve-3d;
            z-index: 2;
            font-family: 'Outfit', sans-serif;
            letter-spacing: -2px;
        }
        
        .rh-label { 
            font-size: 0.85rem; 
            font-weight: 800; 
            color: #94a3b8; 
            text-transform: uppercase; 
            letter-spacing: 4px; 
            margin-top: 45px; 
            z-index: 2;
            text-shadow: 0 2px 4px rgba(0,0,0,0.5);
            background: linear-gradient(90deg, #cbd5e1, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .rh-shimmer {
            position: absolute;
            top: 0; left: -100%;
            width: 50%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.05), transparent);
            transform: skewX(-20deg);
            animation: rhShine 4s infinite;
            pointer-events: none;
            z-index: 3;
        }
        @keyframes rhShine {
            0% { left: -150%; }
            100% { left: 150%; }
        }

        /* ===== DASHBOARD MASTER GRID ===== */
        .dashboard {
            display: flex;
            flex-direction: column;
            height: 100vh;
            padding: 8px 10px;
            gap:  16px;
            max-width: 1600px;
            margin: 0 auto;
        }

        /* ===== ROW 1: TOP BAR ===== */
        .top-bar {
            display: flex;
            gap:  16px;
            flex: 1;
            min-height: 0;
        }
        .clock-col {
            width: 300px;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            gap:  16px;
        }
        .center-col {
            flex: 1;
            min-width: 0;
            min-height: 0;
            display: flex;
            flex-direction: column;
            gap:  16px;
        }

        /* Clock */
        .clock-unit {
            background: var(--glass);
            border: 2px solid rgba(249, 157, 78, 0.2);
            border-radius: 16px;
            padding: 8px 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            backdrop-filter: blur(12px);
        
            box-shadow: inset 0 0 15px rgba(255, 255, 255, 0.3), inset 0 0 5px rgba(249, 157, 78, 0.2), 0 8px 24px rgba(0,0,0,0.06), 0 0 10px rgba(249, 157, 78, 0.15);}
        .analog-clock {
            width: 76px; height: 76px;
            border-radius: 50%;
            position: relative;
            background: linear-gradient(145deg, #fff, #f1f5f9);
            border: 3px solid #fff;
            box-shadow: 3px 3px 8px rgba(0,0,0,0.08), -3px -3px 8px rgba(255,255,255,0.8);
            flex-shrink: 0;
        }
        .clock-mark {
            position: absolute; width: 2px; height: 6px;
            background: #cbd5e1; left: 50%; top: 3px;
            transform-origin: 50% 35px;
        }
        .clock-mark.major { background: #94a3b8; height: 10px; width: 3px; }
        .clock-hand {
            position: absolute; bottom: 50%; left: 50%;
            transform-origin: bottom center; border-radius: 4px;
        }
        .hand-hour { width: 4px; height: 20px; background: #1e293b; z-index: 3; }
        .hand-min  { width: 3px; height: 26px; background: #475569; z-index: 2; }
        .hand-sec  { width: 2px; height: 32px; background: var(--primary); z-index: 1; }
        .clock-center-dot {
            position: absolute; top: 50%; left: 50%;
            width: 10px; height: 10px; background: var(--primary);
            border: 2px solid #fff; border-radius: 50%;
            transform: translate(-50%,-50%); z-index: 5;
        }
        .clock-info { display: flex; flex-direction: column; min-width: 0; }
        .clock-city { font-size: 0.95rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .clock-digital { font-size: 1.3rem; font-weight: 800; color: var(--text-main); font-variant-numeric: tabular-nums; line-height: 1.1; margin: 4px 0; }
        .clock-tz { font-size: 0.85rem; font-weight: 600; color: var(--text-muted); }

        /* News Ticker */
        .news-ticker {
            background: #1e293b;
            border: 2px solid rgba(249, 157, 78, 0.2);
            border-radius: 10px;
            padding: 12px 15px;
            overflow: hidden;
            position: relative;
            display: flex;
            align-items: center;
            box-shadow: inset 0 0 15px rgba(255, 255, 255, 0.3), inset 0 0 5px rgba(249, 157, 78, 0.2), 0 8px 24px rgba(0,0,0,0.06), 0 0 10px rgba(249, 157, 78, 0.15);
        }
        .news-badge {
            background: #ef4444;
            color: #fff;
            font-size: 0.75rem;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 4px;
            margin-right: 15px;
            flex-shrink: 0;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .ticker-wrap {
            overflow: hidden;
            flex: 1;
        }
        .ticker-track {
            display: flex;
            animation: tickerScroll 45s linear infinite;
            white-space: nowrap;
        }
        .ticker-track:hover { animation-play-state: paused; }
        .ticker-item {
            padding: 0 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }
        .ticker-item::before {
            content: '';
            width: 6px; height: 6px;
            background: #f97316;
            border-radius: 50%;
            flex-shrink: 0;
        }
        @keyframes tickerScroll {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }

        /* Weather */
        .weather-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 5px;
        }
        .weather-card {
            background: var(--glass);
            border: 2px solid rgba(249, 157, 78, 0.2);
            border-radius: 8px;
            padding: 6px 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(12px);
        }
        .weather-emoji { font-size: 1.3rem; line-height: 1; flex-shrink: 0; }
        .weather-city { font-size: 0.65rem; font-weight: 700; color: var(--text-main); }
        .weather-temp { font-size: 0.82rem; font-weight: 800; color: var(--primary); }
        .weather-cond { font-size: 0.58rem; color: var(--text-muted); }



        /* ===== LIVE NEWS SECTION ===== */
        .live-news-card {
            background: var(--card);
            border: 2px solid rgba(249, 157, 78, 0.2);
            border-radius: 16px;
            padding: 8px 10px;
            backdrop-filter: blur(12px);
            box-shadow: inset 0 0 15px rgba(255, 255, 255, 0.3), inset 0 0 5px rgba(249, 157, 78, 0.2), 0 8px 24px rgba(0,0,0,0.06), 0 0 10px rgba(249, 157, 78, 0.15);
            overflow: hidden;
            flex: 0 0 auto;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        .news-scroll-area {
            flex: 0 0 auto;
            overflow: hidden;
            position: relative;
            min-height: 0;
            height: 170px; /* Fixed height to fit exactly 2 items of 80px each + gaps */
        }
        .news-scroll-track {
            display: flex;
            flex-direction: column;
            gap: 10px;
            animation: newsScrollUp 30s linear infinite;
        }
        .news-scroll-area:hover .news-scroll-track {
            animation-play-state: paused;
        }
        @keyframes newsScrollUp {
            0% { transform: translateY(0); }
            100% { transform: translateY(-50%); }
        }
        .news-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px;
            border-radius: 8px;
            background: rgba(249,157,78,0.03);
            border: 1px solid rgba(255,255,255,0.3);
            transition: background 0.2s;
            flex-shrink: 0;
            height: 80px;
            box-sizing: border-box;
        }
            transition: background 0.2s;
            flex-shrink: 0;
        }
        .news-item:hover {
            background: rgba(249,157,78,0.08);
        }

        /* ===== BUSINESS CATEGORIES ===== */
        .business-cats-card {
            background: var(--card);
            border: 2px solid rgba(249, 157, 78, 0.2);
            border-radius: 16px;
            padding: 8px 10px;
            backdrop-filter: blur(12px);
            box-shadow: inset 0 0 15px rgba(255, 255, 255, 0.3), inset 0 0 5px rgba(249, 157, 78, 0.2), 0 8px 24px rgba(0,0,0,0.06), 0 0 10px rgba(249, 157, 78, 0.15);
            overflow: hidden;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 85px;
            max-height: 135px;
        }
        .cats-scroll-area {
            flex: 1;
            overflow: hidden;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .cats-track {
            display: flex;
            gap: 6px;
            white-space: nowrap;
            width: max-content;
        }
        .track-left {
            animation: scrollLeft 120s linear infinite;
        }
        .track-right {
            animation: scrollRight 120s linear infinite;
        }
        .business-cats-card:hover .track-left, .business-cats-card:hover .track-right {
            animation-play-state: paused;
        }
        .cat-pill {
            background: rgba(249,157,78,0.05);
            border: 1px solid rgba(249,157,78,0.2);
            color: var(--text-main);
            font-size: 0.65rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 100px;
            transition: all 0.2s;
        }
        .cat-pill:hover {
            background: var(--brand-gradient);
            color: #fff;
            border-color: transparent;
        }
        @keyframes scrollLeft {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        @keyframes scrollRight {
            0% { transform: translateX(-50%); }
            100% { transform: translateX(0); }
        }
        .news-source-badge {
            flex-shrink: 0;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #fff;
            min-width: 40px;
            text-align: center;
        }
        .news-source-badge.bbc { background: linear-gradient(135deg, #bb1919, #d42626); }
        .news-source-badge.cnn { background: linear-gradient(135deg, #cc0000, #ff1a1a); }
        .news-source-badge.nyt { background: linear-gradient(135deg, #111111, #444444); }
        .news-source-badge.fox { background: linear-gradient(135deg, #003366, #0055a4); }
        .news-source-badge.alj { background: linear-gradient(135deg, #ff9900, #e68a00); }
        .news-source-badge.gdn { background: linear-gradient(135deg, #052962, #0a4a9e); }
        .news-item-content {
            flex: 1;
            min-width: 0;
        }
        .news-item-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .news-item-time {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-top: 4px;
        }
        .news-live-dot {
            display: inline-block;
            width: 6px; height: 6px;
            background: #ef4444;
            border-radius: 50%;
            animation: livePulse 1.5s ease-in-out infinite;
            margin-right: 4px;
        }
        @keyframes livePulse {
            0%, 100% { opacity: 1; box-shadow: 0 0 0 0 rgba(239,68,68,0.6); }
            50% { opacity: 0.7; box-shadow: 0 0 0 4px rgba(239,68,68,0); }
        }

        /* Feature Card (right half of bottom row) */
        .feature-card {
            background: var(--card);
            border: 1px solid rgba(255,255,255,0.6);
            border-radius: 12px;
            padding: 8px 10px;
            backdrop-filter: blur(12px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        /* Countdown Section */
        .countdown-section {
            background: rgba(249,157,78,0.04);
            border: 1px solid rgba(255,255,255,0.4);
            border-radius: 10px;
            padding: 8px 10px;
        }
        .countdown-grid {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 6px 0;
        }
        .countdown-unit {
            text-align: center;
            background: rgba(255,255,255,0.7);
            border-radius: 8px;
            padding: 5px 10px;
            min-width: 48px;
            border: 1px solid rgba(255,255,255,0.5);
        }
        .countdown-number {
            font-size: 1.1rem;
            font-weight: 800;
            color: #e8622b;
            line-height: 1.2;
        }
        .countdown-number.cd-sec {
            color: #ef4444;
        }
        .countdown-label {
            font-size: 0.38rem;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .countdown-sep {
            font-size: 1rem;
            font-weight: 800;
            color: var(--text-muted);
            opacity: 0.4;
            padding-bottom: 10px;
        }
        .countdown-msg {
            text-align: center;
            font-size: 0.48rem;
            font-weight: 600;
            color: var(--text-muted);
            padding-top: 2px;
        }


        /* ===== ROW 2: MAIN CONTENT ===== */
        .main-row {
            flex: 1;
            display: flex;
            gap: 6px;
            min-height: 0;
            overflow: hidden;
        }
        .leaderboard-card {
            flex: 1;
            min-width: 0;
            min-height: 0;
            background: var(--card);
            border: 1px solid rgba(255,255,255,0.6);
            border-radius: 12px;
            padding: 8px 10px;
            display: flex;
            flex-direction: column;
            backdrop-filter: blur(12px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        /* Table */
        .table-scroll {
            overflow-y: auto;
            overflow-x: hidden;
            flex: 1;
            min-height: 0;
            max-height: none;
            scrollbar-width: thin;
            scrollbar-color: rgba(249,157,78,0.3) transparent;
            display: flex;
            flex-direction: column;
        }
        .table-scroll::-webkit-scrollbar { width: 4px; }
        .table-scroll::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #f99d4e 0%, #e93b6e 100%); border-radius: 10px; }
        .sidebar-col {
            width: 270px;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            gap: 5px;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            padding-right: 4px;
        }
        .sidebar-col::-webkit-scrollbar { width: 3px; }
        .sidebar-col::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #f99d4e 0%, #e93b6e 100%); border-radius: 10px; }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
            flex-shrink: 0;
        }
        .section-title {
            font-size: 0.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .section-title .icon {
            width: 20px; height: 20px;
            border-radius: 6px;
            background: var(--brand-gradient);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.55rem;
        }
        .section-badge {
            font-size: 0.55rem; font-weight: 600;
            color: var(--text-muted);
            background: rgba(100,116,139,0.08);
            padding: 2px 8px; border-radius: 100px;
        }


        .lb-table {
            width: 100%;
            flex: 1;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.85rem;
        }
        .lb-table thead th {
            text-align: left;
            padding: 6px 10px;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.4px;
            border-bottom: 1px solid var(--border);
            position: sticky; top: 0;
            background: var(--card);
            z-index: 2;
        }
        .lb-table tbody tr { transition: background 0.2s; }
        .lb-table tbody tr:hover { background: var(--brand-soft); }
        .lb-table tbody tr:nth-child(even) { background: rgba(241,245,249,0.4); }
        .lb-table tbody tr:nth-child(even):hover { background: var(--brand-soft); }
        .lb-table td { padding: 8px 10px; vertical-align: middle; }

        /* Rank Badge */
        .rank-badge {
            width: 26px; height: 26px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 0.75rem;
        }
        .rank-1 { background: var(--brand-gradient); color: #fff; box-shadow: 0 2px 8px rgba(249,157,78,0.3); }
        .rank-2 { background: linear-gradient(135deg,#e2e8f0,#f1f5f9); color: var(--text-main); border: 1px solid #cbd5e1; }
        .rank-3 { background: linear-gradient(135deg,#fef3c7,#fde68a); color: #92400e; border: 1px solid #fcd34d; }
        .rank-other { background: #f8fafc; color: var(--text-muted); border: 1px solid #e2e8f0; }

        /* Avatar */
        .agent-row { display: flex; align-items: center; gap: 8px; }
        .agent-avatar {
            width: 30px; height: 30px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.8rem; color: #fff;
            flex-shrink: 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .av-orange { background: linear-gradient(135deg,#f99d4e,#f97316); }
        .av-pink   { background: linear-gradient(135deg,#e93b6e,#ec4899); }
        .av-green  { background: linear-gradient(135deg,#10b981,#059669); }
        .av-blue   { background: linear-gradient(135deg,#3b82f6,#6366f1); }
        .av-purple { background: linear-gradient(135deg,#8b5cf6,#a855f7); }
        .av-teal   { background: linear-gradient(135deg,#14b8a6,#0d9488); }

        .agent-name { font-weight: 700; font-size: 0.9rem; display: block; line-height: 1.2; }
        .agent-role { font-size: 0.65rem; color: var(--text-muted); display: block; }
        .revenue-cell { font-weight: 700; font-size: 0.95rem; }

        /* Progress */
        .goal-cell { width: 180px; }
        .goal-labels {
            display: flex; justify-content: space-between;
            font-size: 0.65rem; color: var(--text-muted); margin-bottom: 4px;
        }
        .goal-labels strong { color: var(--text-main); font-weight: 700; }
        .progress-track {
            width: 100%; height: 5px;
            background: #f1f5f9; border-radius: 10px; overflow: hidden;
        }
        .progress-fill {
            height: 100%; border-radius: 10px;
            background: var(--brand-gradient);
            transition: width 1.2s ease-out;
        }
        .conversion-val { font-weight: 700; font-size: 0.9rem; color: var(--green); }

        /* ===== SIDEBAR ===== */
        .tp-card {
            background: var(--card);
            border: 1px solid rgba(255,255,255,0.6);
            border-radius: 12px;
            padding: 8px 10px;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(12px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            flex-shrink: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .tp-card::before {
            content: ''; position: absolute; top:0; left:0; right:0; height: 3px;
            background: var(--brand-gradient);
        }
        .tp-avatar {
            width: 38px; height: 38px; border-radius: 50%;
            background: var(--brand-gradient); color: #fff;
            font-size: 0.95rem; font-weight: 800;
            display: flex; align-items: center; justify-content: center;
            border: 2px solid #fff;
            box-shadow: 0 3px 10px rgba(249,157,78,0.3);
            flex-shrink: 0;
        }
        .tp-info { flex: 1; min-width: 0; }
        .tp-label {
            font-size: 0.48rem; font-weight: 800; text-transform: uppercase;
            letter-spacing: 1px; color: var(--primary); margin-bottom: 1px;
        }
        .tp-name { font-size: 0.78rem; font-weight: 700; margin-bottom: 0; line-height: 1.2; }
        .tp-role { font-size: 0.55rem; color: var(--text-muted); margin-bottom: 0; }
        .tp-badge {
            display: inline-block; background: var(--brand-gradient); color: #fff;
            padding: 3px 10px; border-radius: 100px;
            font-size: 0.6rem; font-weight: 700;
            white-space: nowrap; flex-shrink: 0;
        }

        /* Deals Bar */
        .deals-bar {
            background: var(--card);
            border: 1px solid rgba(255,255,255,0.6);
            border-radius: 12px;
            padding: 8px 10px;
            display: flex;
            align-items: center;
            gap: 15px;
            backdrop-filter: blur(12px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 6px;
            overflow: hidden;
        }
        .deals-label {
            font-size: 0.65rem; font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.7px;
            color: var(--text-muted); white-space: nowrap;
            display: flex; align-items: center; gap: 6px;
            padding-right: 15px;
            border-right: 1px solid rgba(0,0,0,0.1);
        }
        .deals-scroll {
            flex: 1;
            overflow-x: auto;
            overflow-y: hidden;
            display: flex; gap: 10px;
            scrollbar-width: thin;
            scrollbar-color: rgba(249,157,78,0.3) transparent;
            padding-bottom: 4px;
        }
        .deals-scroll::-webkit-scrollbar { height: 4px; display: block; }
        .deals-scroll::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #f99d4e 0%, #e93b6e 100%); border-radius: 10px; }

        .deal-item {
            display: flex; align-items: center; gap: 10px; padding: 4px 10px;
            border-radius: 50px; border-bottom: 2px solid transparent;
            background: rgba(241,245,249,0.5);
            transition: background 0.2s;
            flex-shrink: 0;
            min-width: 180px;
        }
        .deal-item:hover { background: rgba(241,245,249,0.9); }
        .deal-won    { border-bottom-color: var(--green); }
        .deal-lost   { border-bottom-color: var(--secondary); }
        .deal-pending { border-bottom-color: var(--gold); }
        
        .deal-info { flex: 1; min-width: 0; display: flex; flex-direction: column; }
        .deal-name { font-size: 0.72rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-main); }
        .deal-by { font-size: 0.6rem; color: var(--text-muted); white-space: nowrap; }
        .deal-meta { text-align: right; flex-shrink: 0; display: flex; flex-direction: column; }
        .deal-value { font-size: 0.72rem; font-weight: 800; color: var(--primary); }
        .deal-time { font-size: 0.55rem; color: var(--text-muted); white-space: nowrap; }

        /* ===== ACTIVE CLOSERS ===== */
        .closers-bar {
            background: var(--card);
            border: 1px solid rgba(255,255,255,0.6);
            border-radius: 12px;
            padding: 8px 10px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            backdrop-filter: blur(12px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            flex-shrink: 0;
        }
        .closers-label {
            font-size: 0.65rem; font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.7px;
            color: var(--text-muted); white-space: nowrap;
            display: flex; align-items: center; gap: 6px;
            padding-bottom: 4px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .pulse-dot {
            width: 7px; height: 7px; border-radius: 50%;
            background: var(--green);
            animation: pulse 2s ease-in-out infinite;
        }
        @keyframes pulse {
            0%,100% { box-shadow: 0 0 0 0 rgba(16,185,129,0.5); }
            50% { box-shadow: 0 0 0 4px rgba(16,185,129,0); }
        }
        .closers-list {
            display: flex; flex-direction: column; gap: 4px; flex: 1;
            overflow-y: auto; overflow-x: hidden;
            scrollbar-width: thin;
            scrollbar-color: rgba(249,157,78,0.3) transparent;
            max-height: 105px;
            padding-right: 4px;
        }
        .closers-list::-webkit-scrollbar { width: 3px; display: block; }
        .closers-list::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #f99d4e 0%, #e93b6e 100%); border-radius: 10px; }

        .closer-chip {
            display: flex; align-items: center; gap: 6px;
            background: rgba(241,245,249,0.4);
            border: 1px solid transparent;
            border-radius: 6px;
            padding: 4px 6px;
            flex-shrink: 0;
            transition: all 0.2s;
        }
        .closer-chip:hover { background: var(--brand-soft); border-color: rgba(249,157,78,0.2); }
        .closer-av {
            width: 22px; height: 22px; border-radius: 5px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.52rem; color: #fff; flex-shrink: 0;
        }
        .closer-info { flex: 1; min-width: 0; display: flex; flex-direction: column; line-height: 1.1; }
        .closer-name { font-size: 0.65rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-main); }
        .closer-role { font-size: 0.5rem; color: var(--text-muted); white-space: nowrap; display: none; }
        .closer-stats { text-align: right; flex-shrink: 0; }
        .closer-rev { font-size: 0.65rem; font-weight: 700; color: var(--primary); display: block; margin-bottom: 2px; }
        .closer-minibar {
            width: 35px; height: 4px; background: #e2e8f0;
            border-radius: 10px; overflow: hidden; display: inline-block;
        }
        .closer-minifill { height: 100%; border-radius: 10px; background: var(--brand-gradient); }

        /* ===== ROW 4: STATS BAR ===== */
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 5px;
            flex-shrink: 0;
        }
        .stat-card {
            background: var(--card);
            border: 2px solid rgba(249, 157, 78, 0.2);
            border-radius: 16px;
            padding: 6px 10px;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(12px);
            box-shadow: inset 0 0 15px rgba(255, 255, 255, 0.3), inset 0 0 5px rgba(249, 157, 78, 0.2), 0 8px 24px rgba(0,0,0,0.06), 0 0 10px rgba(249, 157, 78, 0.15);
        }
        .stat-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0;
            height: 3px; background: var(--brand-gradient);
        }
        .stat-label {
            font-size: 0.55rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 1px;
        }
        .stat-value { font-size: 1.05rem; font-weight: 800; margin-bottom: 1px; line-height: 1.1; transform-origin: left center; }
        .stat-trend { font-size: 0.55rem; font-weight: 700; }
        .trend-up { color: var(--green); }
        .trend-down { color: var(--secondary); }

        .pulse-bar-bg {
            width: 100%; height: 5px; background: #f1f5f9;
            border-radius: 10px; overflow: hidden; margin-top: 4px;
        }
        .pulse-bar-fill {
            height: 100%; border-radius: 10px;
            background: var(--brand-gradient);
            transition: width 1.5s ease-out;
        }

        /* ===== RESPONSIVE MEDIA QUERIES ===== */
        @media (max-width: 992px) {
            html, body {
                height: auto !important;
                overflow: auto !important;
            }
            .dashboard {
                height: auto !important;
                padding: 10px;
                gap: 15px;
            }
            .top-bar {
                flex-direction: column;
                height: auto;
                gap: 15px;
            }
            .clock-col {
                width: 100%;
                height: auto;
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }
            .clock-col > :not(.clock-unit) {
                grid-column: span 2;
            }
            .clock-unit {
                padding: 6px 10px !important;
                gap: 8px !important;
            }
            .center-col {
                width: 100%;
                height: auto;
                gap: 15px;
            }
            .weather-row {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }
            .leaderboard-card {
                width: 100%;
                height: auto;
            }
            .table-scroll {
                overflow-x: auto;
                overflow-y: visible;
                width: 100%;
                height: auto;
            }
            .lb-table {
                min-width: 800px;
            }
            .stats-bar-wrapper {
                flex-direction: column;
                gap: 0;
            }
            .stats-spacer {
                display: none;
            }
            .stats-bar-container {
                width: 100%;
            }
            .stats-bar {
                grid-template-columns: 1fr 1fr !important;
                gap: 10px;
            }
            .stats-bar .stat-card:nth-child(3) {
                grid-column: span 2;
            }

            /* Mobile clocks size adjustment (55px diameter) */
            .analog-clock {
                width: 55px !important;
                height: 55px !important;
                border-width: 2px !important;
                box-shadow: 2px 2px 5px rgba(0,0,0,0.06) !important;
            }
            .clock-mark {
                transform-origin: 50% 24.5px !important;
                height: 3px !important;
                top: 3px !important;
            }
            .clock-mark.major {
                height: 5px !important;
            }
            .hand-hour {
                height: 14px !important;
                width: 3px !important;
            }
            .hand-min {
                height: 20px !important;
                width: 2.5px !important;
            }
            .hand-sec {
                height: 23px !important;
                width: 1.5px !important;
            }
            .clock-center-dot {
                width: 6px !important;
                height: 6px !important;
            }
            .clock-digital {
                font-size: 1.15rem !important;
                margin: 2px 0 !important;
            }
            .clock-city {
                font-size: 0.75rem !important;
            }
            .clock-tz {
                font-size: 0.65rem !important;
            }
        }

        @media (max-width: 480px) {
            .clock-unit {
                padding: 5px 8px !important;
                gap: 6px !important;
            }
            .analog-clock {
                width: 45px !important;
                height: 45px !important;
            }
            .clock-mark {
                transform-origin: 50% 19.5px !important;
                height: 2.5px !important;
            }
            .clock-mark.major {
                height: 4px !important;
            }
            .hand-hour {
                height: 11px !important;
            }
            .hand-min {
                height: 16px !important;
            }
            .hand-sec {
                height: 19px !important;
            }
            .clock-digital {
                font-size: 0.95rem !important;
            }
            .clock-city {
                font-size: 0.7rem !important;
            }
            .clock-tz {
                display: none !important; /* Hide timezone on extremely small screens */
            }
        }

        @media (max-width: 768px) {
            .weather-row {
                grid-template-columns: 1fr;
            }
            .stats-bar {
                grid-template-columns: 1fr !important;
            }
            .stats-bar .stat-card:nth-child(3) {
                grid-column: span 1;
            }
        }
        
        /* Dark Mode Overrides */
        html.dark-mode {
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border: #334155;
            --card: rgba(30, 41, 59, 0.92);
            --glass: rgba(30, 41, 59, 0.75);
            --totals-bg: #0f172a;
        }
        html.dark-mode body {
            background: linear-gradient(-45deg, #0f172a, #1e293b, #111827, #020617);
            background-size: 400% 400%;
        }
        html.dark-mode .analog-clock {
            background: linear-gradient(145deg, #1e293b, #334155);
            border: 3px solid #334155;
            box-shadow: 3px 3px 8px rgba(0,0,0,0.3), -3px -3px 8px rgba(255,255,255,0.05);
        }
        html.dark-mode .hand-hour { background: #f8fafc; }
        html.dark-mode .hand-min { background: #cbd5e1; }
        html.dark-mode .clock-center-dot { border-color: #1e293b; }
        html.dark-mode .news-ticker { background: #020617; }
        html.dark-mode .lb-table tbody tr:nth-child(even) { background: rgba(15, 23, 42, 0.4); }
        html.dark-mode .rank-other { background: #0f172a; border-color: #334155; color: var(--text-muted); }
        html.dark-mode .deal-item { background: rgba(30, 41, 59, 0.5); }
        html.dark-mode .deal-item:hover { background: rgba(30, 41, 59, 0.9); }
        html.dark-mode .closer-chip { background: rgba(30, 41, 59, 0.4); }
        html.dark-mode .pulse-bar-bg { background: #334155; }
        html.dark-mode .progress-track { background: #334155; }
        html.dark-mode .lb-table tbody tr:hover { background: rgba(51, 65, 85, 0.4); }
        html.dark-mode .lb-table tbody tr:nth-child(even):hover { background: rgba(51, 65, 85, 0.4); }
        html.dark-mode .countdown-section { background: rgba(249,157,78,0.1); border-color: rgba(249,157,78,0.2); }
        html.dark-mode .countdown-unit { background: rgba(30, 41, 59, 0.8); border-color: #334155; }

        .floating-theme-toggle {
            position: fixed !important; bottom: 20px !important; right: 20px !important; z-index: 2000 !important;
            background: var(--card); border: 1px solid var(--border);
            width: 45px; height: 45px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            color: var(--text-muted); transition: 0.3s;
        }
        .floating-theme-toggle:hover {
            color: var(--primary); transform: scale(1.1);
        }
        .floating-theme-toggle svg { width: 20px; height: 20px; }

        html.dark-mode .logo-for-light { display: none !important; }
        html.dark-mode .logo-for-dark { display: inline-block !important; }
        html:not(.dark-mode) .logo-for-light { display: inline-block !important; }
        html:not(.dark-mode) .logo-for-dark { display: none !important; }

        /* Modern Global Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #f99d4e 0%, #e93b6e 100%); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: linear-gradient(180deg, #e93b6e 0%, #f99d4e 100%); }
    
    
    /* =========================================
       3D Modern Hover Effects (Powered by CSS)
       ========================================= */
    body {
        perspective: 1500px;
        overflow-x: hidden;
    }
    
    /* Base 3D properties */
    .gsap-card {
        transition: transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.5s ease, filter 0.5s ease !important;
        transform-style: preserve-3d;
        will-change: transform, box-shadow;
    }
    
    /* Since we removed CSS animation, GSAP will handle initial opacity.
       But let's apply the gsap-card class via JS or just target the original classes for hover. */
    .clock-unit, .weather-card, .lb-table-card, .live-news-card, .business-cats-card, .stat-card, .top-performer-card, .active-closers-card, .top-bar {
        transition: transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.5s ease, filter 0.5s ease;
        transform-style: preserve-3d;
        will-change: transform, box-shadow;
    }

    /* 3D Hover Lift and Tilt Effects */
    .clock-unit:hover, .weather-card:hover, .lb-table-card:hover, .live-news-card:hover, .business-cats-card:hover, .stat-card:hover, .top-performer-card:hover, .active-closers-card:hover {
        transform: translateY(-12px) scale(1.03) perspective(1000px) rotateX(4deg) rotateY(-2deg) !important;
        box-shadow: 
            inset 0 0 30px rgba(255, 255, 255, 0.8), 
            inset 0 0 15px rgba(249, 157, 78, 0.4), 
            0 35px 60px rgba(0,0,0,0.25), 
            0 15px 35px rgba(249, 157, 78, 0.35) !important;
        z-index: 50;
        position: relative;
        filter: brightness(1.05);
    }

    /* Inner 3D Parallax Effects (elements pop out during hover) */
    .lb-table-card table { transition: transform 0.5s ease; transform-style: preserve-3d; }
    .lb-table-card:hover table { transform: translateZ(40px); }
    
    .stat-card .stat-value { transition: transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275), text-shadow 0.5s ease; }
    .stat-card:hover .stat-value { transform: translateZ(50px) scale(1.15); text-shadow: 0 15px 25px rgba(0,0,0,0.25); }
    
    .clock-unit .analog-clock { transition: transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.5s ease; }
    .clock-unit:hover .analog-clock { transform: translateZ(40px) rotate(8deg) scale(1.1); box-shadow: 0 20px 40px rgba(0,0,0,0.3), inset 0 0 15px rgba(255,255,255,0.9); }
    
    .weather-card .weather-icon { transition: transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275), filter 0.5s ease; }
    .weather-card:hover .weather-icon { transform: translateZ(35px) scale(1.2) rotate(10deg); filter: drop-shadow(0 15px 25px rgba(0,0,0,0.3)); }

    .top-performer-card .performer-avatar, .active-closers-card .closer-avatar { transition: transform 0.5s ease, box-shadow 0.5s ease; }
    .top-performer-card:hover .performer-avatar, .active-closers-card:hover .closer-avatar { transform: translateZ(30px) scale(1.15); box-shadow: 0 10px 20px rgba(249, 157, 78, 0.4); }

    .top-bar:hover { transform: translateZ(25px) translateY(-3px) perspective(1000px) rotateX(1deg); box-shadow: 0 20px 40px rgba(0,0,0,0.3), inset 0 0 20px rgba(255,255,255,0.3); z-index: 100; }
</style>
</head>
<body>

    <button id="themeToggle" class="floating-theme-toggle" title="Toggle Dark Mode">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="moon-icon"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="sun-icon" style="display:none;"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
    </button>

@php
    $avatarColors = ['av-orange','av-pink','av-green','av-blue','av-purple','av-teal'];

    $defaultHeadlines = [
        'Company Q2 targets exceeded by 15% — Great job team!',
        'New client onboarding process launched — Check your email for details',
        'Sales training workshop next Friday at 2 PM',
        'Top performer bonus announced for May — Congratulations!',
        'CRM system upgrade completed successfully',
    ];
    $headlines = (!empty($news_headlines) && count($news_headlines) > 0) ? $news_headlines : $defaultHeadlines;
    $tablePerformers = collect($performers)->reject(function($p) {
        $role = is_object($p) ? ($p->role ?? '') : ($p['role'] ?? '');
        $isCloser = is_object($p) ? ($p->is_closer ?? false) : ($p['is_closer'] ?? false);
        return $isCloser || strtolower(trim($role)) === 'closer';
    })->values();
    $top = $tablePerformers->first();
    $closers = (isset($active_closers) && $active_closers->count() > 0) ? $active_closers : collect([]);
    $pulse = $stats['monthly_target'] > 0 ? min(100, ($stats['total_revenue'] / $stats['monthly_target']) * 100) : 0;
@endphp

<div class="dashboard">

    {{-- ===== LIVE NEWS TICKER (FULL WIDTH) ===== --}}
    <div class="news-ticker">
        <span class="news-badge">LIVE</span>
        <div class="ticker-wrap">
            <div class="ticker-track">
                @foreach($headlines as $h)
                    <span class="ticker-item">{{ $h }}</span>
                @endforeach
                @foreach($headlines as $h)
                    <span class="ticker-item">{{ $h }}</span>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ===== ROW 1: TOP BAR ===== --}}
    <div class="top-bar">
        {{-- LEFT CLOCKS --}}
        <div class="clock-col">
            <div class="clock-unit">
                <div class="analog-clock" id="clock-est">
                    @for($i=0;$i<12;$i++)
                        <div class="clock-mark {{ $i%3===0?'major':'' }}" style="transform:translateX(-50%) rotate({{$i*30}}deg)"></div>
                    @endfor
                    <div class="clock-hand hand-hour"></div>
                    <div class="clock-hand hand-min"></div>
                    <div class="clock-hand hand-sec"></div>
                    <div class="clock-center-dot"></div>
                </div>
                <div class="clock-info">
                    <span class="clock-city">New York</span>
                    <span class="clock-digital" id="digital-est">--:-- --</span>
                    <span class="clock-tz">Eastern (EST)</span>
                </div>
            </div>
            <div class="clock-unit">
                <div class="analog-clock" id="clock-cst">
                    @for($i=0;$i<12;$i++)
                        <div class="clock-mark {{ $i%3===0?'major':'' }}" style="transform:translateX(-50%) rotate({{$i*30}}deg)"></div>
                    @endfor
                    <div class="clock-hand hand-hour"></div>
                    <div class="clock-hand hand-min"></div>
                    <div class="clock-hand hand-sec"></div>
                    <div class="clock-center-dot"></div>
                </div>
                <div class="clock-info">
                    <span class="clock-city">Chicago</span>
                    <span class="clock-digital" id="digital-cst">--:-- --</span>
                    <span class="clock-tz">Central (CST)</span>
                </div>
            </div>

            {{-- LIVE NEWS --}}
            @if(isset($live_news) && count($live_news) > 0)
            <div class="live-news-card" style="flex:0 0 auto; min-width:0;">
                <div class="section-header" style="margin-bottom:4px;">
                    <div class="section-title" style="font-size:0.72rem;">
                        <span class="news-live-dot"></span>
                        📺 Live World News
                    </div>
                    <div style="display:flex; gap:3px; justify-content:flex-end; align-items: center;">
                        <span class="news-source-badge bbc" style="padding: 2px 4px; font-size: 0.55rem; min-width: auto; letter-spacing: 0;">BBC</span>
                        <span class="news-source-badge nyt" style="padding: 2px 4px; font-size: 0.55rem; min-width: auto; letter-spacing: 0;">NYT</span>
                        <span class="news-source-badge fox" style="padding: 2px 4px; font-size: 0.55rem; min-width: auto; letter-spacing: 0;">FOX</span>
                        <span class="news-source-badge gdn" style="padding: 2px 4px; font-size: 0.55rem; min-width: auto; letter-spacing: 0;">GDN</span>
                    </div>
                </div>
                <div class="news-scroll-area">
                    <div class="news-scroll-track">
                        @foreach($live_news as $news)
                        <div class="news-item">
                            <span class="news-source-badge {{ strtolower($news['source']) }}">{{ $news['source'] }}</span>
                            <div class="news-item-content">
                                <div class="news-item-title">{{ $news['title'] }}</div>
                                <div class="news-item-time">{{ $news['time'] }}</div>
                            </div>
                        </div>
                        @endforeach
                        {{-- Duplicate for seamless loop --}}
                        @foreach($live_news as $news)
                        <div class="news-item">
                            <span class="news-source-badge {{ strtolower($news['source']) }}">{{ $news['source'] }}</span>
                            <div class="news-item-content">
                                <div class="news-item-title">{{ $news['title'] }}</div>
                                <div class="news-item-time">{{ $news['time'] }}</div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            {{-- USA STATES --}}
            <div class="business-cats-card" style="margin-top: 6px;">
                <div class="section-header" style="margin-bottom:6px;">
                    <div class="section-title" style="font-size:0.72rem;">
                        <span class="icon">📍</span>
                        USA States
                    </div>
                </div>
                <div class="cats-scroll-area">
                    @if(!empty($usa_states) && count($usa_states) > 0)
                        @php 
                            $half = ceil(count($usa_states) / 2);
                            $top_cats = array_slice($usa_states, 0, $half);
                            $bottom_cats = array_slice($usa_states, $half);
                            if(empty($bottom_cats)) $bottom_cats = $top_cats; // Fallback if only 1 state
                            
                            // Repeat arrays to ensure seamless infinite scroll
                            $top_repeated = array_merge($top_cats, $top_cats, $top_cats, $top_cats, $top_cats);
                            $bottom_repeated = array_merge($bottom_cats, $bottom_cats, $bottom_cats, $bottom_cats, $bottom_cats);
                        @endphp
                        <div class="cats-track track-left">
                            @foreach($top_repeated as $state)
                            <span class="cat-pill">{{ $state }}</span>
                            @endforeach
                        </div>
                        <div class="cats-track track-right" style="margin-top: 6px;">
                            @foreach($bottom_repeated as $state)
                            <span class="cat-pill">{{ $state }}</span>
                            @endforeach
                        </div>
                    @else
                        <div class="cats-track track-left">
                            <span class="cat-pill">No states found</span>
                        </div>
                    @endif
                </div>
            </div>

        </div>

        {{-- CENTER: DEALS + WEATHER --}}
        <div class="center-col">

            @php
                $weatherCities = [
                    'ny'  => 'New York (EST)',
                    'chi' => 'Chicago (CST)',
                    'den' => 'Denver (MST)',
                    'la'  => 'Los Angeles (PST)',
                ];
            @endphp
            <div class="weather-row" style="margin-bottom: 6px;">
                @foreach($weatherCities as $wKey => $wLabel)
                    @php $w = $weather[$wKey] ?? ['emoji' => '🌡️', 'temp' => null, 'cond' => 'Unavailable']; @endphp
                    <div class="weather-card" id="weather-{{ $wKey }}">
                        <span class="weather-emoji">{{ $w['emoji'] }}</span>
                        <div>
                            <div class="weather-city">{{ $wLabel }}</div>
                            <div class="weather-temp" id="wtemp-{{ $wKey }}">{{ $w['temp'] !== null ? $w['temp'] . '°F' : '--' }}</div>
                            <div class="weather-cond" id="wcond-{{ $wKey }}">{{ $w['cond'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div style="display: flex; justify-content: center; align-items: center; gap: 15px; margin: 6px 0 2px 0;">
                @if($company)
                    @if($company->logo && file_exists(public_path($company->logo)))
                        <img src="{{ url('file/' . $company->logo) }}" alt="{{ $company->name }}" class="logo-for-light" style="max-height: 84px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));">
                    @endif
                    
                    @if($company->dark_logo && file_exists(public_path($company->dark_logo)))
                        <img src="{{ url('file/' . $company->dark_logo) }}" alt="{{ $company->name }} Dark Logo" class="logo-for-dark" style="max-height: 84px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3)); display: none;">
                    @elseif($company->logo && file_exists(public_path($company->logo)))
                        <img src="{{ url('file/' . $company->logo) }}" alt="{{ $company->name }}" class="logo-for-dark" style="max-height: 84px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3)); display: none;">
                    @endif
                    
                    @if(!$company->logo && !$company->dark_logo)
                        <h2 style="font-size: 1.2rem; font-weight: 800; color: var(--primary); letter-spacing: 1.5px; text-transform: uppercase; margin: 0; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));">
                            {{ $company->name }}
                        </h2>
                    @endif
                @else
                    <h2 style="font-size: 1.2rem; font-weight: 800; color: var(--primary); letter-spacing: 1.5px; text-transform: uppercase; margin: 0; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));">
                        LIVE SCOREBOARD
                    </h2>
                @endif
            </div>

            <div class="leaderboard-card" style="min-width:0; margin-top: 0;">

                <div class="table-scroll">
                    <table class="lb-table">
                        <thead>
                            <tr>
                                <th style="width:38px">Rank</th>
                                <th>Sales Representative</th>
                                <th style="width:85px">Target</th>
                                <th style="width:130px; text-align: center;">Number of Transfers</th>
                                <th style="width:110px; text-align: center;">Number of Sales</th>
                                <th class="goal-cell">Goal Progress</th>
                                <th style="width:85px">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>

                            @foreach($tablePerformers as $performer)
                            @php
                                $rank = $loop->iteration;
                                $rankClass = match(true) {
                                    $performer->revenue == 0 => 'rank-other',
                                    $rank === 1 => 'rank-1',
                                    $rank === 2 => 'rank-2',
                                    $rank === 3 => 'rank-3',
                                    default => 'rank-other',
                                };
                                $colorClass = $avatarColors[$loop->index % count($avatarColors)];
                                $progress = $performer->goal > 0 ? min(100, ($performer->revenue / $performer->goal) * 100) : 0;
                            @endphp
                            <tr>
                                <td><div class="rank-badge {{ $rankClass }}">{{ $rank }}</div></td>
                                <td>
                                    <div class="agent-row">
                                        <div class="agent-avatar {{ $colorClass }}">
                                            {{ $performer->avatar_initials ?? substr($performer->display_name, 0, 1) }}
                                        </div>
                                        <div>
                                            <div class="agent-name">{{ $performer->display_name }}</div>
                                            <div class="agent-role">{{ $performer->role }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td style="font-weight: 700; color: var(--text-main);">${{ number_format($performer->effective_goal) }}</td>
                                <td style="text-align: center; font-weight: 800; color: var(--text-main);">{{ $performer->effective_transferred }}</td>
                                <td style="text-align: center; font-weight: 800; color: var(--green); font-size: 1rem;">{{ $performer->effective_sales }}</td>
                                <td class="goal-cell">
                                    <div class="goal-info">
                                        <div class="progress-track">
                                            <div class="progress-fill" style="width:{{ $progress }}%"></div>
                                        </div>
                                        <span class="progress-pct">Progress {{ round($progress) }}%</span>
                                    </div>
                                </td>
                                <td><span class="revenue-amount" style="font-weight: 800; color: var(--primary);">${{ number_format($performer->revenue) }}</span></td>
                            </tr>
                            @endforeach

                            @php
                                $selfGenDeals = \App\Models\Deal::where('status', 'WON')->where('closed_by', 'Self-Generated by Closer')->get();
                                $selfGenCount = $selfGenDeals->count();
                                $selfGenRev = $selfGenDeals->sum('value');
                            @endphp

                            @if($selfGenCount > 0)
                            <tr style="background: rgba(249, 157, 78, 0.05); border-top: 1px dashed rgba(249, 157, 78, 0.3);">
                                <td style="text-align: center; color: var(--text-muted); font-size: 0.8rem;">-</td>
                                <td>
                                    <div class="agent-row">
                                        <div class="agent-avatar" style="background: linear-gradient(135deg, #f99d4e 0%, #f67a20 100%); color: white; border: none; font-size: 0.8rem;">
                                            SG
                                        </div>
                                        <div>
                                            <div class="agent-name" style="font-weight: 700; color: #f67a20;">Self-Generated</div>
                                            <div class="agent-role" style="font-size: 0.65rem; font-style: italic;">By Closers</div>
                                        </div>
                                    </div>
                                </td>
                                <td style="font-weight: 700; color: var(--text-muted);">-</td>
                                <td style="text-align: center; font-weight: 800; color: var(--text-muted);">-</td>
                                <td style="text-align: center; font-weight: 800; color: var(--green); font-size: 1rem;">{{ $selfGenCount }}</td>
                                <td class="goal-cell">
                                    <div class="goal-info">
                                        <span class="progress-pct" style="color: var(--text-muted); font-style: italic;">N/A</span>
                                    </div>
                                </td>
                                <td><span class="revenue-amount" style="font-weight: 800; color: var(--primary);">${{ number_format($selfGenRev) }}</span></td>
                            </tr>
                            @endif

                            <tr style="height: 100%;">
                                <td colspan="7" style="padding: 0; border: none; background: transparent;"></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            @php
                                $totalTarget = 0;
                                $totalTransfers = 0;
                                $totalSales = 0;
                                $totalRev = 0;
                                foreach($tablePerformers as $p) {
                                    $totalTarget += is_object($p) ? ($p->effective_goal ?? 0) : ($p['effective_goal'] ?? 0);
                                    $totalTransfers += is_object($p) ? ($p->effective_transferred ?? 0) : ($p['effective_transferred'] ?? 0);
                                    $totalSales += is_object($p) ? ($p->effective_sales ?? 0) : ($p['effective_sales'] ?? 0);
                                    $totalRev += is_object($p) ? ($p->revenue ?? 0) : ($p['revenue'] ?? 0);
                                }
                                
                                // Add Self-Generated Sales by Closers to the overall totals
                                $totalRev += $selfGenRev;
                                $totalSales += $selfGenCount;
                            @endphp
                            <tr>
                                <td colspan="2" style="position: sticky; bottom: 0; z-index: 10; text-align: right; padding: 12px 10px; font-size: 0.95rem; font-weight: 800; color: var(--text-main); border-top: 2px solid rgba(249,157,78,0.3); background: var(--totals-bg, #fdf5ef); box-shadow: 0 -2px 5px rgba(0,0,0,0.02);">TOTALS:</td>
                                <td colspan="1" style="position: sticky; bottom: 0; z-index: 10; padding: 12px 10px; font-size: 0.95rem; font-weight: 800; color: var(--text-main); border-top: 2px solid rgba(249,157,78,0.3); background: var(--totals-bg, #fdf5ef); box-shadow: 0 -2px 5px rgba(0,0,0,0.02);">${{ number_format($totalTarget) }}</td>
                                <td colspan="1" style="position: sticky; bottom: 0; z-index: 10; text-align: center; padding: 12px 10px; font-size: 0.95rem; font-weight: 800; color: var(--text-main); border-top: 2px solid rgba(249,157,78,0.3); background: var(--totals-bg, #fdf5ef); box-shadow: 0 -2px 5px rgba(0,0,0,0.02);">{{ $totalTransfers }}</td>
                                <td colspan="1" style="position: sticky; bottom: 0; z-index: 10; text-align: center; padding: 12px 10px; font-size: 1rem; font-weight: 900; color: var(--green); border-top: 2px solid rgba(249,157,78,0.3); background: var(--totals-bg, #fdf5ef); box-shadow: 0 -2px 5px rgba(0,0,0,0.02);">{{ $totalSales }}</td>
                                <td colspan="1" style="position: sticky; bottom: 0; z-index: 10; border-top: 2px solid rgba(249,157,78,0.3); background: var(--totals-bg, #fdf5ef); box-shadow: 0 -2px 5px rgba(0,0,0,0.02);"></td>
                                <td colspan="1" style="position: sticky; bottom: 0; z-index: 10; padding: 12px 10px; font-size: 1.1rem; font-weight: 900; color: var(--primary); border-top: 2px solid rgba(249,157,78,0.3); background: var(--totals-bg, #fdf5ef); box-shadow: 0 -2px 5px rgba(0,0,0,0.02);">${{ number_format($totalRev) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>





        </div>

        {{-- RIGHT CLOCKS --}}
        <div class="clock-col">
            <div class="clock-unit">
                <div class="analog-clock" id="clock-mst">
                    @for($i=0;$i<12;$i++)
                        <div class="clock-mark {{ $i%3===0?'major':'' }}" style="transform:translateX(-50%) rotate({{$i*30}}deg)"></div>
                    @endfor
                    <div class="clock-hand hand-hour"></div>
                    <div class="clock-hand hand-min"></div>
                    <div class="clock-hand hand-sec"></div>
                    <div class="clock-center-dot"></div>
                </div>
                <div class="clock-info">
                    <span class="clock-city">Denver</span>
                    <span class="clock-digital" id="digital-mst">--:-- --</span>
                    <span class="clock-tz">Mountain (MST)</span>
                </div>
            </div>
            <div class="clock-unit">
                <div class="analog-clock" id="clock-pst">
                    @for($i=0;$i<12;$i++)
                        <div class="clock-mark {{ $i%3===0?'major':'' }}" style="transform:translateX(-50%) rotate({{$i*30}}deg)"></div>
                    @endfor
                    <div class="clock-hand hand-hour"></div>
                    <div class="clock-hand hand-min"></div>
                    <div class="clock-hand hand-sec"></div>
                    <div class="clock-center-dot"></div>
                </div>
                <div class="clock-info">
                    <span class="clock-city">Los Angeles</span>
                    <span class="clock-digital" id="digital-pst">--:-- --</span>
                    <span class="clock-tz">Pacific (PST)</span>
                </div>
            </div>

            <div class="tp-card" style="margin-top: 0;">
                @if($top && $top->revenue > 0)
                    <div class="tp-avatar">{{ $top->avatar_initials ?? substr($top->display_name, 0, 1) }}</div>
                    <div class="tp-info">
                        <div class="tp-label">⭐ Top Performer</div>
                        <div class="tp-name">{{ $top->display_name }}</div>
                        <div class="tp-role">{{ $top->role }}</div>
                    </div>
                    <div class="tp-badge">🏆 ${{ number_format($top->revenue) }}</div>
                @else
                    <div class="tp-avatar" style="background: #e2e8f0; color: #94a3b8; box-shadow: none;">—</div>
                    <div class="tp-info">
                        <div class="tp-label">⭐ Top Performer</div>
                        <div class="tp-name" style="color: var(--text-muted); font-style: italic;">No sales logged</div>
                        <div class="tp-role">Awaiting first sale...</div>
                    </div>
                    <div class="tp-badge" style="background: #e2e8f0; color: #94a3b8; box-shadow: none;">🏆 $0</div>
                @endif
            </div>

            {{-- ===== ACTIVE CLOSERS ===== --}}
            <div class="closers-bar" style="margin-top: 0;">
                <div class="closers-label">
                    @if($closers->count() > 0)
                        <span class="pulse-dot"></span>
                    @else
                        <span class="pulse-dot" style="background: #cbd5e1; animation: none;"></span>
                    @endif
                    Active Closers
                </div>
                <div class="closers-list">
                    @if($closers->count() > 0)
                        @foreach($closers as $closer)
                        @php
                            $cColor = $avatarColors[$loop->index % count($avatarColors)];
                            $cProg = $closer->goal > 0 ? min(100, ($closer->revenue / $closer->goal) * 100) : 0;
                        @endphp
                        <div class="closer-chip">
                            <div class="closer-av {{ $cColor }}">{{ $closer->avatar_initials ?? substr($closer->display_name, 0, 1) }}</div>
                            <div class="closer-info">
                                <div class="closer-name">{{ $closer->display_name }}</div>
                                <div class="closer-role">{{ $closer->role ?? 'Closer' }}</div>
                            </div>
                            <div class="closer-stats" style="text-align: right;">
                                <span class="closer-rev">
                                    ${{ number_format($closer->revenue) }}
                                    <span style="color: var(--text-muted); font-size: 0.55rem; font-weight: 500;">/ ${{ number_format($closer->effective_goal) }}</span>
                                </span>
                                <div class="closer-minibar">
                                    <div class="closer-minifill" data-width="{{ $cProg }}%" style="width:0%"></div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    @else
                        <div class="closer-chip" style="background: rgba(241,245,249,0.2); border: 1px dashed var(--border); justify-content: center; padding: 6px;">
                            <span style="font-size: 0.65rem; color: var(--text-muted); font-style: italic;">No active closers currently</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- USA BUSINESS CATEGORIES --}}
            <div class="business-cats-card" style="margin-top: 6px;">
                <div class="section-header" style="margin-bottom:6px;">
                    <div class="section-title" style="font-size:0.72rem;">
                        <span class="icon">🏢</span>
                        USA Business Categories
                    </div>
                </div>
                <div class="cats-scroll-area">
                    @if(!empty($business_categories) && count($business_categories) > 0)
                        @php 
                            $half = ceil(count($business_categories) / 2);
                            $top_cats = array_slice($business_categories, 0, $half);
                            $bottom_cats = array_slice($business_categories, $half);
                            if(empty($bottom_cats)) $bottom_cats = $top_cats; // Fallback if only 1 category
                            
                            // Repeat arrays to ensure seamless infinite scroll
                            $top_repeated = array_merge($top_cats, $top_cats, $top_cats, $top_cats, $top_cats);
                            $bottom_repeated = array_merge($bottom_cats, $bottom_cats, $bottom_cats, $bottom_cats, $bottom_cats);
                        @endphp
                        <div class="cats-track track-left">
                            @foreach($top_repeated as $cat)
                            <span class="cat-pill">{{ $cat }}</span>
                            @endforeach
                        </div>
                        <div class="cats-track track-right" style="margin-top: 6px;">
                            @foreach($bottom_repeated as $cat)
                            <span class="cat-pill">{{ $cat }}</span>
                            @endforeach
                        </div>
                    @else
                        <div class="cats-track track-left">
                            <span class="cat-pill">No categories found</span>
                        </div>
                    @endif
                </div>
            </div>


        </div>
    </div>

    {{-- STATS BAR ROW --}}
    <div class="stats-bar-wrapper">
        <div class="stats-spacer"></div>
        <div class="stats-bar-container">
            <div class="stats-bar" style="grid-template-columns: 1fr 1fr 2fr 1fr 1fr;">
                <div class="stat-card">
                    <div class="stat-label">Monthly Target</div>
                    <div class="stat-value">${{ number_format($stats['monthly_target']) }}</div>
                    <div class="stat-trend trend-up">Pulse {{ round($pulse) }}%</div>
                    <div class="pulse-bar-bg">
                        <div class="pulse-bar-fill" data-width="{{ $pulse }}%" style="width:0%"></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Achieved Number of Prospects</div>
                    <div class="stat-value">{{ number_format($stats['achieved_prospects']) }}</div>
                    <div class="stat-trend trend-up" style="visibility: hidden;">-</div>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, rgba(249, 157, 78, 0.15), rgba(233, 59, 110, 0.15)); border: 1px solid rgba(249, 157, 78, 0.5); display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; position: relative; overflow: visible; padding-top: 15px;">
                    <div style="position: absolute; top: -12px; background: var(--brand-gradient); color: white; font-size: 0.7rem; font-weight: 800; padding: 3px 12px; border-radius: 20px; box-shadow: 0 4px 10px rgba(249,157,78,0.4); text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 5px;">
                        <i class="fas fa-bolt" style="animation: flash 1.5s infinite;"></i> LATEST UPDATE
                    </div>
                    <div class="stat-value" style="font-size: 1.25rem; color: #d62f5b; font-weight: 900; display: flex; align-items: center; justify-content: center; gap: 8px; line-height: 1.2;">
                        <span style="font-size: 1.5rem; animation: bounce 2s infinite;">🎉</span> 
                        <span>{{ $stats['live_update'] ?: 'No new updates currently' }}</span> 
                        <span style="font-size: 1.5rem; animation: bounce 2s infinite; animation-delay: 1s;">🚀</span>
                    </div>
                    <style>
                        @keyframes flash { 0%, 50%, 100% { opacity: 1; } 25%, 75% { opacity: 0; } }
                        @keyframes bounce { 0%, 20%, 50%, 80%, 100% { transform: translateY(0); } 40% { transform: translateY(-5px); } 60% { transform: translateY(-3px); } }
                    </style>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Active Agents</div>
                    <div class="stat-value">{{ $stats['active_agents'] }}</div>
                    <div class="stat-trend trend-up" style="visibility: hidden;">-</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-value">${{ number_format($stats['total_revenue']) }}</div>
                    <div class="stat-trend {{ str_contains($stats['revenue_trend'], '-') ? 'trend-down' : 'trend-up' }}">
                        {{ str_contains($stats['revenue_trend'], '-') ? '▼' : '▲' }} {{ $stats['revenue_trend'] }}
                    </div>
                </div>
            </div>
        </div>
        <div class="stats-spacer"></div>
    </div>

</div>

<!-- RUSH HOUR OVERLAY -->
<div id="rush-hour-overlay">
    <svg class="rh-svg-defs">
        <defs>
            <linearGradient id="rh-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#ef4444" />
                <stop offset="100%" stop-color="#f97316" />
            </linearGradient>
        </defs>
    </svg>
    <div class="rh-bg-effects"></div>
    <div class="rh-content">
        <div class="rh-title">Rush Hour</div>
        <div class="rh-subtitle">All Hands On Deck</div>
        <div class="rh-clock">
            <div class="rh-unit">
                <div class="rh-shimmer"></div>
                <div class="rh-circle-progress">
                    <svg viewBox="0 0 100 100">
                        <circle cx="50" cy="50" r="45" class="rh-circle-bg"></circle>
                        <circle cx="50" cy="50" r="45" class="rh-circle-bar" id="rh-bar-hours"></circle>
                    </svg>
                </div>
                <div class="rh-val" id="rh-hours">00</div>
                <div class="rh-label">Hours</div>
            </div>
            <div class="rh-unit">
                <div class="rh-shimmer"></div>
                <div class="rh-circle-progress">
                    <svg viewBox="0 0 100 100">
                        <circle cx="50" cy="50" r="45" class="rh-circle-bg"></circle>
                        <circle cx="50" cy="50" r="45" class="rh-circle-bar" id="rh-bar-mins"></circle>
                    </svg>
                </div>
                <div class="rh-val" id="rh-mins">00</div>
                <div class="rh-label">Minutes</div>
            </div>
            <div class="rh-unit">
                <div class="rh-shimmer"></div>
                <div class="rh-circle-progress">
                    <svg viewBox="0 0 100 100">
                        <circle cx="50" cy="50" r="45" class="rh-circle-bg"></circle>
                        <circle cx="50" cy="50" r="45" class="rh-circle-bar" id="rh-bar-secs"></circle>
                    </svg>
                </div>
                <div class="rh-val" id="rh-secs" style="color: #ef4444;">00</div>
                <div class="rh-label">Seconds</div>
            </div>
        </div>
    </div>
</div>


<script>
(function() {
    // ===== CLOCKS =====
    var clocks = [
        { id: 'est', digital: 'digital-est', tz: 'America/New_York' },
        { id: 'cst', digital: 'digital-cst', tz: 'America/Chicago' },
        { id: 'mst', digital: 'digital-mst', tz: 'America/Denver' },
        { id: 'pst', digital: 'digital-pst', tz: 'America/Los_Angeles' },
    ];

    function updateClock(c) {
        var el = document.getElementById('clock-' + c.id);
        var dig = document.getElementById(c.digital);
        if (!el || !dig) return;
        var now = new Date(new Date().toLocaleString('en-US', { timeZone: c.tz }));
        var s = now.getSeconds(), m = now.getMinutes(), h = now.getHours();
        var sDeg = (s/60)*360, mDeg = (m/60)*360 + (s/60)*6, hDeg = ((h%12)/12)*360 + (m/60)*30;
        var hH = el.querySelector('.hand-hour'), mH = el.querySelector('.hand-min'), sH = el.querySelector('.hand-sec');
        if(hH) hH.style.transform = 'translateX(-50%) rotate('+hDeg+'deg)';
        if(mH) mH.style.transform = 'translateX(-50%) rotate('+mDeg+'deg)';
        if(sH) sH.style.transform = 'translateX(-50%) rotate('+sDeg+'deg)';
        dig.textContent = now.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:true });
    }

    function tickAll() { clocks.forEach(updateClock); }
    tickAll();
    setInterval(tickAll, 1000);

    // ===== WEATHER =====
    // Rendered server-side from the Open-Meteo API and refreshed with the page.

    // ===== ANIMATE PROGRESS BARS ON LOAD =====
    setTimeout(function() {
        document.querySelectorAll('[data-width]').forEach(function(bar) {
            bar.style.width = bar.getAttribute('data-width');
        });
    }, 300);

    // ===== MONTH-END COUNTDOWN =====
    function updateCountdown() {
        var now = new Date();
        var year = now.getFullYear(), month = now.getMonth();
        var endOfMonth = new Date(year, month + 1, 0, 23, 59, 59, 999);
        var diff = endOfMonth - now;

        if (diff <= 0) diff = 0;

        var days = Math.floor(diff / (1000 * 60 * 60 * 24));
        var hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        var mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        var secs = Math.floor((diff % (1000 * 60)) / 1000);

        var d = document.getElementById('cd-days');
        var h = document.getElementById('cd-hours');
        var m = document.getElementById('cd-mins');
        var s = document.getElementById('cd-secs');
        if(d) d.textContent = String(days).padStart(2, '0');
        if(h) h.textContent = String(hours).padStart(2, '0');
        if(m) m.textContent = String(mins).padStart(2, '0');
        if(s) s.textContent = String(secs).padStart(2, '0');

        var label = document.getElementById('countdown-month-label');
        if(label) {
            var monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            label.textContent = monthNames[month] + ' ' + year;
        }
    }
    updateCountdown();
    setInterval(updateCountdown, 1000);

    // ===== CELEBRATION ON LIVE UPDATE =====
    var currentLiveUpdate = @json($stats['live_update']);
    if (currentLiveUpdate) {
        var lastLiveUpdate = localStorage.getItem('last_live_update');
        if (lastLiveUpdate !== null && lastLiveUpdate !== currentLiveUpdate) {
            // It changed! Fire confetti!
            var duration = 5 * 1000;
            var animationEnd = Date.now() + duration;
            var defaults = { startVelocity: 30, spread: 360, ticks: 60, zIndex: 9999, colors: ['#f99d4e', '#e93b6e', '#10b981', '#fbbf24'] };

            function randomInRange(min, max) {
              return Math.random() * (max - min) + min;
            }

            var interval = setInterval(function() {
              var timeLeft = animationEnd - Date.now();

              if (timeLeft <= 0) {
                return clearInterval(interval);
              }

              var particleCount = 50 * (timeLeft / duration);
              confetti(Object.assign({}, defaults, { particleCount, origin: { x: randomInRange(0.1, 0.3), y: Math.random() - 0.2 } }));
              confetti(Object.assign({}, defaults, { particleCount, origin: { x: randomInRange(0.7, 0.9), y: Math.random() - 0.2 } }));
            }, 250);
        }
        localStorage.setItem('last_live_update', currentLiveUpdate);
    }

    const sbThemeToggle = document.getElementById('themeToggle');
    const moonIcon = sbThemeToggle.querySelector('.moon-icon');
    const sunIcon = sbThemeToggle.querySelector('.sun-icon');

    function updateSbThemeIcon() {
        if (document.documentElement.classList.contains('dark-mode')) {
            moonIcon.style.display = 'none';
            sunIcon.style.display = 'block';
        } else {
            moonIcon.style.display = 'block';
            sunIcon.style.display = 'none';
        }
    }

    updateSbThemeIcon();

    sbThemeToggle.addEventListener('click', () => {
        document.documentElement.classList.toggle('dark-mode');
        localStorage.setItem('theme', document.documentElement.classList.contains('dark-mode') ? 'dark' : 'light');
        updateSbThemeIcon();
    });

    // ===== RUSH HOUR LOGIC (3D GSAP) =====
    let isRushHourActive = false;
    let rhTimerInterval = null;
    let rhEndTime = null;
    let rhBgInitialized = false;

    function initGSAPBackground() {
        if (rhBgInitialized) return;
        rhBgInitialized = true;
        
        const bgContainer = document.querySelector('.rh-bg-effects');
        // Create 40 glowing particles for a 3D space effect
        for(let i=0; i<40; i++) {
            let p = document.createElement('div');
            p.className = 'rh-particle';
            let size = Math.random() * 8 + 4; // 4 to 12px
            p.style.width = size + 'px';
            p.style.height = size + 'px';
            bgContainer.appendChild(p);
            
            // Initial random placement in 3D space
            gsap.set(p, {
                left: 'random(-10, 110)%',
                top: 'random(-10, 110)%',
                z: 'random(-2000, 0)',
                opacity: 0
            });
            
            // Continuous animation flying towards camera
            gsap.to(p, {
                z: 800, // Move past camera
                opacity: 'random(0.4, 1)',
                ease: "none",
                duration: 'random(3, 8)',
                repeat: -1,
                delay: 'random(0, 5)',
                onRepeat: function() {
                    // Reset position far back when repeating
                    gsap.set(this.targets()[0], {
                        left: 'random(-10, 110)%',
                        top: 'random(-10, 110)%',
                        z: -2000,
                        opacity: 0
                    });
                }
            });
        }
    }

    function checkRushHourStatus() {
        fetch('{{ route("scoreboard.rush-hour.status", ["company_slug" => $company->slug]) }}?t=' + Date.now())
            .then(res => res.json())
            .then(data => {
                if (data.active) {
                    if (!isRushHourActive) {
                        startRushHourUI(data.end_time, data.message);
                    } else {
                        // Just update end time and message if changed
                        rhEndTime = new Date(data.end_time);
                        document.querySelector('.rh-subtitle').textContent = data.message || 'ALL HANDS ON DECK';
                    }
                } else {
                    if (isRushHourActive) {
                        endRushHourUI();
                    }
                }
            })
            .catch(err => console.error("Error polling rush hour status", err));
    }

    function startRushHourUI(endTimeStr, messageStr) {
        isRushHourActive = true;
        rhEndTime = new Date(endTimeStr);
        
        const subtitleEl = document.querySelector('.rh-subtitle');
        subtitleEl.textContent = messageStr || 'ALL HANDS ON DECK';
        
        initGSAPBackground();
        
        // Hide dashboard smoothly
        document.querySelector('.dashboard').classList.add('hidden');
        
        const overlay = document.getElementById('rush-hour-overlay');
        overlay.classList.add('active');
        
        // Reset container that might have been hidden by exit animation
        gsap.set('.rh-clock', { opacity: 1, scale: 1 });
        
        // Master GSAP Timeline for Entrance
        let tl = gsap.timeline();
        
        // Title drops in 3D
        tl.fromTo('.rh-title',
            { opacity: 0, z: -1000, rotationX: 45 },
            { duration: 1.2, opacity: 1, z: 0, rotationX: 0, ease: "elastic.out(1, 0.4)" }
        )
        // Subtitle fades up
        .fromTo('.rh-subtitle',
            { opacity: 0, y: 50, z: -500 },
            { duration: 0.8, opacity: 1, y: 0, z: 0, ease: "back.out(1.7)" },
            "-=0.8"
        )
        // Timer units spin in
        .fromTo('.rh-unit',
            { opacity: 0, scale: 0.5, rotationY: 90 },
            { duration: 1, opacity: 1, scale: 1, rotationY: 0, stagger: 0.2, ease: "elastic.out(1, 0.6)", clearProps: "rotationY" },
            "-=0.5"
        );
        
        if (rhTimerInterval) clearInterval(rhTimerInterval);
        
        let lastH = -1;
        let lastM = -1;
        let lastS = -1;
        
        // Continuous gentle floating animation for the clock cards
        gsap.to('.rh-unit', {
            y: "random(-8, 8)",
            x: "random(-4, 4)",
            rotationZ: "random(-1.5, 1.5)",
            duration: "random(3, 5)",
            repeat: -1,
            yoyo: true,
            ease: "sine.inOut",
            stagger: 0.1
        });
        
        rhTimerInterval = setInterval(() => {
            let now = new Date();
            let diff = rhEndTime - now;
            
            if (diff <= 0) {
                diff = 0;
                clearInterval(rhTimerInterval);
            }
            
            let h = Math.floor(diff / (1000 * 60 * 60));
            let m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            let s = Math.floor((diff % (1000 * 60)) / 1000);
            
            let hStr = String(h).padStart(2, '0');
            let mStr = String(m).padStart(2, '0');
            let sStr = String(s).padStart(2, '0');
            
            let hourEl = document.getElementById('rh-hours');
            let minEl = document.getElementById('rh-mins');
            let secEl = document.getElementById('rh-secs');
            
            if (lastH !== h) {
                hourEl.textContent = hStr;
                gsap.fromTo(hourEl, 
                    { scale: 1.5, color: '#f97316', textShadow: '0 0 40px #f97316' }, 
                    { scale: 1, color: '#fff', textShadow: '0 0 20px rgba(255,255,255,0.5), 0 0 40px rgba(239,68,68,0.4), 0 10px 20px rgba(0,0,0,0.8)', duration: 0.6, ease: "power2.out" }
                );
                lastH = h;
            }
            
            if (lastM !== m) {
                minEl.textContent = mStr;
                gsap.fromTo(minEl, 
                    { scale: 1.5, color: '#f99d4e', textShadow: '0 0 40px #f99d4e' }, 
                    { scale: 1, color: '#fff', textShadow: '0 0 20px rgba(255,255,255,0.4), 0 5px 10px rgba(0,0,0,0.5)', duration: 0.6, ease: "power2.out" }
                );
                lastM = m;
            }
            
            if (lastS !== s) {
                secEl.textContent = sStr;
                gsap.fromTo(secEl, 
                    { scale: 1.2, rotationX: -30, color: '#ff4d4d', textShadow: '0 0 30px #ff4d4d' }, 
                    { scale: 1, rotationX: 0, color: '#fff', textShadow: '0 0 20px rgba(255,255,255,0.5), 0 0 40px rgba(239,68,68,0.4), 0 10px 20px rgba(0,0,0,0.8)', duration: 0.5, ease: "back.out(2)" }
                );
                lastS = s;
            }

            // Update progress rings smoothly using GSAP
            let totalHours = diff / (1000 * 60 * 60);
            let totalMins = (diff % (1000 * 60 * 60)) / (1000 * 60);
            let totalSecs = (diff % (1000 * 60)) / 1000;

            let hourOffset = 283 - (Math.min(totalHours, 1.0) / 1.0) * 283;
            let minOffset = 283 - (totalMins / 60) * 283;
            let secOffset = 283 - (totalSecs / 60) * 283;

            gsap.to('#rh-bar-hours', { strokeDashoffset: hourOffset, duration: 0.5, ease: "power1.out" });
            gsap.to('#rh-bar-mins', { strokeDashoffset: minOffset, duration: 0.5, ease: "power1.out" });
            gsap.to('#rh-bar-secs', { strokeDashoffset: secOffset, duration: 0.5, ease: "power1.out" });
            
        }, 1000);
    }

    function endRushHourUI() {
        isRushHourActive = false;
        if (rhTimerInterval) clearInterval(rhTimerInterval);
        
        // GSAP Exit animation
        gsap.to(['.rh-title', '.rh-subtitle', '.rh-clock'], {
            duration: 0.6,
            opacity: 0,
            scale: 0.8,
            stagger: 0.1,
            ease: "power2.in",
            onComplete: () => {
                document.getElementById('rush-hour-overlay').classList.remove('active');
                document.querySelector('.dashboard').classList.remove('hidden');
            }
        });
    }

    // Poll every 3 seconds
    setInterval(checkRushHourStatus, 3000);
    // Initial check
    checkRushHourStatus();

})();
</script>


<script>
document.addEventListener('DOMContentLoaded', function() {
    // Make sure GSAP is loaded before running
    if(typeof gsap === 'undefined') return;

    // Define target groups for staggered animation
    const dashboardCards = gsap.utils.toArray('.clock-unit, .weather-card, .lb-table-card, .live-news-card, .business-cats-card, .stat-card, .top-performer-card, .active-closers-card');
    
    // Set initial state (invisible and pushed down)
    gsap.set(dashboardCards, { 
        y: 100, 
        opacity: 0,
        rotationX: 30,
        rotationY: -10,
        scale: 0.8
    });
    
    gsap.set('.top-bar', { y: -50, opacity: 0 });
    gsap.set('.stats-bar-container', { y: 100, opacity: 0 });

    // Master Timeline
    const tl = gsap.timeline({ defaults: { ease: 'back.out(1.7)' } });
    
    // 1. Drop in top bar
    tl.to('.top-bar', { y: 0, opacity: 1, duration: 0.8 });
    
    // 2. Wave stagger in all cards
    tl.to(dashboardCards, {
        y: 0,
        opacity: 1,
        rotationX: 0,
        rotationY: 0,
        scale: 1,
        duration: 0.8,
        stagger: {
            amount: 1.2,
            grid: 'auto',
            from: 'start'
        }
    }, '-=0.4');
    
    // 3. Bring up the stats bar
    tl.to('.stats-bar-container', {
        y: 0,
        opacity: 1,
        duration: 0.8
    }, '-=0.8');

    // Continuous Breathing Animation to keep the dashboard feeling alive
    gsap.to('.dashboard', {
        y: -3,
        duration: 3,
        yoyo: true,
        repeat: -1,
        ease: 'sine.inOut'
    });
});
</script>

</body>
</html>
