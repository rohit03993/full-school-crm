<details @class([
    'group rounded-2xl border bg-white shadow-sm',
    'border-amber-200 ring-1 ring-amber-100' => $errors->has('current_password') || $errors->has('password'),
    'border-navy-100' => ! $errors->has('current_password') && ! $errors->has('password'),
]) @if($errors->has('current_password') || $errors->has('password')) open @endif>
    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 p-4 sm:p-5 [&::-webkit-details-marker]:hidden">
        <div class="min-w-0 text-left">
            <h2 class="font-display text-base font-bold text-navy-900 sm:text-lg">Portal password</h2>
            <p class="mt-0.5 text-xs text-navy-500 sm:text-sm">Tap to change your login password</p>
        </div>
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-navy-50 text-navy-500 transition group-open:rotate-180">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </span>
    </summary>

    <div class="border-t border-navy-100 px-4 pb-5 pt-4 sm:px-5">
        <form method="POST" action="{{ route('portal.password.change') }}" class="space-y-4">
            @csrf

            <div>
                <label for="current_password" class="mb-1.5 block text-sm font-semibold text-navy-800">Current password</label>
                <input type="password" name="current_password" id="current_password" required maxlength="64" autocomplete="current-password"
                    class="portal-input">
                @error('current_password')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="portal_new_password" class="mb-1.5 block text-sm font-semibold text-navy-800">New password</label>
                <input type="password" name="password" id="portal_new_password" required minlength="6" maxlength="64" autocomplete="new-password"
                    class="portal-input">
                @error('password')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="password_confirmation" class="mb-1.5 block text-sm font-semibold text-navy-800">Confirm new password</label>
                <input type="password" name="password_confirmation" id="password_confirmation" required minlength="6" maxlength="64" autocomplete="new-password"
                    class="portal-input">
            </div>

            <button type="submit" class="w-full rounded-xl bg-navy-900 py-3 text-sm font-bold text-white transition hover:bg-navy-800 active:scale-[0.99] sm:w-auto sm:px-6">
                Update password
            </button>
        </form>
    </div>
</details>
