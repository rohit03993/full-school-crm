/**
 * PWA Web Push — subscribe after install / login (staff or portal).
 */
async function initPwaPush() {
    if (! ('serviceWorker' in navigator) || ! ('PushManager' in window) || ! ('Notification' in window)) {
        return;
    }

    if (Notification.permission === 'denied') {
        return;
    }

    try {
        const keyRes = await fetch('/pwa/push/public-key', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        const keyJson = await keyRes.json();

        if (! keyJson.enabled || ! keyJson.publicKey) {
            return;
        }

        const registration = await navigator.serviceWorker.ready;

        let subscription = await registration.pushManager.getSubscription();

        if (! subscription) {
            if (Notification.permission === 'default') {
                const permission = await Notification.requestPermission();

                if (permission !== 'granted') {
                    return;
                }
            }

            if (Notification.permission !== 'granted') {
                return;
            }

            subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(keyJson.publicKey),
            });
        }

        await fetch('/pwa/push/subscribe', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify(subscription.toJSON()),
        });
    } catch (error) {
        // Push is optional — never break install / page load.
        console.debug('PWA push subscribe skipped', error);
    }
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('input[name="_token"]')?.value
        || '';
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);
    const output = new Uint8Array(raw.length);

    for (let i = 0; i < raw.length; i += 1) {
        output[i] = raw.charCodeAt(i);
    }

    return output;
}

export { initPwaPush };
