<?php

namespace F4nu\Fllock\Livewire;

use Livewire\Component;

/**
 * The heartbeat behind every lock.
 *
 * A Filament panel in SPA mode never fires a page unload, so this is not an
 * optimisation: the beat is what renews a live editor's lock and what lets an
 * abandoned one lapse. It is mounted once per panel and shouts into the void;
 * whichever page is listening picks the events up.
 */
class RecordLockObserver extends Component {
    /**
     * Seconds since the viewer last touched the keyboard, mouse or screen, as
     * reported by the page.
     */
    public int $idleFor = 0;

    public function render() {
        return view('fllock::observer', [
            'interval' => max(1, (int) config('fllock.heartbeat.interval', 20)),
            'keepAlive' => (bool) config('fllock.heartbeat.keep_alive', true),
            'onlyWhenVisible' => (bool) config('fllock.heartbeat.only_when_visible', false),
        ]);
    }

    public function beat(): void {
        if (! $this->shouldBeat()) {
            return;
        }

        $this->dispatch('fllock::heartbeat');
    }

    /**
     * Whether this beat counts as a sign of life.
     *
     * A tab left open on a record its owner walked away from goes on beating
     * for as long as the browser is running, and a lock that is renewed forever
     * is a lock that never times out. Staying quiet lets it lapse.
     *
     * Public, and separate from `beat()`, so it can be exercised without
     * rendering a Livewire component.
     */
    public function shouldBeat(): bool {
        $idleAfter = config('fllock.heartbeat.idle_after');

        return $idleAfter === null || $this->idleFor <= (int) $idleAfter;
    }
}
