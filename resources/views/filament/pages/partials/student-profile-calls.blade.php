<div wire:init="loadCallsTab" @if ($callsTabLoaded && $callRecordings->contains(fn ($recording) => $recording->isPending())) wire:poll.8s="refreshCallRecordings" @endif>
    @if (! $callsTabLoaded)
        <p class="text-sm text-gray-500 dark:text-gray-400">Loading calls…</p>
    @else
        @if ($callIntelligenceEnabled)
            <div class="mb-4 space-y-3 rounded-xl border border-gray-100 bg-white px-4 py-4 shadow-sm dark:border-white/10 dark:bg-gray-900 sm:px-5">
                <div>
                    <p class="text-sm font-bold text-gray-950 dark:text-white">Call Intelligence</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Upload the recording saved by the phone. The summary appears here when it is ready.</p>
                </div>

                <form wire:submit="uploadCallRecording" class="space-y-3">
                    <input type="file" wire:model="callAudio" accept="audio/*,.m4a,.mp3,.wav,.aac,.ogg" class="block w-full text-sm text-gray-700 dark:text-gray-200">
                    <select wire:model="callAudioDirection" class="block w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-gray-900">
                        <option value="outgoing">Outgoing</option>
                        <option value="incoming">Incoming</option>
                    </select>
                    <button type="submit" class="inline-flex min-h-11 items-center rounded-lg bg-primary-600 px-4 text-sm font-semibold text-white" wire:loading.attr="disabled" wire:target="callAudio,uploadCallRecording">
                        Upload recording
                    </button>
                    <p class="text-xs text-gray-500" wire:loading wire:target="callAudio,uploadCallRecording">Uploading...</p>
                    @error('callAudio')
                        <p class="text-xs text-danger-600">{{ $message }}</p>
                    @enderror
                </form>

                @foreach ($callRecordings as $recording)
                    <div class="rounded-lg border border-gray-100 px-3 py-3 dark:border-white/10" wire:key="call-recording-{{ $recording->id }}">
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $recording->created_at?->format('d M Y, h:i A') }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $recording->friendlyStatus() }}</p>

                        @if ($recording->short_summary)
                            <p class="mt-2 text-sm text-gray-800 dark:text-gray-100">{{ $recording->short_summary }}</p>
                        @endif

                        @if ($recording->summary)
                            <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $recording->summary }}</p>
                        @endif

                        @if ($recording->processing_status === 'FAILED')
                            <button type="button" wire:click="retryCallRecording({{ $recording->id }})" class="mt-2 text-sm font-semibold text-primary-600">Retry</button>
                        @endif

                        @if ($recording->processing_error && $recording->processing_status === 'FAILED')
                            <p class="mt-1 text-xs text-danger-600">{{ $recording->processing_error }}</p>
                        @endif

                        @if (in_array($recording->processing_status, ['COMPLETED', 'AI_READY', 'CRM_SYNCED'], true))
                            <div class="mt-3 space-y-2" x-data>
                                <audio x-ref="audio" controls preload="none" class="w-full" src="{{ route('admin.call-recordings.audio', $recording) }}"></audio>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ([0.75, 1, 1.25, 1.5, 2] as $speed)
                                        <button type="button" class="rounded-md bg-gray-100 px-2 py-1 text-xs font-semibold dark:bg-white/10" x-on:click="$refs.audio.playbackRate = {{ $speed }}">{{ $speed }}x</button>
                                    @endforeach
                                </div>

                                @if (filled($recording->transcript_text))
                                    <button type="button" class="text-sm font-semibold text-primary-600" x-on:click="navigator.clipboard.writeText(@js($recording->transcript_text))">Copy transcript</button>
                                @endif

                                @foreach (($recording->transcript_json['segments'] ?? []) as $segment)
                                    <div class="pt-2">
                                        <button type="button" class="text-xs font-semibold text-primary-700" x-on:click="$refs.audio.currentTime = {{ (float) ($segment['start'] ?? 0) }}; $refs.audio.play()">
                                            {{ $recording->clock((float) ($segment['start'] ?? 0)) }} {{ $segment['speaker'] ?? 'Speaker' }}
                                        </button>
                                        <p class="text-sm text-gray-800 dark:text-gray-100">{{ $segment['text'] ?? '' }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if (is_array($recording->ai_analysis_json) && $recording->ai_analysis_json !== [])
                            <details class="mt-3">
                                <summary class="cursor-pointer text-sm font-semibold text-gray-700 dark:text-gray-200">View AI analysis</summary>
                                <div class="mt-2 space-y-1 text-sm text-gray-700 dark:text-gray-300">
                                    <p>Lead status: {{ $recording->ai_analysis_json['lead_status'] ?? 'unknown' }}</p>
                                    <p>Interest: {{ $recording->ai_analysis_json['interest_level'] ?? 'unknown' }}</p>
                                    <p>Follow-up: {{ ($recording->ai_analysis_json['follow_up_required'] ?? false) ? 'Required' : 'Not required' }}</p>
                                    @if ($recording->suggested_follow_up_date)
                                        <p>Suggested date: {{ $recording->suggested_follow_up_date->format('d M Y') }} {{ $recording->suggested_follow_up_time }}</p>
                                    @endif
                                </div>
                            </details>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if ($calls->isEmpty())
        <div class="fi-section rounded-xl px-4 py-8 text-center shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:px-6">
            <p class="text-sm text-gray-500 dark:text-gray-400">No calls logged yet.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($calls as $call)
                <div class="rounded-xl border border-gray-100 bg-white px-4 py-4 shadow-sm dark:border-white/10 dark:bg-gray-900 sm:px-5">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="text-sm font-bold text-gray-950 dark:text-white">{{ $call->call_status->label() }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $call->called_at?->format('d M Y, h:i A') }}
                                · {{ $call->call_direction->label() }}
                                @if ($call->staff)
                                    · {{ $call->staff->name }}
                                @endif
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                        @if ($call->student_case_id && $call->studentCase)
                            <span class="inline-flex rounded-full bg-violet-50 px-2.5 py-1 text-[10px] font-semibold text-violet-700 dark:bg-violet-500/10 dark:text-violet-300">
                                {{ $call->studentCase->case_number }}
                            </span>
                        @endif
                        @if ($call->call_purpose)
                            <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                {{ $call->call_purpose->label() }}
                            </span>
                        @endif
                        @if ($call->visit_status_changed_to)
                            <span class="inline-flex rounded-full bg-primary-50 px-2.5 py-1 text-xs font-semibold text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">
                                {{ $call->visit_status_changed_to->label() }}
                            </span>
                        @endif
                        @if ($call->whatsapp_auto_status)
                            <span @class([
                                'inline-flex rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide',
                                'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300' => $call->whatsapp_auto_status->value === 'success',
                                'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-300' => $call->whatsapp_auto_status->value === 'queued',
                                'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300' => $call->whatsapp_auto_status->value === 'skipped',
                                'bg-danger-100 text-danger-800 dark:bg-danger-500/15 dark:text-danger-300' => $call->whatsapp_auto_status->value === 'failed',
                            ])>
                                {{ $call->whatsapp_auto_status->label() }}
                            </span>
                        @endif
                        </div>
                    </div>

                    @if ($call->who_answered)
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Answered by: {{ $call->who_answered->label() }}</p>
                    @endif

                    @if ($call->call_notes)
                        <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $call->call_notes }}</p>
                    @endif

                    @if ($call->next_followup_at)
                        <p class="mt-2 text-xs font-medium text-amber-700 dark:text-amber-300">
                            Follow-up: {{ $call->next_followup_at->format('d M Y, h:i A') }}
                        </p>
                    @endif

                    @if (filled($call->tags) && ! $call->call_purpose)
                        <div class="mt-2 flex flex-wrap gap-1">
                            @foreach ($call->tags as $tag)
                                <span class="rounded-md bg-gray-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ $tag }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
    @endif
</div>
