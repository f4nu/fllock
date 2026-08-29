<?php

namespace F4nu\Fllock\Filament\Concerns;

/**
 * Makes a relation manager read-only while the record it belongs to is locked.
 *
 * A relation manager sits on the edit page but is not covered by its lock: the
 * page goes read-only for a second editor while the tabs underneath it stay
 * fully writable, so the record is protected and its relations are not. The
 * related rows usually have no locks of their own -- the lock that governs them
 * is the one on the record the tabs hang off.
 */
trait ReadOnlyWhenOwnerRecordIsLocked {
    private ?bool $fllockOwnerIsLockedElsewhere = null;

    public function isReadOnly(): bool {
        if (parent::isReadOnly()) {
            return true;
        }

        // Consulted on every render, so the lock is read once per request.
        return $this->fllockOwnerIsLockedElsewhere ??= (
            method_exists($this->ownerRecord, 'isLockedByAnotherUser')
            && $this->ownerRecord->isLockedByAnotherUser()
        );
    }
}
