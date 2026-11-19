<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') | {{ $companyName }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon-sis.png') }}">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: #f8fafc; color: #1e293b; line-height: 1.75; padding: 3rem 1.25rem; }
        .sheet { max-width: 760px; margin: 0 auto; background: #fff; padding: 3rem 3rem 2.4rem;
                 border-radius: 16px; box-shadow: 0 10px 40px rgba(15, 23, 42, 0.07); }
        .who { font-size: 0.82rem; font-weight: 600; color: #f99d4e; letter-spacing: 0.08em;
               text-transform: uppercase; margin-bottom: 0.6rem; }
        h1 { font-size: 1.9rem; font-weight: 700; letter-spacing: -0.02em; margin-bottom: 0.4rem; }
        .when { color: #64748b; font-size: 0.85rem; margin-bottom: 2.2rem; }
        h2 { font-size: 1.05rem; font-weight: 700; margin: 2rem 0 0.6rem; }
        p, li { color: #475569; font-size: 0.95rem; }
        ul { margin: 0.5rem 0 0 1.2rem; }
        li { margin-bottom: 0.4rem; }
        .note { background: #fffbeb; border-left: 3px solid #f59e0b; padding: 1rem 1.2rem;
                border-radius: 8px; margin: 1.6rem 0; font-size: 0.88rem; color: #92400e; }
        .foot { margin-top: 2.6rem; padding-top: 1.4rem; border-top: 1px solid #e2e8f0;
                font-size: 0.85rem; color: #64748b; display: flex; gap: 1.2rem; flex-wrap: wrap; }
        .foot a { color: #f99d4e; text-decoration: none; }
        @media (max-width: 640px) { .sheet { padding: 2rem 1.4rem; } }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="who">{{ $companyName }}</div>
        <h1>@yield('heading')</h1>
        <div class="when">Last updated {{ now()->format('j F Y') }}</div>

        @yield('content')

        <div class="foot">
            <a href="{{ route('legal.privacy') }}">Privacy</a>
            <a href="{{ route('legal.terms') }}">Terms</a>
            <a href="{{ url('/login') }}">Sign in</a>
        </div>
    </div>
</body>
</html>
