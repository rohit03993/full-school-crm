@include('portal.partials.change-password')

@if (($showAttendance ?? true))
    <section class="portal-card p-4 sm:p-5">
        <h2 class="font-display text-base font-bold text-navy-900">Attendance</h2>
        <p class="mt-1 text-sm text-navy-500">View class presence and workshop attendance for {{ $student->name }}.</p>
        <button type="button" @click="setTab('attendance')" class="touch-manipulation mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-navy-200 bg-white px-4 py-3 text-sm font-semibold text-navy-800 shadow-sm transition hover:bg-navy-50 sm:w-auto">
            Open attendance
        </button>
    </section>
@endif

<section class="portal-card p-4 sm:p-5">
    <h2 class="font-display text-base font-bold text-navy-900">App notifications</h2>
    <p class="mt-1 text-sm text-navy-500">
        Allow notifications so fee reminders and attendance alerts reach this phone even when the app is closed.
    </p>
    <button
        type="button"
        id="portal-enable-push"
        class="touch-manipulation mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-navy-900 px-4 py-3 text-sm font-semibold text-white shadow-sm transition active:bg-navy-800 sm:w-auto"
    >
        Enable notifications
    </button>
    <p id="portal-push-status" class="mt-2 hidden text-xs text-navy-500"></p>
</section>

@if (($linkedChildren ?? collect())->count() > 1)
    <section class="portal-card p-4 sm:p-5 lg:hidden">
        <h2 class="font-display text-base font-bold text-navy-900">Switch child</h2>
        <p class="mt-1 text-sm text-navy-500">This mobile is linked to more than one student.</p>
        <div class="mt-3 space-y-2">
            @foreach ($linkedChildren as $child)
                @if ((int) $child->id === (int) $student->id)
                    <div class="rounded-xl bg-brand-50 px-3 py-2 text-sm font-semibold text-brand-800 ring-1 ring-brand-200">
                        {{ $child->name }} · viewing
                    </div>
                @else
                    <form method="POST" action="{{ route('portal.switch-child') }}">
                        @csrf
                        <input type="hidden" name="student_id" value="{{ $child->id }}">
                        <button type="submit" class="w-full rounded-xl border border-navy-200 bg-white px-3 py-2 text-left text-sm font-semibold text-navy-800 shadow-sm">
                            {{ $child->name }}
                        </button>
                    </form>
                @endif
            @endforeach
        </div>
    </section>
@endif

<section class="portal-card p-4 sm:p-5 lg:hidden">
    <h2 class="font-display text-base font-bold text-navy-900">Session</h2>
    <p class="mt-1 text-sm text-navy-500">Sign out of the parent &amp; student portal on this device.</p>
    <form method="POST" action="{{ route('portal.logout') }}" class="mt-4">
        @csrf
        <button type="submit" class="touch-manipulation inline-flex w-full items-center justify-center gap-2 rounded-xl border border-navy-200 bg-white px-4 py-3 text-sm font-semibold text-navy-800 shadow-sm transition hover:bg-navy-50 sm:w-auto">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
            Logout
        </button>
    </form>
</section>

<div class="text-center lg:hidden">
    <a href="{{ route('home') }}" class="text-sm font-medium text-navy-500 transition hover:text-navy-800">
        ← Back to {{ $institute['name'] ?? 'website' }}
    </a>
</div>
