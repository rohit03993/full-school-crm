@extends('layouts.public')

@php
    $whatsappUrl = filled($institute['whatsapp'])
        ? 'https://wa.me/'.$institute['whatsapp'].'?text='.urlencode('Hello, I would like to enquire about courses at '.$institute['name'].'.')
        : null;
@endphp

@section('content')
    <section class="border-b border-navy-100 bg-navy-950 py-12 text-white sm:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <p class="text-sm font-semibold uppercase tracking-wider text-brand-400">Get in Touch</p>
            <h1 class="mt-3 font-display text-3xl font-bold sm:text-5xl">Contact Us</h1>
            <p class="mt-4 max-w-2xl text-base text-navy-200 sm:text-lg">
                Submit an enquiry online or visit our campus. Our admissions team will contact you shortly.
            </p>
        </div>
    </section>

    <section class="py-12 sm:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-10 lg:grid-cols-5 lg:gap-16">
                <div class="lg:col-span-2">
                    <h2 class="font-display text-2xl font-bold text-navy-900">Visit or call</h2>
                    <p class="mt-4 text-navy-600">
                        Walk-ins are welcome during office hours. You can also submit the enquiry form — we will call you back.
                    </p>

                    <dl class="mt-10 space-y-8">
                        <div>
                            <dt class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-navy-400">
                                <svg class="h-5 w-5 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                                </svg>
                                Phone
                            </dt>
                            <dd class="mt-2">
                                <a href="tel:{{ preg_replace('/\s+/', '', $institute['phone']) }}" class="text-lg font-semibold text-navy-900 hover:text-brand-700">
                                    {{ $institute['phone'] }}
                                </a>
                            </dd>
                        </div>

                        <div>
                            <dt class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-navy-400">
                                <svg class="h-5 w-5 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                </svg>
                                Email
                            </dt>
                            <dd class="mt-2">
                                <a href="mailto:{{ $institute['email'] }}" class="break-all text-lg font-semibold text-navy-900 hover:text-brand-700">
                                    {{ $institute['email'] }}
                                </a>
                            </dd>
                        </div>

                        <div>
                            <dt class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-navy-400">
                                <svg class="h-5 w-5 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                                </svg>
                                Address
                            </dt>
                            <dd class="mt-2 text-lg text-navy-800">
                                {{ $institute['address'] }}
                                @if (filled($institute['city']))
                                    <br>{{ $institute['city'] }}
                                @endif
                            </dd>
                        </div>

                        <div>
                            <dt class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-navy-400">
                                <svg class="h-5 w-5 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Office Hours
                            </dt>
                            <dd class="mt-2 text-lg text-navy-800">{{ $institute['hours'] }}</dd>
                        </div>
                    </dl>

                    @if ($whatsappUrl)
                        <a
                            href="{{ $whatsappUrl }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-10 inline-flex min-h-[48px] w-full items-center justify-center gap-2 rounded-xl bg-[#25D366] px-6 py-3.5 font-semibold text-white sm:w-auto"
                        >
                            WhatsApp Us
                        </a>
                    @endif
                </div>

                <div class="lg:col-span-3">
                    <div class="rounded-3xl border border-navy-100 bg-white p-6 shadow-sm sm:p-8 lg:p-10">
                        <h2 class="font-display text-xl font-bold text-navy-900 sm:text-2xl">Online Enquiry Form</h2>
                        <p class="mt-2 text-sm text-navy-600 sm:text-base">
                            Fill in your details and course interest. Your enquiry will be registered in our system instantly.
                        </p>

                        @if (session('enquiry_success'))
                            <div class="mt-6 rounded-2xl border border-green-200 bg-green-50 p-5 text-green-900">
                                <p class="font-semibold">Thank you, {{ session('enquiry_success.name') }}!</p>
                                <p class="mt-2 text-sm">
                                    Your enquiry has been submitted successfully.
                                    <span class="font-mono font-semibold">{{ session('enquiry_success.number') }}</span>
                                </p>
                                <p class="mt-2 text-sm text-green-800">Our team will contact you on your mobile number shortly.</p>
                            </div>
                        @endif

                        <form action="{{ route('contact.enquiry') }}" method="POST" class="mt-8 space-y-5">
                            @csrf

                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="name" class="mb-1.5 block text-sm font-semibold text-navy-800">Full Name *</label>
                                    <input type="text" name="name" id="name" value="{{ old('name') }}" required
                                        class="w-full rounded-xl border border-navy-200 px-4 py-3 text-navy-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>

                                <div>
                                    <label for="father_name" class="mb-1.5 block text-sm font-semibold text-navy-800">Father's Name *</label>
                                    <input type="text" name="father_name" id="father_name" value="{{ old('father_name') }}" required
                                        class="w-full rounded-xl border border-navy-200 px-4 py-3 text-navy-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    @error('father_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>

                                <div>
                                    <label for="mobile" class="mb-1.5 block text-sm font-semibold text-navy-800">Mobile Number *</label>
                                    <input type="tel" name="mobile" id="mobile" value="{{ old('mobile') }}" required maxlength="10" inputmode="numeric" pattern="[6-9][0-9]{9}"
                                        placeholder="10-digit mobile"
                                        class="w-full rounded-xl border border-navy-200 px-4 py-3 text-navy-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    @error('mobile')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>

                                <div>
                                    <label for="email" class="mb-1.5 block text-sm font-semibold text-navy-800">Email</label>
                                    <input type="email" name="email" id="email" value="{{ old('email') }}"
                                        class="w-full rounded-xl border border-navy-200 px-4 py-3 text-navy-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>

                                <div>
                                    <label for="date_of_birth" class="mb-1.5 block text-sm font-semibold text-navy-800">Date of Birth *</label>
                                    <input type="date" name="date_of_birth" id="date_of_birth" value="{{ old('date_of_birth') }}" required
                                        class="w-full rounded-xl border border-navy-200 px-4 py-3 text-navy-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    @error('date_of_birth')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>

                                <div>
                                    <label for="gender" class="mb-1.5 block text-sm font-semibold text-navy-800">Gender *</label>
                                    <select name="gender" id="gender" required
                                        class="w-full rounded-xl border border-navy-200 px-4 py-3 text-navy-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                        <option value="">Select gender</option>
                                        @foreach (\App\Enums\Gender::cases() as $gender)
                                            <option value="{{ $gender->value }}" @selected(old('gender') === $gender->value)>{{ $gender->label() }}</option>
                                        @endforeach
                                    </select>
                                    @error('gender')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>

                                <div class="sm:col-span-2">
                                    <label for="course_id" class="mb-1.5 block text-sm font-semibold text-navy-800">Course Interest *</label>
                                    <select name="course_id" id="course_id" required
                                        class="w-full rounded-xl border border-navy-200 px-4 py-3 text-navy-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                        <option value="">Select a course</option>
                                        @foreach ($courses as $course)
                                            <option value="{{ $course->id }}" @selected(old('course_id') == $course->id)>
                                                {{ $course->name }} — {{ $course->duration_label }}
                                                @if ((float) $course->fee > 0)
                                                    ({{ $course->formatted_fee }})
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('course_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>

                                <div>
                                    <label for="city" class="mb-1.5 block text-sm font-semibold text-navy-800">City</label>
                                    <input type="text" name="city" id="city" value="{{ old('city') }}"
                                        class="w-full rounded-xl border border-navy-200 px-4 py-3 text-navy-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    @error('city')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>

                                <div class="sm:col-span-2">
                                    <label for="message" class="mb-1.5 block text-sm font-semibold text-navy-800">Message / Questions</label>
                                    <textarea name="message" id="message" rows="3" placeholder="Any questions about admission, fees, or batches..."
                                        class="w-full rounded-xl border border-navy-200 px-4 py-3 text-navy-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">{{ old('message') }}</textarea>
                                    @error('message')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <button type="submit"
                                class="flex min-h-[52px] w-full items-center justify-center rounded-xl bg-brand-500 px-6 py-3.5 text-base font-bold text-navy-950 touch-manipulation transition hover:bg-brand-400">
                                Submit Enquiry
                            </button>

                            <p class="text-center text-xs text-navy-500">
                                By submitting, you agree to be contacted by {{ $institute['name'] }} regarding your enquiry.
                            </p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
