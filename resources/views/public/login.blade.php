@extends('layouts.public')

@section('content')
    <section class="bg-navy-50 py-16 sm:py-24">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <p class="text-sm font-semibold uppercase tracking-wider text-brand-600">Sign in</p>
                <h1 class="mt-3 font-display text-3xl font-bold text-navy-900 sm:text-4xl">Login to your account</h1>
            <p class="mt-4 text-base text-navy-600">
                Choose how you want to sign in — staff use the CRM; students use the portal.
            </p>

            @if (session('portal_unavailable'))
                <p class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    {{ session('portal_unavailable') }}
                </p>
            @endif
            </div>

            <div class="mt-12 grid gap-6 sm:grid-cols-2">
                <a
                    href="{{ $staffLoginUrl }}"
                    class="group flex flex-col rounded-3xl border border-navy-100 bg-white p-8 shadow-sm transition hover:border-brand-300 hover:shadow-lg"
                >
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-100 text-brand-700">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                        </svg>
                    </div>
                    <h2 class="mt-6 text-xl font-bold text-navy-900">Staff &amp; Admin</h2>
                    <p class="mt-2 flex-1 text-sm leading-relaxed text-navy-600">
                        CRM login for owners, counsellors, admission staff, accountants, and coordinators.
                    </p>
                    <p class="mt-4 text-sm font-semibold text-brand-700 group-hover:text-brand-800">
                        Mobile + password →
                    </p>
                </a>

                <a
                    href="{{ $studentLoginUrl }}"
                    class="group flex flex-col rounded-3xl border border-navy-100 bg-white p-8 shadow-sm transition hover:border-brand-300 hover:shadow-lg"
                >
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-navy-100 text-navy-700">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5" />
                        </svg>
                    </div>
                    <h2 class="mt-6 text-xl font-bold text-navy-900">Student Portal</h2>
                    <p class="mt-2 flex-1 text-sm leading-relaxed text-navy-600">
                        View fees, marks, admission status, receipts, and ID card.
                    </p>
                    <p class="mt-4 text-sm font-semibold text-brand-700 group-hover:text-brand-800">
                        Student mobile + password (not roll number) →
                    </p>
                </a>
            </div>

            <p class="mt-10 text-center text-sm text-navy-500">
                New enquiry?
                <a href="{{ route('contact') }}" class="font-semibold text-brand-700 hover:text-brand-800">Contact us</a>
            </p>
        </div>
    </section>
@endsection
