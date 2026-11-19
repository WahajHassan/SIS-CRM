{{--
    The announcements board.

    It sits beside the time tracker for everybody who clocks in, and beside the
    global figures for the owner, who does not. It needs $announcements and
    $canPost from the page including it.
--}}
<!-- Announcements -->
<div class="card dash-side" id="announcementCard" style="margin-bottom: 0; border-radius: 20px; padding: 1.5rem;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 1rem; margin-bottom: 1.1rem;">
        <div class="card-title" style="display: flex; align-items: center; gap: 8px; font-size: 1.05rem; font-weight: 700; color: var(--text-main);">
            <i class="fas fa-bullhorn" style="color: var(--primary);"></i> Announcements
        </div>
        @if($canPost)
            <button type="button" class="ann-new" onclick="annToggleForm()" title="Post an announcement">
                <i class="fas fa-plus"></i> New
            </button>
        @endif
    </div>

    @if($canPost)
        {{-- Posting happens here and the board updates itself; nobody waits for
             the whole dashboard to come back for three lines of text. --}}
        <form id="annForm" class="ann-form" hidden onsubmit="annPost(event)">
            @csrf
            <input type="text" name="title" id="annTitle" placeholder="What is the announcement?" maxlength="120" required>
            <textarea name="body" id="annBody" rows="3" maxlength="1000" placeholder="A little more detail (optional)"></textarea>
            <div class="ann-form-row">
                <label><input type="checkbox" name="is_pinned" id="annPinned" value="1"> Pin to the top</label>
                <label><input type="checkbox" name="notify" id="annNotify" value="1" checked> Also ring the bell</label>
            </div>
            <div class="ann-form-row">
                <button type="submit" class="btn btn-primary" id="annSubmit" style="cursor: pointer; font-weight: 600;">
                    <i class="fas fa-paper-plane"></i> Post
                </button>
                <button type="button" class="ann-cancel" onclick="annToggleForm()">Cancel</button>
            </div>
        </form>
    @endif

    <div class="ann-list" id="annList">
        @forelse($announcements as $announcement)
            <div class="ann" data-id="{{ $announcement->id }}">
                <div class="ann-top">
                    <b>@if($announcement->is_pinned)<i class="fas fa-thumbtack" title="Pinned"></i> @endif{{ $announcement->title }}</b>
                    @if($canPost)
                        <button type="button" class="ann-remove" title="Take it down" onclick="annRemove({{ $announcement->id }}, this)">
                            <i class="fas fa-xmark"></i>
                        </button>
                    @endif
                </div>
                @if($announcement->body)
                    <p>{{ $announcement->body }}</p>
                @endif
                <small>{{ $announcement->author->name ?? 'Management' }} &middot; {{ $announcement->created_at->diffForHumans() }}</small>
            </div>
        @empty
            <p class="ann-empty" id="annEmpty">Nothing announced yet.</p>
        @endforelse
    </div>
</div>
