@extends('layouts.admin')

@section('title', 'Team Chat')

@push('styles')
<style>
    /* Modern Chat Layout */
    .chat-container {
        display: flex;
        height: calc(100vh - 130px);
        background: rgba(255, 255, 255, 0.5);
        /* No backdrop-filter on the chat surfaces. They sit on a flat page colour,
           and blurring a flat colour gives the same flat colour — so it cost a lot
           and showed nothing. Measured: removing the blurs took the chat page from
           about 25 to 55 frames a second on an ordinary laptop, idle and typing. */
        border: 1px solid rgba(255, 255, 255, 0.8);
        border-radius: 24px;
        overflow: hidden;
        margin-top: 10px;
        box-shadow: 0 15px 40px rgba(0,0,0,0.08), 0 5px 15px rgba(249, 157, 78, 0.05);
        font-family: 'Outfit', sans-serif;
        transition: all 0.3s ease;
    }
    html.dark-mode .chat-container {
        background: rgba(15, 21, 40, 0.7);
        box-shadow: 0 15px 40px rgba(0,0,0,0.3);
        border: 1px solid rgba(255,255,255,0.05);
    }
    
    /* Sidebar Styling */
    .chat-sidebar {
        width: 320px;
        background: rgba(255, 255, 255, 0.4);
        border-right: 1px solid rgba(255, 255, 255, 0.5);
        display: flex;
        flex-direction: column;
        transition: 0.3s ease;
    }
    html.dark-mode .chat-sidebar {
        background: rgba(0, 0, 0, 0.2);
        border-right: 1px solid rgba(255, 255, 255, 0.05);
    }
    .chat-sidebar-header {
        padding: 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: linear-gradient(135deg, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0.0) 100%);
        border-bottom: 1px solid rgba(255, 255, 255, 0.5);
    }
    html.dark-mode .chat-sidebar-header {
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }
    .chat-sidebar-header h1 {
        font-size: 1.5rem;
        font-weight: 800;
        margin: 0;
        background: var(--primary-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    .chat-sidebar-header button {
        background: var(--primary-gradient);
        border: none;
        color: white;
        cursor: pointer;
        font-size: 1rem;
        width: 38px;
        height: 38px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        box-shadow: 0 4px 15px rgba(233, 59, 110, 0.3);
    }
    .chat-sidebar-header button:hover {
        transform: scale(1.1) rotate(90deg);
        box-shadow: 0 6px 20px rgba(233, 59, 110, 0.4);
    }
    .chat-sidebar-content {
        flex: 1;
        overflow-y: auto;
        padding: 1rem;
    }
    .chat-sidebar-content::-webkit-scrollbar { width: 4px; }
    .chat-sidebar-content::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.1); border-radius: 10px; }
    html.dark-mode .chat-sidebar-content::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); }
    
    .chat-section-title {
        font-size: 0.75rem;
        font-weight: 800;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 1.5px;
        margin-bottom: 0.8rem;
        margin-top: 1.5rem;
        padding-left: 0.5rem;
    }
    .chat-section-title:first-child { margin-top: 0.5rem; }
    
    .chat-sidebar-content ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .chat-channel-btn {
        width: 100%;
        text-align: left;
        padding: 0.8rem 1rem;
        border-radius: 14px;
        color: var(--text-main);
        background: transparent;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 1rem;
        font-size: 0.95rem;
        font-weight: 600;
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        margin-bottom: 0.4rem;
        position: relative;
        overflow: hidden;
    }
    .chat-channel-btn::before {
        content: '';
        position: absolute;
        top: 0; left: 0; width: 100%; height: 100%;
        background: linear-gradient(90deg, rgba(249, 157, 78, 0.1), rgba(233, 59, 110, 0.1));
        transform: translateX(-100%);
        transition: all 0.4s ease;
        z-index: 0;
    }
    .chat-channel-btn:hover::before {
        transform: translateX(0);
    }
    .chat-channel-btn > * {
        position: relative;
        z-index: 1;
    }
    .chat-channel-btn:hover {
        color: var(--primary);
        transform: translateX(6px);
    }
    html.dark-mode .chat-channel-btn::before {
        background: linear-gradient(90deg, rgba(249, 157, 78, 0.15), rgba(233, 59, 110, 0.15));
    }
    
    /* ===== Unread conversations =====
     * The poll has always added a .unread-badge with the count, but nothing ever
     * styled it, so the number sat in the row as plain text that was easy to
     * miss. A conversation with unread messages now shows a green count and a
     * green tint; both clear as soon as it is opened. */
    .unread-badge {
        /* inline-flex, not the span's default inline: an inline box ignores
           width and height, which squashed the badge to a sliver of text. */
        display: inline-flex; align-items: center; justify-content: center;
        box-sizing: border-box;
        margin-left: auto;
        min-width: 22px; height: 22px; padding: 0 7px;
        border-radius: 999px;
        background: #10b981; color: #fff;
        font-size: 0.75rem; font-weight: 700; line-height: 1; text-align: center;
        box-shadow: 0 2px 8px rgba(16, 185, 129, 0.45);
        flex-shrink: 0;
        animation: unreadPop 0.25s ease-out;
    }
    @keyframes unreadPop { from { transform: scale(0.6); opacity: 0; } to { transform: none; opacity: 1; } }
    .chat-channel-btn.has-unread {
        background: rgba(16, 185, 129, 0.10);
        color: var(--text-main);
        font-weight: 700;
    }
    html.dark-mode .chat-channel-btn.has-unread { background: rgba(16, 185, 129, 0.14); }

    .status-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        flex-shrink: 0;
        border: 2px solid #fff;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        transition: 0.3s;
    }
    html.dark-mode .status-dot { border: 2px solid var(--bg-card); }
    .status-online { background: #10b981; box-shadow: 0 0 8px rgba(16, 185, 129, 0.5); }
    .status-offline { background: var(--danger); opacity: 0.9; box-shadow: 0 0 8px rgba(239, 68, 68, 0.4); }
    
    /* Main Chat Area */
    .chat-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        background: transparent;
        position: relative;
    }
    .chat-header {
        height: 75px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.5);
        display: flex;
        align-items: center;
        padding: 0 2rem;
        font-weight: 800;
        color: var(--text-main);
        font-size: 1.25rem;
        background: rgba(255, 255, 255, 0.3);
        z-index: 10;
    }
    html.dark-mode .chat-header {
        background: rgba(0, 0, 0, 0.2);
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }
    
    .chat-actions-btn {
        background: rgba(255,255,255,0.5);
        color: var(--text-main);
        border: 1px solid rgba(255,255,255,0.8);
        width: 40px;
        height: 40px;
        border-radius: 12px;
        cursor: pointer;
        font-size: 1.1rem;
        margin-left: 0.8rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    }
    html.dark-mode .chat-actions-btn {
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.1);
    }
    .chat-actions-btn:hover { 
        background: var(--primary-gradient);
        color: white;
        border-color: transparent;
        transform: scale(1.1) translateY(-2px); 
        box-shadow: 0 6px 15px rgba(249, 157, 78, 0.3);
    }
    .chat-actions-btn.delete:hover { 
        background: var(--danger); 
        box-shadow: 0 6px 15px rgba(239, 68, 68, 0.3);
    }
    
    /* Messages Area */
    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 2rem;
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        scroll-behavior: smooth;
    }
    .chat-messages::-webkit-scrollbar { width: 6px; }
    .chat-messages::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.15); border-radius: 10px; }
    html.dark-mode .chat-messages::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); }
    
    @keyframes slideUpFade {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .msg-wrapper {
        display: flex;
        flex-direction: column;
        animation: slideUpFade 0.4s cubic-bezier(0.25, 0.8, 0.25, 1) forwards;
    }
    .msg-mine { align-items: flex-end; }
    .msg-theirs { align-items: flex-start; }
    
    .msg-meta {
        display: flex;
        /* A face has height; baseline would hang it off the bottom of the row. */
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.4rem;
        padding: 0 0.5rem;
    }
    .msg-sender { font-size: 0.85rem; font-weight: 700; color: var(--text-main); }
    .msg-time { font-size: 0.75rem; color: var(--text-muted); font-weight: 600;}
    
    .msg-bubble {
        padding: 1rem 1.5rem;
        border-radius: 20px;
        max-width: 28rem;
        font-size: 1rem;
        line-height: 1.5;
        box-shadow: 0 8px 24px rgba(0,0,0,0.06);
        position: relative;
    }
    .msg-mine .msg-bubble {
        background: var(--primary-gradient);
        color: #fff;
        border-bottom-right-radius: 4px;
        box-shadow: 0 8px 25px rgba(233, 59, 110, 0.25);
    }
    .msg-theirs .msg-bubble {
        background: rgba(255, 255, 255, 0.8);
        color: var(--text-main);
        border-bottom-left-radius: 4px;
        border: 1px solid rgba(255,255,255,0.9);
    }
    html.dark-mode .msg-theirs .msg-bubble {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255,255,255,0.1);
        color: rgba(255,255,255,0.9);
    }
    
    .chat-empty {
        text-align: center;
        color: var(--text-muted);
        margin: auto;
        font-size: 1.2rem;
        font-weight: 600;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1.2rem;
        animation: slideUpFade 0.6s ease forwards;
    }
    .chat-empty i {
        font-size: 4rem;
        background: var(--primary-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        filter: drop-shadow(0 10px 15px rgba(249, 157, 78, 0.3));
    }
    
    /* Input Area */
    /* Waiting for a conversation to arrive. */
    .chat-loading { padding: 1.2rem 1.4rem; display: flex; flex-direction: column; gap: 1.1rem; }
    /* A definite width, so the percentage widths on the rows inside resolve
       against something rather than collapsing to nothing. */
    .chat-skel { display: flex; flex-direction: column; gap: 7px; align-items: flex-start; width: min(60%, 420px); }
    .chat-skel.is-mine { align-self: flex-end; align-items: flex-end; }
    .chat-skel .skeleton { height: 13px; display: block; min-width: 70px; }

    .chat-input-area {
        padding: 1.5rem 2rem;
        background: rgba(255, 255, 255, 0.3);
        border-top: 1px solid rgba(255,255,255,0.5);
        display: none;
    }
    html.dark-mode .chat-input-area {
        background: rgba(0, 0, 0, 0.2);
        border-top: 1px solid rgba(255,255,255,0.05);
    }
    /* ===== Voice notes =====
     * While recording, the composer steps aside and this bar takes its place,
     * in the same pill shape, so it reads as the input changing mode. */
    .chat-input-area.is-recording #messageForm,
    .chat-input-area.is-recording #attachmentPreview { display: none !important; }

    .voice-bar {
        display: flex; align-items: center; gap: 0.75rem;
        background: rgba(255, 255, 255, 0.8);
        padding: 0.5rem 0.5rem 0.5rem 0.6rem;
        border-radius: 30px;
        border: 1px solid rgba(239, 68, 68, 0.35);
        box-shadow: 0 10px 30px rgba(233, 59, 110, 0.10);
        animation: voiceIn 0.18s ease-out;
    }
    html.dark-mode .voice-bar {
        background: rgba(255, 255, 255, 0.05);
        border-color: rgba(239, 68, 68, 0.4);
    }
    @keyframes voiceIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }

    .voice-cancel {
        width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0;
        border: none; cursor: pointer; font-size: 1.05rem;
        background: rgba(239, 68, 68, 0.12); color: var(--danger);
        display: inline-flex; align-items: center; justify-content: center;
        transition: background 0.2s ease, transform 0.2s ease;
    }
    .voice-cancel:hover { background: rgba(239, 68, 68, 0.22); transform: scale(1.06); }

    .voice-dot {
        width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0;
        background: var(--danger);
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.6);
        animation: voicePulse 1.2s ease-out infinite;
    }
    @keyframes voicePulse {
        0%   { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.55); }
        70%  { box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
        100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
    }
    .voice-time {
        font-variant-numeric: tabular-nums; font-size: 0.92rem; font-weight: 700;
        color: var(--text-main); min-width: 2.6rem; flex-shrink: 0;
    }
    .voice-wave-wrap { flex: 1; min-width: 0; position: relative; height: 36px; }
    .voice-wave { width: 100%; height: 36px; display: block; }


    .voice-send {
        width: 46px; height: 46px; border-radius: 50%; flex-shrink: 0;
        border: none; cursor: pointer; font-size: 1.1rem; color: #fff;
        background: var(--primary-gradient);
        box-shadow: 0 4px 15px rgba(233, 59, 110, 0.3);
        display: inline-flex; align-items: center; justify-content: center;
        transition: transform 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .voice-send:hover { transform: scale(1.08); }
    .msg-voice { display: flex; align-items: center; gap: 10px; margin-top: 0.5rem; }
    .msg-voice audio { height: 36px; max-width: 240px; }

    /* ===== Reactions and per-message actions ===== */
    .msg-wrapper { position: relative; }
    .msg-meta { pointer-events: none; }

    .msg-actions {
        /* Inside the row's own box: at a negative offset the bar sits outside it,
         * so pointing at the bar never triggers the row's :hover and the buttons
         * stay pointer-events:none. */
        position: absolute; top: 2px; display: flex; gap: 2px;
        background: var(--bg-card); border: 1px solid var(--border);
        border-radius: 10px; padding: 2px; box-shadow: 0 4px 14px rgba(0,0,0,0.12);
        opacity: 0; pointer-events: none; transition: opacity 0.15s ease; z-index: 5;
    }
    /* Anchored inside the message row, not outside it. Hanging them off the
     * side pushed the bar past the thread's left edge, where the chat sidebar
     * sits on top of it and swallows the click. */
    .msg-mine .msg-actions { right: 6px; }
    .msg-theirs .msg-actions { left: 6px; }
    .msg-wrapper:hover .msg-actions,
    .msg-actions.is-open { opacity: 1; pointer-events: auto; }
    .msg-act {
        width: 28px; height: 28px; border: none; background: none; cursor: pointer;
        border-radius: 7px; color: var(--text-muted); font-size: 0.8rem;
        display: inline-flex; align-items: center; justify-content: center;
    }
    .msg-act:hover { background: rgba(127,127,127,0.12); color: var(--text-main); }
    .msg-act.danger:hover { background: rgba(239,68,68,0.12); color: var(--danger); }

    .react-menu {
        position: absolute; top: 34px; display: none; gap: 2px; z-index: 6;
        background: var(--bg-card); border: 1px solid var(--border);
        border-radius: 12px; padding: 4px; box-shadow: 0 6px 20px rgba(0,0,0,0.16);
    }
    .msg-mine .react-menu { right: 6px; }
    .msg-theirs .react-menu { left: 6px; }
    .react-menu.is-open { display: flex; }
    .react-pick {
        border: none; background: none; cursor: pointer; font-size: 1.05rem;
        width: 30px; height: 30px; border-radius: 8px; line-height: 1;
    }
    .react-pick:hover { background: rgba(127,127,127,0.14); transform: scale(1.15); }

    .msg-reactions { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 5px; }
    .msg-mine .msg-reactions { justify-content: flex-end; }
    .react-pill {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 2px 7px; border-radius: 999px; cursor: pointer;
        border: 1px solid var(--border); background: var(--bg-card);
        font-size: 0.78rem; color: var(--text-main); line-height: 1.5;
    }
    .react-pill.mine { border-color: var(--primary); background: rgba(249,157,78,0.14); }
    .react-pill:hover { border-color: var(--primary); }

    .msg-edited { font-size: 0.7rem; opacity: 0.7; margin-left: 6px; }
    .msg-deleted { font-style: italic; opacity: 0.65; }

    .msg-edit-box { display: flex; flex-direction: column; gap: 6px; }
    .msg-edit-box textarea {
        width: 100%; min-height: 54px; resize: vertical; border-radius: 10px;
        border: 1px solid var(--border); background: var(--bg-card);
        color: var(--text-main); padding: 8px 10px; font-family: inherit; font-size: 0.88rem;
    }
    .msg-edit-actions { display: flex; gap: 6px; justify-content: flex-end; }
    .msg-edit-actions button {
        border: none; border-radius: 8px; cursor: pointer; font-family: inherit;
        font-size: 0.78rem; font-weight: 600; padding: 0.35rem 0.7rem;
    }
    .msg-edit-save { background: var(--primary-gradient); color: #fff; }
    .msg-edit-cancel { background: rgba(127,127,127,0.15); color: var(--text-main); }

    .chat-input-form {
        display: flex;
        gap: 1rem;
        background: rgba(255, 255, 255, 0.8);
        padding: 0.5rem 0.5rem 0.5rem 1rem;
        border-radius: 30px;
        border: 1px solid rgba(255,255,255,0.9);
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        align-items: center;
        transition: 0.3s;
    }
    .chat-input-form:focus-within {
        box-shadow: 0 10px 40px rgba(249, 157, 78, 0.15);
        border-color: rgba(249, 157, 78, 0.4);
    }
    html.dark-mode .chat-input-form {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255,255,255,0.1);
    }
    .chat-input {
        flex: 1;
        padding: 0.8rem 1rem;
        border: none;
        background: transparent;
        color: var(--text-main);
        outline: none;
        font-size: 1rem;
        font-family: inherit;
        font-weight: 500;
    }
    .chat-input::placeholder { color: var(--text-muted); }
    
    .chat-send-btn {
        background: var(--primary-gradient);
        color: #fff;
        border: none;
        width: 46px;
        height: 46px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 1.1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        box-shadow: 0 4px 15px rgba(233, 59, 110, 0.3);
    }
    .chat-send-btn:hover {
        transform: scale(1.1) rotate(10deg);
        box-shadow: 0 8px 25px rgba(233, 59, 110, 0.4);
    }
    .chat-icon-btn {
        background: transparent;
        color: var(--text-muted);
        border: none;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 1.2rem;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: 0.3s;
    }
    .chat-icon-btn:hover {
        background: rgba(249, 157, 78, 0.15);
        color: var(--primary);
        transform: scale(1.1);
    }
    
    /* Attachments & Modals */
    .attachment-image {
        max-width: 100%;
        max-height: 250px;
        border-radius: 12px;
        margin-top: 0.5rem;
        display: block;
        /* No box-shadow: on a transparent PNG the shadow is drawn around the
           image's rectangle, which reads as a background the image does not have. */
        background: transparent;
        transition: transform 0.3s;
    }
    /* A message that is only a picture drops the coloured bubble, so the image
       sits on the thread exactly as it looks — transparent areas included. */
    .msg-bubble.msg-media {
        background: transparent !important;
        box-shadow: none !important;
        border: none !important;
        padding: 0 !important;
    }
    .msg-bubble.msg-media .attachment-image { margin-top: 0; }
    .msg-bubble.msg-media .msg-ticks { color: var(--text-muted) !important; }
    /* The download button sits over the picture and can hide most of a small
       one, so on devices with a mouse it only appears on hover or keyboard focus.
       Touch screens have no hover, so there it stays visible. */
    @media (hover: hover) {
        .msg-bubble .attachment-image + a[download] { opacity: 0; transition: opacity 0.2s ease; }
        .msg-bubble div:hover > .attachment-image + a[download],
        .msg-bubble .attachment-image + a[download]:focus-visible { opacity: 1; }
    }
    .attachment-image:hover { transform: scale(1.02); }
    .attachment-file {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-top: 0.5rem;
        padding: 0.8rem 1rem;
        background: rgba(255,255,255,0.4);
        border-radius: 12px;
        text-decoration: none;
        color: inherit;
        font-size: 0.9rem;
        font-weight: 600;
        transition: 0.3s;
        border: 1px solid rgba(255,255,255,0.5);
    }
    .msg-mine .attachment-file {
        background: rgba(255,255,255,0.2);
        border: 1px solid rgba(255,255,255,0.3);
    }
    .msg-theirs .attachment-file {
        background: rgba(0,0,0,0.05);
    }
    html.dark-mode .msg-theirs .attachment-file {
        background: rgba(255,255,255,0.05);
    }
    .attachment-file:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    
    #emojiPickerContainer {
        position: absolute;
        bottom: 95px;
        right: 35px;
        display: none;
        z-index: 100;
        box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        border-radius: 20px;
        overflow: hidden;
        animation: slideUpFade 0.3s ease;
    }
    
    .chat-modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.6);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }
    .chat-modal-overlay.active {
        display: flex;
        animation: fadeIn 0.3s;
    }
    .chat-modal {
        background: var(--bg-card);
        border-radius: 24px;
        width: 450px;
        padding: 2.5rem;
        box-shadow: 0 25px 50px rgba(0,0,0,0.25);
        border: 1px solid rgba(255,255,255,0.1);
        transform: translateY(20px);
        animation: slideUpFade 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    }
    .chat-modal h3 { margin-top: 0; margin-bottom: 1.5rem; font-size: 1.5rem; font-weight: 800; color: var(--text-main); }
    .form-group { margin-bottom: 1.5rem; }
    .form-group label { display: block; font-size: 0.95rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--text-main); }
    .form-group input[type="text"] { width: 100%; padding: 1rem 1.2rem; border: 1px solid var(--border); background: var(--bg-body); color: var(--text-main); border-radius: 16px; outline: none; font-family: inherit; font-size: 1rem; transition: 0.3s;}
    .form-group input[type="text"]:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(249, 157, 78, 0.15); }
    .members-list { max-height: 220px; overflow-y: auto; border: 1px solid var(--border); background: var(--bg-body); padding: 1rem; border-radius: 16px; }
    .members-list::-webkit-scrollbar { width: 4px; }
    .members-list::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.1); border-radius: 10px; }
    .member-item { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; font-size: 1rem; color: var(--text-main); cursor: pointer; font-weight: 600; padding: 0.5rem; border-radius: 8px; transition: 0.2s; }
    .member-item:hover { background: rgba(249, 157, 78, 0.05); }
    .member-item:last-child { margin-bottom: 0; }
    .member-item input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer; }
    .modal-actions { display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2.5rem; }
    .btn-cancel { background: var(--bg-body); border: 1px solid var(--border); padding: 0.8rem 1.8rem; border-radius: 14px; cursor: pointer; color: var(--text-main); font-weight: 700; transition: 0.3s; font-family: inherit; font-size: 0.95rem;}
    .btn-cancel:hover { background: rgba(0,0,0,0.05); transform: translateY(-2px); }
    html.dark-mode .btn-cancel:hover { background: rgba(255,255,255,0.05); }
    .btn-create { background: var(--primary-gradient); color: #fff; border: none; padding: 0.8rem 1.8rem; border-radius: 14px; cursor: pointer; font-weight: 700; font-size: 0.95rem; font-family: inherit; transition: 0.3s; box-shadow: 0 4px 15px rgba(233, 59, 110, 0.3); }
    .btn-create:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(233, 59, 110, 0.4); }
