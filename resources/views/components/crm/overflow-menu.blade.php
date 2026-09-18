{{--
    Menu that paints on the page (not inside the card/table), so overflow-hidden
    parents cannot clip it. Use for ⋯ / More actions anywhere in the CRM.
--}}
@props([
    'widthClass' => 'min-w-[11rem]',
])

<div
    {{ $attributes->class('relative') }}
    x-data="{
        open: false,
        style: '',
        toggle() {
            this.open = ! this.open
            if (this.open) {
                this.$nextTick(() => this.place())
            }
        },
        place() {
            const trigger = this.$refs.trigger
            const menu = this.$refs.menu
            if (! trigger || ! menu) {
                return
            }

            const rect = trigger.getBoundingClientRect()
            const width = menu.offsetWidth || 176
            const height = menu.offsetHeight || 0
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

            this.style = 'position:fixed;top:' + top + 'px;left:' + left + 'px'
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
    <div class="w-full" x-ref="trigger" x-on:click="toggle()">
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
            class="crm-overflow-menu {{ $widthClass }} overflow-hidden rounded-xl bg-white py-1 shadow-lg ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10"
            role="menu"
        >
            {{ $slot }}
        </div>
    </template>
</div>
