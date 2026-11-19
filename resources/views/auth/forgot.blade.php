@extends('auth.shell')

@section('title', 'Forgotten password')

@section('content')
    <h1>Forgotten your password?</h1>
    <p class="subtitle">
        Type the email address on your account and we will send you a link to set
        a new one. The link lasts an hour.
    </p>

    <form action="{{ route('password.email') }}" method="POST">
        @csrf
        <div class="form-group">
            <label>Email address</label>
            <div class="input-wrapper">
                <i class="far fa-envelope"></i>
                <input type="email" name="email" placeholder="you@company.com" required
                       value="{{ old('email') }}" autofocus>
            </div>
            <div class="hint">
                The address your account was set up with — not your username.
            </div>
        </div>

        <button type="submit" class="btn">
            Send me a link <i class="fas fa-paper-plane"></i>
        </button>
    </form>

    <a href="{{ route('login') }}" class="back">
        <i class="fas fa-arrow-left"></i> Back to sign in
    </a>
@endsection
