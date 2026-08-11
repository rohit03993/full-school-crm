<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $instituteName }}</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f8fafc; color: #0f172a; }
        .card { max-width: 28rem; padding: 2rem; text-align: center; }
        h1 { font-size: 1.5rem; margin: 0 0 0.75rem; }
        p { color: #475569; line-height: 1.5; margin: 0; }
        a { color: #c2410c; font-weight: 600; }
    </style>
</head>
<body>
    <div class="card">
        <h1>{{ $instituteName }}</h1>
        <p>The public website is not enabled for this institute. Staff can sign in to the admin panel.</p>
        <p style="margin-top: 1.25rem;"><a href="{{ url('/admin') }}">Go to admin</a>
            · <a href="{{ url('/portal/login') }}">Student portal</a></p>
    </div>
</body>
</html>
