@php
    /** @var bool $onboardingComplete */
    $steps = [
        [
            'title' => '1. Finish setup wizard',
            'body' => 'Set institute type, name, contact, and labels. Opens automatically on first Super Admin login.',
            'menu' => 'Setup Wizard',
            'url' => \App\Filament\Pages\FirstRunSetup::getUrl(),
            'done' => $onboardingComplete,
        ],
        [
            'title' => '2. Website branding',
            'body' => 'Logo, favicon, phone, email, homepage text, gallery.',
            'menu' => 'Website → Site Content',
            'url' => \App\Filament\Pages\ManageSiteContent::getUrl(),
            'done' => null,
        ],
        [
            'title' => '3. Terminology',
            'body' => 'Rename Course, Batch, Roll number to match your institute (Class, Section, Reg. No., etc.).',
            'menu' => 'Settings → Terminology',
            'url' => \App\Filament\Pages\ManageTerminology::getUrl(),
            'done' => null,
        ],
        [
            'title' => '4. Custom student fields',
            'body' => 'Optional extra fields on student profiles — blood group, transport, scholarship, etc.',
            'menu' => 'Settings → Custom Fields',
            'url' => \App\Filament\Pages\ManageCustomFields::getUrl(),
            'done' => null,
        ],
        [
            'title' => '5. Academic session',
            'body' => 'Create the current year (e.g. 2025–26) and mark it current.',
            'menu' => 'Academics → Academic Sessions',
            'url' => \App\Filament\Resources\AcademicSessions\AcademicSessionResource::getUrl(),
            'done' => null,
        ],
        [
            'title' => '6. Courses / programmes',
            'body' => 'Add every class, programme, or degree you offer. Shown on the public website.',
            'menu' => 'Academics → Courses',
            'url' => \App\Filament\Resources\Courses\CourseResource::getUrl(),
            'done' => null,
        ],
        [
            'title' => '7. Batches / sections',
            'body' => 'Group students by batch, section, or semester under each course.',
            'menu' => 'Academics → Batches',
            'url' => \App\Filament\Resources\Batches\BatchResource::getUrl(),
            'done' => null,
        ],
        [
            'title' => '8. Receipts & PDF logo',
            'body' => 'Receipt footer and document branding for fees and ID cards.',
            'menu' => 'Settings → Institute Settings',
            'url' => \App\Filament\Pages\ManageInstituteSettings::getUrl(),
            'done' => null,
        ],
        [
            'title' => '9. WhatsApp (optional)',
            'body' => 'Pal Digital integration key, sync templates, campaign batch settings.',
            'menu' => 'Settings → WhatsApp Settings',
            'url' => \App\Filament\Pages\ManageWhatsAppSettings::getUrl(),
            'done' => null,
        ],
    ];
@endphp

<div class="rounded-xl border border-primary-200 bg-primary-50/80 p-6 dark:border-primary-500/20 dark:bg-primary-500/5">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-primary-700 dark:text-primary-300">Setup checklist</p>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                Configure the CRM for your school, coaching, or college — all from admin, no code changes.
            </p>
        </div>
        <a
            href="{{ \App\Filament\Pages\SetupGuide::getUrl() }}"
            class="text-sm font-semibold text-primary-700 underline decoration-primary-300 underline-offset-2 hover:text-primary-800 dark:text-primary-300"
        >
            Full setup guide →
        </a>
    </div>

    <ol class="mt-5 space-y-3">
        @foreach ($steps as $step)
            <li class="flex gap-3 rounded-lg bg-white px-4 py-3 ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <span @class([
                    'mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                    'bg-success-100 text-success-700 dark:bg-success-500/15 dark:text-success-300' => $step['done'] === true,
                    'bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-400' => $step['done'] !== true,
                ])>
                    @if ($step['done'] === true)
                        ✓
                    @else
                        {{ $loop->iteration }}
                    @endif
                </span>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-gray-950 dark:text-white">{{ $step['title'] }}</p>
                    <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-400">{{ $step['body'] }}</p>
                    <a href="{{ $step['url'] }}" class="mt-2 inline-flex text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">
                        Open {{ $step['menu'] }} →
                    </a>
                </div>
            </li>
        @endforeach
    </ol>
</div>
