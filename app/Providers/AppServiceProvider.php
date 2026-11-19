<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Pagination\Paginator::useBootstrapFive();

        /*
         * Build every URL as https once the site is served over it.
         *
         * Behind a host's proxy, PHP often sees a plain HTTP request even though
         * the visitor arrived over TLS. Laravel then writes http:// into every
         * link it generates — which matters most for the one link that leaves
         * the building: the password reset email. A reset link over http is a
         * new password travelling in the clear.
         *
         * Decided from APP_URL rather than from the request, so it holds for
         * mail and queued work too, where there is no request to read.
         */
        if (str_starts_with((string) config('app.url'), 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        /*
         * @versioned('js/file.js') -> the asset URL with ?v=<last modified time>.
         *
         * The server sends JS with a one-week cache lifetime, so without this a
         * browser kept running last week's script after an update: the notepad's
         * dock buttons were live on the server but invisible until a hard refresh.
         * The file's modification time changes whenever it is replaced, which
         * changes the URL, which makes every browser fetch the new copy.
         */
        \Illuminate\Support\Facades\Blade::directive('versioned', function ($expression) {
            return "<?php echo e(asset({$expression}) . '?v=' . (@filemtime(public_path({$expression})) ?: '1')); ?>";
        });

        // Presence for the chat's online dots. Registered here rather than in
        // bootstrap/app.php because the production copy of that file is generated
        // by the deploy script for the subfolder layout and is not shipped in
        // updates.
        $this->app['router']->pushMiddlewareToGroup('web', \App\Http\Middleware\TouchLastSeen::class);
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Auth\Events\Login::class,
            \App\Listeners\LogSuccessfulLogin::class
        );
    }
}