</style>

@endpush

@section('content')
<div class="chat-container">
    <!-- Sidebar -->
    <div class="chat-sidebar">
        <div class="chat-sidebar-header">
            <h1>Team Chat</h1>
            {{-- Anybody can start a channel for their team. --}}
            <button onclick="document.getElementById('newChannelModal').classList.add('active')" title="New Channel">
                <i class="fas fa-plus"></i>
            </button>
        </div>
        
        <div class="chat-sidebar-content">
            <!-- Channels -->
            <div>
                <div class="chat-section-title">Channels</div>
                <ul id="group-channels-list">
                    @foreach($conversations->where('type', 'group') as $group)
                        <li>
                            <button id="channel-btn-{{ $group->id }}" data-members="{{ htmlspecialchars($group->users->pluck('display_name')->implode(', '), ENT_QUOTES) }}" data-member-ids="{{ json_encode($group->users->pluck('id')) }}" onclick="loadConversation({{ $group->id }}, '{{ addslashes($group->name) }}', false, null, {{ $group->created_by }}, this)" class="chat-channel-btn">
                                <span># {{ $group->name }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>

            <!-- Direct Messages -->
            <div>
                <div class="chat-section-title">Direct Messages</div>
                <ul>
                    @foreach($all_users as $u)
                        @php
                            // Check if a DM exists with this user
                            $dm = $conversations->where('type', 'direct')->filter(function($c) use ($u) {
                                return $c->users->contains('id', $u->id);
                            })->first();
                            $dmId = $dm ? $dm->id : 'new-'.$u->id;
                            
                            $isOnline = $u->isOnline();
                        @endphp
                        <li>
                            <button id="channel-btn-{{ $dmId }}" data-user-id="{{ $u->id }}" onclick="loadConversation('{{ $dmId }}', '{{ addslashes($u->display_name) }}', true, {{ $u->id }})" class="chat-channel-btn">
                                <span class="status-dot {{ $isOnline ? 'status-online' : 'status-offline' }}"
                                      title="{{ $isOnline ? 'Online' : 'Offline' }}"></span>
                                {{ $u->display_name }}
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    <!-- Main Chat Area -->
    <div class="chat-main" style="position:relative;">
        <!-- Header -->
        <div class="chat-header">
            <span id="currentChatName">Select a conversation</span>
            <div style="margin-left:auto;">
                {{-- These three act on the open channel, and openConversation() shows
                     them once there is one. They start hidden because before that
                     there is nothing for them to act on: the info button has no
                     handler attached yet, and edit/delete would run against nothing. --}}
                <button id="infoChannelBtn" class="chat-actions-btn" title="Channel Members" style="display: none;"><i class="fas fa-info-circle"></i></button>
                {{-- Rendered for everybody and shown by loadConversation(), which
                     knows who started the open channel: renaming and closing go
                     to whoever runs it, leaving goes to everybody else. The
                     markup cannot know that, because it does not yet know which
                     channel will be opened. --}}
                <button id="editChannelBtn" class="chat-actions-btn" onclick="openEditChannelModal()" title="Edit Channel" style="display: none;"><i class="fas fa-edit"></i></button>
                <button id="deleteChannelBtn" class="chat-actions-btn delete" onclick="deleteChannel()" title="Delete Channel" style="display: none;"><i class="fas fa-trash-alt"></i></button>
                <button id="leaveChannelBtn" class="chat-actions-btn" onclick="leaveChannel()" title="Leave Channel" style="display: none;"><i class="fas fa-right-from-bracket"></i></button>
            </div>
        </div>

        <!-- Messages -->
        <div class="chat-messages" id="messagesContainer">
            <div class="chat-empty"><i class="fas fa-comments"></i><div>Choose a channel or person to start chatting.</div></div>
        </div>

        <div id="typingIndicator" style="display:none; padding: 0.5rem 2rem; font-size: 0.85rem; color: var(--text-muted); font-style: italic;">
            <i class="fas fa-keyboard" style="margin-right: 5px;"></i> <span></span>
        </div>

        <!-- Input Area -->
        <div class="chat-input-area" id="chatInputArea">
            <form id="messageForm" class="chat-input-form">
                <input type="file" id="attachmentInput" style="display:none;">
                <button type="button" class="chat-icon-btn" onclick="document.getElementById('attachmentInput').click()" title="Attach File">
                    <i class="fas fa-paperclip"></i>
                </button>
                <input type="text" id="messageInput" class="chat-input" placeholder="Type a message..." autocomplete="off">
                <button type="button" class="chat-icon-btn" onclick="toggleEmojiPicker()" title="Emojis">
                    <i class="fas fa-smile"></i>
                </button>
                {{-- Voice note. Hidden when the browser has no MediaRecorder or the
                     page is not on a secure origin, because the microphone is
                     unavailable there and a button that cannot work is worse than
                     no button. --}}
                <button type="button" class="chat-icon-btn" id="voiceBtn" title="Record a voice message" hidden>
                    <i class="fas fa-microphone"></i>
                </button>
                <button type="submit" class="chat-send-btn">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>
            {{-- Shown in place of the composer while recording. --}}
            <div id="voiceBar" class="voice-bar" hidden>
                <button type="button" id="voiceCancel" class="voice-cancel"
                        title="Discard recording" aria-label="Discard recording">
                    <i class="fas fa-xmark"></i>
                </button>
                <span class="voice-dot" aria-hidden="true"></span>
                <b id="voiceTime" class="voice-time" aria-live="off">0:00</b>
                <div class="voice-wave-wrap">
                    <canvas id="voiceWave" class="voice-wave" aria-hidden="true"></canvas>
                </div>
                <button type="button" id="voiceStop" class="voice-send"
                        title="Send voice message" aria-label="Send voice message">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>

            <div id="attachmentPreview" style="font-size:0.8rem; color:#64748b; margin-top:0.5rem; display:none;">
                Attached: <span id="attachmentName"></span>
                <button type="button" onclick="clearAttachment()" style="background:none;border:none;color:#ef4444;cursor:pointer;margin-left:0.5rem;"><i class="fas fa-times"></i></button>
            </div>
        </div>
        
        <div id="emojiPickerContainer">
            <emoji-picker></emoji-picker>
        </div>
    </div>
