<?php

namespace F4nu\Fllock\Filament\Concerns;

use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

/**
 * Locks records edited from a table -- row modals, slide-overs, inline columns,
 * bulk actions, drag-to-reorder.
 *
 * Most of a Filament panel is edited from a table rather than on an edit page,
 * and a table writes through several endpoints that are not actions at all.
 * Each one needs its own guard: covering only the edit modal leaves a locked
 * record editable by a toggle in its own row.
 *
 * Put this on ListRecords, ManageRecords and RelationManager classes.
 */
trait LocksRecordsEditedInModals {
    /**
     * The record this page locked when it opened a modal, as [morph, key], so
     * the lock can be released without depending on a mounted action that has
     * already been popped off the stack.
     *
     * @var array{0: string, 1: mixed}|null
     */
    public ?array $lockedModalRecord = null;

    /**
     * What `updated_at` said when the modal filled its form.
     *
     * Most of a panel is edited in a slide-over, so a stale-write guard that
     * only covered full pages would miss the ordinary case.
     */
    public ?string $modalRecordFingerprint = null;

    /**
     * @param  array<mixed>  $arguments
     * @param  array<mixed>  $context
     */
    public function mountAction(string $name, array $arguments = [], array $context = []): mixed {
        $result = parent::mountAction($name, $arguments, $context);

        $record = $this->getLockableMountedRecord();

        // A header action carries no record -- Create, or any custom action
        // above the table. On a relation manager that does not make it safe:
        // the lock that governs those rows is the one on the record the table
        // hangs off, and adding a row to a locked record is still writing to it.
        // Filament's own isReadOnly() hides CreateAction and nothing else, so a
        // custom header action walks straight through.
        if ($record === null) {
            if (! $this->isPermittedWhileLocked($name) && $this->isOwnerRecordLocked()) {
                $this->unmountAction(cancelParentActions: false);

                return null;
            }

            return $result;
        }

        if (! $this->isPermittedWhileLocked($name) && $this->refuseLockedRecord($record)) {
            $this->unmountAction(cancelParentActions: false);

            return null;
        }

        // Only a form on an existing record holds the lock while it is open;
        // a one-shot action does its work and is gone.
        if ($name === 'edit' && $record->lock()) {
            $this->lockedModalRecord = [$record->getMorphClass(), $record->getKey()];
            $this->modalRecordFingerprint = $record->{$record->getUpdatedAtColumn()}?->toJSON();
        }

        return $result;
    }

    /**
     * Whether this component's owner record is held by someone else.
     *
     * Only a relation manager has one; a list page returns false, since a
     * header action there belongs to no record.
     */
    protected function isOwnerRecordLocked(): bool {
        $owner = $this->ownerRecord ?? null;

        if (! $this->isLockable($owner)) {
            return false;
        }

        return $this->refuseLockedRecord($owner);
    }

    /**
     * Nothing renews a modal's lock while it sits open, so a slow edit can
     * outlive the timeout and let a second editor in. Re-checking here is what
     * stops both of them saving.
     *
     * @param  array<mixed>  $arguments
     */

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

    public function callMountedAction(array $arguments = []): mixed {
        $record = $this->getLockableMountedRecord();
        $name = $this->fllockMountedActionName();

        if ($record !== null && ! $this->isPermittedWhileLocked($name) && $this->refuseLockedRecord($record)) {
            $this->unmountAction(cancelParentActions: false);

            return null;
        }

        if ($record === null && ! $this->isPermittedWhileLocked($name) && $this->isOwnerRecordLocked()) {
            $this->unmountAction(cancelParentActions: false);

            return null;
        }

        // Something wrote to this record while the modal sat open -- the API, a
        // command, a job. None of them consult the lock, correctly, so this is
        // all that stands between their write and a stale form saved over it.
        if ($record !== null && $this->modalRecordIsStale($record)) {
            $this->notifyModalRecordIsStale();
            $this->unmountAction(cancelParentActions: false);

            return null;
        }

        if (($locked = $this->firstLockedSelectedRecord()) !== null) {
            $this->refuseLockedRecord($locked);
            $this->unmountAction(cancelParentActions: false);

            return null;
        }

        return parent::callMountedAction($arguments);
    }

    /**
     * A bulk action carries a selection rather than a mounted record, so none of
     * the guards above see it and Delete selected would take out the row someone
     * has open. The whole action is refused rather than the offending rows
     * skipped: quietly doing most of what was asked is worse than doing none of
     * it and saying why.
     */
    protected function firstLockedSelectedRecord(): ?Model {
        $action = $this->getMountedAction();

        if ($action === null || ! method_exists($action, 'canAccessSelectedRecords') || ! $action->canAccessSelectedRecords()) {
            return null;
        }

        foreach ($this->getSelectedTableRecords() as $record) {
            if ($this->isLockable($record) && $record->isLockedByAnotherUser()) {
                return $record;
            }
        }

        return null;
    }

    /**
     * An editable column -- ToggleColumn, TextInputColumn, SelectColumn -- is
     * not an action and writes through its own endpoint. A relation manager's
     * `isReadOnly()` does not cover it either: Filament consults that only for
     * its own built-in action classes.
     */
    public function updateTableColumnState(string $column, string $record, mixed $input): mixed {
        if ($this->isTableWriteLocked($record)) {
            // Not null: an editable column reads null as "saved". It then keeps
            // the value the person just set, so a refused toggle stays flipped
            // until the table is reloaded -- it says the write was refused and
            // shows it as though it happened. Filament's own contract for a
            // rejected write is an `error` key, which leaves the server's value
            // in place and puts the state back.
            return ['error' => $this->lockedWriteMessage($record)];
        }

        return parent::updateTableColumnState($column, $record, $input);
    }

