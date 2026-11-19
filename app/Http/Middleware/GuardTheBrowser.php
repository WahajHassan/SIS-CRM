<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The handful of headers that tell a browser to defend the page.
 *
 * None of these change what the CRM does; each one closes an attack that works
 * by default. A CRM holding salaries, card details and a customer list should
 * not be the one application on the server that ships without them.
 *
 * Deliberately not a Content-Security-Policy. A real one would have to list
 * every inline script and style in a forty-screen application written over
 * months, and a CSP that is wrong is worse than none — it breaks the product
 * silently, in one browser, on one screen. That is its own piece of work.
 */
class GuardTheBrowser
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // A downloaded CSV that the browser decides is HTML would run as HTML.
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // The CRM must not be loadable inside somebody else's page, where a
        // transparent layer over it turns a customer's click into ours.
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // A lead's name should not travel to another site in the referer when
        // somebody follows a link out of the CRM.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Nothing here wants a camera, a microphone or a location, except the
        // huddle — which asks for the screen, and that is not on this list.
        $response->headers->set(
            'Permissions-Policy',
            'camera=(self), microphone=(self), geolocation=(self), payment=(), usb=()'
        );

        /*
         * And over HTTPS, tell the browser never to come back over HTTP.
         *
         * Only when the request already arrived securely: sent over plain HTTP
         * it is ignored, and sent from a local development server it would lock
         * the developer's own browser out of http://127.0.0.1 for a year.
         *
         * Six months, including subdomains. Not preloaded — that is a list you
         * cannot easily get off again, and it is not a decision to make inside
         * a middleware.
         */
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=15768000; includeSubDomains');
        }

        return $response;
    }
}
