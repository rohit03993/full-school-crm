@php
    $instituteName = $institute['name'] ?? config('institute.name', config('app.name'));
    $initials = collect(preg_split('/\s+/', $instituteName))
        ->filter()
        ->take(2)
        ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
        ->implode('') ?: 'SC';
@endphp
<footer class="border-t border-navy-100 bg-navy-950 text-navy-200">
    <div class="mx-auto max-w-7xl px-4 py-12 pb-6 sm:px-6 sm:py-14 lg:px-8 lg:pb-14">
        <div class="grid gap-10 md:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-500 text-sm font-bold text-white">{{ $initials }}</div>
                    <span class="font-display text-xl font-semibold text-white">{{ $institute['name'] }}</span>
                </div>
                <p class="mt-4 max-w-md text-sm leading-relaxed text-navy-300">
                    {{ $institute['tagline'] }}. Empowering students through quality education and coaching.
                </p>
            </div>

            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wider text-white">Quick Links</h3>
                <ul class="mt-4 space-y-2 text-sm">
                    <li><a href="{{ route('home') }}" class="transition hover:text-brand-400">Home</a></li>
                    <li><a href="{{ route('courses') }}" class="transition hover:text-brand-400">Courses</a></li>
                    <li><a href="{{ route('home') }}#gallery" class="transition hover:text-brand-400">Gallery</a></li>
                    <li><a href="{{ route('contact') }}" class="transition hover:text-brand-400">Contact</a></li>
                </ul>
            </div>

            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wider text-white">Contact</h3>
                <ul class="mt-4 space-y-3 text-sm">
                    <li>
                        <a href="tel:{{ preg_replace('/\s+/', '', $institute['phone']) }}" class="transition hover:text-brand-400">
                            {{ $institute['phone'] }}
                        </a>
                    </li>
                    <li>
                        <a href="mailto:{{ $institute['email'] }}" class="break-all transition hover:text-brand-400">
                            {{ $institute['email'] }}
                        </a>
                    </li>
                    <li class="text-navy-300">{{ $institute['address'] }}</li>
                </ul>
            </div>
        </div>

        <div class="mt-12 flex flex-col items-center justify-between gap-4 border-t border-navy-800 pt-8 text-sm text-navy-400 sm:flex-row">
            <p>&copy; {{ date('Y') }} {{ $institute['name'] }}. All rights reserved.</p>
            <p>Est. {{ $institute['established'] }}</p>
        </div>
    </div>
</footer>
