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
    public function render() {
        return view('fllock::observer', [
            'interval' => max(1, (int) config('fllock.heartbeat.interval', 20)),
            'keepAlive' => (bool) config('fllock.heartbeat.keep_alive', true),
            'onlyWhenVisible' => (bool) config('fllock.heartbeat.only_when_visible', false),
        ]);
    }

    public function beat(): void {
        $this->dispatch('fllock::heartbeat');
    }
}
