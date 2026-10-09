<div
    class="flex flex-wrap items-center gap-2"
    x-data="{
        listening: false,
        supported: true,
        message: '',
        lang: 'hi-IN',
        recognition: null,
        init() {
            const Speech = window.SpeechRecognition || window.webkitSpeechRecognition
            this.supported = typeof Speech === 'function'
            if (! this.supported) {
                this.message = 'Speech is not available in this browser. Type the homework.'
            }
        },
        toggle() {
            if (this.listening) {
                this.stop()
                return
            }
            this.start()
        },
        start() {
            const Speech = window.SpeechRecognition || window.webkitSpeechRecognition
            if (typeof Speech !== 'function') {
                this.supported = false
                this.message = 'Speech is not available in this browser. Type the homework.'
                return
            }
            const recognition = new Speech()
            recognition.lang = this.lang
            recognition.interimResults = false
            recognition.continuous = false
            let wrote = false
            recognition.onresult = (event) => {
                if (wrote) {
                    return
                }
                wrote = true
                const last = event.results[event.results.length - 1]
                const said = last && last[0] ? last[0].transcript : ''
                this.writeIntoHomework(said)
            }
            recognition.onerror = (event) => {
                this.listening = false
                if (event.error === 'aborted') {
                    return
                }
                if (event.error === 'no-speech') {
                    this.message = 'No speech heard. Tap Speak and try again.'
                    return
                }
                if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
                    this.message = 'Allow the microphone, then tap Speak again.'
                    return
                }
                this.message = 'Speech could not be read. You can still type the homework.'
            }
            recognition.onend = () => {
                this.listening = false
                if (this.message === 'Listening...') {
                    this.message = ''
                }
            }
            this.recognition = recognition
            this.listening = true
            this.message = 'Listening...'
            try {
                recognition.start()
            } catch (error) {
                this.listening = false
                this.message = 'Speech could not be read. You can still type the homework.'
            }
        },
        stop() {
            if (this.recognition) {
                this.recognition.stop()
            }
            this.listening = false
        },
        writeIntoHomework(said) {
            const clean = (said || '').trim()
            const box = document.querySelector('textarea[data-homework-speech=description]')
            if (! box || clean === '') {
                return
            }
            let alpine = null
            try {
                alpine = window.Alpine ? window.Alpine.$data(box) : null
            } catch (error) {
                alpine = null
            }
            const current = String((alpine && alpine.state) ? alpine.state : (box.value || '')).trim()
            const next = current === '' ? clean : (current + ' ' + clean)
            if (alpine) {
                alpine.state = next
            } else {
                box.value = next
                box.dispatchEvent(new Event('input', { bubbles: true }))
            }
        },
    }"
>
    <button
        type="button"
        x-show="supported"
        x-on:click="toggle()"
        class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-white/5"
    >
        <span x-show="! listening">Speak</span>
        <span x-show="listening">Stop</span>
    </button>
    <label x-show="supported" class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
        <span>Language</span>
        <select
            x-model="lang"
            x-bind:disabled="listening"
            class="rounded-lg border border-gray-300 bg-white px-2 py-1 text-xs text-gray-900 dark:border-white/10 dark:bg-gray-900 dark:text-white"
        >
            <option value="hi-IN">Hindi</option>
            <option value="en-IN">English</option>
        </select>
    </label>
    <p x-show="message !== ''" x-text="message" class="text-xs text-gray-500 dark:text-gray-400"></p>
</div>