    protected function lockedWriteMessage(string $recordKey): string {
        $record = $this->getTableRecord($recordKey);

        $owner = $this->isLockable($record)
            ? $record->recordLockOwnerName()
            : ($this->ownerRecord ?? null)?->recordLockOwnerName();

        return $owner
            ? __('fllock::fllock.locked_by', ['name' => $owner])
            : __('fllock::fllock.locked');
    }

    /**
     * Reordering writes a position column on every row it touches, through its
     * own endpoint, with no action and no modal.
     *
     * @param  array<mixed>  $order
     */
    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void {
        foreach ($order as $key) {
            if ($this->isTableWriteLocked((string) $key)) {
                return;
            }
        }

        parent::reorderTable($order, $draggedRecordKey);
    }

    /**
     * Keeps an open modal's lock alive.
     *
     * The lock is taken when the modal mounts and nothing renewed it, so a
     * slide-over left open lost the record after the timeout while still
     * sitting there looking editable. Every other surface listens for the
     * heartbeat; this one did not, because its lock is tied to a modal rather
     * than to the page.
     */
    #[On('fllock::heartbeat')]
    public function fllockRenewModalLock(): void {
        $record = $this->fllockLockedModalRecord();

        if ($record === null) {
            return;
        }

        if ($record->lock()) {
            return;
        }

        // Lost it while the modal sat open -- expired, then taken. Saving is
        // refused anyway, but the person deserves to hear it now rather than
        // when they press save.
        $this->lockedModalRecord = null;
        $this->notifyLocked($record->recordLockOwnerName());
    }

    /**
     * Releases an open modal's lock when the page goes away.
     *
     * Closing the modal releases it through `unmountAction()`; leaving the page
     * with it still open never went through there at all.
     */
    #[On('fllock::release')]
    public function fllockReleaseModalLock(): void {
        $this->releaseModalRecordLock();
    }

    /**
     * Has the record moved since the modal opened?
     *
     * Compared for difference rather than age: a clock skew between servers, or
     * a writer setting the column itself, can leave the stored value earlier,
     * and that is still a write about to be lost.
     */
    protected function modalRecordIsStale(Model $record): bool {
        if ($this->modalRecordFingerprint === null || ! $record->usesTimestamps()) {
            return false;
        }

        $current = $record->newQuery()
            ->whereKey($record->getKey())
            ->value($record->getUpdatedAtColumn())
            ?->toJSON();

        return $current !== $this->modalRecordFingerprint;
    }

    protected function notifyModalRecordIsStale(): void {
        Notification::make('fllock-stale')
            ->warning()
            ->persistent()
            ->title(__('fllock::fllock.stale.title'))
            ->body(__('fllock::fllock.stale.modal_body'))
            ->send();
    }

    protected function fllockLockedModalRecord(): ?Model {
        if ($this->lockedModalRecord === null) {
            return null;
        }

        [$morphClass, $key] = $this->lockedModalRecord;

        $model = Model::getActualClassNameForMorph($morphClass);
        $record = $model::query()->find($key);

        if ($record === null) {
            $this->lockedModalRecord = null;
        }

        return $record;
    }

    public function unmountAction(bool|string|null $cancelParentActions = null): void {
        $this->releaseModalRecordLock();

        parent::unmountAction($cancelParentActions);
    }

    protected function releaseModalRecordLock(): void {
        if ($this->lockedModalRecord === null) {
            return;
        }

        [$morphClass, $key] = $this->lockedModalRecord;
        $this->lockedModalRecord = null;
        $this->modalRecordFingerprint = null;

        $model = Model::getActualClassNameForMorph($morphClass);

        $model::query()->find($key)?->unlock();
    }

    protected function isTableWriteLocked(string $recordKey): bool {
        // On a relation manager the row has no lock of its own; what governs it
        // is the lock on the record the table belongs to.
        if (method_exists($this, 'isReadOnly') && $this->isReadOnly()) {
            $owner = $this->ownerRecord ?? null;

            $this->isLockable($owner)
                ? $this->refuseLockedRecord($owner)
                : $this->notifyLocked(null);

            return true;
        }

        $record = $this->getTableRecord($recordKey);

        return $this->isLockable($record) && $this->refuseLockedRecord($record);
    }

    protected function refuseLockedRecord(Model $record): bool {
        $record->unsetRelation('recordLock');

        if (! $record->isLockedByAnotherUser()) {
            return false;
        }

        $this->notifyLocked($record->recordLockOwnerName());

        return true;
    }

    protected function isPermittedWhileLocked(?string $actionName): bool {
        return $actionName !== null
            && in_array($actionName, config('fllock.permitted_actions', []), true);
    }

    protected function getLockableMountedRecord(): ?Model {
        $record = $this->getMountedAction()?->getRecord();

        return $this->isLockable($record) ? $record : null;
    }

    protected function isLockable(mixed $record): bool {
        return $record instanceof Model && method_exists($record, 'isLockedByAnotherUser');
    }

    protected function notifyLocked(?string $owner): void {
        Notification::make()
            ->warning()
            ->title($owner
                ? __('fllock::fllock.locked_by', ['name' => $owner])
                : __('fllock::fllock.locked'))
            ->send();
    }
}
