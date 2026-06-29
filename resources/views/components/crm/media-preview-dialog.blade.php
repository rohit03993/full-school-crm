@once
    <style>
        #crm-media-preview-modal {
            z-index: 99999;
        }

        #crm-media-preview-modal .crm-media-preview-shell {
            width: min(96vw, 56rem);
            max-height: 92vh;
            display: flex;
            flex-direction: column;
        }

        #crm-media-preview-modal .crm-media-preview-pdf-body {
            flex: 1 1 auto;
            position: relative;
            min-height: 72vh;
            height: 72vh;
            overflow: hidden;
            background: #f3f4f6;
        }

        #crm-media-preview-modal [data-crm-preview-iframe] {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            border: 0;
            background: #fff;
        }

        #crm-media-preview-modal .crm-media-preview-image-body {
            flex: 1 1 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 50vh;
            max-height: 75vh;
            overflow: auto;
            padding: 1rem;
            background: #f9fafb;
        }

        #crm-media-preview-modal [data-crm-preview-image] {
            display: block;
            max-width: 100%;
            max-height: 70vh;
            width: auto;
            height: auto;
            object-fit: contain;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        }

        .dark #crm-media-preview-modal .crm-media-preview-pdf-body {
            background: #111827;
        }

        .dark #crm-media-preview-modal .crm-media-preview-image-body {
            background: #030712;
        }
    </style>
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
                const pdfBody = modal.querySelector('.crm-media-preview-pdf-body');

                shell.style.width = isIdCard ? 'min(96vw, 56rem)' : 'min(96vw, 52rem)';
                pdfBody.style.minHeight = isIdCard ? '28rem' : '72vh';
                pdfBody.style.height = isIdCard ? '28rem' : '72vh';

                if (isPdf) {
                    pdfPanel.hidden = false;
                    imagePanel.hidden = true;
                    const hash = 'toolbar=1&navpanes=0&scrollbar=1&view=Fit';
                    iframe.src = url.includes('#') ? url : url + '#' + hash;
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
    style="position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; padding: 0.75rem;"
>
    <div data-crm-preview-backdrop style="position: absolute; inset: 0; background: rgb(3 7 18 / 0.9); backdrop-filter: blur(4px);"></div>

    <div
        data-crm-preview-shell
        class="crm-media-preview-shell relative overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-white/10 dark:bg-gray-900"
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

        <div data-crm-preview-pdf-panel hidden class="crm-media-preview-pdf-body">
            <iframe
                data-crm-preview-iframe
                title="Document preview"
            ></iframe>
        </div>

        <div data-crm-preview-image-panel hidden class="crm-media-preview-image-body">
            <img data-crm-preview-image alt="Preview" />
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
