<?php

namespace F4nu\Fllock\Filament\Tables;

use Closure;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
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
     * Marks a whole table: tints locked rows, and puts the lock on the column
     * that identifies them.
     *
     * The column is chosen for you, in the order a panel usually means it:
     * whatever you name here, else the table's own `recordTitleAttribute` --
     * Filament's existing answer to "which column says what this row is" --
     * else the first text column there is. Naming one is the override; there is
     * no separate registry to keep in step.
     *
     *     public static function table(Table $table): Table {
     *         return RecordLockIndicator::mark($table->columns([...]));
     *     }
     */
    public static function mark(Table $table, ?string $column = null): Table {
        $table->recordClasses(static::rowClasses());

        $name = $column ?? $table->getRecordTitleAttribute();

        foreach ($table->getColumns() as $tableColumn) {
            if ($name === null) {
                // No stated title: the first text column is the best guess at
                // what a reader looks at to tell one row from another.
                if ($tableColumn instanceof TextColumn) {
                    static::on($tableColumn);

                    return $table;
                }

                continue;
            }

            if ($tableColumn->getName() === $name) {
                static::on($tableColumn);

                return $table;
            }
        }

        // No column to hang it on. The row tint still says everything the lock
        // needs to say; only the owner's name is lost.
        return $table;
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
