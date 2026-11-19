<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\SystemLog;
use App\Models\Training;
use App\Models\TrainingComment;
use App\Models\TrainingView;
use App\Models\User;
use App\Notifications\SystemAnnouncement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The training room.
 *
 * Whoever runs it puts up a recording, a voice note or something written, aimed
 * at a department or at everybody. The team watches it, asks about it, and the
 * answers stay underneath where the next person will find them.
 */
class TrainingController extends Controller
{
    /** As big as a recording may get, however it arrives. */
    private const MAX_UPLOAD_BYTES = 300 * 1024 * 1024;

    /**
     * What a recording may actually be.
     *
     * The same few formats under every name a browser or a phone gives them: a
     * wav arrives as audio/wav from one and audio/x-wav from another, and
     * refusing the second is refusing the same file twice.
     */
    private const ALLOWED_MEDIA = [
        'video/mp4', 'video/webm', 'video/quicktime', 'video/x-matroska', 'video/x-msvideo',
        'audio/mpeg', 'audio/mp3', 'audio/mp4', 'audio/x-m4a', 'audio/aac', 'audio/ogg',
        'audio/wav', 'audio/x-wav', 'audio/vnd.wave', 'audio/webm', 'audio/3gpp',
    ];

    /** The list, as a page or as the feed the dashboard polls. */
    public function index(Request $request)
    {
        $this->authorizeSection();

        $me = auth()->user();

        $trainings = Training::for($me)
            ->with(['author', 'department'])
            ->withCount('comments')
            ->newestFirst()
            ->limit(50)
            ->get();

        if ($request->expectsJson()) {
            return response()->json([
                'trainings' => $trainings->map(fn (Training $t) => $t->forFeed($me))->values(),
                'can_manage' => Training::canManage($me),
            ]);
        }

        return view('admin.training.index', [
            'trainings' => $trainings,
            'departments' => Department::active()->orderBy('name')->get(),
            'canManage' => Training::canManage($me),
            'open' => $request->input('open'),
        ]);
    }

    /** One piece of training, with everything said about it. */
    public function show(Request $request, $id)
    {
        $this->authorizeSection();

        $me = auth()->user();
        $training = Training::for($me)->with(['author', 'department'])->findOrFail($id);

        // Opening it is watching it, as far as "has the team seen this" goes.
        if ($me) {
            TrainingView::updateOrCreate(
                ['training_id' => $training->id, 'user_id' => $me->id],
                ['watched_at' => now()]
            );
        }

        $comments = $training->comments()->with('user')->get();

        if ($request->expectsJson()) {
            return response()->json([
                'training' => array_merge($training->forFeed($me), [
                    'body_html' => $training->bodyHtml(),
                    'media_src' => $training->mediaSrc(),
                    'embed_url' => $training->embedUrl(),
                    'is_file' => $training->isPlayableFile(),
                    'mime' => $training->media_mime,
                    'department' => $training->department?->name,
                    'watched_by' => $training->views()->count(),
                ]),
                'comments' => $comments->map(fn (TrainingComment $c) => $c->forFeed($me))->values(),
            ]);
        }

        return redirect()->route('admin.training', ['open' => $training->id]);
    }

