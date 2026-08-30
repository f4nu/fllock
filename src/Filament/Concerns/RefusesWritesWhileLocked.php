<?php

namespace F4nu\Fllock\Filament\Concerns;

use Closure;

/**
 * Refuses every Livewire call on a component whose subject someone else holds.
 *
 * The guards beside this one each cover a way Filament writes: an action, a
 * form, an inline column, a bulk selection, drag-to-reorder, a property with an
 * `updated` hook. Each was added after something got through, which is the
 * problem — a page can always invent one more. A schedule planner is nothing
 * but bespoke methods called straight from JavaScript, and enumerating those
 * would be the same losing game a seventh time.
 *
 * Livewire calls a trait hook for every method invocation and hands it a way to
 * return early, so this inverts the question: nothing runs while someone else
 * holds the lock unless it is named as a read. A page cannot shadow the hook,
 * because Livewire addresses it by the trait's own name.
 *
 * The allowlist is what keeps a locked page readable. It has to let paging,
 * sorting and searching through, or "read-only" becomes "broken".
 */
trait RefusesWritesWhileLocked {
    /** Whether the thing this component edits is held by somebody else. */
    abstract protected function fllockIsLockedByAnother(): bool;

    /** Tell the viewer why nothing happened. */
    abstract protected function fllockNotifyLocked(): void;

    /**
     * @param  array<mixed>  $params
     */
    public function callRefusesWritesWhileLocked(string $methodName, array $params, Closure $returnEarly): void {
        if ($this->fllockIsMethodPermittedWhileLocked($methodName)) {
            return;
        }

        if (! $this->fllockIsLockedByAnother()) {
            return;
        }

        $this->fllockNotifyLocked();

        // Livewire hands the hook a way to short-circuit the call, which is the
        // point of it. Throwing would do the job too, but an exception escaping
        // into a test reads as a broken page rather than a refused write -- and
        // a refused write is the normal, expected outcome here.
        $returnEarly();
    }

    protected function fllockIsMethodPermittedWhileLocked(string $methodName): bool {
        // The lock's own plumbing, and the take-over action.
        if (str_starts_with($methodName, 'fllock')) {
            return true;
        }

        // Livewire's internals, `__dispatch` above all: that is how a dispatched
        // event reaches its listener, including the lock's own. Refuse it and
        // the page can never be told it is locked in the first place.
        //
        // The cost is that a component writing from a listener it exposes to
        // `Livewire.dispatch()` is not covered here. Such a listener is nearly
        // always reachable as an action or a property too, and those are
        // guarded; a page that writes only that way needs its own check.
        if (str_starts_with($methodName, '__')) {
            return true;
        }

        // Filament's action plumbing has its own, finer guard: it knows which
        // actions are permitted while locked, and this one does not.
        if (str_contains($methodName, 'ountAction') || str_contains($methodName, 'ountedAction')) {
            return true;
        }

        return in_array($methodName, config('fllock.permitted_methods', []), true);
    }
}
