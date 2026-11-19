<?php

namespace App\Http\Middleware;

use App\Support\SectionAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSectionAccess
{
    /**
     * Turns a user's section switches into an actual block.
     *
     * Hiding a link in the sidebar is not access control; anyone can type the
     * address. This looks up which section the route being requested belongs to and
     * refuses the request when that section is switched off for the user, so the
     * sidebar and the enforcement can never disagree.
     *
     * The mapping lives in config/permissions.php, which means a new feature is
     * covered as soon as it is listed there.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        $route = $request->route();

        if (!$user || !$route) {
            return $next($request);
        }

        // The owner account reaches everything.
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $name = $route->getName();

        if (!$name || SectionAccess::isAlwaysAllowed($name)) {
            return $next($request);
        }

        $section = SectionAccess::sectionForRoute($name);

        // A route nothing claims is left to the controller's own checks.
        if ($section === null || $user->canAccess($section)) {
            return $next($request);
        }

        $label = config("permissions.sections.{$section}.label", $section);
        $message = "You do not have access to {$label}. Ask an administrator if you need it.";

        if ($request->expectsJson()) {
            return response()->json(['error' => $message], 403);
        }

        abort(403, $message);
    }
}
