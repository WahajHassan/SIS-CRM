<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Maintenance</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f3f4f6;
            font-family: 'Inter', sans-serif;
        }
        .container {
            text-align: center;
            background: white;
            padding: 3rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            max-width: 500px;
            width: 90%;
        }
        .icon {
            font-size: 4rem;
            color: #f59e0b;
            margin-bottom: 1rem;
        }
        h1 {
            color: #111827;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        p {
            color: #4b5563;
            line-height: 1.5;
            margin-bottom: 2rem;
        }
        .logout-btn {
            display: inline-block;
            background-color: #3b82f6;
            color: white;
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        .logout-btn:hover {
            background-color: #2563eb;
        }
    </style>
    <!-- Include FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <i class="fas fa-tools icon"></i>
        <h1>System Under Maintenance</h1>
        <p>
            We are upgrading our servers, please check back in 10 minutes. 
            We apologize for any inconvenience.
        </p>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="logout-btn" style="border: none; cursor: pointer;">
                Return to Login
            </button>
        </form>
    </div>
</body>
</html>