</div>

<!-- New Channel Modal -->
<div id="newChannelModal" class="chat-modal-overlay">
    <div class="chat-modal">
        <h3>Create New Channel</h3>
        <form id="newChannelForm">
            <div class="form-group">
                <label>Channel Name</label>
                <input type="text" id="channelName" required>
            </div>
            <div class="form-group">
                <label>Members</label>
                <div class="members-list">
                    @foreach($all_users as $u)
                        <label class="member-item">
                            <input type="checkbox" name="channel_users[]" value="{{ $u->id }}"> {{ $u->display_name }}
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" onclick="document.getElementById('newChannelModal').classList.remove('active')" class="btn-cancel">Cancel</button>
                <button type="submit" class="btn-create">Create</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Channel Modal -->
<div id="editChannelModal" class="chat-modal-overlay">
    <div class="chat-modal">
        <h3>Edit Channel</h3>
        <form id="editChannelForm">
            <div class="form-group">
                <label>Channel Name</label>
                <input type="text" id="editChannelName" required>
            </div>
            <div class="form-group">
                <label>Manage Members</label>
                <div class="members-list">
                    @foreach($all_users as $u)
                        <label class="member-item">
                            <input type="checkbox" name="edit_channel_users[]" value="{{ $u->id }}" class="edit-user-checkbox"> {{ $u->display_name }}
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" onclick="document.getElementById('editChannelModal').classList.remove('active')" class="btn-cancel">Cancel</button>
                <button type="submit" class="btn-create">Save Changes</button>
            </div>
        </form>
    </div>
