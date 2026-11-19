@extends('layouts.admin')

@section('title', 'Games')

@section('content')
{{--
    Five minutes of something that is not a call.

    Three games, played in the page, with nothing loaded from anywhere else.
    Each keeps one number per person — their best — and the board beside it
    shows the top few on the floor, which is the only thing that makes an idle
    five minutes worth having.
--}}
<style>
    .gm-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.1rem; }

    .gm-card {
        border: 1px solid var(--border); border-radius: var(--radius-lg); background: var(--bg-card);
        padding: 1.3rem; cursor: pointer; box-shadow: var(--shadow-xs);
        transition: border-color var(--base) var(--ease), box-shadow var(--base) var(--ease), transform var(--base) var(--ease);
    }
    .gm-card:hover { border-color: var(--border-strong); box-shadow: var(--shadow-md); transform: translateY(-2px); }
    .gm-card h3 { margin: 0 0 0.3rem; font-size: 1.05rem; font-weight: 700; display: flex; align-items: center; gap: 9px; }
    .gm-card h3 i { color: var(--primary); }
    .gm-card p { margin: 0 0 1rem; font-size: 0.82rem; color: var(--text-muted); line-height: 1.6; }
    .gm-best { display: flex; align-items: center; gap: 8px; font-size: 0.78rem; color: var(--text-muted); }
    .gm-best b { color: var(--text-main); font-variant-numeric: tabular-nums; }

    /* ---- a game, open ---- */
    .gm-open { display: grid; grid-template-columns: minmax(0, 1fr) 260px; gap: 1.3rem; align-items: start; }
    @media (max-width: 1000px) { .gm-open { grid-template-columns: 1fr; } }

    .gm-stage { display: flex; flex-direction: column; align-items: center; gap: 1rem; padding: 0.5rem 0 1rem; }
    .gm-scorebar { display: flex; align-items: center; gap: 1.4rem; flex-wrap: wrap; justify-content: center; }
    .gm-num { text-align: center; }
    .gm-num span { display: block; font-size: 0.66rem; font-weight: 700; letter-spacing: 0.07em;
                   text-transform: uppercase; color: var(--text-soft); }
    .gm-num b { font-size: 1.3rem; font-weight: 700; font-variant-numeric: tabular-nums; }

    .gm-hint { font-size: 0.78rem; color: var(--text-muted); text-align: center; }

    /* ---- 2048 ---- */
    .g48 { --gap: 10px; background: var(--bg-subtle); border-radius: var(--radius); padding: var(--gap);
           display: grid; grid-template-columns: repeat(4, 1fr); gap: var(--gap);
           width: min(420px, 86vw); aspect-ratio: 1; touch-action: none; user-select: none; }
    .g48 .g48-cell { background: var(--bg-card); border-radius: var(--radius-sm); display: flex;
                     align-items: center; justify-content: center; font-weight: 800;
                     font-size: clamp(1rem, 5vw, 1.7rem); color: var(--text-main);
                     transition: background-color 120ms var(--ease), color 120ms var(--ease); }
    .g48 .g48-cell.is-new { animation: g48Pop 140ms var(--ease); }
    @keyframes g48Pop { from { transform: scale(0.6); } to { transform: none; } }

    /* ---- snake ---- */
    .gsnake { background: var(--bg-subtle); border-radius: var(--radius); display: block;
              width: min(420px, 86vw); aspect-ratio: 1; touch-action: none; }

    /* ---- memory ---- */
    .gmem { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; width: min(420px, 86vw); }
    .gmem button {
        aspect-ratio: 3 / 4; border-radius: var(--radius-sm); border: 1px solid var(--border);
        background: var(--bg-card); cursor: pointer; font-size: clamp(1.2rem, 6vw, 1.9rem);
        display: flex; align-items: center; justify-content: center; font-family: inherit;
        transition: background-color var(--fast) var(--ease), border-color var(--fast) var(--ease), transform var(--fast) var(--ease);
    }
    .gmem button.is-down { color: transparent; background: var(--bg-subtle); }
    .gmem button.is-up { border-color: var(--primary); }
    .gmem button.is-made { border-color: var(--success); background: rgba(16,185,129,0.1); cursor: default; }
    .gmem button:disabled { cursor: default; }

    /* ---- the board ---- */
    .gm-row { display: flex; align-items: center; gap: 10px; padding: 0.5rem 0.1rem;
              border-bottom: 1px solid var(--border); font-size: 0.84rem; }
    .gm-row:last-child { border-bottom: none; }
    .gm-row .gm-place { width: 20px; color: var(--text-soft); font-weight: 700; font-size: 0.76rem; }
    .gm-row .gm-who { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .gm-row .gm-pts { font-weight: 700; font-variant-numeric: tabular-nums; }
    .gm-row.is-me { color: var(--primary); }
    .gm-row.is-me .gm-pts { color: var(--primary); }
</style>

<div class="page-head">
    <div class="page-sub" style="margin: 0;">Five minutes of something that is not a call. Your best is kept; the floor can see it.</div>
    <div class="page-actions">
        <button type="button" class="btn btn-ghost" id="gmBack" hidden><i class="fas fa-arrow-left"></i> All games</button>
    </div>
</div>

{{-- ---------- the shelf ---------- --}}
<div class="gm-grid" id="gmShelf">
    @foreach($games as $key => $game)
        <div class="gm-card" data-game="{{ $key }}" role="button" tabindex="0">
            <h3><i class="fas {{ $game['icon'] }}"></i> {{ $game['name'] }}</h3>
            <p>{{ $game['blurb'] }}</p>
            <div class="gm-best">
                @if(isset($mine[$key]))
                    <i class="fas fa-trophy" style="color: #FBBF24;"></i>
                    Your best: <b>{{ number_format($mine[$key]) }}</b> {{ $game['unit'] }}
                @else
                    <i class="fas fa-circle-play" style="color: var(--text-soft);"></i>
                    Not played yet
                @endif
            </div>
        </div>
    @endforeach
</div>

{{-- ---------- one of them, open ---------- --}}
<div class="gm-open" id="gmOpen" hidden>
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-gamepad"></i> <span id="gmTitle">—</span></div>
            <button type="button" class="btn btn-ghost btn-sm" id="gmRestart"><i class="fas fa-rotate-right"></i> Start again</button>
        </div>

        <div class="gm-stage">
            <div class="gm-scorebar">
                <div class="gm-num"><span id="gmScoreLabel">Score</span><b id="gmScore">0</b></div>
                <div class="gm-num"><span>Your best</span><b id="gmBest">—</b></div>
            </div>

            <div id="gmBoardHolder"></div>

            <div class="gm-hint" id="gmHint"></div>
        </div>
    </div>

    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-trophy" style="color: #FBBF24;"></i> On the floor</div>
        </div>
        <div id="gmBoard">
            <div class="empty-state" style="padding: 1.4rem 0.5rem;"><p>Nobody has played yet.</p></div>
        </div>
    </div>
</div>

@include('admin.breakroom._games_js')
@endsection
