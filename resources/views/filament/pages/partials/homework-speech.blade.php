@php
    $speechSelector = $speechSelector ?? 'textarea[data-homework-speech=description]';
    $speechWire = $speechWire ?? '';
@endphp

<div
    class="mt-2 flex flex-wrap items-center gap-2"
    data-speech-selector="{{ $speechSelector }}"
    data-speech-wire="{{ $speechWire }}"
    x-data="{
        listening: false,
        supported: true,
        message: '',
        lang: 'hi-IN',
        recognition: null,
        baseText: '',
        spokenFinal: '',
        spokenInterim: '',
        gotSpeech: false,
        init() {
            const Speech = window.SpeechRecognition || window.webkitSpeechRecognition
            this.supported = typeof Speech === 'function'
            if (! this.supported) {
                this.message = 'Speech is not available in this browser. You can still type.'
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
                this.message = 'Speech is not available in this browser. You can still type.'
                return
            }
            this.baseText = this.readBox()
            this.spokenFinal = ''
            this.spokenInterim = ''
            this.gotSpeech = false
            const recognition = new Speech()
            recognition.lang = this.lang
            recognition.interimResults = true
            recognition.continuous = true
            recognition.onresult = (event) => {
                let finalText = ''
                let interimText = ''
                for (let index = 0; index < event.results.length; index++) {
                    const piece = event.results[index][0] ? event.results[index][0].transcript : ''
                    if (event.results[index].isFinal) {
                        finalText += piece
                    } else {
                        interimText += piece
                    }
                }
                this.spokenFinal = finalText.trim()
                this.spokenInterim = interimText.trim()
                const spoken = [this.spokenFinal, this.spokenInterim].filter(Boolean).join(' ').trim()
                if (spoken !== '') {
                    this.gotSpeech = true
                }
                this.putText(this.joinText(this.baseText, spoken))
            }
            recognition.onerror = (event) => {
                if (event.error === 'aborted') {
                    return
                }
                this.listening = false
                if (event.error === 'no-speech') {
                    this.message = 'No speech heard. Tap Speak and try again.'
                    return
                }
                if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
                    this.message = 'Allow the microphone, then tap Speak again.'
                    return
                }
                if (event.error === 'language-not-supported') {
                    this.message = 'This language is not available. Choose English and tap Speak again.'
                    return
                }
                this.message = 'Speech could not be read. You can still type.'
            }
            recognition.onend = () => {
                this.commitSpoken()
                this.listening = false
                if (this.message === 'Listening...') {
                    this.message = this.gotSpeech ? '' : 'No speech heard. Tap Speak and try again.'
                }
            }
            this.recognition = recognition
            this.listening = true
            this.message = 'Listening...'
            try {
                recognition.start()
            } catch (error) {
                this.listening = false
                this.message = 'Speech could not be read. You can still type.'
            }
        },
        stop() {
            this.listening = false
            this.commitSpoken()
            if (this.recognition) {
                try {
                    this.recognition.stop()
                } catch (error) {
                }
            }
            if (this.message === 'Listening...') {
                this.message = ''
            }
        },
        commitSpoken() {
            const spoken = [this.spokenFinal, this.spokenInterim].filter(Boolean).join(' ').trim()
            if (spoken === '') {
                return
            }
            this.baseText = this.joinText(this.baseText, spoken)
            this.spokenFinal = ''
            this.spokenInterim = ''
            this.putText(this.baseText)
        },
        joinText(base, spoken) {
            const left = String(base || '').trim()
            const right = String(spoken || '').trim()
            if (right === '') {
                return left
            }
            if (left === '') {
                return right
            }
            return left + ' ' + right
        },
        box() {
            const selector = this.$el.getAttribute('data-speech-selector')
            if (! selector) {
                return null
            }
            const root = this.$el.closest('form, .fi-modal, [role=dialog]') || document
            const matches = root.querySelectorAll(selector)
            for (let index = 0; index < matches.length; index++) {
                if (matches[index].getClientRects().length > 0) {
                    return matches[index]
                }
            }
            if (matches.length > 0) {
                return matches[matches.length - 1]
            }
            return document.querySelector(selector)
        },
        readBox() {
            const box = this.box()
            if (! box) {
                return ''
            }
            if (box.hasAttribute('x-data') && window.Alpine) {
                try {
                    const alpine = window.Alpine.$data(box)
                    if (alpine && alpine.state) {
                        return String(alpine.state)
                    }
                } catch (error) {
                }
            }
            return String(box.value || '')
        },
        wirePath(box) {
            const marked = this.$el.getAttribute('data-speech-wire') || ''
            if (marked !== '') {
                return marked
            }
            if (! box) {
                return ''
            }
            for (let index = 0; index < box.attributes.length; index++) {
                const name = box.attributes[index].name
                if (name === 'wire:model' || name.indexOf('wire:model.') === 0) {
                    return box.attributes[index].value
                }
            }
            return ''
        },
        putText(next) {
            const box = this.box()
            if (! box) {
                this.message = 'Could not find the text box. You can still type.'
                return
            }
            const value = String(next || '')
            const setter = Object.getOwnPropertyDescriptor(window.HTMLTextAreaElement.prototype, 'value')
            if (setter && setter.set) {
                setter.set.call(box, value)
            } else {
                box.value = value
            }
            if (box.hasAttribute('x-data') && window.Alpine) {
                try {
                    const alpine = window.Alpine.$data(box)
                    if (alpine) {
                        alpine.state = value
                    }
                } catch (error) {
                }
            }
            if (box._x_model && typeof box._x_model.set === 'function') {
                box._x_model.set(value)
            }
            box.dispatchEvent(new Event('input', { bubbles: true }))
            box.dispatchEvent(new Event('change', { bubbles: true }))
            const path = this.wirePath(box)
            if (path !== '' && this.$wire && typeof this.$wire.set === 'function') {
                this.$wire.set(path, value, false)
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
