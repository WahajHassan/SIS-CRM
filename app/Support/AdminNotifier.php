<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\SystemAnnouncement;
use Illuminate\Support\Facades\Notification;

class AdminNotifier
{
    /**
     * Pushes a database notification (bell + toast + sound, picked up by the
     * existing 3s poll in layouts/admin.blade.php) to the admins/floor managers
     * of a company, optionally adding extra roles (e.g. closers for break appeals)
     * and excluding the user who triggered the event so they don't get pinged
     * about their own action.
     */
    public static function notifyCompanyAdmins(int $companyId, array $data, array $extraRoles = [], ?int $excludeUserId = null): void
    {
        $roles = array_unique(array_merge(['admin', 'superadmin', 'floor_manager'], $extraRoles));

        $recipients = User::where('company_id', $companyId)
            ->whereIn('role', $roles)
            ->when($excludeUserId, fn ($q) => $q->where('id', '!=', $excludeUserId))
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new SystemAnnouncement($data));
    }
}
