import { initPwaInstall } from './pwa-install';
import { initPwaPush } from './pwa-push';

document.addEventListener('DOMContentLoaded', () => {
    initPwaInstall();
    window.setTimeout(() => initPwaPush(), 2500);
});
