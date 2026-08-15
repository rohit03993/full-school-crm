import './bootstrap';
import { initPwaInstall } from './pwa-install';
import { initPwaPush } from './pwa-push';

document.addEventListener('DOMContentLoaded', () => {
    initPwaInstall();
    window.setTimeout(() => initPwaPush(), 2500);

    document.getElementById('portal-enable-push')?.addEventListener('click', async () => {
        const status = document.getElementById('portal-push-status');
        try {
            await initPwaPush();
            if (status) {
                status.textContent = Notification.permission === 'granted'
                    ? 'Notifications are on for this device.'
                    : 'Permission was not granted. You can enable it in browser settings.';
                status.classList.remove('hidden');
            }
        } catch (error) {
            if (status) {
                status.textContent = 'Could not enable notifications on this device.';
                status.classList.remove('hidden');
            }
        }
    });
});
