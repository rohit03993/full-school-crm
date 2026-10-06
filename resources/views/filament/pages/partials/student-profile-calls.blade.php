<div wire:init="loadCallsTab" @if ($callsTabLoaded && $callRecordings->contains(fn ($recording) => $recording->isPending())) wire:poll.8s="refreshCallRecordings" @endif>
    @if (! $callsTabLoaded)
        <p class="text-sm text-gray-500 dark:text-gray-400">Loading calls…</p>
    @else
        @if ($callIntelligenceEnabled)
            <div class="mb-4 space-y-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:p-5">
                <div>
                    <p class="text-sm font-bold text-gray-950 dark:text-white">Call Intelligence</p>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Choose the recording saved on the phone. MP4, M4A, MP3 and WAV files are allowed. The summary appears on this card when it is ready.</p>
                </div>

                <form wire:submit="uploadCallRecording" class="space-y-3">
                    <div class="flex flex-wrap items-center gap-3">
                        <label class="fi-crm-btn-secondary cursor-pointer">
                            Choose recording
                            <input type="file" wire:model="callAudio" accept=".mp4,.m4a,.mp3,.wav,.aac,.ogg,.webm,.3gp,.amr,audio/*,video/mp4,video/3gpp" class="sr-only">
                        </label>
                        <p class="min-w-0 text-sm text-gray-700 dark:text-gray-200">
                            @if ($callAudio)
                                {{ $callAudio->getClientOriginalName() }}
                            @else
                                No file chosen yet.
                            @endif
                        </p>
                    </div>
                    <p class="text-xs text-gray-500" wire:loading wire:target="callAudio">Reading the file...</p>

                    <div class="flex flex-wrap gap-2">
                        <button type="button" wire:click="$set('callAudioDirection', 'outgoing')" @class([
                            'min-h-11 rounded-xl px-4 text-sm font-semibold',
                            'bg-primary-600 text-white' => $callAudioDirection === 'outgoing',
                            'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200' => $callAudioDirection !== 'outgoing',
                        ])>Outgoing</button>
                        <button type="button" wire:click="$set('callAudioDirection', 'incoming')" @class([
                            'min-h-11 rounded-xl px-4 text-sm font-semibold',
                            'bg-primary-600 text-white' => $callAudioDirection === 'incoming',
                            'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200' => $callAudioDirection !== 'incoming',
                        ])>Incoming</button>
                    </div>

                    <button type="submit" class="fi-crm-btn-primary" wire:loading.attr="disabled" wire:target="callAudio,uploadCallRecording">
                        Upload recording
                    </button>
                    <p class="text-xs text-gray-500" wire:loading wire:target="uploadCallRecording">Uploading...</p>
                    @error('callAudio')
                        <p class="text-sm text-danger-600">{{ $message }}</p>
                    @enderror
                </form>

                @foreach ($callRecordings as $recording)
                    <div @class([
                        'rounded-xl px-4 py-4 ring-1',
                        'bg-danger-50 ring-danger-200 dark:bg-danger-500/10 dark:ring-danger-500/30' => $recording->processing_status === 'FAILED',
                        'bg-gray-50 ring-gray-200 dark:bg-white/5 dark:ring-white/10' => $recording->processing_status !== 'FAILED',
                    ]) wire:key="call-recording-{{ $recording->id }}">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $recording->created_at?->timezone(config('app.timezone'))->format('d M Y, h:i A') }}</p>
                            <span @class([
                                'rounded-full px-3 py-1 text-xs font-bold',
                                'bg-danger-500/15 text-danger-700 dark:text-danger-300' => $recording->processing_status === 'FAILED',
                                'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' => in_array($recording->processing_status, ['COMPLETED', 'AI_READY', 'CRM_SYNCED'], true),
                                'bg-amber-500/15 text-amber-800 dark:text-amber-200' => ! in_array($recording->processing_status, ['FAILED', 'COMPLETED', 'AI_READY', 'CRM_SYNCED'], true),
                            ])>{{ $recording->friendlyStatus() }}</span>
                        </div>

                        @if ($recording->short_summary)
                            <p class="mt-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $recording->short_summary }}</p>
                        @endif

                        @if ($recording->summary)
                            <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $recording->summary }}</p>
                        @endif

                        @if ($recording->processing_status === 'FAILED')
                            <p class="mt-3 text-sm text-danger-700 dark:text-danger-300">{{ $recording->processing_error ?: 'Processing failed.' }}</p>
                            @if ($recording->canRetry())
                                <button type="button" wire:click="retryCallRecording({{ $recording->id }})" class="mt-3 text-sm font-semibold text-primary-600">Retry</button>
                            @else
                                <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">Choose the file again and press Upload recording.</p>
                            @endif
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