</div>


<!-- Info Channel Modal -->
<div id="infoChannelModal" class="chat-modal-overlay">
    <div class="chat-modal">
        <h3>Channel Members</h3>
        <div class="members-list" id="infoChannelMembersList" style="margin-bottom: 1.5rem;">
            <!-- Members will be loaded here -->
        </div>
        <div class="modal-actions">
            <button type="button" onclick="document.getElementById('infoChannelModal').classList.remove('active')" class="btn-cancel">Close</button>
        </div>
    </div>
</div>


<!-- Custom Confirm Modal -->
<div id="customConfirmModal" class="chat-modal-overlay">
    <div class="chat-modal" style="width: 400px; text-align: center;">
        <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: var(--danger, #ef4444); margin-bottom: 1rem;"></i>
        <h3 id="confirmModalTitle" style="margin-bottom: 0.5rem;">Are you sure?</h3>
        <p id="confirmModalText" style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.95rem;"></p>
        <div class="modal-actions" style="justify-content: center; gap: 1rem; margin-top: 1rem;">
            <button type="button" id="confirmModalCancelBtn" class="btn-cancel">Cancel</button>
            <button type="button" id="confirmModalOkBtn" class="btn-create" style="background: var(--danger, #ef4444); box-shadow: 0 4px 10px rgba(239, 68, 68, 0.2);">Yes, do it!</button>
        </div>
    </div>
</div>

@endsection


@push('scripts')
<script type="module" src="https://cdn.jsdelivr.net/npm/emoji-picker-element@1.x.x/index.js"></script>
<script>
    let currentConversationId = null;
    let targetUserId = null;
    let currentUserRole = '{{ auth()->user()->role }}';
    let currentUserId = {{ auth()->id() }};
    let currentChannelMemberIds = [];
    let isPolling = false;
    let previousChatCountsLocal = {};
    let isInitialLocalPoll = true;

    function customConfirm(title, text, confirmCallback) {
        document.getElementById('confirmModalTitle').innerText = title;
        document.getElementById('confirmModalText').innerText = text;
        
        const modal = document.getElementById('customConfirmModal');
        const okBtn = document.getElementById('confirmModalOkBtn');
        const cancelBtn = document.getElementById('confirmModalCancelBtn');
        
        modal.classList.add('active');
        
        const newOkBtn = okBtn.cloneNode(true);
        okBtn.parentNode.replaceChild(newOkBtn, okBtn);
        
        const newCancelBtn = cancelBtn.cloneNode(true);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        
        newOkBtn.addEventListener('click', () => {
            modal.classList.remove('active');
            confirmCallback();
        });
        
        newCancelBtn.addEventListener('click', () => {
            modal.classList.remove('active');
        });
    }

    async function loadConversation(id, name, isDm = false, uId = null, createdBy = null, btnElement = null) {
        // Instantly reset unread badge
        if (btnElement) {
            btnElement.classList.remove('has-unread');
            let badge = btnElement.querySelector('.unread-badge');
            if (badge) {
                let badgeCount = parseInt(badge.innerText) || 0;
                badge.remove();
                // Deduct from global badge
                const chatBadge = document.getElementById('globalChatBadge');
                if (chatBadge && chatBadge.style.display !== 'none') {
                    let globalCount = parseInt(chatBadge.textContent === '9+' ? 10 : chatBadge.textContent) || 0;
                    globalCount = Math.max(0, globalCount - badgeCount);
                    if (globalCount > 0) {
                        chatBadge.textContent = globalCount > 9 ? '9+' : globalCount;
                    } else {
                        chatBadge.style.display = 'none';
                    }
                }
            }
        }

        // Highlight selected
        document.querySelectorAll('.chat-channel-btn').forEach(btn => btn.classList.remove('active'));
        if (btnElement) btnElement.classList.add('active');

        document.getElementById('currentChatName').innerText = (isDm ? '' : '# ') + name;
        document.getElementById('chatInputArea').style.display = 'block';
        const container = document.getElementById('messagesContainer');
        // The shape of the conversation while it is on its way, rather than a
        // spinner on an empty box: the messages land where the grey rows are.
        container.innerHTML =
            '<div class="chat-loading" aria-label="Loading messages">' +
            '<div class="chat-skel"><span class="skeleton" style="width: 42%"></span><span class="skeleton" style="width: 64%"></span></div>' +
            '<div class="chat-skel is-mine"><span class="skeleton" style="width: 52%"></span></div>' +
            '<div class="chat-skel"><span class="skeleton" style="width: 36%"></span><span class="skeleton" style="width: 58%"></span><span class="skeleton" style="width: 30%"></span></div>' +
            '<div class="chat-skel is-mine"><span class="skeleton" style="width: 44%"></span><span class="skeleton" style="width: 26%"></span></div>' +
            '</div>';

        // Manage Edit/Delete buttons visibility
        const editBtn = document.getElementById('editChannelBtn');
        const delBtn = document.getElementById('deleteChannelBtn');
        const infoBtn = document.getElementById('infoChannelBtn');
        
        
        if (!isDm && id !== 'new') {
            if (infoBtn) infoBtn.style.display = 'inline-block';
            let membersStr = btnElement ? btnElement.getAttribute('data-members') : 'Members info not available';
            let memberIdsStr = btnElement ? btnElement.getAttribute('data-member-ids') : '[]';
            try { currentChannelMemberIds = JSON.parse(memberIdsStr); } catch(e) { currentChannelMemberIds = []; }
            if (infoBtn) infoBtn.onclick = () => openInfoChannelModal(membersStr);
            
            const leaveBtn = document.getElementById('leaveChannelBtn');

            if (editBtn && delBtn) {
                const runsIt = ['admin', 'superadmin', 'floor_manager'].includes(currentUserRole) || createdBy == currentUserId;

                if (runsIt) {
                    editBtn.style.display = 'inline-block';
                    delBtn.style.display = 'inline-block';
                } else {
                    editBtn.style.display = 'none';
                    delBtn.style.display = 'none';
                }

                // Whoever started it cannot walk out of their own channel —
                // there would be nobody left to rename or close it.
                if (leaveBtn) {
                    leaveBtn.style.display = createdBy == currentUserId ? 'none' : 'inline-block';
                }
            }
        } else {
            if (infoBtn) infoBtn.style.display = 'none';
            if (editBtn) editBtn.style.display = 'none';
            if (delBtn) delBtn.style.display = 'none';
        }

        if (id.toString().startsWith('new-')) {
            currentConversationId = 'new';
            targetUserId = uId;
            container.innerHTML = '<div class="chat-empty"><i class="fas fa-paper-plane"></i><div>Start a new conversation</div></div>';
            return;
        }

        currentConversationId = id;
        
        try {
            const res = await fetch(`{{ url('/chat') }}/${id}`);
            const messages = await res.json();
            
            container.innerHTML = '';
            if (messages.length === 0) {
                container.innerHTML = '<div class="chat-empty"><i class="fas fa-hand-sparkles"></i><div>No messages yet. Say hi!</div></div>';
            }
            messages.forEach(msg => {
                appendMessage(msg);
            });
            scrollToBottom();
        } catch (e) {
            console.error(e);
            container.innerHTML = '<div class="chat-empty" style="color:#ef4444;">Error loading messages</div>';
        }
    }

    const REACTION_SET = ['\u{1F44D}', '\u{2764}\u{FE0F}', '\u{1F602}', '\u{1F62E}', '\u{1F622}', '\u{1F389}'];
    const MY_ID = {{ auth()->id() }};
    const IS_ADMIN = {{ auth()->user()->isAdmin() ? 'true' : 'false' }};

    /* Message bodies are user input and go into innerHTML below, so they are
       escaped here. Without this a message containing markup would run in every
       recipient's window. */
    function esc(text) {
        return String(text == null ? '' : text)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function attachmentMarkup(msg) {
        if (!msg.attachment_path) return '';

        const url = `{{ asset('storage') }}/${msg.attachment_path}`;
        const filename = msg.attachment_path.split('/').pop();
        const ext = msg.attachment_path.split('.').pop().toLowerCase();

        if (msg.attachment_type === 'voice') {
            // preload="metadata" so the length shows straight away; with "none"
            // the player sat at 0:00 / 0:00 until someone pressed play.
            return `<div class="msg-voice"><audio class="voice-audio" controls preload="metadata" src="${esc(url)}"></audio></div>`;
        }

        if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
            return `
                <div style="position:relative; display:inline-block; margin-top:0.5rem; max-width: 100%;">
                    <img src="${esc(url)}" class="attachment-image" alt="Attachment" style="display:block; border-radius: 8px;">
                    <a href="${esc(url)}" download="${esc(filename)}" title="Download Image" style="position:absolute; bottom:10px; right:10px; background:rgba(0,0,0,0.6); color:white; padding:8px 12px; border-radius:8px; font-size:1rem; text-decoration:none; box-shadow: 0 4px 6px rgba(0,0,0,0.3);"><i class="fas fa-download"></i></a>
                </div>`;
        }

        return `
            <div style="margin-top:0.5rem;">
                <a href="${esc(url)}" download="${esc(filename)}" class="attachment-file" style="display:flex; align-items:center; gap:12px; text-decoration:none; padding:10px 15px; background:var(--bg-card); border:1px solid var(--border); border-radius:10px; color:var(--text-main); font-weight:500; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                    <i class="fas fa-file-alt" style="font-size: 1.5rem; color: var(--primary);"></i>
                    <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 200px;">${esc(filename)}</span>
                    <span style="margin-left:auto; background:var(--primary-gradient); color:white; padding:6px 12px; border-radius:8px; font-size:0.85rem; display:flex; align-items:center; gap:5px;"><i class="fas fa-download"></i> Download</span>
                </a>
            </div>`;
    }

    function isImageOnly(msg) {
        if (msg.deleted || !msg.attachment_path || msg.attachment_type === 'voice') return false;
        if (msg.body && String(msg.body).trim()) return false;
        return /\.(jpe?g|png|gif|webp)$/i.test(msg.attachment_path);
    }

    function reactionsMarkup(msg) {
        if (!msg.reactions || !msg.reactions.length) return '';
        return '<div class="msg-reactions">' + msg.reactions.map(function (r) {
            return `<button type="button" class="react-pill ${r.mine ? 'mine' : ''}" data-emoji="${esc(r.emoji)}">${esc(r.emoji)} ${r.count}</button>`;
        }).join('') + '</div>';
    }

    function actionsMarkup(msg, isMine) {
        if (msg.deleted) return '';

        const canEdit = isMine;
        const canDelete = isMine || IS_ADMIN;

        return `
            <div class="msg-actions">
                <button type="button" class="msg-act act-react" title="React"><i class="far fa-smile"></i></button>
                ${canEdit ? '<button type="button" class="msg-act act-edit" title="Edit"><i class="fas fa-pen"></i></button>' : ''}
                ${canDelete ? '<button type="button" class="msg-act danger act-delete" title="Delete"><i class="fas fa-trash"></i></button>' : ''}
            </div>
            <div class="react-menu">
                ${REACTION_SET.map(function (e) {
                    return `<button type="button" class="react-pick" data-emoji="${esc(e)}">${e}</button>`;
                }).join('')}
            </div>`;
    }

    /*
     * A fingerprint of everything about a message that someone else can change
     * after it was sent: whether and when it was edited, whether it was deleted,
     * and its reactions. The poll compares the thread's fingerprints with the
     * server's, which is what lets edits, deletions and reactions from other
     * people appear live. Counting messages, as it used to, misses all three —
     * none of them change the count.
     */
    function msgSig(m) {
        return [
            m.id,
            m.edited_at || (m.edited ? 'e' : ''),
            m.deleted ? 'd' : '',
            (m.reactions || []).map(function (r) { return r.emoji + ':' + r.count + (r.mine ? '*' : ''); }).join(',')
        ].join('.');
    }

    function renderMessageInto(div, msg) {
        div._msg = msg;
        div.dataset.sig = msgSig(msg);
        const isMine = msg.sender_id == MY_ID;
        const time = new Date(msg.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        const tickHtml = (isMine && !msg.deleted)
            ? `<div class="msg-ticks" style="text-align: right; font-size: 0.75rem; margin-top: 5px; color: rgba(255, 255, 255, 0.6);"> <i class="fas fa-check tick-icon"></i> </div>` : '';

        const bodyHtml = msg.deleted
            ? '<span class="msg-deleted"><i class="fas fa-ban"></i> This message was deleted</span>'
            : esc(msg.body || '').replace(/\n/g, '<br>');

        div.className = `msg-wrapper ${isMine ? 'msg-mine' : 'msg-theirs'}`;
        div.dataset.id = msg.id;
        if (isMine && msg.created_at) div.setAttribute('data-timestamp', msg.created_at);

        div.innerHTML = `
            <div class="msg-meta">
                ${faceMarkup(msg.sender)}
                <span class="msg-sender">${esc((msg.sender && msg.sender.name) || 'You')}</span>
                ${msg.sender && msg.sender.verified
                    ? '<i class="fas fa-circle-check person-tick" title="Verified"></i>' : ''}
                <span class="msg-time">${time}</span>
            </div>
            <div class="msg-bubble${isImageOnly(msg) ? ' msg-media' : ''}">
                ${bodyHtml}${msg.edited ? '<span class="msg-edited">(edited)</span>' : ''}
                ${msg.deleted ? '' : attachmentMarkup(msg)}
                ${tickHtml}
            </div>
            ${reactionsMarkup(msg)}
            ${actionsMarkup(msg, isMine)}
        `;
    }

    /* The same face the rest of the CRM draws, built here because these rows
       are made in the browser rather than by Blade. */
    function faceMarkup(sender) {
        if (!sender) return '';

        const inner = sender.face
            ? `<img src="${esc(sender.face)}" alt="" class="face-pic" loading="lazy">`
            : `<span class="face-letter" style="background: ${esc(sender.tone || '#94A3B8')};">${esc(sender.initial || '?')}</span>`;

        return `<span class="face" style="--face-size: 20px; --face-round: 6px;">${inner}</span>`;
    }

    function appendMessage(msg) {
        const container = document.getElementById('messagesContainer');
        const emptyState = container.querySelector('.chat-empty');
        if (emptyState) emptyState.remove();

        const div = document.createElement('div');
        renderMessageInto(div, msg);
        container.appendChild(div);
    }

    // ---- message actions -----------------------------------------------------

    function chatApi(method, path, body) {
        return fetch(`{{ url('/chat') }}` + path, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            credentials: 'same-origin',
            body: body ? JSON.stringify(body) : undefined
        }).then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        });
    }

    function findRow(id) {
        return document.querySelector(`#messagesContainer .msg-wrapper[data-id="${id}"]`);
    }

    function toggleReaction(id, emoji) {
        chatApi('POST', `/message/${id}/react`, { emoji: emoji })
            .then(function (data) {
                const row = findRow(data.id);
                if (!row) return;
                const old = row.querySelector('.msg-reactions');
                if (old) old.remove();
                if (row._msg) {
                    row._msg.reactions = data.reactions;
                    row.dataset.sig = msgSig(row._msg);
                }
                const html = reactionsMarkup({ reactions: data.reactions });
                if (html) row.querySelector('.msg-bubble').insertAdjacentHTML('afterend', html);
            })
            .catch(function () { alert('Could not save that reaction.'); });
    }

    function startEdit(id) {
        const row = findRow(id);
        if (!row || row.querySelector('.msg-edit-box')) return;

        const bubble = row.querySelector('.msg-bubble');
        const original = bubble.innerHTML;

        // Read the text back out of the DOM, so what gets edited is exactly what
        // is displayed rather than a second copy that could drift from it.
        const tmp = document.createElement('div');
        tmp.innerHTML = original.split('<span class="msg-edited"')[0];
        const currentText = (tmp.textContent || '').trim();

        bubble.innerHTML = `
            <div class="msg-edit-box">
                <textarea class="msg-edit-input">${esc(currentText)}</textarea>
                <div class="msg-edit-actions">
                    <button type="button" class="msg-edit-cancel">Cancel</button>
                    <button type="button" class="msg-edit-save">Save</button>
                </div>
            </div>`;

        const input = bubble.querySelector('.msg-edit-input');
        input.focus();
        input.setSelectionRange(input.value.length, input.value.length);

        bubble.querySelector('.msg-edit-cancel').onclick = function () { bubble.innerHTML = original; };
        bubble.querySelector('.msg-edit-save').onclick = function () { saveEdit(id, input.value, bubble, original); };
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); saveEdit(id, input.value, bubble, original); }
            if (e.key === 'Escape') { bubble.innerHTML = original; }
        });
    }

    function saveEdit(id, text, bubble, original) {
        if (!text.trim()) { bubble.innerHTML = original; return; }
        chatApi('PUT', `/message/${id}`, { body: text })
            .then(function (msg) {
                const row = findRow(id);
                if (row) renderMessageInto(row, msg);
            })
            .catch(function () { bubble.innerHTML = original; alert('Could not save the edit.'); });
    }

    function deleteMessage(id) {
        if (!confirm('Delete this message?')) return;
        chatApi('DELETE', `/message/${id}`)
            .then(function () {
                const row = findRow(id);
                if (row) renderMessageInto(row, {
                    id: id, sender_id: MY_ID, deleted: true,
                    created_at: new Date().toISOString(), sender: { name: '' }
                });
            })
            .catch(function () { alert('Could not delete that message.'); });
    }

    /*
     * Chrome's MediaRecorder writes WebM without a duration in the header, so a
     * voice note can load with duration = Infinity and the player shows 0:00 / 0:00
     * even though it plays. Seeking far past the end makes the browser scan the
     * file and work out the real length; then it is put back to the start.
     * Media events do not bubble, hence a capturing listener on the thread.
     */
    document.getElementById('messagesContainer').addEventListener('loadedmetadata', function (e) {
        const a = e.target;
        if (!a.classList || !a.classList.contains('voice-audio')) return;
        if (isFinite(a.duration) && a.duration > 0) return;
        const restore = function () {
            a.removeEventListener('durationchange', restore);
            a.currentTime = 0;
        };
        a.addEventListener('durationchange', restore);
        try { a.currentTime = 1e101; } catch (err) { /* browser refused; it still plays */ }
    }, true);

    // One delegated listener for the whole thread, so messages arriving from the
    // poll are wired up without re-binding anything.
    document.getElementById('messagesContainer').addEventListener('click', function (e) {
        const row = e.target.closest('.msg-wrapper');
        if (!row) return;
        const id = row.dataset.id;

        if (e.target.closest('.act-react')) {
            const menu = row.querySelector('.react-menu');
            document.querySelectorAll('.react-menu.is-open').forEach(function (m) { if (m !== menu) m.classList.remove('is-open'); });
            if (menu) menu.classList.toggle('is-open');
            return;
        }
        if (e.target.closest('.act-edit'))   { startEdit(id); return; }
        if (e.target.closest('.act-delete')) { deleteMessage(id); return; }

        const pick = e.target.closest('.react-pick');
        if (pick) {
            row.querySelector('.react-menu').classList.remove('is-open');
            toggleReaction(id, pick.dataset.emoji);
            return;
        }
        const pill = e.target.closest('.react-pill');
        if (pill) { toggleReaction(id, pill.dataset.emoji); }
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.msg-wrapper')) {
            document.querySelectorAll('.react-menu.is-open').forEach(function (m) { m.classList.remove('is-open'); });
        }
    });

    /*
     * The assistant orb is pinned 24px from the bottom of every page. Here that
     * puts it on the end of the message composer, so on this page it sits just
     * above the composer instead. Measured rather than hard-coded, because the
     * composer's distance from the bottom changes with the window height.
     */
    (function () {
        const orb = document.getElementById('aiOrbTrigger');
        const area = document.getElementById('chatInputArea');
        if (!orb || !area) return;

        function place() {
            const r = area.getBoundingClientRect();
            if (!r.height) return;
            const fromBottom = Math.round(window.innerHeight - r.top) + 12;
            orb.style.setProperty('bottom', Math.max(24, fromBottom) + 'px', 'important');
        }

        place();
        window.addEventListener('resize', place);
        // The composer can appear or change height after load (conversation opened,
        // attachment preview shown), so follow its size too.
        if (window.ResizeObserver) new ResizeObserver(place).observe(area);
    })();

    // ---- voice notes ---------------------------------------------------------

    /*
     * Recording replaces the composer, WhatsApp-style: discard, timer, a waveform
     * that follows the voice, send. Nothing else — no device picker, no warnings.
     *
     * Two things here are deliberately defensive, because this is the part that
     * failed silently for a real user while every test passed:
     *   - the recorder is started with a timeslice, so audio is collected in small
     *     pieces as it is recorded rather than in one final event that some
     *     browsers can deliver after "stop";
     *   - no path ends quietly: if nothing was captured, or the upload fails, the
     *     user is told why.
     */
    (function () {
        const btn = document.getElementById('voiceBtn');
        const bar = document.getElementById('voiceBar');
        const area = document.getElementById('chatInputArea');
        const canvas = document.getElementById('voiceWave');
        if (!btn || !bar || !area || !canvas) return;

        // Needs MediaRecorder and a secure origin; localhost counts as secure.
        const supported = !!(navigator.mediaDevices && window.MediaRecorder && window.isSecureContext);
        if (!supported) return;
        btn.hidden = false;

        // A device choice saved by an earlier version of this bar would otherwise
        // linger unseen; recording always uses the system's default microphone now.
        try { localStorage.removeItem('crm.micDevice.' + window.CRM_USER_ID); } catch (e) { /* ignore */ }

        const BAR_W = 3, BAR_GAP = 3, SAMPLE_MS = 55, TIMESLICE_MS = 250;

        let recorder = null, chunks = [], stream = null, timer = null, started = 0;
        let cancelled = false, stopping = false;
        let audioCtx = null, analyser = null, samples = null, levels = [], raf = 0, lastSample = 0;
        let recentDb = [];

        function notify(title, message) {
            if (typeof showToast === 'function') showToast(title, message);
            else alert(title + ': ' + message);
        }

        function tick() {
            const secs = Math.floor((Date.now() - started) / 1000);
            document.getElementById('voiceTime').textContent =
                Math.floor(secs / 60) + ':' + String(secs % 60).padStart(2, '0');
        }

        // ---- waveform ------------------------------------------------------------

        function sizeCanvas() {
            const dpr = window.devicePixelRatio || 1;
            const r = canvas.getBoundingClientRect();
            canvas.width = Math.max(1, Math.round(r.width * dpr));
            canvas.height = Math.max(1, Math.round(r.height * dpr));
            return { w: r.width, h: r.height, dpr: dpr };
        }

        /* Created synchronously inside the click, before the permission prompt:
           Safari and some Chrome configurations only let an AudioContext run if it
           was made during a user gesture, and a suspended context reads as silence. */
        function prepareAudio() {
            try {
                const Ctx = window.AudioContext || window.webkitAudioContext;
                if (Ctx && !audioCtx) audioCtx = new Ctx();
            } catch (e) { audioCtx = null; }
        }

        function startWave() {
            try {
                if (!audioCtx) prepareAudio();
                if (!audioCtx) return;
                if (audioCtx.state === 'suspended') audioCtx.resume();
                analyser = audioCtx.createAnalyser();
                analyser.fftSize = 1024;
                analyser.smoothingTimeConstant = 0.6;
                audioCtx.createMediaStreamSource(stream).connect(analyser);
                samples = new Uint8Array(analyser.fftSize);
            } catch (e) {
                analyser = null;   // no waveform; the recording itself is unaffected
            }
            levels = [];
            lastSample = 0;
            recentDb = [];
            raf = window.requestAnimationFrame(drawWave);
        }

        function currentLevel() {
            if (!analyser) return 0;
            analyser.getByteTimeDomainData(samples);
            let sum = 0;
            for (let i = 0; i < samples.length; i++) {
                const v = (samples[i] - 128) / 128;
                sum += v * v;
            }
            const db = 20 * Math.log10(Math.max(Math.sqrt(sum / samples.length), 1e-6));

            /*
             * Relative to the microphone's own background noise, not a fixed scale.
             * Browsers apply automatic gain, which lifts a quiet room to about
             * -36 dB on this laptop's array; on a fixed scale that drew half-height
             * bars during silence. The floor is the quiet end (15th percentile) of
             * the last two seconds, so it settles on the room noise straight away,
             * silence draws flat, and speech — which always has short gaps — rises
             * clearly above it.
             */
            // Pure digital silence (the first moments after a microphone opens
            // read as -120 dB) is not room noise; counting it dragged the floor
            // down to the limit and made steady background hiss draw as speech.
            if (db > -100) {
                recentDb.push(db);
                if (recentDb.length > 40) recentDb.shift();
            }
            const sorted = recentDb.slice().sort(function (a, b) { return a - b; });
            const floor = sorted.length
                ? Math.max(-65, Math.min(-25, sorted[Math.floor(sorted.length * 0.15)]))
                : -65;

            return Math.max(0, Math.min(1, (db - floor - 4) / 24));
        }

        function drawWave(now) {
            raf = window.requestAnimationFrame(drawWave);

            const { w, h, dpr } = sizeCanvas();
            const ctx = canvas.getContext('2d');
            const capacity = Math.max(1, Math.floor(w / (BAR_W + BAR_GAP)));

            if (!lastSample || now - lastSample >= SAMPLE_MS) {
                lastSample = now;
                levels.push(currentLevel());
                if (levels.length > capacity) levels.splice(0, levels.length - capacity);
            }

            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            ctx.clearRect(0, 0, w, h);

            const grad = ctx.createLinearGradient(0, 0, w, 0);
            grad.addColorStop(0, '#f99d4e');
            grad.addColorStop(1, '#e93b6e');
            ctx.strokeStyle = grad;
            ctx.lineWidth = BAR_W;
            ctx.lineCap = 'round';

            // Newest on the right, scrolling left.
            const mid = h / 2;
            const maxHalf = h / 2 - BAR_W / 2 - 1;
            for (let i = 0; i < capacity; i++) {
                const idx = levels.length - capacity + i;
                const level = idx >= 0 ? levels[idx] : 0;
                const half = Math.max(1, level * maxHalf);
                const x = i * (BAR_W + BAR_GAP) + BAR_W / 2;
                ctx.globalAlpha = idx >= 0 ? 1 : 0.25;
                ctx.beginPath();
                ctx.moveTo(x, mid - half);
                ctx.lineTo(x, mid + half);
                ctx.stroke();
            }
            ctx.globalAlpha = 1;
        }

        function stopWave() {
            window.cancelAnimationFrame(raf);
            raf = 0;
            if (audioCtx) { try { audioCtx.close(); } catch (e) { /* already closed */ } }
            audioCtx = null; analyser = null; levels = [];
            const ctx = canvas.getContext('2d');
            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }

        // ---- recording lifecycle -------------------------------------------------

        function stopTracks() {
            if (stream) stream.getTracks().forEach(function (t) { t.stop(); });
            stream = null;
        }

        function closeBar() {
            window.clearInterval(timer);
            stopWave();
            stopTracks();
            area.classList.remove('is-recording');
            bar.hidden = true;
            stopping = false;
            const input = document.getElementById('messageInput');
            if (input) input.focus();
        }

        /* The container the browser can actually produce, and a file name that
           matches it: Safari records MP4/AAC, and calling that ".webm" makes it
           unplayable in other browsers. */
        function recorderOptions() {
            const prefs = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/mp4'];
            const type = prefs.find(t => MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(t));
            return type ? { mimeType: type } : {};
        }

        function extensionFor(type) {
            if (/ogg/.test(type)) return 'ogg';
            if (/mp4|aac|m4a/.test(type)) return 'm4a';
            return 'webm';
        }

        btn.addEventListener('click', async function () {
            // 'new' is a person picked from the list whose chat does not exist on
            // the server yet; it is created when the recording is sent, the same
            // way typing the first message creates it.
            if (!currentConversationId) {
                notify('Voice message', 'Choose a conversation first.');
                return;
            }

            prepareAudio();   // inside the click, see prepareAudio()
            try {
                stream = await navigator.mediaDevices.getUserMedia({
                    audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true }
                });
            } catch (err) {
                stopWave();
                notify('Microphone', err && err.name === 'NotAllowedError'
                    ? 'Allow microphone access in the browser to record voice messages.'
                    : 'The microphone could not be opened. Is another app using it?');
                return;
            }

            chunks = []; cancelled = false; stopping = false;
            try {
                recorder = new MediaRecorder(stream, recorderOptions());
            } catch (e) {
                recorder = new MediaRecorder(stream);
            }
            recorder.ondataavailable = function (e) { if (e.data && e.data.size) chunks.push(e.data); };
            recorder.onerror = function (e) {
                notify('Voice message', 'Recording stopped unexpectedly: ' + ((e.error && e.error.name) || 'unknown error'));
                cancelled = true;
            };
            recorder.onstop = function () {
                const type = (recorder && recorder.mimeType) || (chunks[0] && chunks[0].type) || 'audio/webm';
                const wasCancelled = cancelled;
                const captured = chunks.slice();
                closeBar();
                if (wasCancelled) return;
                const blob = new Blob(captured, { type: type });
                if (!blob.size) {
                    notify('Voice message', 'No audio was captured. Please try again.');
                    return;
                }
                sendVoice(blob);
            };

            recorder.start(TIMESLICE_MS);

            area.classList.add('is-recording');
            bar.hidden = false;
            started = Date.now();
            tick();
            timer = window.setInterval(tick, 250);
            startWave();
            document.getElementById('voiceStop').focus();
        });

        function stopAndSend() {
            if (!recorder || recorder.state === 'inactive' || stopping) return;
            stopping = true;
            // Flush what has been recorded since the last slice before stopping.
            try { recorder.requestData(); } catch (e) { /* not supported; stop still flushes */ }
            recorder.stop();
        }

        function discard() {
            cancelled = true;
            if (recorder && recorder.state !== 'inactive') recorder.stop(); else closeBar();
        }

        document.getElementById('voiceStop').addEventListener('click', stopAndSend);
        document.getElementById('voiceCancel').addEventListener('click', discard);

        document.addEventListener('keydown', function (e) {
            if (!area.classList.contains('is-recording')) return;
            if (e.key === 'Escape') { e.preventDefault(); discard(); }
        });

        async function sendVoice(blob) {
            const form = new FormData();
            form.append('attachment', blob, 'voice-' + Date.now() + '.' + extensionFor(blob.type));
            form.append('attachment_type', 'voice');

            btn.disabled = true;

            const conversationId = await ensureConversation();
            if (!conversationId) {
                btn.disabled = false;
                notify('Voice message', 'Choose a conversation first.');
                return;
            }

            fetch(`{{ url('/chat') }}/${conversationId}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                credentials: 'same-origin',
                body: form
            })
            .then(function (r) {
                if (!r.ok) {
                    return r.json().catch(function () { return {}; }).then(function (j) {
                        throw new Error((j && (j.message || j.error)) || ('server replied ' + r.status));
                    });
                }
                return r.json();
            })
            .then(function (msg) {
                appendMessage({
                    id: msg.id, sender_id: msg.sender_id, body: msg.body,
                    attachment_path: msg.attachment_path, attachment_type: 'voice',
                    reactions: [], created_at: msg.created_at,
                    sender: { name: 'You' }
                });
                const c = document.getElementById('messagesContainer');
                c.scrollTop = c.scrollHeight;
            })
            .catch(function (e) {
                notify('Voice message not sent', e.message || 'Please check your connection and try again.');
            })
            .finally(function () { btn.disabled = false; });
        }
    })();




    let typingTimeout = null;
    document.getElementById('messageInput').addEventListener('input', () => {
        if (!currentConversationId || currentConversationId === 'new') return;
        
        if (!typingTimeout) {
            fetch(`{{ url('/chat') }}/typing/${currentConversationId}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            }).catch(e => {});
        }
        
        clearTimeout(typingTimeout);
        typingTimeout = setTimeout(() => {
            typingTimeout = null;
        }, 2000);
    });

    function scrollToBottom() {
        const container = document.getElementById('messagesContainer');
        container.scrollTop = container.scrollHeight;
    }

    /**
     * The conversation to send into, creating it first when this is a person the
     * user has never written to.
     *
     * A direct chat opened from the people list does not exist on the server until
     * something is sent to it. Typing a message created it on the way past, but
     * voice notes did not know that and refused with "Choose a conversation
     * first" — so both go through here now.
     *
     * @returns {Promise<number|string|null>} the id, or null if there is nowhere to send
     */
    async function ensureConversation() {
        if (currentConversationId && currentConversationId !== 'new') {
            return currentConversationId;
        }

        if (currentConversationId !== 'new' || !targetUserId) {
            return null;
        }

        try {
            const res = await fetch('{{ url('/chat') }}/conversation/create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ type: 'direct', user_ids: [targetUserId] })
            });
            const conv = await res.json();
            if (!res.ok || !conv.id) throw new Error(conv.error || 'could not start the chat');
            currentConversationId = conv.id;
            return currentConversationId;
        } catch (e) {
            if (typeof showToast === 'function') showToast('Chat', 'Could not start this conversation: ' + e.message);
            return null;
        }
    }

    document.getElementById('messageForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const input = document.getElementById('messageInput');
        const fileInput = document.getElementById('attachmentInput');
        
        const body = input.value.trim();
        const file = fileInput.files[0];
        
        if (!body && !file) return;

        // Reset inputs
        input.value = '';
        clearAttachment();
        document.getElementById('emojiPickerContainer').style.display = 'none';

        if (!(await ensureConversation())) return;

        // Send message
        try {
            const formData = new FormData();
            if (body) formData.append('body', body);
            if (file) formData.append('attachment', file);

            const res = await fetch(`{{ url('/chat') }}/${currentConversationId}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, // Do not set Content-Type for FormData
                body: formData
            });
            const msg = await res.json();
            if (res.ok) {
                appendMessage(msg);
                scrollToBottom();
            } else {
                alert(msg.error || 'Failed to send message');
            }
        } catch (e) {
            console.error(e);
            alert('An error occurred: ' + e.message);
        }
    });

    document.getElementById('newChannelForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const name = document.getElementById('channelName').value;
        const checkboxes = document.querySelectorAll('input[name="channel_users[]"]:checked');
        const user_ids = Array.from(checkboxes).map(cb => cb.value);

        try {
            const res = await fetch('{{ url('/chat') }}/conversation/create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ type: 'group', name, user_ids })
            });
            if (res.ok) {
                window.location.reload();
            } else {
                let errorMsg = 'Failed to create channel';
                try {
                    const data = await res.json();
                    if (data.errors && data.errors.name) {
                        errorMsg = data.errors.name[0];
                    } else if (data.message) {
                        errorMsg = data.message;
                    } else if (data.error) {
                        errorMsg = data.error;
                    }
                } catch(err) {}
                alert(errorMsg);
            }
        } catch(e) {
            console.error(e);
        }
    });

    // Attachment Preview Logic
    document.getElementById('attachmentInput').addEventListener('change', function() {
        if (this.files.length > 0) {
            document.getElementById('attachmentPreview').style.display = 'block';
            document.getElementById('attachmentName').innerText = this.files[0].name;
        } else {
            clearAttachment();
        }
    });

    function clearAttachment() {
        document.getElementById('attachmentInput').value = '';
        document.getElementById('attachmentPreview').style.display = 'none';
        document.getElementById('attachmentName').innerText = '';
    }

    // Emoji Logic
    function toggleEmojiPicker() {
        const p = document.getElementById('emojiPickerContainer');
        p.style.display = p.style.display === 'block' ? 'none' : 'block';
    }
    
    document.querySelector('emoji-picker').addEventListener('emoji-click', event => {
        const input = document.getElementById('messageInput');
        input.value += event.detail.unicode;
        toggleEmojiPicker();
        input.focus();
    });


    function openInfoChannelModal(membersStr) {
        const list = document.getElementById('infoChannelMembersList');
        list.innerHTML = '';
        if(membersStr) {
            membersStr.split(',').forEach(member => {
                const m = member.trim();
                if(m) {
                    list.innerHTML += `<div class="member-item"><i class="fas fa-user-circle" style="color:var(--primary); font-size:1.2rem;"></i> ${m}</div>`;
                }
            });
        } else {
            list.innerHTML = '<div class="member-item">No members found.</div>';
        }
        document.getElementById('infoChannelModal').classList.add('active');
    }

    // Channel Edit/Delete
    function openEditChannelModal() {
        let name = document.getElementById('currentChatName').innerText.replace('# ', '').trim();
        document.getElementById('editChannelName').value = name;
        
        document.querySelectorAll('.edit-user-checkbox').forEach(cb => {
            cb.checked = currentChannelMemberIds.includes(parseInt(cb.value));
        });
        
        document.getElementById('editChannelModal').classList.add('active');
    }

    document.getElementById('editChannelForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const name = document.getElementById('editChannelName').value;
        const checkboxes = document.querySelectorAll('input[name="edit_channel_users[]"]:checked');
        const user_ids = Array.from(checkboxes).map(cb => cb.value);
        
        try {
            const res = await fetch(`{{ url('/chat') }}/conversation/${currentConversationId}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ name, user_ids })
            });
            if (res.ok) {
                window.location.reload();
            } else {
                let errorMsg = 'Failed to update channel.';
                try {
                    const data = await res.json();
                    if (data.errors && data.errors.name) {
                        errorMsg = data.errors.name[0];
                    } else if (data.message) {
                        errorMsg = data.message;
                    } else if (data.error) {
                        errorMsg = data.error;
                    }
                } catch(err) {}
                alert(errorMsg);
            }
        } catch(e) { console.error(e); }
    });

    async function leaveChannel() {
        if (!confirm('Leave this channel? You will stop seeing its messages.')) return;

        try {
            const res = await fetch(`{{ url('/chat') }}/conversation/${currentConversationId}/leave`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            });

            if (res.ok) {
                window.location.href = '{{ route('chat.index') }}';
                return;
            }

            const data = await res.json().catch(() => ({}));
            alert(data.error || 'Could not leave that channel.');
        } catch (e) {
            alert('Could not leave that channel.');
        }
    }

    async function deleteChannel() {
        customConfirm(
            'Delete Channel',
            'Are you sure you want to delete this channel? All messages will be permanently lost.',
            async () => {
                try {
                    const res = await fetch(`{{ url('/chat') }}/conversation/${currentConversationId}`, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                    });
                    if (res.ok) window.location.reload();
                    else alert('Failed to delete channel.');
                } catch(e) { console.error(e); }
            }
        );
    }

    /*
     * Online / offline dots beside each person. The page renders them once; this
     * keeps them current. Presence comes from users.last_seen_at, which every open
     * dashboard tab refreshes, so a dot turns green within seconds of someone
     * opening the CRM and red a couple of minutes after they close it.
     */
    async function refreshPresence() {
        try {
            const res = await fetch('{{ route('chat.presence') }}', { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            const online = new Set((data.online || []).map(String));
            document.querySelectorAll('.chat-channel-btn[data-user-id]').forEach(function (btn) {
                const dot = btn.querySelector('.status-dot');
                if (!dot) return;
                const on = online.has(String(btn.dataset.userId));
                dot.classList.toggle('status-online', on);
                dot.classList.toggle('status-offline', !on);
                dot.title = on ? 'Online' : 'Offline';
            });
        } catch (e) { /* next tick will try again */ }
    }
    refreshPresence();
    setInterval(refreshPresence, 10000);

    // Advanced Polling for real-time updates and unread badges
    let syncTick = 0;
    setInterval(async () => {
        if (isPolling) return;
        isPolling = true;
        // Poll for unread counts
        try {
            const pollRes = await fetch('{{ url('/chat') }}/api/poll');
            const convs = await pollRes.json();
            
            let totalUnread = 0;
            let activeChatHasUnread = false;
            
            convs.forEach(c => {
                totalUnread += c.unread_count;
                
                if (!isInitialLocalPoll && c.unread_count > (previousChatCountsLocal[c.id] || 0)) {
                    if (c.id != currentConversationId || document.hidden) {
                        if (typeof showToast === 'function') {
                            showToast('New Chat Message', `New message in ${c.type === 'group' ? '#' : ''}${c.name}`, 'info');
                        }
                    }
                }
                previousChatCountsLocal[c.id] = c.unread_count;

                let btn = document.getElementById('channel-btn-' + c.id);
                
                // If it's a group channel and button doesn't exist, dynamically add it
                if (!btn && c.type === 'group') {
                    const ul = document.getElementById('group-channels-list');
                    if (ul) {
                        const li = document.createElement('li');
                        const safeName = c.name ? c.name.replace(/'/g, "\\'") : '';
                        li.innerHTML = `<button id="channel-btn-${c.id}" data-members="${c.member_names}" data-member-ids='${c.member_ids}' onclick="loadConversation(${c.id}, '${safeName}', false, null, ${c.created_by}, this)" class="chat-channel-btn">
                                            <span># ${c.name}</span>
                                        </button>`;
                        ul.appendChild(li);
                        btn = li.querySelector('button');
                    }
                }
                
                if (btn) {
                    let badge = btn.querySelector('.unread-badge');
                    // Do not show badge for currently opened chat to avoid flicker
                    if (c.unread_count > 0 && currentConversationId != c.id) {
                        if (!badge) {
                            badge = document.createElement('span');
                            badge.className = 'unread-badge';
                            btn.appendChild(badge);
                        }
                        badge.innerText = c.unread_count > 99 ? '99+' : c.unread_count;
                        badge.title = c.unread_count + ' unread';
                        btn.classList.add('has-unread');
                    } else {
                        if (badge) badge.remove();
                        btn.classList.remove('has-unread');
                    }
                }

                if (c.id == currentConversationId) {
                    if (c.unread_count > 0) activeChatHasUnread = true;
                    
                    const typingIndicator = document.getElementById('typingIndicator');
                    const span = typingIndicator.querySelector('span');
                    if (c.typing_users && c.typing_users.length > 0) {
                        span.innerText = c.typing_users.join(', ') + ' is typing...';
                        typingIndicator.style.display = 'block';
                    } else {
                        typingIndicator.style.display = 'none';
                    }

                    if (c.max_last_read_at) {
                        const maxReadTime = new Date(c.max_last_read_at).getTime();
                        document.querySelectorAll('.msg-mine').forEach(msg => {
                            const ts = msg.getAttribute('data-timestamp');
                            if (ts) {
                                const msgTime = new Date(ts).getTime();
                                if (maxReadTime >= msgTime) {
                                    const tick = msg.querySelector('.tick-icon');
                                    if (tick && tick.classList.contains('fa-check')) {
                                        tick.classList.remove('fa-check');
                                        tick.classList.add('fa-check-double');
                                        tick.style.color = '#ffffff';
                                    }
                                }
                            }
                        });
                    }
                }
            });
            
            isInitialLocalPoll = false;
            
            // Sync deleted groups
            const fetchedConvIds = convs.map(c => c.id.toString());
            document.querySelectorAll('#group-channels-list li').forEach(li => {
                const btn = li.querySelector('button');
                if (btn && btn.id.startsWith('channel-btn-')) {
                    const id = btn.id.replace('channel-btn-', '');
                    if (!fetchedConvIds.includes(id)) {
                        li.remove();
                    }
                }
            });

            // If current conversation was deleted, clear UI
            if (currentConversationId && currentConversationId !== 'new' && !fetchedConvIds.includes(currentConversationId.toString())) {
                currentConversationId = null;
                document.getElementById('currentChatName').innerText = 'Select a conversation';
                document.getElementById('chatInputArea').style.display = 'none';
                document.getElementById('messagesContainer').innerHTML = '<div class="chat-empty"><i class="fas fa-comments"></i><div>Choose a channel or person to start chatting.</div></div>';
                document.querySelectorAll('.chat-channel-btn').forEach(b => b.classList.remove('active'));
                
                ['infoChannelBtn', 'editChannelBtn', 'deleteChannelBtn', 'leaveChannelBtn'].forEach(id => {
                    const btn = document.getElementById(id);
                    if (btn) btn.style.display = 'none';
                });
            }
            
            // If current conversation history was cleared, clear UI
            if (currentConversationId && currentConversationId !== 'new') {
                const currentConv = convs.find(c => c.id == currentConversationId);
                if (currentConv && currentConv.messages_count === 0) {
                    const container = document.getElementById('messagesContainer');
                    const currentMsgCount = container.querySelectorAll('.msg-wrapper').length;
                    if (currentMsgCount > 0) {
                        container.innerHTML = '<div class="chat-empty"><i class="fas fa-hand-sparkles"></i><div>No messages yet. Say hi!</div></div>';
                    }
                }
            }
            
            const chatBadge = document.getElementById('globalChatBadge');
            if (chatBadge) {
                if (totalUnread > 0) {
                    chatBadge.style.display = 'inline-flex';
                    chatBadge.textContent = totalUnread > 9 ? '9+' : totalUnread;
                } else {
                    chatBadge.style.display = 'none';
                }
            }

            // Refresh the open conversation. Straight away when something unread
            // has arrived; otherwise every other tick, which is what picks up
            // edits, deletions and reactions made by other people — those never
            // create "unread" and never change the message count.
            syncTick++;
            if (currentConversationId && currentConversationId !== 'new' &&
                (activeChatHasUnread || syncTick % 2 === 0)) {
                const container = document.getElementById('messagesContainer');

                // Never re-render under someone mid-action: it would throw away a
                // half-written edit or snap shut the reaction picker.
                if (!container.querySelector('.msg-edit-box, .react-menu.is-open')) {
                    const convAtRequest = currentConversationId;
                    const res = await fetch(`{{ url('/chat') }}/${convAtRequest}`, { headers: { 'Accept': 'application/json' } });
                    const messages = await res.json();

                    // The user may have switched conversation while we waited.
                    if (convAtRequest === currentConversationId && Array.isArray(messages)) {
                        const rows = [...container.querySelectorAll('.msg-wrapper')];
                        const domSig = rows.map(r => r.dataset.sig || '').join('|');
                        const serverSig = messages.map(msgSig).join('|');

                        if (serverSig !== domSig || container.querySelector('.chat-empty')) {
                            const nearBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 80;
                            const keepTop = container.scrollTop;

                            // The thread normally scrolls smoothly. Restoring the
                            // position after a rebuild must be instant, or the reader
                            // watches the list animate up from the top each time
                            // someone reacts to something.
                            const prevBehaviour = container.style.scrollBehavior;
                            container.style.scrollBehavior = 'auto';

                            container.innerHTML = '';
                            if (messages.length === 0) {
                                container.innerHTML = '<div class="chat-empty"><i class="fas fa-hand-sparkles"></i><div>No messages yet. Say hi!</div></div>';
                            } else {
                                messages.forEach(msg => appendMessage(msg));
                                // Follow new messages only if the reader was already at
                                // the bottom; a reaction or edit elsewhere must not yank
                                // someone who has scrolled up to read history.
                                if (nearBottom) container.scrollTop = container.scrollHeight;
                                else container.scrollTop = keepTop;
                            }

                            container.style.scrollBehavior = prevBehaviour;
                        }
                    }
                }
            }
        } catch(e) {
            console.error('Polling error:', e);
        } finally {
            isPolling = false;
        }
    }, 3000);
</script>
@endpush
