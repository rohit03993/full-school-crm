<div class="fi-ta rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
            <thead class="bg-gray-50 dark:bg-white/5">
                <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                    <th class="px-4 py-3">When</th>
                    <th class="px-4 py-3">Direction</th>
                    <th class="px-4 py-3">Phone</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Template</th>
                    <th class="px-4 py-3">Preview</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                @forelse ($messages as $message)
                    <tr>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $message->created_at?->format('d M Y H:i') }}</td>
                        <td class="px-4 py-3 capitalize">{{ $message->direction }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $message->phone }}</td>
                        <td class="px-4 py-3 capitalize">{{ $message->status }}</td>
                        <td class="px-4 py-3">{{ $message->template_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-gray-500">{{ \Illuminate\Support\Str::limit($message->body_preview, 100) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">
                            No Meta WhatsApp messages logged yet.
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
