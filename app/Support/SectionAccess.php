<?php

namespace App\Support;

/**
 * Works out which section of the admin area a route belongs to.
 *
 * The middleware uses this to decide whether to let a request through, and the
 * controllers use it as a second check on the way in. Both read the same map in
 * config/permissions.php, so a section added there is picked up everywhere at once
 * and the two can never disagree about what a route is.
 */
class SectionAccess
{
    /**
     * The section a route name belongs to, or null when no section claims it.
     */
    public static function sectionForRoute(?string $routeName): ?string
    {
        if (!$routeName) {
            return null;
        }

        foreach (config('permissions.sections', []) as $key => $section) {
            if (self::matches($routeName, $section['routes'] ?? [])) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Whether a route is one of the handful everybody signed in may reach.
     */
    public static function isAlwaysAllowed(?string $routeName): bool
    {
        return $routeName !== null
            && self::matches($routeName, config('permissions.always_allowed', []));
    }

    /**
     * Patterns are route names, optionally ending in * to cover a whole family.
     */
    public static function matches(string $routeName, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($routeName === $pattern) {
                return true;
            }

            if (str_ends_with($pattern, '*') && str_starts_with($routeName, rtrim($pattern, '*'))) {
                return true;
            }

            if (str_contains($pattern, '*') && fnmatch($pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }
}
