<?php

namespace F4nu\Fllock\Filament\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Loads the lock and its owner with the table query.
 *
 * RecordLockColumn asks every row who is editing it, and the tooltip asks for
 * that person's name; without this a page of fifty rows is a hundred queries.
 */
trait EagerLoadsRecordLocks {
    public function getTableQuery(): Builder {
        return parent::getTableQuery()->with(['recordLock', 'recordLock.user']);
    }
}
