<?php

namespace F4nu\Fllock\Filament\Tables;

use Closure;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\Column;
use Illuminate\Database\Eloquent\Model;

/**
 * Shows which rows somebody is editing, without spending a column on it.
 *
 * A column of its own is honest but costly: it is a header, a width and a
 * vertical rule on every table in the panel, carrying nothing at all most of
 * the time. These two put the same information where there is already room --
 * on the row, and on a column that is there anyway.
 */
class RecordLockIndicator {
    /**
     * Classes for a table's rows. Pass to `$table->recordClasses(...)`.
     *
     * The tint is not red on purpose. Red means something went wrong, and a
     * locked row is neither wrong nor broken -- somebody is simply working on
     * it. It also carries a stripe down its left edge, because colour alone is
     * no use to a reader who cannot see this particular colour, and easy to
     * miss on a dense table besides.
     */
    public static function rowClasses(): Closure {
        return function (Model $record): ?string {
            if (! method_exists($record, 'isLocked') || ! $record->isLocked()) {
                return null;
            }

            return $record->isLockedByCurrentUser()
                ? 'fllock-row fllock-row-mine'
                : 'fllock-row fllock-row-theirs';
        };
    }

    /**
     * Puts a small lock before a column's value, and names the holder on hover.
     *
     * Decorates a column you already have -- normally the one carrying the
     * title -- rather than adding one:
     *
     *     RecordLockIndicator::on(TextColumn::make('name'))
     */
    public static function on(Column $column): Column {
        return $column
            ->icon(fn (Model $record): ?string => static::isLockedRow($record)
                ? 'heroicon-m-lock-closed'
                : null)
            ->iconColor(fn (Model $record): ?string => static::isLockedRow($record)
                ? ($record->isLockedByCurrentUser() ? 'gray' : 'warning')
                : null)
            ->iconPosition(IconPosition::Before)
            ->tooltip(function (Model $record): ?string {
                if (! static::isLockedRow($record)) {
                    return null;
                }

                if ($record->isLockedByCurrentUser()) {
                    return __('fllock::fllock.column.by_you');
                }

                $name = $record->recordLockOwnerName();

                return $name
                    ? __('fllock::fllock.column.by', ['name' => $name])
                    : __('fllock::fllock.column.by_other');
            });
    }

    protected static function isLockedRow(Model $record): bool {
        return method_exists($record, 'isLocked') && $record->isLocked();
    }
}
