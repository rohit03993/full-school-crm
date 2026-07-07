@php
    $messageType = (string) ($message['messageType'] ?? 'text');
    $mediaUrl = $message['mediaUrl'] ?? null;
    $caption = trim((string) ($message['caption'] ?? ''));
    $body = trim((string) ($message['body'] ?? ''));
    $showBody = $body !== '' && ! in_array($messageType, ['reaction'], true);
    $showCaption = $caption !== '' && $caption !== $body && in_array($messageType, ['image', 'video', 'document'], true);
@endphp

@if ($messageType === 'image' || $messageType === 'sticker')
    @if ($mediaUrl)
        <button type="button" class="crm-wa-bubble__media-trigger js-media-preview-trigger" data-crm-preview-image="{{ $mediaUrl }}" data-crm-preview-title="{{ $messageType === 'sticker' ? 'Sticker' : 'Photo' }}">
            <img src="{{ $mediaUrl }}" alt="{{ $messageType === 'sticker' ? 'Sticker' : 'Photo' }}" class="crm-wa-bubble__image" loading="lazy" />
        </button>
    @else
        <p class="crm-wa-bubble__media-fallback">{{ $body !== '' ? $body : '📷 Photo' }}</p>
    @endif
@elseif ($messageType === 'video')
    @if ($mediaUrl)
        <video src="{{ $mediaUrl }}" class="crm-wa-bubble__video" controls playsinline preload="metadata"></video>
    @else
        <p class="crm-wa-bubble__media-fallback">{{ $body !== '' ? $body : '🎬 Video' }}</p>
    @endif
@elseif ($messageType === 'audio')
    @if ($mediaUrl)
        <audio src="{{ $mediaUrl }}" class="crm-wa-bubble__audio" controls preload="metadata"></audio>
    @else
        <p class="crm-wa-bubble__media-fallback">{{ $body !== '' ? $body : '🎤 Voice message' }}</p>
    @endif
@elseif ($messageType === 'document')
    @if ($mediaUrl)
        <a href="{{ $mediaUrl }}" target="_blank" rel="noopener" class="crm-wa-bubble__document">
            <x-filament::icon icon="heroicon-o-document-arrow-down" class="crm-wa-bubble__document-icon" />
            <span class="crm-wa-bubble__document-name">{{ $message['mediaFilename'] ?? 'Document' }}</span>
        </a>
    @else
        <p class="crm-wa-bubble__media-fallback">{{ $body !== '' ? $body : '📄 Document' }}</p>
    @endif
@elseif ($messageType === 'location' && filled($message['locationUrl'] ?? null))
    <a href="{{ $message['locationUrl'] }}" target="_blank" rel="noopener" class="crm-wa-bubble__location">
        <x-filament::icon icon="heroicon-o-map-pin" class="crm-wa-bubble__location-icon" />
        <span>{{ $body !== '' ? $body : '📍 Location' }}</span>
    </a>
@elseif ($messageType === 'reaction')
    <p class="crm-wa-bubble__reaction">{{ $body }}</p>
@endif

@if ($showCaption)
    <p class="crm-wa-bubble__caption">{{ $caption }}</p>
@endif

@if ($showBody && ! in_array($messageType, ['image', 'video', 'audio', 'document', 'sticker'], true))
    <p class="crm-wa-bubble__body">{{ $body }}</p>
@elseif ($showBody && in_array($messageType, ['image', 'video', 'document'], true) && $caption === '' && ! str_starts_with($body, '📷') && ! str_starts_with($body, '🎬') && ! str_starts_with($body, '📄'))
    <p class="crm-wa-bubble__caption">{{ $body }}</p>
@endif
