<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /** How many wrong passwords before the door closes. */
    private const TRIES_ALLOWED = 5;

    /** And for how long it stays closed. */
    private const LOCKED_OUT_FOR_SECONDS = 60;

    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required'],
        ]);

        // A password could be guessed forever, as fast as a script could ask.
        // Five tries, then a minute's wait that doubles — slow enough that
        // guessing stops being worth doing, short enough that somebody who has
        // simply mistyped theirs is not locked out of their working day.
        $this->mustNotBeGuessing($request);

        $login_type = filter_var($request->input('login'), FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $request->merge([
            $login_type => $request->input('login')
        ]);

        $credentials = $request->only($login_type, 'password');

        if (Auth::attempt($credentials)) {
            $user = Auth::user();

            if (!$user->is_active) {
                $message = $user->disable_message ?: 'Your account has been disabled. Please contact the administrator.';
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return response()->view('disabled', ['message' => $message]);
            }

            if ($user->company && !$user->company->is_active) {
                $message = $user->company->disable_message ?: 'Your company account has been disabled. Please contact the administrator.';
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return response()->view('disabled', ['message' => $message]);
            }

            RateLimiter::clear($this->attemptKey($request));

            $request->session()->regenerate();
            return redirect()->intended('admin');
        }

        // Counted only on a failure, so somebody signing in and out all day is
        // never near the limit.
        RateLimiter::hit($this->attemptKey($request), self::LOCKED_OUT_FOR_SECONDS);

        return back()->withErrors([
            'login' => 'The provided credentials do not match our records.',
        ])->onlyInput('login');
    }

    /**
     * Stop a guesser without stopping the person who fumbled their password.
     *
     * Keyed on the address being tried *and* where it is being tried from, so
     * one account under attack cannot lock everybody else out, and one machine
     * cannot work through a list of accounts either.
     */
    private function mustNotBeGuessing(Request $request): void
    {
        $key = $this->attemptKey($request);

        if (!RateLimiter::tooManyAttempts($key, self::TRIES_ALLOWED)) {
            return;
        }

        $wait = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'login' => $wait > 60
                ? 'Too many attempts. Try again in ' . ceil($wait / 60) . ' minutes.'
                : 'Too many attempts. Try again in ' . $wait . ' seconds.',
        ]);
    }

    private function attemptKey(Request $request): string
    {
        return 'login:' . Str::lower((string) $request->input('login')) . '|' . $request->ip();
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}
