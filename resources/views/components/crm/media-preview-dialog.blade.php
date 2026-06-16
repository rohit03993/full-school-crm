@once
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
@endonce

<div
    x-data="{
        open: false,
        url: '',
        downloadUrl: '',
        title: 'Preview',
        isPdf: false,
        previewMode: 'document',
        loading: false,
        pdfError: null,
        get isIdCard() {
            return this.previewMode === 'id-card';
        },
        close() {
            this.open = false;
            this.url = '';
            this.downloadUrl = '';
            this.previewMode = 'document';
            this.loading = false;
            this.pdfError = null;
            document.body.classList.remove('overflow-hidden');
            const canvas = this.$refs.pdfCanvas;
            if (canvas) {
                const ctx = canvas.getContext('2d');
                ctx?.clearRect(0, 0, canvas.width, canvas.height);
                canvas.width = 0;
                canvas.height = 0;
            }
        },
        openPreview(detail) {
            this.title = detail.title || 'Preview';
            this.isPdf = Boolean(detail.isPdf);
            this.previewMode = detail.previewMode || 'document';
            this.url = detail.url;
            this.downloadUrl = detail.downloadUrl || detail.url;
            this.open = true;
            document.body.classList.add('overflow-hidden');
            if (this.isPdf) {
                this.$nextTick(() => this.renderPdf());
            }
        },
        async renderPdf() {
            if (! this.url) {
                return;
            }

            if (typeof pdfjsLib === 'undefined') {
                this.pdfError = 'PDF viewer failed to load. Use Download instead.';
                return;
            }

            this.loading = true;
            this.pdfError = null;

            try {
                pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

                const pdf = await pdfjsLib.getDocument({ url: this.url, withCredentials: true }).promise;
                const page = await pdf.getPage(1);
                const canvas = this.$refs.pdfCanvas;
                const frame = this.$refs.pdfFrame;
                const context = canvas.getContext('2d');
                const frameWidth = Math.max(frame.clientWidth - (this.isIdCard ? 48 : 0), 280);
                const baseViewport = page.getViewport({ scale: 1 });
                const scale = frameWidth / baseViewport.width;
                const viewport = page.getViewport({ scale });
                const outputScale = window.devicePixelRatio || 1;

                canvas.width = Math.floor(viewport.width * outputScale);
                canvas.height = Math.floor(viewport.height * outputScale);
                canvas.style.width = Math.floor(viewport.width) + 'px';
                canvas.style.height = Math.floor(viewport.height) + 'px';

                context.setTransform(outputScale, 0, 0, outputScale, 0, 0);
                await page.render({ canvasContext: context, viewport }).promise;
            } catch (error) {
                this.pdfError = 'Could not load PDF preview. Try Download instead.';
            } finally {
                this.loading = false;
            }
        },
    }"
    x-on:open-media-preview.window="openPreview($event.detail)"
    x-on:keydown.escape.window="if (open) close()"
    x-cloak
