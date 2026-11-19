@php
    $primaryCompany = \App\Models\Company::first();
    $companyName = $primaryCompany->name ?? 'Systematic IT Solutions';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') | {{ $companyName }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon-sis.png') }}">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /*
            The shell the two password pages share.

            Deliberately its own file rather than a refactor of the login page:
            that page is six hundred lines with an animation in it, and the way
            to add a forgot-password screen is not to risk the screen everybody
            already uses. The colours and type are taken from it so the three
            read as one place.
        */
        :root {
            --primary: #f99d4e;
            --primary-dark: #e88c3d;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --error: #ef4444;
            --ok: #10b981;
            --navy: #070c17;
            --navy-2: #0d1526;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }

        html, body { height: 100%; }

        body {
            background: radial-gradient(circle at 20% 15%, var(--navy-2) 0%, var(--navy) 60%);
            display: flex; align-items: center; justify-content: center;
            padding: 2rem 1rem; color: var(--text-main);
        }

        .card {
            width: 100%; max-width: 420px;
            background: #fff; border-radius: 20px;
            padding: 2.4rem 2.2rem;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.35);
        }

        .mark {
            font-size: 1.05rem; font-weight: 700; letter-spacing: -0.02em;
            color: var(--navy); margin-bottom: 1.6rem; display: flex; align-items: center; gap: 9px;
        }
        .mark i { color: var(--primary); }
        .mark img { max-height: 34px; width: auto; }

        h1 { font-size: 1.45rem; font-weight: 700; letter-spacing: -0.02em; margin-bottom: 0.4rem; }
        .subtitle { color: var(--text-muted); font-size: 0.9rem; line-height: 1.6; margin-bottom: 1.6rem; }

        .form-group { margin-bottom: 1.1rem; }
        label { display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 7px; color: var(--text-main); }

        .input-wrapper { position: relative; }
        .input-wrapper > i {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            color: var(--text-muted); font-size: 0.9rem;
        }
        input[type=email], input[type=password] {
            width: 100%; padding: 0.85rem 1rem 0.85rem 2.6rem;
            border: 1px solid #e2e8f0; border-radius: 12px;
            font-size: 0.95rem; background: #f8fafc; color: var(--text-main);
            transition: border-color 0.2s, background 0.2s;
        }
        input:focus { outline: none; border-color: var(--primary); background: #fff; }

        .btn {
            width: 100%; padding: 0.9rem 1rem; border: none; border-radius: 12px;
            background: var(--primary); color: #fff; font-size: 0.95rem; font-weight: 600;
            cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 9px;
            transition: background 0.2s, transform 0.15s;
        }
        .btn:hover { background: var(--primary-dark); transform: translateY(-1px); }

        .note {
            padding: 0.85rem 1rem; border-radius: 12px; font-size: 0.86rem;
            line-height: 1.6; margin-bottom: 1.3rem; display: flex; gap: 10px; align-items: flex-start;
        }
        .note.bad { background: rgba(239, 68, 68, 0.08); color: #b91c1c; }
        .note.good { background: rgba(16, 185, 129, 0.09); color: #047857; }
        .note i { margin-top: 2px; }

        .back { display: block; text-align: center; margin-top: 1.4rem; font-size: 0.86rem; color: var(--text-muted); text-decoration: none; }
        .back:hover { color: var(--primary); }

        .hint { font-size: 0.78rem; color: var(--text-muted); margin-top: 6px; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="card">
        <div class="mark">
            @if($primaryCompany?->logo && file_exists(public_path($primaryCompany->logo)))
                <img src="{{ url('file/' . $primaryCompany->logo) }}" alt="{{ $companyName }}">
            @else
                <i class="fas fa-shield-halved"></i> {{ $companyName }}
            @endif
        </div>

        @if(session('status'))
            <div class="note good">
                <i class="fas fa-circle-check"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div class="note bad">
                <i class="fas fa-circle-exclamation"></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        @yield('content')
    </div>
</body>
</html>
