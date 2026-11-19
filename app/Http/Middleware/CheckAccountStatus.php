<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAccountStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $user = auth()->user();

            if (!$user->is_active) {
                return response()->view('disabled', ['message' => $user->disable_message]);
            }

            if ($user->company && !$user->company->is_active) {
                return response()->view('disabled', ['message' => $user->company->disable_message]);
            }

            if ($user->isPendingApproval()) {
                return response()->view('pending-approval');
            }

            // Check Maintenance Mode
            if (!$user->isSuperAdmin()) {
                $maintenance = \App\Models\SystemSetting::where('key', 'maintenance_mode')->first();
                if ($maintenance && $maintenance->value === '1') {
                    return response()->view('maintenance');
                }
            }
        }

        return $next($request);
    }
}
