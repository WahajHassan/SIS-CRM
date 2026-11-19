<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

/*
 * Where the web-served files actually are.
 *
 * Normally that is public/ beside this folder. The shared-hosting layout this
 * CRM also ships to is flatter: the contents of public/ sit directly in the
 * site folder with the framework folders beneath them, so public_path() has to
 * be the base path itself.
 *
 * It used to be told which by a bootstrap/app.php the deploy script generated,
 * which meant this file could never be shipped in an update — and when it was,
 * public_path() silently went back to the default and every check that asks
 * "is the company logo on disk" started answering no. The login page fell back
 * to the company's name as text and nothing said why.
 *
 * Working it out here instead removes the trap. index.php is the one file that
 * is always in the web-served folder, whichever layout this is, so finding it
 * answers the question without anything having to be remembered.
 */
$base = dirname(__DIR__);
$public = file_exists($base . '/public/index.php') ? $base . '/public' : $base;

return Application::configure(basePath: $base)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            // First in the list because it only sets headers on the way back
            // out: whatever the rest of the stack decides, the response carries
            // them — including the error pages, which are the ones most likely
            // to be served to somebody probing.
            \App\Http\Middleware\GuardTheBrowser::class,
            \App\Http\Middleware\CheckAccountStatus::class,
            \App\Http\Middleware\CheckSectionAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create()
    // After create(), because this one belongs to the application rather than
    // to the builder that assembles it.
    ->usePublicPath($public);