    /**
     * A recording, sent up a piece at a time.
     *
     * Shared hosting refuses a request bigger than a few megabytes, and a screen
     * recording is nothing like a few megabytes — such a request is rejected by
     * the server before any of this code runs, which looks to whoever is
     * uploading like nothing happened at all. Sent in half-megabyte pieces, every
     * request stays small and the file is put back together here.
     *
     * The same path serves a file somebody picked and a recording made in the
     * browser a moment ago; by the time it reaches here they are the same thing.
     */
    public function chunk(Request $request)
    {
        abort_unless(Training::canManage(auth()->user()), 403, 'Only HR and admins can upload training.');

        $request->validate([
            'upload_id' => 'required|string|max:64',
            'index' => 'required|integer|min:0',
            'total' => 'required|integer|min:1|max:4000',
            'extension' => 'required|string|max:8',
            'chunk' => 'required|file|max:4096',
        ]);

        // The id names a file on disk, so it is cut down to characters that
        // cannot walk out of the directory it belongs in.
        $uploadId = preg_replace('/[^a-zA-Z0-9]/', '', $request->input('upload_id'));
        $extension = preg_replace('/[^a-zA-Z0-9]/', '', $request->input('extension')) ?: 'webm';

        abort_if(strlen($uploadId) < 8, 422, 'That upload could not be identified.');

        $folder = storage_path('app/training-uploads/' . auth()->id());

        if (!is_dir($folder)) {
            mkdir($folder, 0775, true);
        }

        $part = $folder . '/' . $uploadId . '.part';
        $index = (int) $request->input('index');
        $total = (int) $request->input('total');

        // Starting again overwrites whatever was half sent last time.
        if ($index === 0 && is_file($part)) {
            unlink($part);
        }

        if (is_file($part) && filesize($part) > self::MAX_UPLOAD_BYTES) {
            unlink($part);
            abort(422, 'That recording is larger than this allows.');
        }

        file_put_contents($part, file_get_contents($request->file('chunk')->getRealPath()), FILE_APPEND);

        if ($index + 1 < $total) {
            return response()->json(['success' => true, 'received' => $index + 1, 'of' => $total]);
        }

        // The last piece: check what was actually assembled before keeping it.
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($part);

        if (!in_array($mime, self::ALLOWED_MEDIA, true)) {
            unlink($part);

            return response()->json([
                'success' => false,
                'message' => 'That is not a recording this accepts (' . $mime . ').',
            ], 422);
        }

        $name = 'training/' . Str::random(40) . '.' . $extension;
        Storage::disk('public')->put($name, fopen($part, 'r'));
        unlink($part);

        return response()->json([
            'success' => true,
            'path' => $name,
            'mime' => $mime,
            'size' => Storage::disk('public')->size($name),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(Training::canManage(auth()->user()), 403, 'Only HR and admins can put training up.');

        $data = $request->validate([
            'title' => 'required|string|max:160',
            'kind' => 'required|in:video,voice,text',
            'body' => 'nullable|string|max:20000',
            'media_url' => 'nullable|url|max:500',
            // Whatever the server's own upload limit allows; a recording that is
            // too big for it goes up wherever it already lives and comes in as a
            // link instead.
            'media' => 'nullable|file|mimetypes:' . implode(',', self::ALLOWED_MEDIA) . '|max:204800',
            // Already up here, a piece at a time, before this form was sent.
            'uploaded_path' => 'nullable|string|max:255',
            'uploaded_mime' => 'nullable|string|max:100',
            'department_id' => 'nullable|exists:departments,id',
            'is_pinned' => 'nullable|boolean',
            'notify' => 'nullable|boolean',
        ]);

        if ($data['kind'] !== 'text' && !$request->hasFile('media')
            && empty($data['media_url']) && empty($data['uploaded_path'])) {
            return back()->with('error', 'A recording needs either a file or a link to where it is.');
        }

        $training = new Training([
            'title' => $data['title'],
            'kind' => $data['kind'],
            'body' => $data['body'] ?? null,
            'media_url' => $data['media_url'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'is_pinned' => $request->boolean('is_pinned'),
            'is_published' => true,
            'created_by' => auth()->id(),
        ]);

        if ($request->hasFile('media')) {
            $file = $request->file('media');
            $training->media_path = $file->store('training', 'public');
            $training->media_mime = $file->getMimeType();
        } elseif (!empty($data['uploaded_path'])) {
            // Sent in pieces and checked on arrival; all that is left is to be
            // sure it is one of ours and still there.
            $path = $data['uploaded_path'];

            abort_unless(Str::startsWith($path, 'training/') && Storage::disk('public')->exists($path),
                422, 'That upload could not be found any more.');

            $training->media_path = $path;
            $training->media_mime = $data['uploaded_mime'] ?? null;
        }

        $training->save();

        $this->tellTheTeam($training);

        SystemLog::log('Added Training', "Put up training: {$training->title}");

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'training' => $training->fresh('author')->forFeed(auth()->user())]);
        }

        return back()->with('success', 'Training is up. The team can see it now.');
    }