>
    {{-- Teleport to body so fixed overlay works (Filament layout breaks position:fixed inside content). --}}
    <template x-teleport="body">
        {{-- Image preview popup --}}
        <div
            x-show="open && ! isPdf"
            x-transition.opacity
            class="fixed inset-0 z-[99999] flex items-center justify-center p-4"
            style="display: none;"
            role="dialog"
            aria-modal="true"
        >
            <div class="absolute inset-0 bg-gray-950/75 backdrop-blur-sm" x-on:click="close()"></div>

            <div
                class="relative flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10"
                x-on:click.stop
            >
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-white/10 sm:px-5">
                    <h3 class="truncate text-sm font-semibold text-gray-950 dark:text-white" x-text="title"></h3>
                    <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-white/10 dark:hover:text-white" x-on:click="close()" aria-label="Close preview">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="flex flex-1 items-center justify-center overflow-auto bg-gray-50 p-4 dark:bg-gray-950">
                    <img x-bind:src="url" x-bind:alt="title" class="max-h-[70vh] max-w-full rounded-lg object-contain shadow-md" />
                </div>

                <div class="flex justify-end gap-2 border-t border-gray-100 px-4 py-3 dark:border-white/10 sm:px-5">
                    <a x-bind:href="downloadUrl" target="_blank" rel="noopener" class="inline-flex items-center rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 ring-1 ring-gray-200 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200 dark:ring-white/10">Open in tab</a>
                    <button type="button" class="inline-flex items-center rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-500" x-on:click="close()">Close</button>
                </div>
            </div>
        </div>

        {{-- PDF / ID card popup --}}
        <div
            x-show="open && isPdf"
            x-transition.opacity
            class="fixed inset-0 z-[99999] flex items-center justify-center p-3 sm:p-8"
            style="display: none;"
            role="dialog"
            aria-modal="true"
        >
            <div class="absolute inset-0 bg-gray-950/90 backdrop-blur-sm" x-on:click="close()"></div>

            <div
                class="relative flex max-h-[96vh] w-full flex-col overflow-hidden rounded-2xl shadow-2xl ring-1 ring-white/10"
                :class="isIdCard ? 'max-w-[min(100%,56rem)] bg-gray-950' : 'max-w-[min(100%,42rem)] bg-white dark:bg-gray-900'"
                x-on:click.stop
            >
                <div
                    class="flex items-center justify-between gap-3 border-b px-4 py-3 sm:px-5"
                    :class="isIdCard ? 'border-white/10 bg-gray-900/80' : 'border-gray-100 dark:border-white/10'"
                >
                    <h3
                        class="truncate text-sm font-semibold"
                        :class="isIdCard ? 'text-white' : 'text-gray-950 dark:text-white'"
                        x-text="title"
                    ></h3>
                    <button
                        type="button"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-lg hover:bg-white/10"
                        :class="isIdCard ? 'text-gray-300' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-white/10 dark:hover:text-white'"
                        x-on:click="close()"
                        aria-label="Close preview"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div
                    x-ref="pdfFrame"
                    class="flex flex-1 items-center justify-center overflow-auto px-4 py-6 sm:px-8 sm:py-8"
                    :class="isIdCard ? 'bg-[#0f0f0f]' : 'bg-gray-100 dark:bg-gray-950'"
                >
                    <div class="w-full" :class="isIdCard ? 'max-w-[48rem]' : 'max-w-[36rem]'">
                        <div
                            x-show="loading"
                            class="flex items-center justify-center rounded-xl shadow-2xl ring-1 ring-white/10"
                            :class="isIdCard ? 'aspect-[16/9] bg-gray-900' : 'min-h-[28rem] bg-white dark:bg-gray-800'"
                        >
                            <p class="text-sm text-gray-400">Loading…</p>
                        </div>

                        <div
                            x-show="pdfError"
                            class="flex flex-col items-center justify-center gap-3 rounded-xl px-6 py-12 text-center shadow-2xl ring-1 ring-white/10"
                            :class="isIdCard ? 'aspect-[16/9] bg-gray-900' : 'min-h-[16rem] bg-white dark:bg-gray-800'"
                        >
                            <p class="text-sm text-gray-300" x-text="pdfError"></p>
                            <a x-bind:href="downloadUrl" class="inline-flex items-center rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-500">Download PDF</a>
                        </div>

                        <div
                            x-show="! loading && ! pdfError"
                            class="mx-auto overflow-hidden shadow-2xl ring-1 ring-amber-500/30"
                            :class="isIdCard ? 'w-full rounded-2xl bg-white' : 'rounded-lg bg-white dark:ring-white/10'"
                        >
                            <canvas x-ref="pdfCanvas" class="mx-auto block h-auto w-full"></canvas>
                        </div>
                    </div>
                </div>

                <div
                    class="flex justify-end gap-2 border-t px-4 py-3 sm:px-5"
                    :class="isIdCard ? 'border-white/10 bg-gray-900/80' : 'border-gray-100 dark:border-white/10'"
                >
                    <a
                        x-bind:href="downloadUrl"
                        class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-semibold ring-1"
                        :class="isIdCard ? 'bg-white/10 text-gray-200 ring-white/10 hover:bg-white/15' : 'bg-gray-100 text-gray-700 ring-gray-200 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200 dark:ring-white/10'"
                    >
                        <span x-text="isIdCard ? 'Download ID Card' : 'Download PDF'"></span>
                    </a>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-500"
                        x-on:click="close()"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
