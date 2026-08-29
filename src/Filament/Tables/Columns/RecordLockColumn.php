<?php

namespace F4nu\Fllock\Filament\Tables\Columns;

use Filament\Tables\Columns\IconColumn;
use Illuminate\Database\Eloquent\Model;

/**
 * A lock icon on rows someone is editing: primary if it is you, danger if not.
 *
 * Eager-load `recordLock.user` on the page's table query, or this is an N+1.
 */
class RecordLockColumn extends IconColumn {
    public static function getDefaultName(): ?string {
        return 'recordLock';
    }

    protected function setUp(): void {
        parent::setUp();

        $this->label(__('fllock::fllock.column.label'));

        $this->getStateUsing(function (Model $record): ?string {
            if (! method_exists($record, 'isLocked') || ! $record->isLocked()) {
                return null;
            }

            return $record->isLockedByCurrentUser() ? 'mine' : 'theirs';
        });

        $this->icon(fn (?string $state): ?string => $state === null ? null : 'heroicon-s-lock-closed');

        $this->color(fn (?string $state): ?string => match ($state) {
            'mine' => 'primary',
            'theirs' => 'danger',
            default => null,
        });

        $this->tooltip(function (Model $record, ?string $state): ?string {
            if ($state === null) {
                return null;
            }

            if ($state === 'mine') {
                return __('fllock::fllock.column.by_you');
            }

            $name = $record->recordLockOwnerName();

            return $name
                ? __('fllock::fllock.column.by', ['name' => $name])
                : __('fllock::fllock.column.by_other');
        });
    }
}
