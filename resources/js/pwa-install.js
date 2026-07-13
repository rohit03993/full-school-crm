/**
 * School CRM — PWA install prompts (mobile banner, menu, footer, admin topbar).
 */

const DISMISS_MS = 14 * 24 * 60 * 60 * 1000;
const BANNER_DELAY_MS = 4000;

let deferredPrompt = null;

function pwaContext() {
    return document.querySelector('meta[name="crm-pwa-context"]')?.content || 'public';
}

function dismissKey() {
    return `crm_pwa_install_dismissed_until_${pwaContext()}`;
}

function appName() {
    return document.querySelector('meta[name="crm-pwa-app-name"]')?.content || 'School CRM';
}

function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
}

function isIos() {
    return /iphone|ipad|ipod/i.test(navigator.userAgent);
}

function isMobile() {
    return window.matchMedia('(max-width: 1023px)').matches;
}

function isDismissed() {
    const until = localStorage.getItem(dismissKey());

    return until !== null && Date.now() < Number(until);
}

function dismissInstallPrompt() {
    localStorage.setItem(dismissKey(), String(Date.now() + DISMISS_MS));
    hideBanner();
}

function shouldOfferInstallByContext() {
    const context = pwaContext();

    return context === 'public' || context === 'portal';
}

function canOfferInstall() {
    return shouldOfferInstallByContext() && ! isStandalone();
}

function canUseNativeInstall() {
    return deferredPrompt !== null;
}

function canUseIosGuide() {
    return isIos() && isMobile();
}

function shouldShowInstallUi() {
    if (! canOfferInstall() || isDismissed()) {
        return false;
    }

    return canUseNativeInstall() || canUseIosGuide();
}

function showInstallTriggers() {
    document.querySelectorAll('[data-crm-pwa-install]').forEach((element) => {
        element.hidden = false;
        element.classList.remove('hidden');
    });
}

function hideInstallTriggers() {
    document.querySelectorAll('[data-crm-pwa-install]').forEach((element) => {
        element.hidden = true;
    });
}

function hideBanner() {
    const banner = document.getElementById('crm-pwa-install-banner');

    if (banner) {
        banner.hidden = true;
        banner.classList.add('crm-pwa-install-banner--hidden');
    }
}

function showBanner() {
    const banner = document.getElementById('crm-pwa-install-banner');

    if (! banner || ! isMobile() || ! shouldShowInstallUi()) {
        return;
    }

    const title = banner.querySelector('[data-crm-pwa-banner-title]');
    const androidCopy = banner.querySelector('[data-crm-pwa-android-copy]');
    const iosCopy = banner.querySelector('[data-crm-pwa-ios-copy]');

    if (title) {
        title.textContent = `Install ${appName()}`;
    }

    if (isIos()) {
        androidCopy?.classList.add('hidden');
        iosCopy?.classList.remove('hidden');
    } else {
        androidCopy?.classList.remove('hidden');
        iosCopy?.classList.add('hidden');
    }

    banner.hidden = false;
    banner.classList.remove('crm-pwa-install-banner--hidden');
}

function showIosGuide() {
    const modal = document.getElementById('crm-pwa-ios-guide');

    if (! modal) {
        return;
    }

    const title = modal.querySelector('[data-crm-pwa-ios-title]');

    if (title) {
        title.textContent = `Add ${appName()} to Home Screen`;
    }

    modal.hidden = false;
    modal.classList.add('crm-pwa-ios-guide--open');
    document.body.classList.add('crm-pwa-ios-guide-open');
}

function hideIosGuide() {
    const modal = document.getElementById('crm-pwa-ios-guide');

    if (! modal) {
        return;
    }

    modal.hidden = true;
    modal.classList.remove('crm-pwa-ios-guide--open');
    document.body.classList.remove('crm-pwa-ios-guide-open');
}

async function triggerInstall() {
    document.querySelectorAll('details.mobile-nav[open]').forEach((details) => {
        details.removeAttribute('open');
    });

    if (canUseNativeInstall()) {
        deferredPrompt.prompt();

        const choice = await deferredPrompt.userChoice;

        deferredPrompt = null;

        if (choice.outcome === 'accepted') {
            hideBanner();
            hideInstallTriggers();
        }

        return;
    }

    if (canUseIosGuide()) {
        showIosGuide();
    }
}

function bindInstallControls() {
    document.querySelectorAll('[data-crm-pwa-install]').forEach((element) => {
        element.addEventListener('click', (event) => {
            event.preventDefault();
            triggerInstall();
        });
    });

    document.querySelector('[data-crm-pwa-dismiss]')?.addEventListener('click', () => {
        dismissInstallPrompt();
    });

    document.querySelectorAll('[data-crm-pwa-ios-close]').forEach((button) => {
        button.addEventListener('click', () => hideIosGuide());
    });

    document.querySelector('#crm-pwa-ios-guide')?.addEventListener('click', (event) => {
        if (event.target.closest('[data-crm-pwa-ios-close]')) {
            hideIosGuide();
        }
    });
}

function scheduleBanner() {
    if (! isMobile() || ! shouldShowInstallUi()) {
        return;
    }

    window.setTimeout(() => {
        if (shouldShowInstallUi()) {
            showBanner();
        }
    }, BANNER_DELAY_MS);
}

function registerServiceWorker() {
    if (! ('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}

function initPwaInstall() {
    if (! canOfferInstall()) {
        return;
    }

    registerServiceWorker();
    bindInstallControls();

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredPrompt = event;

        if (shouldShowInstallUi()) {
            showInstallTriggers();
            scheduleBanner();
        }
    });

    if (canUseIosGuide() && ! isDismissed()) {
        showInstallTriggers();
        scheduleBanner();
    }

    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        hideBanner();
        hideInstallTriggers();
    });
}

export { initPwaInstall, triggerInstall };
