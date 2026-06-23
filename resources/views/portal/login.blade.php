<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0a1929">
    <title>Student Portal — {{ $institute['name'] ?? config('app.name') }}</title>

    @if (! empty($institute['favicon_url']))
        <link rel="icon" href="{{ $institute['favicon_url'] }}" type="image/png">
    @endif

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700|playfair-display:500,600,700" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-navy-950 text-white antialiased">
    <div class="relative flex min-h-screen flex-col">
        <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            <div class="absolute -left-20 top-0 h-72 w-72 rounded-full bg-brand-500/10 blur-3xl"></div>
            <div class="absolute -right-16 bottom-20 h-64 w-64 rounded-full bg-brand-400/10 blur-3xl"></div>
        </div>

        <div class="relative mx-auto flex w-full max-w-md flex-1 flex-col justify-center px-4 py-10 sm:py-14">
            <div class="text-center">
                @if (! empty($institute['logo_url']))
                    <img src="{{ $institute['logo_url'] }}" alt="" class="mx-auto mb-4 h-14 w-auto object-contain">
                @endif
                <p class="text-xs font-bold uppercase tracking-widest text-brand-400">{{ $institute['name'] ?? config('app.name') }}</p>
                <h1 class="mt-2 font-display text-3xl font-bold sm:text-4xl">Student Portal</h1>
                <p class="mx-auto mt-3 max-w-xs text-sm leading-relaxed text-navy-300">{{ $loginHint ?? 'Login with mobile and password' }}</p>
            </div>

            <form method="POST" action="{{ route('portal.login.submit') }}" class="mt-8 space-y-4 rounded-3xl border border-white/10 bg-white p-5 text-navy-900 shadow-2xl sm:mt-10 sm:p-6">
                @csrf

                <div>
                    <label for="mobile" class="mb-1.5 block text-sm font-semibold text-navy-800">Mobile number</label>
                    <input type="tel" name="mobile" id="mobile" value="{{ old('mobile') }}" maxlength="14" required autocomplete="tel"
                        inputmode="numeric"
                        class="portal-input" placeholder="10-digit mobile or +91…">
                    @error('mobile')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="password" class="mb-1.5 block text-sm font-semibold text-navy-800">Portal password</label>
                    <div class="relative">
                        <input type="password" name="password" id="password" maxlength="64" required autocomplete="current-password"
                            class="portal-input pr-12" placeholder="Institute default password">
                        <button type="button" id="toggle-password" class="absolute right-3 top-1/2 -translate-y-1/2 rounded-lg p-1.5 text-navy-400 hover:text-navy-700" aria-label="Show password">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                    @error('password')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="touch-manipulation w-full rounded-xl bg-brand-500 py-3.5 text-base font-bold text-navy-950 shadow-lg transition hover:bg-brand-400 active:scale-[0.99]">
                    Sign in
                </button>
            </form>

            <nav class="mt-8 flex flex-col items-center gap-3 text-sm text-navy-300 sm:flex-row sm:justify-center sm:gap-6">
                <a href="{{ route('login') }}" class="font-medium transition hover:text-white">Other login options</a>
                <a href="{{ route('home') }}" class="font-medium transition hover:text-white">← Back to website</a>
            </nav>
        </div>
    </div>

    <script>
        document.getElementById('toggle-password')?.addEventListener('click', function () {
            const input = document.getElementById('password');
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            this.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        });
    </script>
</body>
</html>
