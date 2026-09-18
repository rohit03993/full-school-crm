{{--
    Menu that paints on the page (not inside the card/table), so overflow-hidden
    parents cannot clip it. Position from the button; do not wait for the
    teleported node or it lands at the left of the page.
--}}
@props([
    'widthClass' => 'min-w-[11rem]',
])

<div
    {{ $attributes->class('relative inline-flex') }}
    x-data="{
        open: false,
        style: '',
        anchor() {
            return this.$refs.trigger.querySelector('button, a, [role=button]') || this.$refs.trigger
        },
        toggle() {
            this.open = ! this.open
            if (this.open) {
                this.place()
                this.$nextTick(() => this.place())
            }
        },
        place() {
            const trigger = this.anchor()
            if (! trigger) {
                return
            }

            const menu = this.$refs.menu
            const rect = trigger.getBoundingClientRect()
            const width = menu && menu.offsetWidth ? menu.offsetWidth : 176
            const height = menu && menu.offsetHeight ? menu.offsetHeight : 0
            let top = rect.bottom + 4
            let left = rect.right - width

            if (left < 8) {
                left = 8
            }

            if (left + width > window.innerWidth - 8) {
                left = Math.max(8, window.innerWidth - width - 8)
            }

            if (height > 0 && top + height > window.innerHeight - 8 && rect.top - height - 4 > 8) {
                top = rect.top - height - 4
            }

            this.style = 'position:fixed;top:' + top + 'px;left:' + left + 'px;z-index:80'
        },
        onOutside(event) {
            if (this.$refs.trigger && this.$refs.trigger.contains(event.target)) {
                return
            }

            this.open = false
        },
    }"
    x-on:keydown.escape.window="open = false"
    x-on:resize.window="open && place()"
    x-on:scroll.window.passive="open && place()"
>
    <div class="inline-flex w-full" x-ref="trigger" x-on:click="toggle()">
        {{ $trigger }}
    </div>

    <template x-teleport="body">
        <div
            x-ref="menu"
            x-show="open"
            x-cloak
            x-transition.opacity
            x-bind:style="style"
            x-on:click.outside="onOutside($event)"
            x-on:click="open = false"
            class="crm-overflow-menu fixed {{ $widthClass }} overflow-hidden rounded-xl bg-white py-1 shadow-lg ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10"
            role="menu"
        >
            {{ $slot }}
        </div>
    </template>
</div>
