<div
    x-data="{
        lastActivity: Date.now(),

        init() {
            $nextTick(() => Livewire.dispatch('fllock::init'))

            // Is anybody still there? A heartbeat from a tab left open on a
            // record its owner walked away from is not a sign of life, and
            // without this the lock outlives the working day.
            const seen = () => this.lastActivity = Date.now()

            for (const event of ['keydown', 'pointerdown', 'pointermove', 'wheel', 'touchstart']) {
                window.addEventListener(event, seen, { passive: true })
            }

            // set(..., false) is deliberate: assigning to $wire would send a
            // request of its own, and these events fire constantly. This way
            // the figure simply rides along with the next heartbeat.
            setInterval(
                () => $wire.set('idleFor', Math.round((Date.now() - this.lastActivity) / 1000), false),
                5000,
            )
        },
    }"
>
    {{-- The beat. `.keep-alive` keeps it running in a backgrounded tab, which is
         the ordinary way an admin leaves an edit page open. --}}
    <div wire:poll{{ $keepAlive ? '.keep-alive' : '' }}{{ $onlyWhenVisible ? '.visible' : '' }}.{{ $interval }}s="beat"></div>

    <script>
        // Leaving the page inside an SPA panel. `wire:navigate` swaps the body
        // without unloading the document, so `pagehide` never fires and the
        // lock sat there until it expired -- minutes during which nobody else
        // could edit the record its owner had already walked away from.
        //
        // Nothing is unloading, so this is an ordinary Livewire request and it
        // completes normally.
        document.addEventListener('livewire:navigating', () => {
            Livewire.dispatch('fllock::release')
        })

        // A real unload: closing the tab, or a full page load. The request may
        // not survive it, which is what the expiry is for -- this is the fast
        // path, not the guarantee.
        //
        // addEventListener, not window.onpagehide =: assigning would silently
        // replace whatever else the app has registered there.
        window.addEventListener('pagehide', () => {
            Livewire.dispatch('fllock::release')
        }, { once: false })

        // A modal closing releases the lock its form was holding. Filament
        // dispatches this for every modal, so the page decides whether it had
        // one to release.
        window.addEventListener('close-modal', () => {
            Livewire.dispatch('fllock::release-modal')
        })
    </script>
</div>
