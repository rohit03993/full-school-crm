@php
    use App\Enums\MetaWhatsAppMessageStatus;
    use App\Filament\Pages\StudentProfilePage;
    $stats = app(\App\Services\MetaWhatsAppDashboardService::class)->stats();
@endphp

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

    <div class="crm-meta-wa-log__table fi-ta rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <th class="px-4 py-3">When</th>
                        <th class="px-4 py-3">Direction</th>
                        <th class="px-4 py-3">Student</th>
                        <th class="px-4 py-3">Phone</th>
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
                        @endphp
                        <tr class="hover:bg-gray-50/80 dark:hover:bg-white/5">
                            <td class="px-4 py-3 whitespace-nowrap text-gray-700 dark:text-gray-200">
                                <span class="block font-medium">{{ $message->created_at?->format('d M Y') }}</span>
                                <span class="text-xs text-gray-500">{{ $message->created_at?->format('h:i A') }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'crm-wa-pill',
                                    'crm-wa-pill--in' => $message->direction === 'inbound',
                                    'crm-wa-pill--out' => $message->direction === 'outbound',
                                ])>
                                    {{ $message->direction === 'inbound' ? 'Parent' : 'School' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
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
                            <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-300">{{ $message->phone }}</td>
                            <td class="px-4 py-3">
                                <span @class(['crm-wa-status font-medium capitalize', $statusClass])>
                                    {{ $status?->label() ?? $message->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $message->template_name ?? '—' }}</td>
                            <td class="px-4 py-3 max-w-xs text-xs leading-relaxed text-gray-600 dark:text-gray-300">
                                {{ \Illuminate\Support\Str::limit($message->body_preview, 120) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">No Meta WhatsApp messages yet</p>
                                <p class="mt-1 text-xs text-gray-500">Send a test from Connection &amp; Setup or configure the Meta webhook.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-200 px-4 py-3 dark:border-white/10">
            {{ $messages->links() }}
        </div>
    </div>
</div>
