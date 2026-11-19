<?php

namespace App\Http\Controllers;

use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * Getting back in.
 *
 * Somebody locked out of the CRM could only be rescued by an administrator
 * resetting their password by hand — which meant waiting for one to be awake,
 * and meant the administrator knew the new password. On a floor that runs
 * through the night, that is most of a shift lost.
 *
 * Two things this is careful about:
 *
 *   - It never says whether an address is on file. "If that address is on file
 *     we have sent a link" is the same answer either way, so the form cannot be
 *     used to find out who works here.
 *   - It is rate limited like the login is, because a form that sends email to
 *     a typed-in address is a way to send mail on somebody else's behalf.
 */
class PasswordResetController extends Controller
{
    private const ASKS_ALLOWED = 4;
    private const ASK_WINDOW_SECONDS = 600;

    /* ---- asking ------------------------------------------------------------- */

    public function askForm()
    {
        return view('auth.forgot');
    }

    public function sendLink(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        $this->mustNotBeFishing($request);

        $email = Str::lower($request->input('email'));

        // Only accounts that can actually sign in. A disabled or removed person
        // getting a working reset link would be a way back into a door somebody
        // deliberately closed.
        $user = User::where('email', $email)->where('is_active', true)->first();

        if ($user) {
            Password::sendResetLink(['email' => $user->email]);

            SystemLog::log('Password Reset Asked', "A reset link was sent to {$user->email}", $user);
        }

        RateLimiter::hit($this->askKey($request), self::ASK_WINDOW_SECONDS);

        // Said the same way whether or not anything was sent, so the form cannot
        // be used to find out who has an account here.
        return back()->with('status', 'If that address belongs to an account here, a link is on its way. It is good for one hour.');
    }

    /* ---- setting a new one --------------------------------------------------- */

    public function resetForm(Request $request, string $token)
    {
        return view('auth.reset', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $outcome = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));

                SystemLog::log('Password Reset', "{$user->display_name} set a new password from an emailed link", $user);
            }
        );

        if ($outcome !== Password::PASSWORD_RESET) {
            return back()->withErrors([
                'email' => $outcome === Password::INVALID_TOKEN
                    ? 'That link has been used already, or it has expired. Ask for a new one.'
                    : 'That did not work. Ask for a new link.',
            ]);
        }

        return redirect()->route('login')->with('status', 'Your password is set. Sign in with it.');
    }

    // ---- the plumbing ---------------------------------------------------------

    /**
     * A form that emails whatever address is typed into it is a way to send
     * mail on somebody else's behalf, so it is held to the same kind of limit
     * the login is.
     */
    private function mustNotBeFishing(Request $request): void
    {
        $key = $this->askKey($request);

        if (!RateLimiter::tooManyAttempts($key, self::ASKS_ALLOWED)) {
            return;
        }

        $wait = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'email' => 'Too many requests. Try again in ' . max(1, ceil($wait / 60)) . ' minute(s).',
        ]);
    }

    private function askKey(Request $request): string
    {
        return 'reset:' . $request->ip();
    }
}