    public function destroy(Request $request, $id)
    {
        abort_unless(Training::canManage(auth()->user()), 403, 'Only HR and admins can take training down.');

        $training = Training::findOrFail($id);

        // The file goes with it; leaving recordings behind on a shared host adds
        // up quickly and nothing points at them any more.
        if ($training->media_path) {
            Storage::disk('public')->delete($training->media_path);
        }

        $title = $training->title;
        $training->delete();

        SystemLog::log('Removed Training', "Took down training: {$title}");

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Training removed.');
    }

    /** Somebody asking about it, or answering somebody else. */
    public function comment(Request $request, $id)
    {
        $this->authorizeSection();

        $data = $request->validate(['body' => 'required|string|max:2000']);

        $training = Training::for(auth()->user())->findOrFail($id);

        $comment = TrainingComment::create([
            'training_id' => $training->id,
            'user_id' => auth()->id(),
            'body' => $data['body'],
        ]);

        // Whoever put it up should know a question has been asked.
        if ($training->author && $training->author->id !== auth()->id()) {
            $training->author->notify(new SystemAnnouncement([
                'title' => 'A question on your training',
                'message' => auth()->user()->display_name . ' commented on "' . $training->title . '".',
                'icon' => 'fas fa-comment',
                'type' => 'info',
                'url' => route('admin.training', ['open' => $training->id]),
            ]));
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'comment' => $comment->fresh('user')->forFeed(auth()->user()),
            ]);
        }

        return back()->with('success', 'Posted.');
    }

    public function removeComment(Request $request, $id)
    {
        $this->authorizeSection();

        $comment = TrainingComment::findOrFail($id);

        abort_unless($comment->canBeRemovedBy(auth()->user()), 403, 'That comment is not yours to remove.');

        $comment->delete();

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Comment removed.');
    }

    /** Everything said about one piece of training, for the panel's own polling. */
    public function comments(Request $request, $id)
    {
        $this->authorizeSection();

        $training = Training::for(auth()->user())->findOrFail($id);
        $me = auth()->user();

        return response()->json([
            'comments' => $training->comments()->with('user')->get()
                ->map(fn (TrainingComment $c) => $c->forFeed($me))->values(),
        ]);
    }

    /** Who has watched it — the question whoever put it up actually asks. */
    public function watchers(Request $request, $id)
    {
        abort_unless(Training::canManage(auth()->user()), 403, 'Unauthorized action.');

        $training = Training::findOrFail($id);

        $watched = TrainingView::where('training_id', $training->id)
            ->with('user')
            ->get()
            ->map(fn (TrainingView $v) => [
                'name' => $v->user->display_name ?? 'Someone',
                'when' => $v->watched_at?->diffForHumans(),
            ]);

        $seenIds = TrainingView::where('training_id', $training->id)->pluck('user_id');

        $notYet = User::where('role', '!=', 'superadmin')
            ->whereNotIn('id', $seenIds)
            ->when($training->department_id, function ($q) use ($training) {
                $q->whereIn('id', \App\Models\EmployeeProfile::where('department_id', $training->department_id)->pluck('user_id'));
            })
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => ['name' => $u->display_name]);

        return response()->json(['watched' => $watched, 'not_yet' => $notYet]);
    }

    /**
     * Tell the people it is for.
     *
     * The bell rings for them; the panel on their dashboard picks it up on its
     * own a moment later either way.
     */
    private function tellTheTeam(Training $training): void
    {
        $recipients = User::where('company_id', $training->company_id)
            ->where('id', '!=', auth()->id())
            ->when($training->department_id, function ($q) use ($training) {
                $q->whereIn('id', \App\Models\EmployeeProfile::where('department_id', $training->department_id)->pluck('user_id'));
            })
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        \Illuminate\Support\Facades\Notification::send($recipients, new SystemAnnouncement([
            'title' => 'New training',
            'message' => $training->title,
            'icon' => 'fas fa-graduation-cap',
            'type' => 'info',
            'url' => route('admin.training', ['open' => $training->id]),
        ]));
    }
}
