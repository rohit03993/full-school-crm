<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Portal — {{ config('institute.name', config('app.name')) }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-navy-950 text-white">
    <div class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-12">
        <div class="text-center">
            <p class="text-sm font-semibold uppercase tracking-wider text-brand-400">{{ config('institute.name', config('app.name')) }}</p>
            <h1 class="mt-2 font-display text-3xl font-bold">Student Portal</h1>
            <p class="mt-2 text-sm text-navy-200">Login with mobile + date of birth (DDMMYYYY)</p>
        </div>

        <form method="POST" action="{{ route('portal.login.submit') }}" class="mt-10 space-y-5 rounded-3xl bg-white p-6 text-navy-900 shadow-xl">
            @csrf

            <div>
                <label for="mobile" class="mb-1.5 block text-sm font-semibold">Mobile Number</label>
                <input type="tel" name="mobile" id="mobile" value="{{ old('mobile') }}" maxlength="10" required
                    class="w-full rounded-xl border border-navy-200 px-4 py-3" placeholder="10-digit mobile">
                @error('mobile')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="password" class="mb-1.5 block text-sm font-semibold">Date of Birth (DDMMYYYY)</label>
                <input type="password" name="password" id="password" maxlength="8" inputmode="numeric" required
                    class="w-full rounded-xl border border-navy-200 px-4 py-3" placeholder="e.g. 15052000">
                @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <button type="submit" class="w-full rounded-xl bg-brand-500 py-3.5 font-bold text-navy-950 hover:bg-brand-400">
                Login
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-navy-300">
            <a href="{{ route('home') }}" class="underline hover:text-white">← Back to website</a>
        </p>
    </div>
</body>
</html>
