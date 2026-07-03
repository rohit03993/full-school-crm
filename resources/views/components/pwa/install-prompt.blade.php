@props([
    'context' => 'public',
])

<div id="crm-pwa-install-banner" class="crm-pwa-install-banner crm-pwa-install-banner--{{ $context }} crm-pwa-install-banner--hidden" hidden>
    <div class="crm-pwa-install-banner-inner">
        <div class="crm-pwa-install-banner-copy">
            <p class="crm-pwa-install-banner-title" data-crm-pwa-banner-title>Install app</p>
            <p class="crm-pwa-install-banner-text" data-crm-pwa-android-copy>
                Add to your home screen or desktop for quick access.
            </p>
            <p class="crm-pwa-install-banner-text hidden" data-crm-pwa-ios-copy>
                Tap Install for step-by-step Add to Home Screen instructions.
            </p>
        </div>
        <div class="crm-pwa-install-banner-actions">
            <button type="button" class="crm-pwa-install-banner-install" data-crm-pwa-install>
                Install
            </button>
            <button type="button" class="crm-pwa-install-banner-dismiss" data-crm-pwa-dismiss>
                Not now
            </button>
        </div>
    </div>
</div>

<div id="crm-pwa-ios-guide" class="crm-pwa-ios-guide" hidden>
    <div class="crm-pwa-ios-guide-card" role="dialog" aria-labelledby="crm-pwa-ios-guide-title" aria-modal="true">
        <button type="button" class="crm-pwa-ios-guide-close" data-crm-pwa-ios-close aria-label="Close">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
        <h2 id="crm-pwa-ios-guide-title" class="crm-pwa-ios-guide-title" data-crm-pwa-ios-title>Add to Home Screen</h2>
        <ol class="crm-pwa-ios-guide-steps">
            <li>Tap the <strong>Share</strong> button in Safari (square with arrow).</li>
            <li>Scroll down and tap <strong>Add to Home Screen</strong>.</li>
            <li>Tap <strong>Add</strong> in the top-right corner.</li>
        </ol>
        <button type="button" class="crm-pwa-install-banner-install w-full" data-crm-pwa-ios-close>Got it</button>
    </div>
</div>
