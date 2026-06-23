<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify Student — {{ config('institute.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gray-50 text-gray-900 antialiased">
    <main class="mx-auto flex min-h-screen max-w-lg items-center px-4 py-10">
        <div class="w-full overflow-hidden rounded-2xl bg-white shadow-lg ring-1 ring-gray-950/5">
            <div class="bg-gradient-to-r from-amber-600 to-amber-500 px-6 py-5 text-white">
                <p class="text-xs font-semibold uppercase tracking-widest opacity-90">Student verification</p>
                <h1 class="mt-1 text-xl font-bold">{{ config('institute.name') }}</h1>
            </div>

            <div class="space-y-4 px-6 py-6">
                <div class="rounded-xl bg-emerald-50 px-4 py-3 ring-1 ring-emerald-200">
                    <p class="text-sm font-semibold text-emerald-800">Valid enrolled student</p>
                </div>

                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Student name</dt>
                        <dd class="mt-0.5 font-semibold text-gray-950">{{ $student->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ \App\Support\StudentLabels::rollNumberLabel() }}</dt>
                        <dd class="mt-0.5 font-mono font-semibold text-amber-700">{{ $enrollment->enrollment_number }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Course</dt>
                        <dd class="mt-0.5 font-semibold text-gray-950">{{ $course?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Enrolled on</dt>
                        <dd class="mt-0.5 font-semibold text-gray-950">{{ $enrollment->enrolled_at?->format('d M Y') ?? '—' }}</dd>
                    </div>
                </dl>

                <p class="text-xs text-gray-500">
                    This page confirms the QR code on a student ID card matches our records.
                </p>
            </div>
        </div>
    </main>
</body>
</html>
