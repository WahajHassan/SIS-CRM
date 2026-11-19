<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function fetch()
    {
        if (!auth()->check()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }
        // Trigger automated global breaks if the time matches
        \App\Http\Controllers\AttendanceController::checkAndTriggerAutomatedBreaks();

        // Run auto-checkout once every 5 minutes to prevent DB lockups from excessive polling
        if (!\Illuminate\Support\Facades\Cache::has('auto_checkout_run')) {
            \App\Models\Attendance::autoCheckoutExpired();
            \Illuminate\Support\Facades\Cache::put('auto_checkout_run', true, 300);
        }

        $user = auth()->user();
        
        return response()->json([
            'unread_count' => $user->unreadNotifications->count(),
            'notifications' => $user->notifications()->take(20)->get(),
            'is_on_break' => $user->todayAttendance() && $user->todayAttendance()->status === 'on_break'
        ]);
    }

    public function markAsRead($id)
    {
        if (!auth()->check()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $notification = auth()->user()->notifications()->find($id);
        
        if ($notification) {
            $notification->markAsRead();
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false], 404);
    }

    public function markAllAsRead()
    {
        if (!auth()->check()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        auth()->user()->unreadNotifications->markAsRead();

        return response()->json(['success' => true]);
    }

    public function deleteGlobal($id)
    {
        if (!auth()->check() || !auth()->user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $notification = \Illuminate\Support\Facades\DB::table('notifications')->where('id', $id)->first();
        
        if ($notification) {
            if (auth()->user()->isSuperAdmin()) {
                // Delete all notifications that match this specific announcement
                \Illuminate\Support\Facades\DB::table('notifications')
                    ->where('data', $notification->data)
                    ->where('created_at', $notification->created_at)
                    ->delete();
            } else {
                // Only delete notifications for users belonging to the admin's company
                $userIds = \App\Models\User::pluck('id')->toArray();
                \Illuminate\Support\Facades\DB::table('notifications')
                    ->whereIn('notifiable_id', $userIds)
                    ->where('notifiable_type', \App\Models\User::class)
                    ->where('data', $notification->data)
                    ->where('created_at', $notification->created_at)
                    ->delete();
            }
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false], 404);
    }
}
