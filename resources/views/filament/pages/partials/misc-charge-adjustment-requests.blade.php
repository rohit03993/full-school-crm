<div class="space-y-6">
  @if ($requests->isEmpty())
    <div class="rounded-2xl bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
      <p class="text-sm font-medium text-gray-900 dark:text-white">No pending requests</p>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">When staff request a discount or waive-off on a student’s additional charge, it will appear here for approval.</p>
    </div>
  @else
    <div class="space-y-4">
      @foreach ($requests as $request)
        @php
          $charge = $request->charge;
          $student = $charge?->feeStructure?->enrollment?->student;
          $pending = $charge?->pendingAmount() ?? 0;
          $adjustAmount = $request->type === \App\Enums\FeeMiscChargeAdjustmentType::WaiveOff
              ? $pending
              : (float) $request->discount_amount;
        @endphp
        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10" wire:key="adj-req-{{ $request->id }}">
          <div class="border-b border-gray-100 px-5 py-4 dark:border-white/10">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p class="text-xs font-bold uppercase tracking-wide text-amber-700 dark:text-amber-300">{{ $request->type->label() }}</p>
                <p class="mt-1 text-base font-semibold text-gray-950 dark:text-white">{{ $charge?->label ?? 'Charge' }}</p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                  {{ $student?->name ?? 'Student' }}
                  @if ($student)
                    · <a href="{{ \App\Filament\Pages\StudentProfilePage::getUrl(['record' => $student->id]) }}" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">Open profile</a>
                  @endif
                </p>
              </div>
              <div class="text-right text-sm">
                <p class="font-semibold text-amber-700 dark:text-amber-300">− ₹{{ number_format($adjustAmount, 2) }}</p>
                <p class="text-xs text-gray-500">Pending: ₹{{ number_format($pending, 2) }}</p>
              </div>
            </div>
          </div>
          <div class="grid gap-4 px-5 py-4 sm:grid-cols-2">
            <div>
              <p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Reason from staff</p>
              <p class="mt-1 text-sm text-gray-800 dark:text-gray-200">{{ $request->reason }}</p>
              <p class="mt-2 text-xs text-gray-500">
                Requested by {{ $request->requestedBy?->name ?? '—' }}
                · {{ $request->created_at?->format('d M Y H:i') }}
              </p>
            </div>
            <div x-data="{ notes: '' }">
              <label class="mb-1.5 block text-xs font-semibold text-gray-700 dark:text-gray-300">Admin note (optional)</label>
              <textarea x-model="notes" rows="2" class="w-full rounded-xl border-gray-200 text-sm dark:border-white/10 dark:bg-white/5" placeholder="Optional note for audit trail"></textarea>
              <div class="mt-3 flex flex-wrap gap-2">
                <button
                  type="button"
                  class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500"
                  @click="$wire.approveRequest({{ $request->id }}, notes || null)"
                >
                  Approve
                </button>
                <button
                  type="button"
                  class="rounded-xl border border-red-200 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 dark:border-red-500/30 dark:text-red-300 dark:hover:bg-red-500/10"
                  @click="$wire.rejectRequest({{ $request->id }}, notes || null)"
                >
                  Reject
                </button>
              </div>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  @endif
</div>
