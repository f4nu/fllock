<?php

namespace F4nu\Fllock\Filament\Concerns;

use F4nu\Fllock\Models\PageLock;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;

/**
 * Locks a settings page — one with no record behind it.
 *
 * `LocksRecordWhileEditing` needs a `$record` to attach a lock to, and a
 * settings page has none: it is one form over a singleton, which is precisely
 * the shape where a second editor's save silently overwrites the first and
 * nothing anywhere says so.
 *
 * The subject is the page class by default. Two pages editing the same settings
 * should override `recordLockSubject()` to return the same string, or each will
 * happily lock its own view of one shared thing.
 */
trait LocksPageWhileEditing {
    use RefusesWritesWhileLocked;

    public bool $isReadOnly = false;

    public ?string $recordLockOwner = null;

    protected function recordLockSubject(): string {
        return static::class;
    }

    public function pageLock(): PageLock {
        return PageLock::for($this->recordLockSubject());
    }

    #[On('fllock::init')]
    public function fllockInit(): void {
        $this->syncPageLock();
    }

    #[On('fllock::heartbeat')]
    public function fllockHeartbeat(): void {
        $this->syncPageLock();
    }

    #[On('fllock::release')]
    public function fllockRelease(): void {
        $this->pageLock()->unlock();
    }

    protected function syncPageLock(): void {
        $wasReadOnly = $this->isReadOnly;
        $lock = $this->pageLock();

        if ($lock->lock()) {
            $this->isReadOnly = false;
            $this->recordLockOwner = null;
        } else {
            $this->isReadOnly = true;
            $this->recordLockOwner = $lock->ownerName();
        }

        if (! $this->isReadOnly && $wasReadOnly) {
            $this->fllockDismissLockNotice();
        }

        if ($this->isReadOnly && ! $wasReadOnly) {
            $this->notifyPageIsLocked();
        }

        // Nothing about the lock changed, so nothing on the page needs redrawing.
        // Without this every heartbeat re-renders the component -- twenty
        // seconds apart, forever -- and a page whose interactivity is built in
        // Alpine gets it torn down and rebuilt underneath the person using it.
        // On a schedule planner that meant a drag in progress sometimes simply
        // did not happen.
        if ($this->isReadOnly === $wasReadOnly) {
            $this->skipRender();
        }
    }

    /**
     * Applied on every render from a Livewire trait hook, which a host page
     * cannot shadow by defining its own `form()`.
     */
    public function renderingLocksPageWhileEditing(): void {
        foreach ($this->getCachedSchemas() as $schema) {
            $schema->disabled(fn (): bool => $this->isReadOnly);
        }
    }

    /**
     * @param  array<mixed>  $arguments
     * @param  array<mixed>  $context
     */
    public function mountAction(string $name, array $arguments = [], array $context = []): mixed {
        if ($this->refusePageActionWhileLocked($name)) {
            return null;
        }

        return parent::mountAction($name, $arguments, $context);
    }

    /** @param array<mixed> $arguments */
    public function callMountedAction(array $arguments = []): mixed {
        if ($this->refusePageActionWhileLocked($this->getMountedAction()?->getName())) {
            return null;
        }

        return parent::callMountedAction($arguments);
    }

    protected function refusePageActionWhileLocked(?string $actionName): bool {
        if ($actionName !== null && in_array($actionName, array_merge(
            config('fllock.permitted_actions', []),
            ['fllockForceUnlock'],
        ), true)) {
            return false;
        }

        $lock = $this->pageLock();

        if (! $lock->isLockedByAnotherUser()) {
            return false;
        }

        $this->isReadOnly = true;
        $this->recordLockOwner = $lock->ownerName();
        $this->notifyPageIsLocked();

        return true;
    }


    /**
     * Refuses a bare Livewire property write while someone else holds the lock.
     *
     * A page can write without an action, a form or a table: a public property
     * with an `updatedFoo()` hook is arbitrary application code, and no guard
     * above sees it. The Twitch settings page picks its current event exactly
     * that way.
     *
     * Livewire calls trait hooks before the component's own, so halting here
     * stops the property being set *and* the app's hook from running. `Halt` is
     * Filament's own stop-cleanly exception, so this reads as a refusal rather
     * than a 500.
     */
    public function updatingLocksPageWhileEditing(string $property, mixed $value): void {
        if ($this->fllockIsUnguardedProperty($property)) {
            return;
        }

        if (! $this->pageLock()->isLockedByAnotherUser()) {
            return;
        }

        $this->notifyPageIsLocked();

        throw new Halt();
    }

    /**
     * Property paths the lock ignores.
     *
     * Reading a locked page has to keep working: searching, sorting, paging and
     * switching tabs are reads, and so is typing into a form that is already
     * disabled and whose save is refused anyway. Halting on those turns a
     * read-only page into a page that throws.
     *
     * @return array<string>
     */
    protected function fllockUnguardedProperties(): array {
        return [
            'isReadOnly',
            'recordLockOwner',
            'data',
            'mountedActions',
            'mountedActionsData',
            'mountedActionsArguments',
            'activeRelationManager',
            'activeTab',
            'paginators',
            'page',
            'tableSearch',
            'tableColumnSearches',
            'tableFilters',
            'tableSortColumn',
            'tableSortDirection',
            'toggledTableColumns',
            'selectedTableRecords',
        ];
    }

    protected function fllockIsUnguardedProperty(string $property): bool {
        foreach ($this->fllockUnguardedProperties() as $unguarded) {
            if ($property === $unguarded || str_starts_with($property, $unguarded . '.')) {
                return true;
            }
        }

        return false;
    }

    public function canForceUnlockPage(): bool {
        $gate = config('fllock.unlock_gate');

        return $gate === null || Gate::allows($gate);
    }

    /**
     * Take the banner down once the page becomes writable again.
     *
     * It is persistent on purpose -- a read-only page has to keep saying why --
     * so nothing removes it when the lock is handed over, and the page ends up
     * editable underneath a notice claiming it is not.
     */
    protected function fllockDismissLockNotice(): void {
        $this->dispatch('close-notification', id: 'fllock');
    }

    protected function notifyPageIsLocked(): void {
        Notification::make('fllock')
            ->warning()
            ->persistent()
            ->title($this->recordLockOwner
                ? __('fllock::fllock.locked_by', ['name' => $this->recordLockOwner])
                : __('fllock::fllock.locked'))
            ->send();
    }

    protected function fllockIsLockedByAnother(): bool {
        return $this->pageLock()->isLockedByAnotherUser();
    }

    protected function fllockNotifyLocked(): void {
        $this->notifyPageIsLocked();
    }
}
