@php
    use App\Enums\MetaWhatsAppMessageStatus;
    use App\Filament\Pages\StudentProfilePage;
    $stats = app(\App\Services\MetaWhatsAppDashboardService::class)->stats();
    $showMessageCost = $showMessageCost ?? false;
    $toneFor = function (string $category): string {
        return match (strtoupper($category)) {
            'MARKETING' => '#d97706',
            'UTILITY' => '#2563eb',
            'AUTHENTICATION', 'AUTHENTICATION_INTERNATIONAL' => '#7c3aed',
            'SERVICE' => '#059669',
            default => '#6b7280',
        };
    };
@endphp

<style>
    .crm-msg-log__filters {
        display: flex; flex-wrap: wrap; gap: 12px; align-items: end;
        padding: 14px 16px; border-radius: 16px; background: #fff;
        border: 1px solid rgba(15, 23, 42, 0.08);
    }
    .dark .crm-msg-log__filters { background: rgba(17, 24, 39, 0.72); border-color: rgba(255, 255, 255, 0.08); }
    .crm-msg-log__filters label { display: block; min-width: 180px; }
    .crm-msg-log__filters span { display: block; margin-bottom: 4px; font-size: 11px; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; color: #6b7280; }
    .crm-msg-log__filters select {
        width: 100%; border-radius: 12px; border: 1px solid #e5e7eb; background: #fff;
        padding: 8px 12px; font-size: 14px; color: #111827;
    }
    .dark .crm-msg-log__filters select { background: #030712; border-color: rgba(255,255,255,0.1); color: #fff; }
    .crm-msg-cat {
        display: inline-flex; align-items: center; gap: 6px;
        border-radius: 999px; padding: 2px 8px; font-size: 12px; font-weight: 600;
        background: color-mix(in srgb, var(--cat) 12%, transparent); color: var(--cat);
    }
    .crm-msg-cat i { width: 6px; height: 6px; border-radius: 99px; background: var(--cat); }
</style>

<div class="crm-meta-wa-log">
    <div class="crm-meta-wa-log__stats">
        <div class="crm-meta-wa-stat crm-meta-wa-stat--total">
            <span class="crm-meta-wa-stat__value">{{ number_format($stats['total']) }}</span>
            <span class="crm-meta-wa-stat__label">Total logged</span>
        </div>
        <div class="crm-meta-wa-stat crm-meta-wa-stat--out">
            <span class="crm-meta-wa-stat__value">{{ number_format($stats['outbound']) }}</span>
            <span class="crm-meta-wa-stat__label">Sent out</span>
        </div>
        <div class="crm-meta-wa-stat crm-meta-wa-stat--in">
            <span class="crm-meta-wa-stat__value">{{ number_format($stats['inbound']) }}</span>
            <span class="crm-meta-wa-stat__label">Parent replies</span>
        </div>
        <div class="crm-meta-wa-stat crm-meta-wa-stat--delivered">
            <span class="crm-meta-wa-stat__value">{{ number_format($stats['delivered']) }}</span>
            <span class="crm-meta-wa-stat__label">Delivered / read</span>
        </div>
    </div>

    <div class="crm-msg-log__filters">
        <label>
            <span>Category</span>
            <select wire:model.live="categoryFilter">
                <option value="">All categories</option>
                <option value="UTILITY">Utility</option>
                <option value="MARKETING">Marketing</option>
                <option value="AUTHENTICATION">Authentication</option>
                <option value="SERVICE">Service</option>
            </select>
        </label>
        @if ($showMessageCost)
            <label>
                <span>Charge</span>
                <select wire:model.live="chargeFilter">
                    <option value="">All charges</option>
                    <option value="charged">Has a charge</option>
                    <option value="free">No charge</option>
                </select>
            </label>
        @endif
        <p class="text-xs text-gray-500 dark:text-gray-400" style="flex: 1 1 220px; margin: 0;">
            Category is the WhatsApp bucket for that send. @if ($showMessageCost) Charge is the price saved then. A failed send is ₹0. This is not the card debit. @endif
        </p>
    </div>

    <div class="crm-meta-wa-log__table fi-ta rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <x-crm.responsive-table>
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <th class="px-4 py-3">When</th>
                        <th class="px-4 py-3">Direction</th>
                        <th class="px-4 py-3">Sent by</th>
                        <th class="px-4 py-3">Student</th>
                        <th class="px-4 py-3">Phone</th>
                        <th class="px-4 py-3">Category</th>
                        @if ($showMessageCost)
                            <th class="px-4 py-3">Charge</th>
                        @endif
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Template</th>
                        <th class="px-4 py-3">Preview</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @forelse ($messages as $message)
                        @php
                            $status = MetaWhatsAppMessageStatus::tryFrom($message->status);
                            $statusClass = match ($message->status) {
                                'sent', 'delivered', 'read', 'received' => 'crm-wa-status--ok',
                                'queued' => 'crm-wa-status--warn',
                                'failed' => 'crm-wa-status--bad',
                                default => '',
                            };
                            $categoryCode = strtoupper((string) ($message->conversation_category ?? ''));
                            $tone = $categoryCode !== '' ? $toneFor($categoryCode) : '#6b7280';
                        @endphp
                        <tr class="hover:bg-gray-50/80 dark:hover:bg-white/5">
                            <td class="px-4 py-3 whitespace-nowrap text-gray-700 dark:text-gray-200" data-label="When">
                                <span class="block font-medium">{{ $message->created_at?->format('d M Y') }}</span>
                                <span class="text-xs text-gray-500">{{ $message->created_at?->format('h:i A') }}</span>
                            </td>
                            <td class="px-4 py-3" data-label="Direction">
                                <span @class([
                                    'crm-wa-pill',
                                    'crm-wa-pill--in' => $message->direction === 'inbound',
                                    'crm-wa-pill--out' => $message->direction === 'outbound',
                                ])>
                                    {{ $message->direction === 'inbound' ? 'Parent' : 'School' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200" data-label="Sent by">
                                {{ $message->direction === 'outbound' ? $message->senderLabel() : '—' }}
                            </td>
                            <td class="crm-responsive-table__title px-4 py-3" data-label="">
                                @if ($message->student)
                                    <a
                                        href="{{ StudentProfilePage::getUrl(['record' => $message->student_id]).'?tab=messages' }}"
                                        class="font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400"
                                    >
                                        {{ $message->student->name }}
                                    </a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-300" data-label="Phone">{{ $message->phone }}</td>
                            <td class="px-4 py-3" data-label="Category">
                                @if ($categoryCode !== '')
                                    <span class="crm-msg-cat" style="--cat: {{ $tone }}">
                                        <i></i>{{ $this->categoryLabel($categoryCode) }}
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            @if ($showMessageCost)
                                <td class="px-4 py-3 tabular-nums font-semibold text-gray-900 dark:text-white" data-label="Charge">
                                    {{ $this->chargeLabel($message) }}
                                </td>
                            @endif
                            <td class="px-4 py-3" data-label="Status">
                                <span @class(['crm-wa-status font-medium capitalize', $statusClass])>
                                    {{ $status?->label() ?? $message->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200" data-label="Template">{{ $message->template_name ?? '—' }}</td>
                            <td class="crm-responsive-table__wide px-4 py-3 max-w-xs text-xs leading-relaxed text-gray-600 dark:text-gray-300" data-label="Preview">
                                {{ \Illuminate\Support\Str::limit($message->body_preview, 120) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $showMessageCost ? 10 : 9 }}" class="px-4 py-12 text-center">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">No messages match these filters</p>
                                <p class="mt-1 text-xs text-gray-500">Choose All categories, or send a message and refresh.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </x-crm.responsive-table>

        <div class="border-t border-gray-200 px-4 py-3 dark:border-white/10">
            {{ $messages->links() }}
        </div>
    </div>
</div>
