@extends('auth.shell')

@section('title', 'Set a new password')

@section('content')
    <h1>Set a new password</h1>
    <p class="subtitle">
        Pick something you have not used here before. At least 8 characters.
    </p>

    <form action="{{ route('password.update') }}" method="POST">
        @csrf
        {{-- The token is what proves this came from the emailed link, and the
             address is what it was sent to. Neither is for the person to change. --}}
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="form-group">
            <label>Email address</label>
            <div class="input-wrapper">
                <i class="far fa-envelope"></i>
                <input type="email" name="email" required value="{{ old('email', $email) }}" readonly
                       style="background: #f1f5f9; color: var(--text-muted);">
            </div>
        </div>

        <div class="form-group">
            <label>New password</label>
            <div class="input-wrapper">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" placeholder="••••••••" required autofocus>
            </div>
        </div>

        <div class="form-group">
            <label>Type it again</label>
            <div class="input-wrapper">
                <i class="fas fa-lock"></i>
                <input type="password" name="password_confirmation" placeholder="••••••••" required>
            </div>
        </div>

        <button type="submit" class="btn">
            Set my password <i class="fas fa-check"></i>
        </button>
    </form>

    <a href="{{ route('login') }}" class="back">
        <i class="fas fa-arrow-left"></i> Back to sign in
    </a>
@endsection
