@once
    <script>
        (function () {
            if (window.__crmMediaPreviewInit) {
                return;
            }

            window.__crmMediaPreviewInit = true;

            function modalEl() {
                return document.getElementById('crm-media-preview-modal');
            }

            window.closeCrmMediaPreview = function () {
                const modal = modalEl();

                if (! modal || modal.hidden) {
                    return;
                }

                modal.hidden = true;
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('overflow-hidden');

                const iframe = modal.querySelector('[data-crm-preview-iframe]');
                const image = modal.querySelector('[data-crm-preview-image]');

                if (iframe) {
                    iframe.src = 'about:blank';
                }

                if (image) {
                    image.removeAttribute('src');
                }
            };

            window.openCrmMediaPreview = function (trigger) {
                const modal = modalEl();

                if (! trigger?.dataset?.previewUrl || ! modal) {
                    return;
                }

                const isPdf = trigger.dataset.previewPdf === '1';
                const url = trigger.dataset.previewUrl;
                const title = trigger.dataset.previewTitle || 'Preview';
                const downloadUrl = trigger.dataset.previewDownload || url;
                const isIdCard = (trigger.dataset.previewMode || 'document') === 'id-card';

                modal.querySelector('[data-crm-preview-title]').textContent = title;

                modal.querySelectorAll('[data-crm-preview-download]').forEach(function (link) {
                    link.href = downloadUrl;
                });

                const pdfPanel = modal.querySelector('[data-crm-preview-pdf-panel]');
                const imagePanel = modal.querySelector('[data-crm-preview-image-panel]');
                const iframe = modal.querySelector('[data-crm-preview-iframe]');
                const image = modal.querySelector('[data-crm-preview-image]');
                const shell = modal.querySelector('[data-crm-preview-shell]');

                shell.classList.toggle('max-w-[min(100%,56rem)]', isIdCard);
                shell.classList.toggle('bg-gray-950', isIdCard);
                shell.classList.toggle('max-w-[min(100%,42rem)]', ! isIdCard);
                shell.classList.toggle('bg-white', ! isIdCard);
                shell.classList.toggle('dark:bg-gray-900', ! isIdCard);

                if (isPdf) {
                    pdfPanel.hidden = false;
                    imagePanel.hidden = true;
                    iframe.src = url + (url.includes('#') ? '' : '#toolbar=1&navpanes=0');
                } else {
                    pdfPanel.hidden = true;
                    imagePanel.hidden = false;
                    image.src = url;
                }

                modal.hidden = false;
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('overflow-hidden');
            };

            document.addEventListener('click', function (event) {
                const trigger = event.target.closest('.js-media-preview-trigger');

                if (trigger) {
                    event.preventDefault();
                    event.stopPropagation();
                    window.openCrmMediaPreview(trigger);

                    return;
                }

                if (event.target.closest('[data-crm-preview-close]') || event.target.closest('[data-crm-preview-backdrop]')) {
                    event.preventDefault();
                    window.closeCrmMediaPreview();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    window.closeCrmMediaPreview();
                }
            });
        })();
    </script>
@endonce

<div
    id="crm-media-preview-modal"
    hidden
    aria-hidden="true"
    class="fixed inset-0 z-[99999] flex items-center justify-center p-3 sm:p-8"
>
    <div data-crm-preview-backdrop class="absolute inset-0 bg-gray-950/90 backdrop-blur-sm"></div>

    <div
        data-crm-preview-shell
        class="relative flex max-h-[96vh] w-full max-w-[min(100%,42rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-white/10 dark:bg-gray-900"
        role="dialog"
        aria-modal="true"
    >
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-white/10 sm:px-5">
            <h3 data-crm-preview-title class="truncate text-sm font-semibold text-gray-950 dark:text-white">Preview</h3>
            <button
                type="button"
                data-crm-preview-close
                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-white/10 dark:hover:text-white"
                aria-label="Close preview"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
        </div>

        <div data-crm-preview-pdf-panel hidden class="flex flex-1 flex-col overflow-hidden bg-gray-100 dark:bg-gray-950">
            <iframe
                data-crm-preview-iframe
                title="Document preview"
                class="block h-[min(72vh,48rem)] w-full border-0 bg-white"
            ></iframe>
        </div>

        <div data-crm-preview-image-panel hidden class="flex flex-1 items-center justify-center overflow-auto bg-gray-50 p-4 dark:bg-gray-950">
            <img
                data-crm-preview-image
                alt="Preview"
                class="max-h-[70vh] max-w-full rounded-lg object-contain shadow-md"
            />
        </div>

        <div class="flex justify-end gap-2 border-t border-gray-100 px-4 py-3 dark:border-white/10 sm:px-5">
            <a
                data-crm-preview-download
                href="#"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 ring-1 ring-gray-200 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200 dark:ring-white/10"
            >
                Open in tab
            </a>
            <button
                type="button"
                data-crm-preview-close
                class="inline-flex items-center rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-500"
            >
                Close
            </button>
        </div>
    </div>
</div>
