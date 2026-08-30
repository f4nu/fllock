<?php

namespace F4nu\Fllock\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;

/**
 * Locks an EditRecord page's record for as long as the page is open.
 *
 * A second editor gets the page read-only: every field disabled, the save and
 * header actions gone, a banner naming who holds it, and -- if they are allowed
 * to -- one action to take it anyway.
 *
 * The guards are deliberately doubled. Hiding a control is a rendering
 * decision, and a render happens after the observer has reported in; refusing
 * the call is what covers the window before that, and anything that reaches the
 * endpoint without going through the page at all.
 */
trait LocksRecordWhileEditing {
    public bool $isReadOnly = false;

    public ?string $recordLockOwner = null;

    /**
     * Disabling the form has to be declarative. Stamping `disabled(true)` into
     * the schema once, when the lock is first detected, holds only until
     * something rebuilds it -- switching relation manager tabs is enough -- and
     * then every field on a record someone else is editing comes back to life.
     */
    public function form(Schema $schema): Schema {
        return parent::form($schema)->disabled(fn (): bool => $this->isReadOnly);
    }

    /**
     * The same thing again, where a host page cannot switch it off by accident.
     *
     * `form()` above is the readable version, and it is also the one that stops
     * working the moment a page defines its own `form()` -- a class method beats
     * a trait method, so the guard vanishes with no error and the page just goes
     * writable. That is not a hypothetical: it happened on the first page this
     * package was pointed at that builds a custom schema.
     *
     * Livewire calls trait hooks by their trait's name, so this one cannot be
     * shadowed by the class, and it runs on every render rather than once.
     */
    public function renderingLocksRecordWhileEditing(): void {
        $this->getSchema('form')?->disabled(fn (): bool => $this->isReadOnly);
    }

    #[On('fllock::init')]
    public function fllockInit(): void {
        $this->syncRecordLock();
    }

    #[On('fllock::heartbeat')]
    public function fllockHeartbeat(): void {
        $this->syncRecordLock();
    }

    #[On('fllock::release')]
    public function fllockRelease(): void {
        $this->record?->unlock();
    }

    /**
     * Take the lock, renew it, or fall to read-only. Called on every heartbeat,
     * so it is also what lets a page recover: if the other editor leaves and the
     * lock lapses, the next beat picks it up and the page becomes writable
     * again without a reload.
     */
    protected function syncRecordLock(): void {
        $record = $this->record ?? null;

        if ($record === null) {
            return;
        }

        $wasReadOnly = $this->isReadOnly;

        $record->refresh();

        if ($record->lock()) {
            $this->isReadOnly = false;
            $this->recordLockOwner = null;
        } else {
            $this->isReadOnly = true;
            $this->recordLockOwner = $record->recordLockOwnerName();
        }

        if ($this->isReadOnly && ! $wasReadOnly) {
            $this->notifyRecordIsLocked();
        }
    }

    protected function notifyRecordIsLocked(): void {
        Notification::make('fllock')
            ->warning()
            ->persistent()
            ->title($this->recordLockOwner
                ? __('fllock::fllock.locked_by', ['name' => $this->recordLockOwner])
                : __('fllock::fllock.locked'))
            ->send();
    }

    /**
     * Save and Cancel go too. Disabling the fields without removing these
     * leaves a page that looks editable and refuses at the last step -- and the
     * refusal is server-side, so the only feedback is the click doing nothing.
     *
     * @return array<mixed>
     */
    protected function getFormActions(): array {
        return $this->isReadOnly ? [] : parent::getFormActions();
    }

    /**
     * Read-only means read-only: Delete and every other header action goes.
     *
     * What replaces them is the one action that makes sense on a record you
     * cannot edit -- taking it. Putting it here rather than in a hidden modal
     * keeps it authorizable and testable like any other action.
     *
     * @return array<mixed>
     */
    public function getCachedHeaderActions(): array {
        $actions = parent::getCachedHeaderActions();

        if (! $this->isReadOnly) {
            return $actions;
        }

        return array_values(array_filter(
            $actions,
            fn ($action): bool => method_exists($action, 'getName')
                && $action->getName() === 'fllockForceUnlock',
        ));
    }

    /** @return array<mixed> */
    protected function getHeaderActions(): array {
        return array_merge(parent::getHeaderActions(), [
            Action::make('fllockForceUnlock')
                ->label(__('fllock::fllock.take_over'))
                ->icon('heroicon-o-lock-open')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription(__('fllock::fllock.take_over_warning'))
                ->visible(fn (): bool => $this->isReadOnly && $this->canForceUnlockRecord())
                ->action(function (): void {
                    $this->record->unlock(force: true);
                    $this->record->refresh();
                    $this->syncRecordLock();
                    $this->fillForm();
                }),
        ]);
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
    public function updatingLocksRecordWhileEditing(string $property, mixed $value): void {
        if ($this->fllockIsUnguardedProperty($property)) {
            return;
        }

        if (! $this->isRecordLockedByAnother()) {
            return;
        }

        $this->notifyRecordIsLocked();

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

    protected function isRecordLockedByAnother(): bool {
        $record = $this->record ?? null;

        if ($record === null || ! method_exists($record, 'isLockedByAnotherUser')) {
            return false;
        }

        $record->refresh();

        return $record->isLockedByAnotherUser();
    }

    protected function canForceUnlockRecord(): bool {
        $gate = config('fllock.unlock_gate');

        return $gate === null || Gate::allows($gate);
    }

    /**
     * @param  array<mixed>  $arguments
     * @param  array<mixed>  $context
     */
    public function mountAction(string $name, array $arguments = [], array $context = []): mixed {
        if ($this->refuseWhileLocked($name)) {
            return null;
        }

        return parent::mountAction($name, $arguments, $context);
    }

    /** @param array<mixed> $arguments */
    public function callMountedAction(array $arguments = []): mixed {
        if ($this->refuseWhileLocked($this->getMountedAction()?->getName())) {
            return null;
        }

        return parent::callMountedAction($arguments);
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void {
        if ($this->refuseWhileLocked(null)) {
            return;
        }

        parent::save($shouldRedirect, $shouldSendSavedNotification);
    }

    /**
     * Re-reads the lock rather than trusting `$isReadOnly`, which is only ever
     * as fresh as the last heartbeat.
     */
    protected function refuseWhileLocked(?string $actionName): bool {
        $record = $this->record ?? null;

        if ($record === null) {
            return false;
        }

        if ($actionName !== null && in_array($actionName, array_merge(
            config('fllock.permitted_actions', []),
            ['fllockForceUnlock'],
        ), true)) {
            return false;
        }

        $record->refresh();

        if (! $record->isLockedByAnotherUser()) {
            return false;
        }

        $this->isReadOnly = true;
        $this->recordLockOwner = $record->recordLockOwnerName();
        $this->notifyRecordIsLocked();

        return true;
    }
}
