<?php

namespace F4nu\Fllock\Filament\Concerns;

use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

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
     * @param  array<mixed>  $arguments
     * @param  array<mixed>  $context
     */
    public function mountAction(string $name, array $arguments = [], array $context = []): mixed {
        $result = parent::mountAction($name, $arguments, $context);

        $record = $this->getLockableMountedRecord();

        if ($record === null) {
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
        }

        return $result;
    }

    /**
     * Nothing renews a modal's lock while it sits open, so a slow edit can
     * outlive the timeout and let a second editor in. Re-checking here is what
     * stops both of them saving.
     *
     * @param  array<mixed>  $arguments
     */
    public function callMountedAction(array $arguments = []): mixed {
        $record = $this->getLockableMountedRecord();
        $name = $this->getMountedAction()?->getName();

        if ($record !== null && ! $this->isPermittedWhileLocked($name) && $this->refuseLockedRecord($record)) {
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
            return null;
        }

        return parent::updateTableColumnState($column, $record, $input);
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
        $record->refresh();

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
