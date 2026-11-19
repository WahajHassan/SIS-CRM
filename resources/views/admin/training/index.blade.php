@extends('layouts.admin')

@section('title', 'Training')

@section('content')
{{--
    The training room.

    The list on the left, and whatever is open on the right: the recording plays
    where it is, the written part keeps its highlights, and the questions sit
    underneath where the next person with the same one will find them.
--}}
@php
    $me = auth()->user();
@endphp

<style>
    .tr-wrap { display: grid; grid-template-columns: 340px 1fr; gap: 1.5rem; align-items: start; }
    @media (max-width: 1100px) { .tr-wrap { grid-template-columns: 1fr; } }

    .tr-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 18px; padding: 1.2rem; }
    .tr-head { display: flex; justify-content: space-between; align-items: center; gap: 10px;
               border-bottom: 1px solid var(--border); padding-bottom: 0.9rem; margin-bottom: 1rem; }
    .tr-head h3 { margin: 0; font-size: 1.02rem; font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 8px; }

    .tr-list { display: flex; flex-direction: column; gap: 10px; max-height: 640px; overflow-y: auto; padding-right: 4px; }
    .tr-item { border: 1px solid var(--border); border-left: 3px solid var(--primary); border-radius: 12px;
               padding: 0.75rem 0.9rem; background: var(--bg-body); cursor: pointer; transition: border-color 0.15s ease; }
    .tr-item:hover { border-color: var(--primary); }
    .tr-item.is-open { border-color: var(--primary); background: rgba(249,157,78,0.07); }
    .tr-item.is-watched { border-left-color: #10b981; }
    .tr-item b { display: block; font-size: 0.9rem; color: var(--text-main); line-height: 1.4; }
    .tr-meta { font-size: 0.72rem; color: var(--text-muted); margin-top: 4px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .tr-kind { display: inline-flex; align-items: center; gap: 4px; padding: 1px 8px; border-radius: 999px; font-weight: 700; font-size: 0.68rem; }
    .tr-kind.video { background: rgba(99,102,241,0.12); color: #6366f1; }
    .tr-kind.voice { background: rgba(236,72,153,0.12); color: #db2777; }
    .tr-kind.text  { background: rgba(14,165,233,0.12); color: #0284c7; }
    .tr-seen { color: #10b981; font-weight: 700; }

    .tr-stage { min-height: 300px; }
    .tr-player { border-radius: 14px; overflow: hidden; background: #000; margin-bottom: 1.1rem; }
    .tr-player video, .tr-player iframe { display: block; width: 100%; height: 460px; border: 0; background: #000; }
    .tr-player.is-audio { background: var(--bg-body); padding: 1.2rem; }
    .tr-player.is-audio audio { width: 100%; }

    .tr-body { font-size: 0.92rem; line-height: 1.75; color: var(--text-main); }
    .tr-body mark { background: rgba(250, 204, 21, 0.45); color: inherit; padding: 1px 4px; border-radius: 4px; }
    .tr-body ul { margin: 0.6rem 0 0.6rem 1.2rem; }
    .tr-body li { margin-bottom: 4px; }

    .tr-comments { margin-top: 1.4rem; border-top: 1px solid var(--border); padding-top: 1.2rem; }
    .tr-comment { display: flex; gap: 10px; padding: 0.7rem 0; border-bottom: 1px dashed var(--border); }
    .tr-comment:last-child { border-bottom: none; }
    .tr-avatar { width: 32px; height: 32px; border-radius: 50%; flex: 0 0 32px; display: flex; align-items: center;
                 justify-content: center; font-size: 0.72rem; font-weight: 700; color: #fff;
                 background: linear-gradient(135deg, #f99d4e 0%, #e93b6e 100%); }
    .tr-comment-body { flex: 1; min-width: 0; }
    .tr-comment-body b { font-size: 0.82rem; color: var(--text-main); }
    .tr-comment-body small { font-size: 0.7rem; color: var(--text-muted); margin-left: 6px; }
    .tr-comment-body p { margin: 3px 0 0; font-size: 0.88rem; color: var(--text-main); line-height: 1.55; white-space: pre-wrap; word-break: break-word; }
    .tr-comment-remove { border: none; background: transparent; color: var(--text-muted); cursor: pointer; font-size: 0.78rem; }
    .tr-comment-remove:hover { color: var(--danger); }

    .tr-ask { display: flex; gap: 8px; margin-top: 1rem; }
    .tr-ask textarea { flex: 1; min-height: 44px; max-height: 140px; padding: 0.6rem 0.8rem; border-radius: 12px;
                       border: 1px solid var(--border); background: var(--bg-body); color: var(--text-main);
                       font-family: inherit; font-size: 0.88rem; resize: vertical; }

    .tr-form { display: none; margin-bottom: 1.2rem; padding-bottom: 1.2rem; border-bottom: 1px dashed var(--border); }
    .tr-form.is-open { display: block; }
    .tr-field { margin-bottom: 0.8rem; }
    .tr-field label { display: block; font-size: 0.78rem; font-weight: 600; color: var(--text-muted); margin-bottom: 5px; }
    .tr-field input[type=text], .tr-field input[type=url], .tr-field select, .tr-field textarea, .tr-field input[type=file] {
        width: 100%; padding: 0.6rem 0.7rem; border-radius: 10px; border: 1px solid var(--border);
        background: var(--bg-body); color: var(--text-main); font-family: inherit; font-size: 0.86rem;
    }
    .tr-field textarea { min-height: 110px; resize: vertical; }
    .tr-hint { font-size: 0.72rem; color: var(--text-muted); margin-top: 5px; line-height: 1.5; }
    .tr-kinds { display: flex; gap: 8px; }
    .tr-kinds label { flex: 1; text-align: center; border: 1px solid var(--border); border-radius: 10px;
                      padding: 0.5rem; cursor: pointer; font-size: 0.82rem; font-weight: 600; color: var(--text-muted); }
    .tr-kinds input { display: none; }
    .tr-kinds input:checked + span { color: var(--primary); }
    .tr-kinds label:has(input:checked) { border-color: var(--primary); background: rgba(249,157,78,0.08); }

    /* ---- the questions, one at a time ---- */
    .tr-step { display: none; }
    .tr-step.is-on { display: block; }
    .tr-step-head { display: flex; align-items: center; gap: 8px; font-size: 0.86rem; font-weight: 700;
                    color: var(--text-main); margin-bottom: 0.8rem; }
    .tr-step-head > span:first-of-type { width: 20px; height: 20px; border-radius: 50%; flex: 0 0 20px;
                                         background: var(--primary); color: #fff; font-size: 0.7rem;
                                         display: flex; align-items: center; justify-content: center; }
    .tr-back { border: 1px solid var(--border); background: var(--bg-body); color: var(--text-muted);
               border-radius: 8px; width: 26px; height: 24px; cursor: pointer; font-size: 0.72rem; padding: 0; }
    .tr-back:hover { color: var(--text-main); border-color: var(--primary); }

    .tr-picks { display: flex; flex-direction: column; gap: 8px; }
    .tr-pick { display: grid; grid-template-columns: 26px 1fr; grid-template-rows: auto auto; gap: 0 10px;
               text-align: left; border: 1px solid var(--border); background: var(--bg-body);
               border-radius: 12px; padding: 0.7rem 0.9rem; cursor: pointer; font-family: inherit;
               transition: border-color 0.15s ease; }
    .tr-pick:hover { border-color: var(--primary); }
    .tr-pick i { grid-row: 1 / 3; align-self: center; font-size: 1rem; color: var(--primary); text-align: center; }
    .tr-pick b { font-size: 0.86rem; color: var(--text-main); }
    .tr-pick small { font-size: 0.72rem; color: var(--text-muted); }

    .tr-next { width: 100%; justify-content: center; margin-top: 0.8rem; }

    .tr-attached { display: flex; align-items: center; gap: 8px; font-size: 0.76rem; color: var(--text-muted);
                   background: var(--bg-body); border: 1px solid var(--border); border-radius: 10px;
                   padding: 0.5rem 0.7rem; margin-bottom: 0.8rem; }
    .tr-attached span { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .tr-attached button { border: none; background: transparent; color: var(--primary); cursor: pointer;
                          font-size: 0.74rem; font-weight: 700; font-family: inherit; }

    /* ---- the studio ---- */
    .tr-studio { position: fixed; inset: 0; z-index: 3000; background: rgba(8, 12, 20, 0.72);
                 backdrop-filter: blur(3px); display: flex; align-items: center; justify-content: center; padding: 2rem; }
    .tr-studio[hidden] { display: none; }
    .tr-studio-box { width: min(1100px, 100%); max-height: 92vh; display: flex; flex-direction: column;
                     background: var(--bg-card); border: 1px solid var(--border); border-radius: 20px;
                     overflow: hidden; box-shadow: 0 30px 70px rgba(0,0,0,0.4); }
    .tr-studio-head { display: flex; align-items: center; justify-content: space-between; gap: 10px;
                      padding: 0.9rem 1.2rem; border-bottom: 1px solid var(--border); }
    .tr-studio-title { display: flex; align-items: center; gap: 10px; font-size: 0.95rem; color: var(--text-main); }
    .tr-studio-close { border: none; background: transparent; color: var(--text-muted); font-size: 1rem;
                       cursor: pointer; padding: 4px 8px; border-radius: 8px; }
    .tr-studio-close:hover { color: var(--danger); background: var(--bg-body); }
    .tr-studio-body { padding: 1.2rem; overflow-y: auto; }
    .tr-studio-foot { display: flex; align-items: center; gap: 10px; padding: 0.9rem 1.2rem;
                      border-top: 1px solid var(--border); background: var(--bg-body); }
    @media (max-width: 760px) {
        .tr-studio { padding: 0.8rem; }
        .tr-studio-foot { flex-wrap: wrap; }
    }

    /* The voice meter: something that moves while there is nothing to see. */
    .tr-levels { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
                 gap: 5px; padding: 2rem; }
    .tr-levels[hidden] { display: none; }
    .tr-levels i { width: 7px; height: 10%; border-radius: 999px;
                   background: linear-gradient(180deg, #f99d4e 0%, #e93b6e 100%); transition: height 0.08s linear; }

    .tr-ways { display: flex; gap: 6px; margin-bottom: 0.7rem; }
    .tr-way { flex: 1; border: 1px solid var(--border); background: var(--bg-body); color: var(--text-muted);
              border-radius: 10px; padding: 0.45rem 0.4rem; font-size: 0.76rem; font-weight: 700;
              cursor: pointer; font-family: inherit; }
    .tr-way.is-on { border-color: var(--primary); color: var(--primary); background: rgba(249,157,78,0.08); }
    .tr-way-panel { display: none; }
    .tr-way-panel.is-on { display: block; }

    .tr-modes { display: flex; gap: 6px; margin-bottom: 0.6rem; }
    .tr-mode { flex: 1; border: 1px solid var(--border); background: var(--bg-body); color: var(--text-muted);
               border-radius: 10px; padding: 0.4rem 0.3rem; font-size: 0.74rem; font-weight: 700;
               cursor: pointer; font-family: inherit; }
    .tr-mode.is-on { border-color: var(--primary); color: var(--primary); background: rgba(249,157,78,0.08); }

    /* Where the face sits: a row anybody can see and press. */
    .tr-bubble-opts { display: grid; grid-template-columns: auto auto 1fr; gap: 1.4rem; align-items: start;
                      margin-top: 1rem; padding: 0.9rem 1rem; border: 1px solid var(--border);
                      border-radius: 14px; background: var(--bg-body); }
    .tr-opt { display: flex; flex-direction: column; gap: 7px; }
    .tr-opt-label { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase;
                    color: var(--text-muted); }

    .tr-corners { display: grid; grid-template-columns: 1fr 1fr; gap: 5px; }
    .tr-corners button { width: 42px; height: 30px; border: 1px solid var(--border); border-radius: 7px;
                         background: var(--bg-card); cursor: pointer; padding: 4px; position: relative; }
    /* A round mark, because what goes in that corner is a round bubble. */
    .tr-corners button i { position: absolute; width: 11px; height: 11px; border-radius: 50%;
                           background: var(--text-muted); opacity: 0.45; }
    .tr-corners button[data-corner="tl"] i { top: 4px; left: 4px; }
    .tr-corners button[data-corner="tr"] i { top: 4px; right: 4px; }
    .tr-corners button[data-corner="bl"] i { bottom: 4px; left: 4px; }
    .tr-corners button[data-corner="br"] i { bottom: 4px; right: 4px; }
    .tr-corners button:hover { border-color: var(--primary); }
    .tr-corners button.is-on { border-color: var(--primary); background: rgba(249,157,78,0.1); }
    .tr-corners button.is-on i { background: var(--primary); opacity: 1; }

    .tr-sizes { display: flex; gap: 5px; }
    .tr-sizes button { height: 30px; padding: 0 0.8rem; border: 1px solid var(--border); border-radius: 8px;
                       background: var(--bg-card); color: var(--text-muted); font-size: 0.78rem;
                       font-weight: 700; cursor: pointer; font-family: inherit; }
    .tr-sizes button:hover { border-color: var(--primary); }
    .tr-sizes button.is-on { border-color: var(--primary); color: var(--primary); background: rgba(249,157,78,0.1); }

    .tr-bubble-toggle { height: 30px; padding: 0 0.9rem; border: 1px solid var(--border); background: var(--bg-card);
                        color: var(--text-main); border-radius: 8px; font-size: 0.78rem; font-weight: 700;
                        cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 7px; }
    .tr-bubble-toggle:hover { border-color: var(--primary); }
    .tr-bubble-toggle.is-off { border-color: var(--danger); color: var(--danger); }

    @media (max-width: 760px) { .tr-bubble-opts { grid-template-columns: 1fr; gap: 0.9rem; } }

    /* What is left on screen while it records. */
    /* ---- what is left on screen while it records ----
       The bubble you are being seen through, and beside it the handful of
       things worth pressing. Both move together when dragged. */
    .tr-bar { position: fixed; left: 24px; bottom: 24px; z-index: 3200; display: flex; align-items: center; gap: 12px; }
    .tr-bar[hidden] { display: none; }

    /* The camera, round, the same shape it is recorded in. */
    .tr-bar-cam {
        width: 124px; height: 124px; border-radius: 50%;
        background: #000; object-fit: cover; display: block; flex-shrink: 0;
        border: 3px solid #fff;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.35);
        cursor: grab;
        transition: opacity var(--base) var(--ease), box-shadow var(--base) var(--ease);
    }
    .tr-bar-cam:active { cursor: grabbing; }
    .tr-bar-cam[hidden] { display: none; }
    html.dark-mode .tr-bar-cam { border-color: rgba(255,255,255,0.85); }

    /* The controls, stacked beside it. */
    .tr-dock {
        display: flex; flex-direction: column; align-items: center; gap: 6px;
        padding: 9px 7px;
        background: var(--bg-card); border: 1px solid var(--border);
        border-radius: var(--radius-pill);
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.22);
    }

    .tr-bar-grip { color: var(--text-soft); cursor: grab; font-size: 0.75rem; padding: 2px 0; line-height: 1; }
    .tr-bar-grip:active { cursor: grabbing; }

    .tr-dock-time {
        display: flex; flex-direction: column; align-items: center; gap: 3px;
        padding: 2px 0 4px;
    }
    .tr-bar b {
        font-size: 0.78rem; font-variant-numeric: tabular-nums; font-weight: 700;
        color: var(--text-main); line-height: 1;
    }
    .tr-bar-dot { width: 9px; height: 9px; border-radius: 50%; background: #ef4444; animation: trPulse 1.1s infinite; }

    /* Icon buttons: a label on each would make the stack as wide as a panel. */
    .tr-bar-btn {
        width: 36px; height: 36px; padding: 0; border-radius: 50%;
        border: 1px solid var(--border); background: var(--bg-body); color: var(--text-main);
        font-size: 0.82rem; cursor: pointer; font-family: inherit;
        display: inline-flex; align-items: center; justify-content: center;
        transition: border-color var(--fast) var(--ease), background-color var(--fast) var(--ease), color var(--fast) var(--ease);
    }
    .tr-bar-btn:hover { border-color: var(--primary); color: var(--primary); }
    .tr-bar-btn.is-off { border-color: var(--danger); color: var(--danger); }
    .tr-bar-btn.is-stop { background: #ef4444; color: #fff; border-color: transparent; }
    .tr-bar-btn.is-stop:hover { background: #dc2626; color: #fff; }
    .tr-bar-btn.is-resume { border-color: var(--success); color: var(--success); }

    /* Paused: the dot stops pulsing, because nothing is being recorded. */
    .tr-bar.is-paused .tr-bar-dot { animation: none; background: var(--warning); }
    .tr-bar.is-paused .tr-bar-cam { opacity: 0.5; }

    /* On a phone the bubble would be most of the screen. */
    @media (max-width: 560px) {
        .tr-bar { left: 12px; bottom: 12px; gap: 8px; }
        .tr-bar-cam { width: 84px; height: 84px; }
        .tr-bar-btn { width: 32px; height: 32px; }
    }

    .tr-stagebox { position: relative; border-radius: 14px; overflow: hidden; background: #0b1220;
                   aspect-ratio: 16 / 9; max-height: 58vh; display: flex; align-items: center; justify-content: center; }
    .tr-stagebox video, .tr-stagebox canvas { width: 100%; height: 100%; object-fit: contain; background: #0b1220; }
    .tr-stagebox canvas[hidden], .tr-stagebox video[hidden] { display: none; }
    .tr-rec-overlay { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
                      color: rgba(255,255,255,0.55); font-size: 0.82rem; font-style: italic; pointer-events: none; }
    @keyframes trPulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.25; } }

    .tr-rec-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-top: 0.7rem; }

    .tr-progress { margin-top: 0.8rem; display: flex; align-items: center; gap: 10px; font-size: 0.76rem; color: var(--text-muted); }
    .tr-progress-bar { flex: 1; height: 7px; border-radius: 999px; background: var(--bg-body);
                       border: 1px solid var(--border); overflow: hidden; }
    .tr-progress-bar i { display: block; height: 100%; width: 0; background: var(--primary); transition: width 0.2s ease; }

    .tr-empty { text-align: center; padding: 3rem 1rem; color: var(--text-muted); }
    .tr-watchers { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.8rem; }
</style>

<div class="page-head">
    <div class="page-sub" style="margin: 0;">Recordings, voice notes and written training — and whatever the team asks about them.</div>
    @if($canManage)
        <div class="page-actions">
            <button type="button" class="btn btn-primary" onclick="trToggleForm()">
                <i class="fas fa-plus"></i> Put something up
            </button>
        </div>
    @endif
</div>

<div class="tr-wrap">
    {{-- ---------------- the list ---------------- --}}
    <div class="tr-card">
        <div class="tr-head">
            <h3><i class="fas fa-list" style="color: var(--primary);"></i> Training</h3>
            <span style="font-size: 0.75rem; color: var(--text-muted);" id="trCount">{{ $trainings->count() }}</span>
        </div>

        @if($canManage)
            <form class="tr-form" id="trForm" action="{{ route('admin.training.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                {{-- A refused upload must say why. The usual reason is the
                     server's own size limit, which rejects the request before
                     any of this runs — hence the note under the file box. --}}
                @if($errors->any())
                    <div style="background: rgba(239,68,68,0.08); border: 1px solid var(--danger); color: var(--danger);
                                border-radius: 10px; padding: 0.7rem 0.9rem; margin-bottom: 0.9rem; font-size: 0.82rem;">
                        @foreach($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                {{-- One question at a time, and the next one follows from the
                     answer. The old form asked everything at once, including
                     things that did not apply — a written piece does not need a
                     camera, and a pasted link does not need a corner to sit in. --}}
                <div class="tr-step is-on" data-step="kind">
                    <div class="tr-step-head"><span>1</span> What is it?</div>

                    <div class="tr-picks">
                        <button type="button" class="tr-pick" onclick="trPickKind('video')">
                            <i class="fas fa-video"></i>
                            <b>Recording</b>
                            <small>A screen or camera recording</small>
                        </button>
                        <button type="button" class="tr-pick" onclick="trPickKind('voice')">
                            <i class="fas fa-microphone"></i>
                            <b>Voice note</b>
                            <small>Just your voice</small>
                        </button>
                        <button type="button" class="tr-pick" onclick="trPickKind('text')">
                            <i class="fas fa-align-left"></i>
                            <b>Written</b>
                            <small>Something to read</small>
                        </button>
                    </div>

                    <input type="hidden" name="kind" id="trKind" value="video">
                </div>

                <div class="tr-step" data-step="how">
                    <div class="tr-step-head">
                        <button type="button" class="tr-back" onclick="trBack()"><i class="fas fa-arrow-left"></i></button>
                        <span>2</span> <span id="trHowTitle">How does it get here?</span>
                    </div>

                    <div class="tr-picks">
                        <button type="button" class="tr-pick" onclick="trPickHow('record')">
                            <i class="fas fa-circle" style="color: #ef4444;"></i>
                            <b>Record it now</b>
                            <small id="trRecordHint">Straight from this page</small>
                        </button>
                        <button type="button" class="tr-pick" onclick="trPickHow('file')">
                            <i class="fas fa-upload"></i>
                            <b>Upload a file</b>
                            <small>One you already have</small>
                        </button>
                        <button type="button" class="tr-pick" onclick="trPickHow('link')">
                            <i class="fas fa-link"></i>
                            <b>Paste a link</b>
                            <small>Drive, YouTube, Loom…</small>
                        </button>
                    </div>
                </div>

                <div class="tr-step" data-step="source">
                    <div class="tr-step-head">
                        <button type="button" class="tr-back" onclick="trBack()"><i class="fas fa-arrow-left"></i></button>
                        <span>3</span> What should it show?
                    </div>

                    <div class="tr-picks">
                        <button type="button" class="tr-pick" onclick="trPickSource('both')">
                            <i class="fas fa-display"></i>
                            <b>Screen + me</b>
                            <small>Your screen with your face on it</small>
                        </button>
                        <button type="button" class="tr-pick" onclick="trPickSource('screen')">
                            <i class="fas fa-desktop"></i>
                            <b>Screen only</b>
                            <small>Just what you are showing</small>
                        </button>
                        <button type="button" class="tr-pick" onclick="trPickSource('camera')">
                            <i class="fas fa-user"></i>
                            <b>Just me</b>
                            <small>Camera only</small>
                        </button>
                    </div>
                </div>

                <div class="tr-step" data-step="file">
                    <div class="tr-step-head">
                        <button type="button" class="tr-back" onclick="trBack()"><i class="fas fa-arrow-left"></i></button>
                        <span>3</span> Pick the file
                    </div>

                    <input type="file" id="trMedia" name="media" accept="video/*,audio/*" onchange="trFilePicked()">
                    <div class="tr-hint">
                        However big it is. It goes up in pieces, so the server's own
                        upload limit is not what decides how long a recording can be.
                    </div>

                    <button type="button" class="btn btn-primary tr-next" onclick="trGo('details')"
                            style="cursor: pointer; font-weight: 600;">Next</button>
                </div>

                <div class="tr-step" data-step="link">
                    <div class="tr-step-head">
                        <button type="button" class="tr-back" onclick="trBack()"><i class="fas fa-arrow-left"></i></button>
                        <span>3</span> Paste the link
                    </div>

                    <input type="url" id="trUrl" name="media_url" placeholder="https://drive.google.com/file/d/…">
                    <div class="tr-hint">
                        YouTube, Vimeo, Drive and Loom links play inside the page.
                        Anything else opens where it lives.
                    </div>

                    <button type="button" class="btn btn-primary tr-next" onclick="trGo('details')"
                            style="cursor: pointer; font-weight: 600;">Next</button>
                </div>

                <div class="tr-step" data-step="details">
                    <div class="tr-step-head">
                        <button type="button" class="tr-back" onclick="trBack()"><i class="fas fa-arrow-left"></i></button>
                        <span>4</span> The last of it
                    </div>

                    {{-- What is attached, so nobody reaches the end wondering
                         whether the recording made it. --}}
                    <div class="tr-attached" id="trAttached" hidden>
                        <i class="fas fa-paperclip"></i>
                        <span id="trAttachedText">Nothing attached</span>
                        <button type="button" onclick="trBack()">change</button>
                    </div>

                    <div class="tr-field">
                        <label for="trTitle">Title</label>
                        <input type="text" id="trTitle" name="title" maxlength="160" required
                               placeholder="e.g. Handling the first objection">
                    </div>

                    <div class="tr-field">
                        <label for="trBody">Notes</label>
                        <textarea id="trBody" name="body" placeholder="What they should take from it…"></textarea>
                        <div class="tr-hint">
                            <b>==like this==</b> highlights a line, <b>**like this**</b> makes it bold,
                            and a line starting with <b>-</b> becomes a bullet.
                        </div>
                    </div>

                    <div class="tr-field">
                        <label for="trDept">Who is it for?</label>
                        <select id="trDept" name="department_id">
                            <option value="">Everybody</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}">{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="tr-field" style="display: flex; gap: 16px; align-items: center; font-size: 0.82rem; color: var(--text-muted);">
                        <label style="display: inline-flex; align-items: center; gap: 6px; margin: 0;">
                            <input type="checkbox" name="is_pinned" value="1" style="width: auto;"> Keep it at the top
                        </label>
                        <label style="display: inline-flex; align-items: center; gap: 6px; margin: 0;">
                            <input type="checkbox" name="notify" value="1" checked style="width: auto;"> Ring the bell
                        </label>
                    </div>

                    <div style="display: flex; gap: 8px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1; justify-content: center; cursor: pointer; font-weight: 600;">
                            <i class="fas fa-paper-plane"></i> Put it up
                        </button>
                        <button type="button" class="btn btn-ghost" style="border: 1px solid var(--border);" onclick="trToggleForm()">Cancel</button>
                    </div>
                </div>

                <div class="tr-progress" id="trProgress" hidden>
                    <div class="tr-progress-bar"><i id="trProgressFill"></i></div>
                    <span id="trProgressText">Sending…</span>
                </div>

                <input type="hidden" name="uploaded_path" id="trUploadedPath">
                <input type="hidden" name="uploaded_mime" id="trUploadedMime">

            </form>
        @endif

        <div class="tr-list" id="trList">
            @forelse($trainings as $training)
                <div class="tr-item {{ $training->watchedBy($me) ? 'is-watched' : '' }}"
                     data-id="{{ $training->id }}" onclick="trOpen({{ $training->id }})">
                    <b>
                        @if($training->is_pinned)<i class="fas fa-thumbtack" style="color: var(--primary);"></i> @endif
                        {{ $training->title }}
                    </b>
                    <div class="tr-meta">
                        <span class="tr-kind {{ $training->kind }}">
                            <i class="fas {{ $training->kind === 'video' ? 'fa-play' : ($training->kind === 'voice' ? 'fa-microphone' : 'fa-align-left') }}"></i>
                            {{ $training->kindLabel() }}
                        </span>
                        <span>{{ $training->author->display_name ?? 'Management' }}</span>
                        <span>&middot; {{ $training->created_at->diffForHumans() }}</span>
                        @if($training->comments_count)
                            <span><i class="fas fa-comment"></i> {{ $training->comments_count }}</span>
                        @endif
                        @if($training->watchedBy($me))
                            <span class="tr-seen"><i class="fas fa-check"></i> watched</span>
                        @endif
                    </div>
                    @if($training->department)
                        <div class="tr-meta"><span>for {{ $training->department->name }}</span></div>
                    @endif
                </div>
            @empty
                <div class="tr-empty" id="trEmpty">Nothing up yet.</div>
            @endforelse
        </div>
    </div>

    {{-- ---------------- whatever is open ---------------- --}}
    <div class="tr-card tr-stage" id="trStage">
        <div class="tr-empty empty-state" id="trNothingOpen">
            <i class="fas fa-circle-play"></i>
            <strong>Nothing open yet</strong>
            <p>Pick something from the list to watch it, listen to it or read it.</p>
        </div>

        <div id="trOpenPanel" hidden>
            <div class="tr-head">
                <h3 id="trOpenTitle">—</h3>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <span style="font-size: 0.75rem; color: var(--text-muted);" id="trOpenMeta"></span>
                    @if($canManage)
                        <button type="button" class="btn btn-ghost" id="trRemove"
                                style="border: 1px solid var(--border); padding: 0.3rem 0.7rem; font-size: 0.78rem; cursor: pointer;">
                            <i class="fas fa-trash-alt" style="color: var(--danger);"></i>
                        </button>
                    @endif
                </div>
            </div>

            <div id="trPlayer"></div>
            <div class="tr-body" id="trBodyOut"></div>

            @if($canManage)
                <div class="tr-watchers" id="trWatchers"></div>
            @endif

            <div class="tr-comments">
                <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.6rem;">
                    Questions &amp; comments <span id="trCommentCount" style="color: var(--text-muted); font-weight: 500;"></span>
                </div>

                <div id="trComments"></div>

                <form class="tr-ask" id="trAsk" onsubmit="trComment(event)">
                    @csrf
                    <textarea id="trAskBody" name="body" maxlength="2000" placeholder="Ask about this…" required></textarea>
                    <button type="submit" class="btn btn-primary" style="align-self: flex-end; cursor: pointer; font-weight: 600;">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- The studio.

     Setting a recording up needs room — whoever is recording has to see what
     they are about to show. Recording it needs the opposite: a screen recording
     of the CRM is useless if a panel is sitting over the CRM, so once it starts
     the panel gets out of the way and leaves a small bar behind. --}}
<div class="tr-studio" id="trStudio" hidden>
    <div class="tr-studio-box">
        <div class="tr-studio-head">
            <div class="tr-studio-title">
                <i class="fas fa-circle" style="color: #ef4444; font-size: 0.6rem;"></i>
                <b id="trStudioTitle">Recording</b>
            </div>
            <button type="button" class="tr-studio-close" onclick="trCloseStudio()" title="Close">
                <i class="fas fa-xmark"></i>
            </button>
        </div>

        <div class="tr-studio-body">
            <div class="tr-stagebox" id="trStage">
                <canvas id="trCanvas" hidden></canvas>
                <video id="trPreview" playsinline muted></video>

                {{-- A voice note has nothing to look at, so it gets something
                     that moves with the voice instead of a black rectangle. --}}
                <div class="tr-levels" id="trLevels" hidden>
                    <i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i>
                    <i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i>
                </div>

                <div class="tr-rec-overlay" id="trRecIdle">
                    <i class="fas fa-spinner fa-spin"></i> Asking the browser…
                </div>
            </div>

            {{-- Where the face sits. Big enough to see and to press. --}}
            <div class="tr-bubble-opts" id="trBubbleOpts">
                <div class="tr-opt">
                    <span class="tr-opt-label">Your camera sits</span>
                    <div class="tr-corners">
                        <button type="button" data-corner="tl" onclick="trSetCorner('tl')" title="Top left"><i></i></button>
                        <button type="button" data-corner="tr" onclick="trSetCorner('tr')" title="Top right"><i></i></button>
                        <button type="button" data-corner="bl" onclick="trSetCorner('bl')" title="Bottom left"><i></i></button>
                        <button type="button" class="is-on" data-corner="br" onclick="trSetCorner('br')" title="Bottom right"><i></i></button>
                    </div>
                </div>

                <div class="tr-opt">
                    <span class="tr-opt-label">How big</span>
                    <div class="tr-sizes">
                        <button type="button" data-size="s" onclick="trSetBubbleSize('s')">Small</button>
                        <button type="button" class="is-on" data-size="m" onclick="trSetBubbleSize('m')">Medium</button>
                        <button type="button" data-size="l" onclick="trSetBubbleSize('l')">Large</button>
                    </div>
                </div>

                <div class="tr-opt">
                    <span class="tr-opt-label">Camera</span>
                    <button type="button" class="tr-bubble-toggle" id="trBubbleToggle" onclick="trToggleBubble()">
                        <i class="fas fa-video"></i> On — hide me
                    </button>
                </div>
            </div>

            <div class="tr-hint" id="trRecNote">
                Nothing leaves this page until you put it up. Once it starts, this panel
                shrinks to a small bar so you can carry on using the CRM — and moving to
                another tab does not stop it.
            </div>
        </div>

        <div class="tr-studio-foot">
            <button type="button" class="btn btn-ghost" onclick="trCloseStudio()"
                    style="border: 1px solid var(--border); cursor: pointer; font-weight: 600;">Cancel</button>

            <span class="tr-hint" id="trRecSize" style="margin: 0; flex: 1;"></span>

            <button type="button" class="btn btn-ghost" id="trRecAgain" onclick="trRecordAgain()" hidden
                    style="border: 1px solid var(--border); cursor: pointer; font-weight: 600;">
                <i class="fas fa-rotate-left"></i> Record again
            </button>
            <button type="button" class="btn btn-primary" id="trRecStart" onclick="trStartRecording()" disabled
                    style="cursor: pointer; font-weight: 600;">
                <i class="fas fa-circle"></i> Start recording
            </button>
            <button type="button" class="btn btn-primary" id="trRecUse" onclick="trUseRecording()" hidden
                    style="cursor: pointer; font-weight: 600; background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                <i class="fas fa-check"></i> Use this
            </button>
        </div>
    </div>
</div>

{{-- What is left on screen while it records: enough to stop it and to take
     yourself off camera, and nothing else in the way. --}}
<div class="tr-bar" id="trBar" hidden>
    {{-- The bubble you are being seen through. It is the picture going into the
         file, so you can see you are still in frame — and it is the handle, so
         the whole thing is dragged by the thing you are looking at. --}}
    <video class="tr-bar-cam" id="trBarCam" muted playsinline autoplay hidden title="Drag me"></video>

    <div class="tr-dock">
        <span class="tr-bar-grip" id="trBarGrip" title="Drag me"><i class="fas fa-grip-vertical"></i></span>

        <span class="tr-dock-time">
            <span class="tr-bar-dot"></span>
            <b id="trRecTime">0:00</b>
        </span>

        <button type="button" class="tr-bar-btn" id="trRecPause" onclick="trPauseRecording()"
                title="Pause — the recording carries on from here" aria-label="Pause">
            <i class="fas fa-pause"></i>
        </button>

        <button type="button" class="tr-bar-btn" id="trBarBubble" onclick="trToggleBubble()"
                title="Take yourself off the recording" aria-label="Camera on or off">
            <i class="fas fa-video"></i>
        </button>

        <button type="button" class="tr-bar-btn is-stop" id="trRecStop" onclick="trStopRecording()"
                title="Stop and keep the recording" aria-label="Stop">
            <i class="fas fa-stop"></i>
        </button>
    </div>
</div>


<script>
/*
 * The training room.
 *
 * One piece is open at a time and everything about it — the recording, the
 * notes, the questions — is fetched when it is opened rather than rendered for
 * all of them up front. A room with thirty recordings in it would otherwise send
 * thirty players to a browser that will show one.
 */
var trOpenId = {{ $open ? (int) $open : 'null' }};
var trCanManage = @json($canManage);
var trToken = '{{ csrf_token() }}';

function trEscape(value) {
    var div = document.createElement('div');
    div.textContent = value == null ? '' : value;
    return div.innerHTML;
}

function trToggleForm() {
    var form = document.getElementById('trForm');
    if (!form) return;

    form.classList.toggle('is-open');

    // Opening it again starts at the first question rather than wherever the
    // last attempt was abandoned.
    if (form.classList.contains('is-open')) {
        trTrail = [];
        trGo('kind', false);
    }
}

/* A written piece has nothing to upload, so it does not ask. */
function trKindChanged() {
    var kind = document.querySelector('input[name=kind]:checked');
    var media = document.getElementById('trMediaFields');
    if (kind && media) media.style.display = kind.value === 'text' ? 'none' : 'block';
}

/*
 * Putting a recording up.
 *
 * Three ways in — a file, one made here and now, or a link — and the first two
 * end at the same place: a blob that goes up in half-megabyte pieces. Shared
 * hosting rejects a large request before any of our code runs, so sending one
 * large request is sending nothing at all; sending forty small ones works
 * everywhere and shows progress while it happens.
 */
/*
 * Putting training up, one question at a time.
 *
 * The form used to ask everything at once, including things that did not apply:
 * a written piece was still offered a camera, and a pasted link was still asked
 * which corner the face should sit in. Each answer now decides what is asked
 * next, and every step after the first can be stepped back out of.
 */
var trKindNow = 'video';
var trHowNow = null;
var trStepNow = 'kind';
var trTrail = [];

var trRecorder = null;
var trStream = null;            // what is being recorded
var trScreen = null;            // the shared screen, while it is being drawn
var trCamera = null;            // the camera or the microphone
var trAudio = null;             // the mixer, when there is more than one sound
var trMeter = null;             // the voice meter, while a voice note is recording
var trChunks = [];
var trBlob = null;
var trTicker = null;
var trDrawing = null;
var trMode = 'both';
var trStartedAt = 0;            // when this recording began
var trPausedMs = 0;             // how long it has spent paused
var trPausedAt = null;          // when the current pause started, if it is paused
var trCorner = 'br';
var trBubble = 'm';
var trBubbleOn = true;

/* ---- the steps ---------------------------------------------------------- */

function trGo(step, remember) {
    if (remember !== false && trStepNow && trStepNow !== step) {
        trTrail.push(trStepNow);
    }

    trStepNow = step;

    document.querySelectorAll('.tr-step').forEach(function (el) {
        el.classList.toggle('is-on', el.dataset.step === step);
    });

    if (step === 'details') trShowAttached();
}

function trBack() {
    var previous = trTrail.pop() || 'kind';
    trGo(previous, false);
}

function trPickKind(kind) {
    trKindNow = kind;
    document.getElementById('trKind').value = kind;

    // Nothing carries over from a different kind.
    trClearMedia();

    if (kind === 'text') {
        trGo('details');
        return;
    }

    document.getElementById('trHowTitle').textContent =
        kind === 'voice' ? 'How does the voice note get here?' : 'How does the recording get here?';
    document.getElementById('trRecordHint').textContent =
        kind === 'voice' ? 'Speak into this page' : 'Straight from this page';

    trGo('how');
}

function trPickHow(how) {
    trHowNow = how;

    if (how === 'file') { trGo('file'); return; }
    if (how === 'link') { trGo('link'); return; }

    // A voice note has nothing to choose between: it is the microphone.
    if (trKindNow === 'voice') {
        trMode = 'voice';
        trGo('details');            // where they land once the studio closes
        trOpenStudio();
        return;
    }

    trGo('source');
}

function trPickSource(mode) {
    trMode = mode;
    trGo('details');
    trOpenStudio();
}

function trClearMedia() {
    trBlob = null;
    document.getElementById('trMedia').value = '';
    document.getElementById('trUrl').value = '';
    document.getElementById('trUploadedPath').value = '';
    document.getElementById('trUploadedMime').value = '';
}

function trFilePicked() {
    // A picked file is sent the same way a recorded one is.
    trBlob = document.getElementById('trMedia').files[0] || null;
    trShowAttached();
}

function trSize(bytes) {
    if (!bytes) return '';
    var mb = bytes / (1024 * 1024);
    return mb < 1 ? Math.round(bytes / 1024) + ' KB' : mb.toFixed(1) + ' MB';
}

/** What is attached, said plainly on the last step. */
function trShowAttached() {
    var box = document.getElementById('trAttached');
    var text = document.getElementById('trAttachedText');
    var link = document.getElementById('trUrl').value;

    if (trKindNow === 'text') { box.hidden = true; return; }

    if (trBlob) {
        text.textContent = (trBlob.name ? 'File: ' + trBlob.name : 'Recorded here') + ' — ' + trSize(trBlob.size);
        box.hidden = false;
        return;
    }

    if (link) {
        text.textContent = 'Link: ' + link;
        box.hidden = false;
        return;
    }

    text.textContent = 'Nothing attached yet';
    box.hidden = false;
}

/* ---- the studio --------------------------------------------------------- */

var trStage = null;             // what the recorder will be handed
var trReady = false;

async function trOpenStudio() {
    var studio = document.getElementById('trStudio');

    document.getElementById('trStudioTitle').textContent = {
        both: 'Screen + you',
        screen: 'Your screen',
        camera: 'Just you',
        voice: 'Voice note',
    }[trMode] || 'Recording';

    // Only a face drawn onto a screen has a corner to sit in.
    document.getElementById('trBubbleOpts').style.display = trMode === 'both' ? 'grid' : 'none';
    document.getElementById('trBarBubble').style.display = trMode === 'both' ? 'inline-flex' : 'none';
    document.getElementById('trLevels').hidden = trMode !== 'voice';
    document.getElementById('trPreview').hidden = trMode === 'voice';

    trResetStudio();

    studio.hidden = false;
    document.body.style.overflow = 'hidden';

    // Asking for the camera and the screen now, rather than when Start is
    // pressed, is the difference between seeing what will be recorded and
    // hoping. The click that chose the source is still the gesture the browser
    // wants, so the prompt appears straight away.
    await trPrepare();
}

/** Get hold of everything, show it live, and leave it ready to record. */
async function trPrepare() {
    var note = document.getElementById('trRecNote');
    var idle = document.getElementById('trRecIdle');

    try {
        if (trMode !== 'camera' && trMode !== 'voice') {
            trScreen = await navigator.mediaDevices.getDisplayMedia({
                video: { frameRate: 30 },
                audio: true,
            });
        }

        if (trMode === 'both' || trMode === 'camera') {
            trCamera = await navigator.mediaDevices.getUserMedia({
                video: { width: 1280, height: 720 },
                audio: true,
            });
        } else {
            // Screen capture brings the screen's own sound, not the voice
            // explaining it, so the microphone comes along anyway — and for a
            // voice note the microphone is the whole of it.
            try {
                trCamera = await navigator.mediaDevices.getUserMedia({ audio: true });
            } catch (e) {
                if (trMode === 'voice') throw e;
            }
        }
    } catch (e) {
        trCleanupStreams();
        idle.innerHTML = '<i class="fas fa-triangle-exclamation"></i> Nothing was shared';
        note.textContent = 'The browser would not give access to that. Check its permissions and try again.';
        return false;
    }

    trStage = trBuildStage();

    if (!trStage) {
        trCleanupStreams();
        idle.textContent = 'Nothing came back to show.';
        return false;
    }

    // Ending the share from the browser's own bar ends it here too.
    var screenTrack = trScreen && trScreen.getVideoTracks()[0];
    if (screenTrack) {
        screenTrack.addEventListener('ended', function () {
            if (trRecorder && trRecorder.state === 'recording') trStopRecording();
            else trCloseStudio();
        });
    }

    idle.hidden = true;
    trReady = true;

    var start = document.getElementById('trRecStart');
    start.disabled = false;
    start.innerHTML = '<i class="fas fa-circle"></i> Start recording';

    return true;
}

function trCloseStudio() {
    trStopRecording(true);

    document.getElementById('trStudio').hidden = true;
    document.getElementById('trBar').hidden = true;
    document.body.style.overflow = '';

    trShowAttached();
}

function trUseRecording() {
    trCloseStudio();
    trGo('details', false);
}

function trResetStudio() {
    var preview = document.getElementById('trPreview');

    preview.src = '';
    preview.srcObject = null;
    preview.controls = false;
    document.getElementById('trCanvas').hidden = true;

    var idle = document.getElementById('trRecIdle');
    idle.hidden = false;
    idle.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Asking the browser…';

    document.getElementById('trRecSize').textContent = '';
    document.getElementById('trRecAgain').hidden = true;
    document.getElementById('trRecUse').hidden = true;
    document.getElementById('trRecStart').hidden = false;
    document.getElementById('trRecStart').disabled = true;
    document.getElementById('trBar').hidden = true;

    trReady = false;
    trStage = null;
}

function trSetCorner(corner) {
    trCorner = corner;

    document.querySelectorAll('.tr-corners button').forEach(function (b) {
        b.classList.toggle('is-on', b.dataset.corner === corner);
    });
}

function trSetBubbleSize(size) {
    trBubble = size;

    document.querySelectorAll('.tr-sizes button').forEach(function (b) {
        b.classList.toggle('is-on', b.dataset.size === size);
    });
}

/* Taking yourself off the screen, before or during. */
function trToggleBubble() {
    trBubbleOn = !trBubbleOn;

    var inPanel = document.getElementById('trBubbleToggle');
    var inBar = document.getElementById('trBarBubble');

    inPanel.classList.toggle('is-off', !trBubbleOn);
    inPanel.innerHTML = trBubbleOn
        ? '<i class="fas fa-video"></i> On — hide me'
        : '<i class="fas fa-video-slash"></i> Off — show me';

    // The one in the dock is an icon on its own, so the state is the icon and
    // the colour, and the words are in the tooltip.
    inBar.classList.toggle('is-off', !trBubbleOn);
    inBar.innerHTML = trBubbleOn
        ? '<i class="fas fa-video"></i>'
        : '<i class="fas fa-video-slash"></i>';
    inBar.title = trBubbleOn ? 'Take yourself off the recording' : 'Put yourself back on the recording';

    // No camera on screen while it is off; what is recorded has none either.
    var dockCam = document.getElementById('trBarCam');
    if (dockCam && !document.getElementById('trBar').hidden && trMode === 'both') {
        dockCam.style.visibility = trBubbleOn ? '' : 'hidden';
    }
}

/* ---- keeping the recording alive --------------------------------------- */

/*
 * Is there something running that would be lost?
 *
 * Paused counts: a paused recording is a recording with everything still in it.
 */
function trRecordingInProgress() {
    return !!trRecorder && (trRecorder.state === 'recording' || trRecorder.state === 'paused');
}

(function () {
    var told = false;

    /*
     * A link, while recording, opens beside this page instead of replacing it.
     *
     * Only ordinary in-app links: anything the browser already treats as a new
     * context, a download, or a modified click is left exactly as it was.
     */
    document.addEventListener('click', function (e) {
        if (!trRecordingInProgress()) return;

        var link = e.target.closest('a[href]');
        if (!link) return;

        if (link.target === '_blank' || link.hasAttribute('download')) return;
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;

        var href = link.getAttribute('href') || '';
        if (!href || href.charAt(0) === '#' || /^(mailto:|tel:|javascript:)/i.test(href)) return;

        // Somewhere else entirely is the browser's business, not ours.
        var url;
        try { url = new URL(link.href, window.location.href); } catch (err) { return; }
        if (url.origin !== window.location.origin) return;

        e.preventDefault();
        window.open(link.href, '_blank', 'noopener');

        if (!told && typeof showToast === 'function') {
            told = true;
            showToast('Still recording',
                      'This page is holding the recording, so links open in a new tab. Move around there; come back here to stop.',
                      'info');
        }
    }, true);

    /*
     * A form that would navigate does the same, so a filter or a search in the
     * middle of a walkthrough does not end the take.
     */
    document.addEventListener('submit', function (e) {
        if (!trRecordingInProgress()) return;

        var form = e.target;
        if (!form || form.target === '_blank') return;
        // The studio's own forms post with fetch and never navigate.
        if (form.closest('#trStudio')) return;

        form.target = '_blank';
    }, true);

    /* Everything else that would unload the page. */
    window.addEventListener('beforeunload', function (e) {
        if (!trRecordingInProgress()) return;
        e.preventDefault();
        // Chrome shows its own wording; returning a string is what asks for it.
        e.returnValue = 'A recording is still running. Leaving now throws it away.';
        return e.returnValue;
    });
})();

/* ---- recording ---------------------------------------------------------- */

async function trStartRecording() {
    if (!trReady && !(await trPrepare())) return;

    trChunks = [];
    trBlob = null;

    var wanted = trMode === 'voice'
        ? ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4']
        : ['video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm', 'video/mp4'];

    var type = wanted.find(function (t) { return window.MediaRecorder && MediaRecorder.isTypeSupported(t); });

    var settings = type ? { mimeType: type } : undefined;
    if (settings && trMode !== 'voice') settings.videoBitsPerSecond = 2500000;

    trRecorder = new MediaRecorder(trStage, settings);

    trRecorder.ondataavailable = function (e) { if (e.data && e.data.size) trChunks.push(e.data); };

    trRecorder.onstop = function () {
        trBlob = new Blob(trChunks, { type: (trRecorder.mimeType || 'video/webm').split(';')[0] });

        // Back to the big panel, playing back what was just recorded.
        var preview = document.getElementById('trPreview');

        document.getElementById('trStudio').hidden = false;
        document.getElementById('trBar').hidden = true;
        document.body.style.overflow = 'hidden';

        document.getElementById('trCanvas').hidden = true;
        document.getElementById('trLevels').hidden = true;
        preview.hidden = false;
        preview.srcObject = null;
        preview.src = URL.createObjectURL(trBlob);
        preview.controls = true;
        preview.muted = false;

        document.getElementById('trRecSize').textContent = 'Recorded — ' + trSize(trBlob.size);
        document.getElementById('trRecUse').hidden = false;
        document.getElementById('trRecAgain').hidden = false;
        document.getElementById('trRecStart').hidden = true;
    };

    // A second of video at a time, so a long recording is never held whole in
    // memory and a crash costs a second rather than everything.
    trRecorder.start(1000);

    // Out of the way: recording the CRM is pointless with a panel over it.
    document.getElementById('trStudio').hidden = true;
    document.getElementById('trBar').hidden = false;
    document.body.style.overflow = '';

    // The bubble on screen is the same camera the recording draws into its own
    // bubble, so what you see is where you are in the frame.
    trShowBarPreview();

    trPausedMs = 0;
    trPausedAt = null;
    trStartedAt = Date.now();

    trTicker = setInterval(trTickClock, 500);
    trTickClock();
}

/* The clock counts recorded time, so it stands still while paused. */
function trTickClock() {
    var frozen = trPausedAt ? (Date.now() - trPausedAt) : 0;
    var seconds = Math.floor((Date.now() - trStartedAt - trPausedMs - frozen) / 1000);
    if (seconds < 0) seconds = 0;

    document.getElementById('trRecTime').textContent =
        Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0');
}

/*
 * The live picture in the bar.
 *
 * It plays the same MediaStream the recorder is reading, so there is no second
 * capture and no extra cost — one stream, two consumers.
 */
function trShowBarPreview() {
    var cam = document.getElementById('trBarCam');
    if (!cam) return;

    // The bubble is you, so it shows the camera. A screen-only recording has
    // nobody to show and a voice note has no picture at all: in both cases the
    // controls stand on their own.
    var faceTracks = trCamera && trCamera.getVideoTracks ? trCamera.getVideoTracks() : [];
    if (!faceTracks.length) { cam.hidden = true; cam.srcObject = null; return; }

    cam.srcObject = new MediaStream(faceTracks);
    cam.hidden = false;
    cam.style.visibility = trBubbleOn ? '' : 'hidden';
    cam.play().catch(function () { /* autoplay is muted, so this rarely refuses */ });
}

/*
 * Pause and carry on.
 *
 * MediaRecorder keeps the chunks it already has and simply stops adding to
 * them, so what comes back at the end is one file with the pause cut out of it.
 */
function trPauseRecording() {
    if (!trRecorder) return;

    var bar = document.getElementById('trBar');
    var button = document.getElementById('trRecPause');

    if (trRecorder.state === 'recording') {
        trRecorder.pause();
        trPausedAt = Date.now();
        bar.classList.add('is-paused');
        button.classList.add('is-resume');
        button.innerHTML = '<i class="fas fa-play"></i>';
        button.title = 'Resume — carry on recording';
        button.setAttribute('aria-label', 'Resume');
        return;
    }

    if (trRecorder.state === 'paused') {
        trRecorder.resume();
        if (trPausedAt) { trPausedMs += Date.now() - trPausedAt; trPausedAt = null; }
        bar.classList.remove('is-paused');
        button.classList.remove('is-resume');
        button.innerHTML = '<i class="fas fa-pause"></i>';
        button.title = 'Pause — the recording carries on from here';
        button.setAttribute('aria-label', 'Pause');
    }
}

/*
 * What the recorder is handed.
 *
 * One source on its own is recorded as it is — the browser does that better
 * than we can. Screen and camera together have to be drawn into one picture,
 * which is the only case that needs a canvas, and the only case where moving to
 * another tab could stop the picture updating.
 */
function trBuildStage() {
    var canvas = document.getElementById('trCanvas');
    var preview = document.getElementById('trPreview');

    if (trMode === 'voice') {
        trStartMeter(trCamera);
        return trCamera;
    }

    if (trMode === 'camera') {
        canvas.hidden = true;
        preview.hidden = false;
        preview.srcObject = trCamera;
        preview.controls = false;
        preview.muted = true;
        preview.play();

        return trCamera;
    }

    if (trMode === 'screen') {
        canvas.hidden = true;
        preview.hidden = false;
        preview.srcObject = trScreen;
        preview.controls = false;
        preview.muted = true;
        preview.play();

        // The screen's picture with the voice over it.
        var screenOnly = new MediaStream(trScreen.getVideoTracks());
        var voice = trMixAudio();
        if (voice) screenOnly.addTrack(voice);

        return screenOnly;
    }

    return trComposite();
}

/** Bars that move with the voice, so a voice note is not a black rectangle. */
function trStartMeter(stream) {
    var bars = document.getElementById('trLevels');
    bars.hidden = false;

    try {
        var context = new (window.AudioContext || window.webkitAudioContext)();
        var analyser = context.createAnalyser();
        analyser.fftSize = 64;

        context.createMediaStreamSource(stream).connect(analyser);

        var data = new Uint8Array(analyser.frequencyBinCount);
        var all = bars.querySelectorAll('i');

        trMeter = {
            context: context,
            timer: setInterval(function () {
                analyser.getByteFrequencyData(data);

                all.forEach(function (bar, i) {
                    var value = data[Math.min(i, data.length - 1)] / 255;
                    bar.style.height = Math.max(8, Math.round(value * 100)) + '%';
                });
            }, 80),
        };
    } catch (e) { /* no meter: the recording is what matters */ }
}

function trStopMeter() {
    if (!trMeter) return;

    clearInterval(trMeter.timer);
    if (trMeter.context) trMeter.context.close();
    trMeter = null;
}

/** Screen and face in one picture, with the face where they put it. */
function trComposite() {
    var canvas = document.getElementById('trCanvas');
    var preview = document.getElementById('trPreview');

    var screenVideo = document.createElement('video');
    screenVideo.srcObject = trScreen;
    screenVideo.muted = true;
    screenVideo.playsInline = true;
    screenVideo.play();

    var faceVideo = document.createElement('video');
    faceVideo.srcObject = new MediaStream(trCamera.getVideoTracks());
    faceVideo.muted = true;
    faceVideo.playsInline = true;
    faceVideo.play();

    var settings = trScreen.getVideoTracks()[0].getSettings();
    canvas.width = Math.min(settings.width || 1280, 1920);
    canvas.height = Math.min(settings.height || 720, 1080);

    canvas.hidden = false;
    preview.hidden = true;

    var ctx = canvas.getContext('2d');

    function frame() {
        if (!trScreen) return;

        // Counted so that "is it still drawing while they are away" is a
        // question with an answer rather than a guess.
        window.trFrames = (window.trFrames || 0) + 1;

        try {
            ctx.fillStyle = '#000';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            if (screenVideo.videoWidth) {
                ctx.drawImage(screenVideo, 0, 0, canvas.width, canvas.height);
            }

            if (trBubbleOn && faceVideo.videoWidth) {
                trDrawFace(ctx, faceVideo, canvas);
            }
        } catch (e) { /* a frame that cannot be drawn is not worth stopping for */ }
    }

    trStartDrawing(frame);

    var mixed = new MediaStream(canvas.captureStream(30).getVideoTracks());
    var voice = trMixAudio();
    if (voice) mixed.addTrack(voice);

    return mixed;
}

/**
 * The face, as a round bubble in whichever corner was chosen.
 *
 * A circle rather than a box: it takes less of the screen it is sitting on, it
 * has no corner that can line up wrongly with the window behind it, and it is
 * what the picker in the panel shows, so what is set is what is recorded.
 */
function trDrawFace(ctx, video, canvas) {
    var scale = { s: 0.13, m: 0.18, l: 0.25 }[trBubble] || 0.18;

    // The circle is measured against the shorter side, so a wide screen does
    // not get a bubble half its height.
    var size = Math.round(Math.min(canvas.width, canvas.height) * scale);
    var pad = Math.round(canvas.width * 0.018);

    var x = trCorner === 'tl' || trCorner === 'bl' ? pad : canvas.width - size - pad;
    var y = trCorner === 'tl' || trCorner === 'tr' ? pad : canvas.height - size - pad;

    var cx = x + size / 2;
    var cy = y + size / 2;
    var radius = size / 2;

    ctx.save();

    // A shadow under it, so the face reads as sitting on top of the screen
    // rather than being part of it.
    ctx.shadowColor = 'rgba(0, 0, 0, 0.45)';
    ctx.shadowBlur = Math.round(size * 0.08);
    ctx.shadowOffsetY = Math.round(size * 0.02);

    ctx.beginPath();
    ctx.arc(cx, cy, radius, 0, Math.PI * 2);
    ctx.fillStyle = '#000';
    ctx.fill();

    ctx.shadowColor = 'transparent';
    ctx.clip();

    // Fill the circle without squashing the picture: crop what does not fit.
    var ratio = Math.max(size / video.videoWidth, size / video.videoHeight);
    var drawWidth = video.videoWidth * ratio;
    var drawHeight = video.videoHeight * ratio;

    ctx.drawImage(video, cx - drawWidth / 2, cy - drawHeight / 2, drawWidth, drawHeight);

    ctx.restore();

    ctx.save();
    ctx.beginPath();
    ctx.arc(cx, cy, radius, 0, Math.PI * 2);
    ctx.lineWidth = Math.max(2, Math.round(size * 0.016));
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.9)';
    ctx.stroke();
    ctx.restore();
}

/*
 * Keep drawing, including while they are looking at something else.
 *
 * requestAnimationFrame stops in a tab nobody is looking at, which would freeze
 * the recording on whatever was on screen when they left — the recording keeps
 * running and records a still picture, which is worse than stopping. A worker's
 * timer keeps ticking in the background, so the frames keep coming; when the tab
 * is in front, the animation frame takes over for a smooth picture.
 */
function trStartDrawing(frame) {
    var worker = null;

    try {
        var source = 'var t=null;onmessage=function(e){' +
            'if(e.data==="go"){clearInterval(t);t=setInterval(function(){postMessage(1)},100)}' +
            'else{clearInterval(t);t=null}}';

        worker = new Worker(URL.createObjectURL(new Blob([source], { type: 'text/javascript' })));
        worker.onmessage = function () { if (document.hidden) frame(); };
        worker.postMessage('go');
    } catch (e) {
        // No worker: a plain timer still beats stopping altogether.
        var backup = setInterval(function () { if (document.hidden) frame(); }, 200);
        worker = { terminate: function () { clearInterval(backup); } };
    }

    var handle = { stop: function () { worker.terminate(); }, raf: null };

    function spin() {
        if (!trScreen) return;
        if (!document.hidden) frame();
        handle.raf = requestAnimationFrame(spin);
    }

    spin();
    trDrawing = handle;
}

/** One sound out of however many there are. */
function trMixAudio() {
    var sources = [];

    if (trScreen && trScreen.getAudioTracks().length) sources.push(new MediaStream(trScreen.getAudioTracks()));
    if (trCamera && trCamera.getAudioTracks().length) sources.push(new MediaStream(trCamera.getAudioTracks()));

    if (!sources.length) return null;
    if (sources.length === 1) return sources[0].getAudioTracks()[0];

    trAudio = new (window.AudioContext || window.webkitAudioContext)();
    var destination = trAudio.createMediaStreamDestination();

    sources.forEach(function (stream) {
        trAudio.createMediaStreamSource(stream).connect(destination);
    });

    return destination.stream.getAudioTracks()[0];
}

function trStopRecording(quietly) {
    // A paused recorder is still a recording in progress; stopping it gives
    // back everything recorded up to the pause.
    var wasRecording = trRecorder && (trRecorder.state === 'recording' || trRecorder.state === 'paused');

    if (trRecorder && trRecorder.state !== 'inactive') trRecorder.stop();

    var bar = document.getElementById('trBar');
    bar.hidden = true;
    bar.classList.remove('is-paused');

    // The bar is reused by the next recording, so it goes back to how it starts.
    var pause = document.getElementById('trRecPause');
    if (pause) {
        pause.classList.remove('is-resume');
        pause.innerHTML = '<i class="fas fa-pause"></i>';
        pause.title = 'Pause — the recording carries on from here';
    }

    var cam = document.getElementById('trBarCam');
    if (cam) { cam.srcObject = null; cam.hidden = true; }

    trPausedAt = null;
    trPausedMs = 0;

    if (trDrawing) {
        if (trDrawing.stop) trDrawing.stop();
        if (trDrawing.raf) cancelAnimationFrame(trDrawing.raf);
        trDrawing = null;
    }

    if (trTicker) { clearInterval(trTicker); trTicker = null; }

    trStopMeter();
    trCleanupStreams();

    // onstop puts the panel back with the playback on it; there is nothing to
    // put back when it was never recording.
    if (!wasRecording && !quietly) {
        document.getElementById('trRecStart').hidden = false;
    }
}

function trCleanupStreams() {
    [trScreen, trCamera, trStream].forEach(function (stream) {
        if (stream && stream.getTracks) stream.getTracks().forEach(function (t) { t.stop(); });
    });

    if (trAudio) { trAudio.close(); trAudio = null; }

    trScreen = null;
    trCamera = null;
    trStream = null;
}

async function trRecordAgain() {
    trBlob = null;
    trChunks = [];

    trCleanupStreams();
    trResetStudio();

    document.getElementById('trLevels').hidden = trMode !== 'voice';
    document.getElementById('trPreview').hidden = trMode === 'voice';

    // Everything was let go when the last one stopped, so it is asked for again
    // and the preview comes back live.
    await trPrepare();
}

/* Half a megabyte at a time, with the bar moving as it goes. */
async function trSendInPieces(blob) {
    var CHUNK = 512 * 1024;
    var total = Math.max(1, Math.ceil(blob.size / CHUNK));
    var uploadId = (Date.now().toString(36) + Math.random().toString(36).slice(2)).replace(/[^a-z0-9]/g, '');
    var extension = (blob.type || '').indexOf('mp4') > -1 ? 'mp4'
        : ((blob.name || '').split('.').pop() || 'webm').toLowerCase().replace(/[^a-z0-9]/g, '') || 'webm';

    var progress = document.getElementById('trProgress');
    var fill = document.getElementById('trProgressFill');
    var text = document.getElementById('trProgressText');

    progress.hidden = false;

    for (var i = 0; i < total; i++) {
        var slice = blob.slice(i * CHUNK, (i + 1) * CHUNK);
        var form = new FormData();

        form.append('upload_id', uploadId);
        form.append('index', i);
        form.append('total', total);
        form.append('extension', extension);
        form.append('chunk', slice, 'part');

        var res = await fetch('{{ route('admin.training.chunk') }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': trToken, 'Accept': 'application/json' },
            body: form,
        });

        if (!res.ok) {
            var problem = await res.json().catch(function () { return {}; });
            progress.hidden = true;
            throw new Error(problem.message || 'The upload stopped partway.');
        }

        var done = Math.round(((i + 1) / total) * 100);
        fill.style.width = done + '%';
        text.textContent = done + '% — ' + trSize(Math.min(blob.size, (i + 1) * CHUNK)) + ' of ' + trSize(blob.size);

        var data = await res.json();

        if (data.path) {
            progress.hidden = true;
            return data;
        }
    }

    progress.hidden = true;
    throw new Error('The upload finished without a file.');
}

/*
 * Putting it up: anything to send goes first, and the form follows with the
 * path it was given.
 */
async function trSubmit(e) {
    var form = document.getElementById('trForm');
    var button = form.querySelector('button[type=submit]');

    if (trKindNow === 'text') return true;                   // nothing to send
    if (trHowNow === 'link') return true;                    // nothing to send either
    if (!trBlob) return true;                                // let the server say what is missing

    e.preventDefault();
    button.disabled = true;

    try {
        var sent = await trSendInPieces(trBlob);

        document.getElementById('trUploadedPath').value = sent.path;
        document.getElementById('trUploadedMime').value = sent.mime || '';

        // The file is already up; the form must not carry it a second time.
        document.getElementById('trMedia').value = '';

        form.submit();
    } catch (err) {
        button.disabled = false;

        if (typeof showToast === 'function') showToast('Training', err.message, 'error');
        else alert(err.message);
    }

    return false;
}

function trOpen(id) {
    trOpenId = id;

    document.querySelectorAll('.tr-item').forEach(function (el) {
        el.classList.toggle('is-open', String(el.dataset.id) === String(id));
    });

    document.getElementById('trNothingOpen').hidden = true;
    document.getElementById('trOpenPanel').hidden = false;
    document.getElementById('trPlayer').innerHTML =
        '<div class="tr-empty"><i class="fas fa-spinner fa-spin"></i></div>';

    fetch('{{ url('admin/training') }}/' + id, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
    .then(function (res) { return res.ok ? res.json() : null; })
    .then(function (data) {
        if (!data) return;

        var t = data.training;

        document.getElementById('trOpenTitle').textContent = t.title;
        document.getElementById('trOpenMeta').textContent =
            t.author + ' · ' + t.ago + (t.department ? ' · for ' + t.department : '') +
            (trCanManage ? ' · ' + t.watched_by + ' watched' : '');

        document.getElementById('trPlayer').innerHTML = trPlayerHtml(t);
        document.getElementById('trBodyOut').innerHTML = t.body_html || '';

        trRenderComments(data.comments);

        // The one on the list has now been watched.
        var item = document.querySelector('.tr-item[data-id="' + id + '"]');
        if (item) item.classList.add('is-watched');

        if (trCanManage) trLoadWatchers(id);

        history.replaceState(null, '', '{{ route('admin.training') }}?open=' + id);
    })
    .catch(function () {
        document.getElementById('trPlayer').innerHTML = '<div class="tr-empty">That could not be opened.</div>';
    });
}

/*
 * What to put on the stage. A file the browser can play gets the browser's own
 * player; a link to somewhere else gets that place's embed; anything left is a
 * written piece, which needs no player at all.
 */
function trPlayerHtml(t) {
    if (t.is_file && t.kind === 'voice') {
        return '<div class="tr-player is-audio"><audio controls preload="metadata" src="' + trEscape(t.media_src) + '"></audio></div>';
    }

    if (t.is_file) {
        return '<div class="tr-player"><video controls preload="metadata" src="' + trEscape(t.media_src) + '"></video></div>';
    }

    if (t.embed_url) {
        return '<div class="tr-player"><iframe src="' + trEscape(t.embed_url) +
               '" allow="accelerometer; autoplay; clipboard-write; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe></div>';
    }

    if (t.media_src) {
        return '<div class="tr-player is-audio"><a href="' + trEscape(t.media_src) +
               '" target="_blank" rel="noopener" class="btn btn-primary" style="text-decoration: none;">' +
               '<i class="fas fa-external-link-alt"></i> Open the recording</a></div>';
    }

    return '';
}

function trRenderComments(comments) {
    var box = document.getElementById('trComments');
    document.getElementById('trCommentCount').textContent = comments.length ? '(' + comments.length + ')' : '';

    if (!comments.length) {
        box.innerHTML = '<div class="empty-state" style="padding: 1.6rem 1rem;">' +
                        '<i class="fas fa-comment-dots"></i>' +
                        '<strong>No questions yet</strong>' +
                        '<p>Be the first to ask about this one.</p></div>';
        return;
    }

    box.innerHTML = comments.map(trCommentHtml).join('');
}

function trCommentHtml(c) {
    var initials = (c.author || '?').split(' ').map(function (w) { return w[0]; }).slice(0, 2).join('').toUpperCase();

    return '<div class="tr-comment" data-id="' + c.id + '">' +
        '<div class="tr-avatar">' + trEscape(initials) + '</div>' +
        '<div class="tr-comment-body">' +
            '<b>' + trEscape(c.author) + '</b><small>' + trEscape(c.ago) + '</small>' +
            '<p>' + trEscape(c.body) + '</p>' +
        '</div>' +
        (c.can_remove
            ? '<button type="button" class="tr-comment-remove" onclick="trRemoveComment(' + c.id + ')"><i class="fas fa-xmark"></i></button>'
            : '') +
        '</div>';
}

function trComment(e) {
    e.preventDefault();

    var field = document.getElementById('trAskBody');
    var body = field.value.trim();
    if (!body || !trOpenId) return;

    field.disabled = true;

    fetch('{{ url('admin/training') }}/' + trOpenId + '/comments', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': trToken },
        body: JSON.stringify({ body: body }),
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
        field.disabled = false;

        if (!data.success) {
            if (typeof showToast === 'function') showToast('Training', data.message || 'That did not post.', 'error');
            return;
        }

        field.value = '';

        var box = document.getElementById('trComments');
        if (box.querySelector('div[style*="italic"]')) box.innerHTML = '';
        box.insertAdjacentHTML('beforeend', trCommentHtml(data.comment));

        var count = box.querySelectorAll('.tr-comment').length;
        document.getElementById('trCommentCount').textContent = '(' + count + ')';
    })
    .catch(function () { field.disabled = false; });
}

function trRemoveComment(id) {
    if (!confirm('Remove this comment?')) return;

    fetch('{{ url('admin/training/comments') }}/' + id, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': trToken },
    })
    .then(function (res) {
        if (!res.ok) return;
        var el = document.querySelector('.tr-comment[data-id="' + id + '"]');
        if (el) el.remove();
    });
}

function trLoadWatchers(id) {
    fetch('{{ url('admin/training') }}/' + id + '/watchers', {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' },
    })
    .then(function (res) { return res.ok ? res.json() : null; })
    .then(function (data) {
        if (!data) return;

        var out = document.getElementById('trWatchers');
        if (!out) return;

        out.innerHTML = '<b>Watched by:</b> ' +
            (data.watched.length ? data.watched.map(function (w) { return trEscape(w.name); }).join(', ') : 'nobody yet') +
            (data.not_yet.length
                ? '<br><b>Still to watch:</b> ' + data.not_yet.map(function (w) { return trEscape(w.name); }).join(', ')
                : '');
    });
}

document.addEventListener('DOMContentLoaded', function () {

    if (trOpenId) trOpen(trOpenId);

    // Something was refused: the form opens again with the reason showing.
    if (document.querySelector('#trForm [style*="--danger"]')) trToggleForm();

    var putUp = document.getElementById('trForm');
    if (putUp) putUp.addEventListener('submit', trSubmit);

    // Leaving the page mid-recording would otherwise leave the camera light on.
    window.addEventListener('beforeunload', function () { trStopRecording(true); });

    /*
     * The recording bar can be moved: it sits bottom left, which is exactly
     * where a sidebar or a chat bubble tends to be, and whoever is recording
     * knows better than we do what is in their way.
     */
    (function () {
        var bar = document.getElementById('trBar');
        var grip = document.getElementById('trBarGrip');
        if (!bar || !grip) return;

        var dragging = false;
        var offsetX = 0;
        var offsetY = 0;

        function onDown(handle, e) {
            dragging = true;
            var box = bar.getBoundingClientRect();
            offsetX = e.clientX - box.left;
            offsetY = e.clientY - box.top;
            handle.setPointerCapture(e.pointerId);
            e.preventDefault();
        }

        function onMove(e) {
            if (!dragging) return;

            var box = bar.getBoundingClientRect();
            var left = Math.min(Math.max(0, e.clientX - offsetX), window.innerWidth - box.width);
            var top = Math.min(Math.max(0, e.clientY - offsetY), window.innerHeight - box.height);

            bar.style.left = left + 'px';
            bar.style.top = top + 'px';
            bar.style.bottom = 'auto';
        }

        // Two handles, one behaviour: the little grip, and the bubble itself,
        // which is the thing people reach for.
        [grip, document.getElementById('trBarCam')].forEach(function (handle) {
            if (!handle) return;
            handle.addEventListener('pointerdown', function (e) { onDown(handle, e); });
            handle.addEventListener('pointermove', onMove);
            handle.addEventListener('pointerup', function () { dragging = false; });
        });
    })();

    var remove = document.getElementById('trRemove');
    if (remove) {
        remove.addEventListener('click', function () {
            if (!trOpenId || !confirm('Take this training down for everybody?')) return;

            fetch('{{ url('admin/training') }}/' + trOpenId, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': trToken },
            }).then(function () { window.location = '{{ route('admin.training') }}'; });
        });
    }

    /*
     * New training, and new questions on what is open, arrive on their own. The
     * list asks every ten seconds; the open piece's comments come with it, so a
     * conversation does not need refreshing to follow.
     */
    var known = new Set([].map.call(document.querySelectorAll('.tr-item'), function (el) { return String(el.dataset.id); }));

    function poll() {
        fetch('{{ route('admin.training') }}', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
        .then(function (res) { return res.ok ? res.json() : null; })
        .then(function (data) {
            if (!data || !data.trainings) return;

            var list = document.getElementById('trList');
            var empty = document.getElementById('trEmpty');

            data.trainings.slice().reverse().forEach(function (t) {
                if (known.has(String(t.id))) return;
                known.add(String(t.id));

                if (empty) empty.remove();

                list.insertAdjacentHTML('afterbegin',
                    '<div class="tr-item" data-id="' + t.id + '" onclick="trOpen(' + t.id + ')">' +
                        '<b>' + (t.pinned ? '<i class="fas fa-thumbtack" style="color: var(--primary);"></i> ' : '') + trEscape(t.title) + '</b>' +
                        '<div class="tr-meta">' +
                            '<span class="tr-kind ' + trEscape(t.kind) + '">' + trEscape(t.kind_label) + '</span>' +
                            '<span>' + trEscape(t.author) + '</span><span>&middot; just now</span>' +
                        '</div>' +
                    '</div>');

                if (typeof showToast === 'function') {
                    showToast('New training', t.title, 'info');
                }
            });

            document.getElementById('trCount').textContent = data.trainings.length;
        })
        .catch(function () { /* the next tick will try again */ });

        if (trOpenId) {
            fetch('{{ url('admin/training') }}/' + trOpenId + '/comments', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            })
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(function (data) {
                if (!data) return;

                var showing = document.querySelectorAll('#trComments .tr-comment').length;
                if (data.comments.length !== showing) trRenderComments(data.comments);
            })
            .catch(function () { /* nothing to do */ });
        }
    }

    setInterval(poll, 10000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
});
</script>
@endsection
