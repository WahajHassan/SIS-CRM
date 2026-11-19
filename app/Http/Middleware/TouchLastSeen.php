<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records that the signed-in user is around, for the chat's online dots.
 *
 * The dashboard polls notifications every few seconds on every page, so any
 * open tab keeps this fresh on its own. Writes are throttled to once per
 * THROTTLE_SECONDS per user, and go through the query builder so they neither
 * bump updated_at nor fire model events.
 */
class TouchLastSeen
{
    public const THROTTLE_SECONDS = 30;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        if ($user) {
            $last = $user->last_seen_at;
            if (!$last || $last->lt(now()->subSeconds(self::THROTTLE_SECONDS))) {
                DB::table('users')->where('id', $user->id)->update(['last_seen_at' => now()]);
            }
        }

        return $response;
    }
}
