<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $institute }}</title>
    <style>
        body { margin: 0; font-family: sans-serif; background: #fafaf9; color: #1c1917; }
        main { max-width: 26rem; margin: 4rem auto; padding: 1.25rem; background: #fff; border: 1px solid #e7e5e4; border-radius: 0.9rem; }
        h1 { margin: 0 0 0.4rem; font-size: 1.35rem; }
        p { margin: 0 0 1rem; color: #57534e; line-height: 1.45; }
        label { display: block; margin-bottom: 0.75rem; font-size: 0.85rem; font-weight: 650; }
        input { display: block; width: 100%; margin-top: 0.3rem; box-sizing: border-box; padding: 0.5rem 0.65rem; border: 1px solid #e7e5e4; border-radius: 0.6rem; font: inherit; }
        button { border: 0; border-radius: 0.65rem; background: #ea580c; color: #fff; padding: 0.55rem 0.9rem; font: inherit; font-weight: 650; cursor: pointer; }
        .note { padding: 0.7rem 0.8rem; border-radius: 0.6rem; background: #fff7ed; }
    </style>
</head>
<body>
<main>
    <h1>{{ $institute }}</h1>
    @if ($notice)
        <p class="note">{{ $notice }}</p>
    @else
        <p>Leave your name and WhatsApp number. This does not create a student record.</p>
    @endif
    @if (! $saved)
        <form method="post" action="{{ route('marketing.join.store') }}">
            @csrf
            <label>Name<input type="text" name="name" maxlength="120" required></label>
            <label>WhatsApp number<input type="tel" name="mobile" inputmode="numeric" maxlength="15" required></label>
            <button type="submit">Join the list</button>
        </form>
    @endif
</main>
</body>
</html>
