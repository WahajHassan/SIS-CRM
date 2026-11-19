@php
    $company = \App\Models\Company::first();
    $companyName = $company->name ?? 'Systematic IT Solutions';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('code') | {{ $companyName }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon-sis.png') }}">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /*
            What somebody sees when something has gone wrong.

            Laravel's own page says "Server Error" on a white background with a
            stack trace underneath if debug is on. The first tells a customer
            nothing; the second tells an attacker everything. This says what
            happened in a sentence and gives one way onward.
        */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        html, body { height: 100%; }
        body {
            background: radial-gradient(circle at 20% 15%, #0d1526 0%, #070c17 60%);
            color: #e2e8f0; display: flex; align-items: center; justify-content: center;
            padding: 2rem 1rem; text-align: center;
        }
        .box { max-width: 480px; }
        .mark { font-size: 2.6rem; color: #f99d4e; margin-bottom: 1.2rem; }
        .code { font-size: 3.6rem; font-weight: 700; letter-spacing: -0.04em; line-height: 1; color: #fff; }
        h1 { font-size: 1.25rem; font-weight: 600; margin: 0.9rem 0 0.5rem; color: #fff; }
        p { color: #94a3b8; font-size: 0.95rem; line-height: 1.7; }
        .go {
            display: inline-flex; align-items: center; gap: 9px; margin-top: 1.8rem;
            padding: 0.8rem 1.5rem; border-radius: 12px; background: #f99d4e; color: #fff;
            font-size: 0.92rem; font-weight: 600; text-decoration: none; transition: background 0.2s;
        }
        .go:hover { background: #e88c3d; }
        .who { margin-top: 2.4rem; font-size: 0.78rem; color: #475569; }
    </style>
</head>
<body>
    <div class="box">
        <div class="mark"><i class="@yield('icon', 'fas fa-triangle-exclamation')"></i></div>
        <div class="code">@yield('code')</div>
        <h1>@yield('heading')</h1>
        <p>@yield('said')</p>

        <a href="{{ url('/admin') }}" class="go">
            <i class="fas fa-arrow-left"></i> Back to the CRM
        </a>

        <div class="who">{{ $companyName }}</div>
    </div>
</body>
</html>
