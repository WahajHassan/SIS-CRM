<?php

namespace App\Http\Controllers;

use App\Support\SectionAccess;

abstract class Controller
{
    /**
     * Refuses the request when the signed in user's switch for this section is off.
     *
     * CheckSectionAccess already does this for every route, so this is a second rail
     * rather than the only one. The section is read from the current route through
     * the same map the middleware uses, which is why it takes no argument and stays
     * correct as config/permissions.php grows.
     *
     * It replaced a set of hard coded role checks. Those said things like "not for
     * agents", which quietly overruled an admin who had switched a section on for one
     * particular person.
     */
    protected function authorizeSection(): void
    {
        $section = SectionAccess::sectionForRoute(request()->route()?->getName());

        if ($section === null || !auth()->check()) {
            return;
        }

        abort_if(!auth()->user()->canAccess($section), 403, 'Unauthorized action.');
    }
}
