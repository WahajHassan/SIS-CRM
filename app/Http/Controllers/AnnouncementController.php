<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\SystemAnnouncement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

/**
 * The board on the dashboard: what the office is telling everybody.
 *
 * Posting and removing answer in JSON as well as in a redirect, so the board can
 * update itself without the whole dashboard reloading — which is the point of a
 * board somebody glances at between calls.
 */
class AnnouncementController extends Controller
{
    /** The current board, newest first. */
    public function index()
    {
        $announcements = Announcement::board();

        return response()->json([
            'announcements' => $announcements->map->forFeed()->values(),
            'can_manage' => Announcement::canManage(auth()->user()),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(Announcement::canManage(auth()->user()), 403, 'Only HR and admins can post announcements.');

        $data = $request->validate([
            'title' => 'required|string|max:120',
            'body' => 'nullable|string|max:1000',
            'is_pinned' => 'nullable|boolean',
            'notify' => 'nullable|boolean',
        ]);

        $announcement = Announcement::create([
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'is_pinned' => $request->boolean('is_pinned'),
            'notified' => $request->boolean('notify'),
            'created_by' => auth()->id(),
        ]);

        // Pushing it also rings the bell, for the ones that should not wait to
        // be noticed on a dashboard.
        if ($request->boolean('notify')) {
            $recipients = User::where('company_id', auth()->user()->company_id)
                ->where('id', '!=', auth()->id())
                ->get();

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new SystemAnnouncement([
                    'title' => $announcement->title,
                    'message' => $announcement->body ?: 'A new announcement is on the dashboard.',
                    'icon' => 'fas fa-bullhorn',
                    'type' => 'info',
                ]));
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'announcement' => $announcement->fresh('author')->forFeed(),
            ]);
        }

        return back()->with('success', 'Announcement posted.');
    }

    public function destroy(Request $request, $id)
    {
        abort_unless(Announcement::canManage(auth()->user()), 403, 'Only HR and admins can remove announcements.');

        // Taken off the board rather than deleted, so what was announced and
        // when can still be answered later.
        Announcement::findOrFail($id)->update(['is_active' => false]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Announcement removed.');
    }
}
