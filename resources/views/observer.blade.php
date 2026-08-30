<div
    x-data
    x-init="$nextTick(() => Livewire.dispatch('fllock::init'))"
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
