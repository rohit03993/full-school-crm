<div class="space-y-5">
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
        <div class="flex flex-col gap-1">
            <h2 class="text-base font-bold text-gray-950 dark:text-white">
                {{ $canManage ? 'Today’s homework desk' : 'Submit today’s homework' }}
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-300">
                @if ($canManage)
                    Review teacher submissions, approve the ready subjects, and send one combined message to parents.
                @else
                    Add homework only for the classes and subjects assigned to you. Admin will review and send it.
                @endif
            </p>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @if ($canManage && $canReview)
            <a
                href="{{ $reviewUrl }}"
                class="group rounded-2xl border border-primary-200 bg-primary-50 p-5 transition hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-md dark:border-primary-500/30 dark:bg-primary-500/10"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <span class="inline-flex rounded-full bg-primary-600 px-2.5 py-1 text-xs font-bold text-white">Start here</span>
                        <h3 class="mt-3 text-base font-bold text-gray-950 dark:text-white">Review &amp; send</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            See every subject for a class and date, approve submissions, then send one combined WhatsApp.
                        </p>
                    </div>
                    <span class="text-xl text-primary-600 transition group-hover:translate-x-1 dark:text-primary-400">→</span>
                </div>
            </a>
        @endif

        @if ($canSubmit)
            <a
                href="{{ $submitUrl }}"
                class="group rounded-2xl border border-gray-200 bg-white p-5 transition hover:-translate-y-0.5 hover:border-amber-300 hover:shadow-md dark:border-white/10 dark:bg-gray-900"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        @if (! $canManage)
                            <span class="inline-flex rounded-full bg-amber-500 px-2.5 py-1 text-xs font-bold text-white">Start here</span>
                        @endif
                        <h3 @class(['text-base font-bold text-gray-950 dark:text-white', 'mt-3' => ! $canManage])>
                            Submit homework
                        </h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            Add subject-wise homework with text, PDF, or image. Teachers only see their assigned subjects.
                        </p>
                    </div>
                    <span class="text-xl text-amber-600 transition group-hover:translate-x-1 dark:text-amber-400">→</span>
                </div>
            </a>
        @endif

        @if ($canCheck)
            <a
                href="{{ $checkUrl }}"
                class="group rounded-2xl border border-gray-200 bg-white p-5 transition hover:-translate-y-0.5 hover:border-sky-300 hover:shadow-md dark:border-white/10 dark:bg-gray-900"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-base font-bold text-gray-950 dark:text-white">Check completion</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            Mark students Done or Not Done and notify parents only when required.
                        </p>
                    </div>
                    <span class="text-xl text-sky-600 transition group-hover:translate-x-1 dark:text-sky-400">→</span>
                </div>
            </a>
        @endif

        @if ($canViewHistory)
            <a
                href="{{ $historyUrl }}"
                class="group rounded-2xl border border-gray-200 bg-white p-5 transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md dark:border-white/10 dark:bg-gray-900"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-base font-bold text-gray-950 dark:text-white">Homework history</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            View previously published homework, links, delivery counts, and parent viewing activity.
                        </p>
                    </div>
                    <span class="text-xl text-emerald-600 transition group-hover:translate-x-1 dark:text-emerald-400">→</span>
                </div>
            </a>
        @endif
    </div>

    <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
        <strong class="text-gray-900 dark:text-white">Daily flow:</strong>
        Teacher submits by subject → admin reviews and approves → one combined WhatsApp goes to parents.
        Subjects with no homework are not shown in the message.
    </div>
</div>
