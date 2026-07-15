<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Staff OTP Login — {{ $institute['name'] ?? config('app.name') }}</title>

    @if (! empty($institute['favicon_url']))
        <link rel="icon" href="{{ $institute['favicon_url'] }}" type="image/png">
    @else
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @endif

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700|playfair-display:500,600,700" rel="stylesheet">

    @include('partials.vite-assets', ['assets' => ['resources/css/app.css', 'resources/js/app.js']])
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
                <h1 class="mt-2 font-display text-3xl font-bold sm:text-4xl">Staff OTP Login</h1>
                <p class="mx-auto mt-3 max-w-xs text-sm leading-relaxed text-navy-300">
                    Sign in with a 4-digit code sent to your WhatsApp. Password login still works as usual.
                </p>
            </div>

            <div class="mt-8 space-y-4 rounded-3xl border border-white/10 bg-white p-5 text-navy-900 shadow-2xl sm:mt-10 sm:p-6">
                @unless ($otpAvailable ?? false)
                    <p class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                        WhatsApp OTP login is not configured yet. Ask an admin to enable Meta WhatsApp and set the OTP template under WhatsApp setup.
                    </p>
                    <a href="{{ $passwordLoginUrl }}" class="block w-full rounded-xl bg-navy-900 py-3.5 text-center text-base font-bold text-white">
                        Use password login
                    </a>
                @else
                    @if (session('otp_success'))
                        <p class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ session('otp_success') }}</p>
                    @endif

                    @unless ($otpSent ?? false)
                        <form method="POST" action="{{ route('staff.otp-login.send') }}" class="space-y-4">
                            @csrf
                            <div>
                                <label for="mobile" class="mb-1.5 block text-sm font-semibold text-navy-800">Staff mobile number</label>
                                <input type="tel" name="mobile" id="mobile" value="{{ old('mobile', $otpMobile) }}" maxlength="14" required autocomplete="tel"
                                    inputmode="numeric"
                                    class="portal-input" placeholder="10-digit mobile or +91…">
                                @error('mobile')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <button type="submit" class="touch-manipulation w-full rounded-xl bg-brand-500 py-3.5 text-base font-bold text-navy-950 shadow-lg transition hover:bg-brand-400">
                                Send 4-digit OTP
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('staff.otp-login.verify') }}" class="space-y-4">
                            @csrf
                            <input type="hidden" name="mobile" value="{{ old('mobile', $otpMobile) }}">
                            <p class="text-sm text-navy-600">Code sent to <strong>{{ old('mobile', $otpMobile) }}</strong> on WhatsApp.</p>
                            <div>
                                <label for="otp" class="mb-1.5 block text-sm font-semibold text-navy-800">4-digit OTP</label>
                                <input type="text" name="otp" id="otp" maxlength="4" required autocomplete="one-time-code"
                                    inputmode="numeric" pattern="\d{4}"
                                    class="portal-input tracking-[0.4em] text-center text-lg font-semibold" placeholder="••••">
                                @error('otp')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <label class="flex items-center gap-2 text-sm text-navy-700">
                                <input type="checkbox" name="remember" value="1" class="rounded border-navy-300">
                                Remember me
                            </label>
                            <button type="submit" class="touch-manipulation w-full rounded-xl bg-brand-500 py-3.5 text-base font-bold text-navy-950 shadow-lg transition hover:bg-brand-400">
                                Verify &amp; sign in
                            </button>
                        </form>
                        <form method="POST" action="{{ route('staff.otp-login.send') }}" class="mt-3">
                            @csrf
                            <input type="hidden" name="mobile" value="{{ old('mobile', $otpMobile) }}">
                            <button type="submit" class="w-full text-sm font-semibold text-brand-700 hover:text-brand-800">Resend OTP</button>
                        </form>
                    @endunless
                @endunless
            </div>

            <nav class="mt-8 flex flex-col items-center gap-3 text-sm text-navy-300 sm:flex-row sm:justify-center sm:gap-6">
                <a href="{{ $passwordLoginUrl }}" class="font-medium transition hover:text-white">Password login</a>
                <a href="{{ route('login') }}" class="font-medium transition hover:text-white">Other login options</a>
            </nav>
        </div>
    </div>
</body>
</html>
