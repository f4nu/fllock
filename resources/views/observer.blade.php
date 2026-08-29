<div
    x-data
    x-init="$nextTick(() => Livewire.dispatch('fllock::init'))"
>
    {{-- The beat. `.keep-alive` keeps it running in a backgrounded tab, which is
         the ordinary way an admin leaves an edit page open. --}}
    <div wire:poll{{ $keepAlive ? '.keep-alive' : '' }}{{ $onlyWhenVisible ? '.visible' : '' }}.{{ $interval }}s="beat"></div>

    <script>
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
