<?php

namespace F4nu\Fllock\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
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
    use RefusesStaleWrites;
    use RefusesWritesWhileLocked;

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
        $this->rememberRecordFingerprint();
    }

    #[On('fllock::heartbeat')]
    public function fllockHeartbeat(): void {
        $this->syncRecordLock();

        // Somebody wrote to this record while the form sat open -- the API, a
        // command, a job. Say so now rather than at save time.
        if (! $this->isStale && $this->recordChangedElsewhere()) {
            $this->isStale = true;
            $this->notifyRecordChangedElsewhere();
        }
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

        // unsetRelation, not refresh(): the lock has to be read fresh, the
        // record does not. refresh() throws away every loaded relation with it,
        // and a page built from those relations -- a schedule planner, say --
        // then rebuilds itself from nothing. That broke drag-to-reorder, and
        // once the heartbeat started calling this every twenty seconds it broke
        // it only sometimes, which was worse.
        $record->unsetRelation('recordLock');

        if ($record->lock()) {
            $this->isReadOnly = false;
            $this->recordLockOwner = null;
        } else {
            $this->isReadOnly = true;
            $this->recordLockOwner = $record->recordLockOwnerName();
        }

        if (! $this->isReadOnly && $wasReadOnly) {
            $this->fllockDismissLockNotice();
        }

        if ($this->isReadOnly && ! $wasReadOnly) {
            $this->notifyRecordIsLocked();
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
     * Take the banner down once the page becomes writable again.
     *
     * It is persistent on purpose -- a read-only page has to keep saying why --
     * so nothing removes it when the lock is handed over, and the page ends up
     * editable underneath a notice claiming it is not.
     */
    protected function fllockDismissLockNotice(): void {
        $this->dispatch('close-notification', id: 'fllock');
    }

    protected function notifyRecordIsLocked(): void {
        $notification = Notification::make('fllock')
            ->warning()
            ->persistent()
            ->title($this->recordLockOwner
                ? __('fllock::fllock.locked_by', ['name' => $this->recordLockOwner])
                : __('fllock::fllock.locked'));

        // The take-over lives on the notification, not in the header.
        //
        // A header action has to be registered through the page's own
        // `getHeaderActions()`, and every page worth locking defines that
        // itself -- a class method beats a trait one, so the action never
        // existed. Caching it by hand got it drawn, and then Filament could not
        // resolve it when the button was pressed, so the confirmation appeared
        // and confirming it did nothing. A notification action is just a
        // dispatched event, and needs none of that machinery.
        if ($this->canForceUnlockRecord()) {
            $notification->actions([
                Action::make('fllockTakeOver')
                    ->label(__('fllock::fllock.take_over'))
                    ->button()
                    ->color('danger')
                    ->dispatch('fllock::take-over'),
            ]);
        }

        $notification->send();
    }

    #[On('fllock::take-over')]
    public function fllockTakeOver(): void {
        if (! $this->canForceUnlockRecord()) {
            return;
        }

        $this->record->unlock(force: true);
        // A full refresh is right here, and only here: taking the record over
        // means showing what it says now.
        $this->record->refresh();
        $this->syncRecordLock();
        $this->fillForm();

        // The form now matches the database, so the snapshot has to say so too.
        // Without this the very next save is refused as stale -- against a
        // change the person has just been shown.
        $this->rememberRecordFingerprint();
        $this->fllockDismissLockNotice();
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

        // Read-only means read-only: Delete and the rest go. The take-over is
        // offered on the notification instead.
        return [];
    }



    protected function isRecordLockedByAnother(): bool {
        $record = $this->record ?? null;

        if ($record === null || ! method_exists($record, 'isLockedByAnotherUserFresh')) {
            return false;
        }

        return $record->isLockedByAnotherUserFresh();
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


    /**
     * The name of the action being called, as Livewire recorded it.
     *
     * Not `getMountedAction()?->getName()`: resolving can fail -- it returns
     * null for an action registered outside the page's own `getHeaderActions()`
     * -- and a guard that cannot name the action treats it as a write and
     * refuses it. That is how the take-over button mounted its confirmation and
     * then did nothing when confirmed.
     */
    protected function fllockMountedActionName(): ?string {
        $mounted = $this->mountedActions[array_key_last($this->mountedActions ?? [])] ?? null;

        return $mounted['name'] ?? null;
    }

    /** @param array<mixed> $arguments */
    public function callMountedAction(array $arguments = []): mixed {
        if ($this->refuseWhileLocked($this->fllockMountedActionName())) {
            return null;
        }

        return parent::callMountedAction($arguments);
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void {
        if ($this->refuseWhileLocked(null)) {
            return;
        }

        // The heartbeat is the courtesy; this is the protection. A write
        // landing two seconds before the button was pressed would otherwise go
        // straight over the top.
        if ($this->recordChangedElsewhere()) {
            $this->isStale = true;
            $this->notifyRecordChangedElsewhere();

            return;
        }

        parent::save($shouldRedirect, $shouldSendSavedNotification);

        // Our own write moved it. Without this the next save refuses itself.
        $this->rememberRecordFingerprint();
    }

    protected function staleCheckRecord(): ?Model {
        return $this->record ?? null;
    }

    protected function reloadStaleRecord(): void {
        $this->record->refresh();
        $this->fillForm();
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
            ['fllockTakeOver'],
        ), true)) {
            return false;
        }

        // Same rule as above: reload the lock, leave the record alone. This one
        // runs on every action and every save.
        $record->unsetRelation('recordLock');

        if (! $record->isLockedByAnotherUser()) {
            return false;
        }

        $this->isReadOnly = true;
        $this->recordLockOwner = $record->recordLockOwnerName();
        $this->notifyRecordIsLocked();

        return true;
    }

    protected function fllockIsLockedByAnother(): bool {
        return $this->isRecordLockedByAnother();
    }

    protected function fllockNotifyLocked(): void {
        $this->notifyRecordIsLocked();
    }
}
