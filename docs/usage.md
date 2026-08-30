# Usage

Three kinds of surface need three different traits, because a Filament panel has three
shapes of page. Get this wrong and the page silently locks nothing.

## Models

Every model that can be locked:

```php
use F4nu\Fllock\Models\Concerns\HasRecordLock;

class Post extends Model {
    use HasRecordLock;
}
```

This also deletes a record's locks when the record is deleted. `lockable` is a morph, so
nothing in the database cleans up after it.

## Edit pages

An `EditRecord` page:

```php
use F4nu\Fllock\Filament\Concerns\LocksRecordWhileEditing;

class EditPost extends EditRecord {
    use LocksRecordWhileEditing;
}
```

## Tables, modals and relation managers

Any page with a table: `ListRecords`, `ManageRecords`, and every relation manager.

```php
use F4nu\Fllock\Filament\Concerns\LocksRecordsEditedInModals;

class ListPosts extends ListRecords {
    use LocksRecordsEditedInModals;
}
```

Relation managers take a second trait, so their rows follow the lock on the record the
tabs belong to rather than needing locks of their own:

```php
use F4nu\Fllock\Filament\Concerns\ReadOnlyWhenOwnerRecordIsLocked;
```

## Hand written pages that carry a record

A page routed as `/{record}/something`, such as a planner or a dashboard. It takes the
record's own lock, so every page editing one record contends for one lock.

```php
use F4nu\Fllock\Filament\Concerns\LocksRecordOnPage;

class EventPlanning extends Page {
    use LocksRecordOnPage;

    // Only if the page does not call it $record.
    protected function lockedRecord(): ?Model {
        return $this->event;
    }
}
```

## Settings pages

A page with no record behind it, such as API credentials or site globals. The lock is
keyed on the page class.

```php
use F4nu\Fllock\Filament\Concerns\LocksPageWhileEditing;

class TwitchSettings extends Page {
    use LocksPageWhileEditing;
}
```

Two pages editing the same settings should override `recordLockSubject()` to return the
same string, or each locks its own view of one shared thing.

## Showing which rows are taken

```php
use F4nu\Fllock\Filament\Concerns\EagerLoadsRecordLocks;  // on the list page
use F4nu\Fllock\Filament\Tables\RecordLockIndicator;

public static function table(Table $table): Table {
    return RecordLockIndicator::mark($table->columns([...]));
}
```

That tints locked rows, marks them with a stripe, and puts a lock before the value of
the column that identifies the row, naming the holder on hover.

Which column, in order: whatever you name (`mark($table, 'game_title')`), else the
table's own `recordTitleAttribute`, else the first text column. If there is no column to
hang it on the row tint still says everything except who has it.

`EagerLoadsRecordLocks` is not optional in practice. The indicator asks every row who is
editing it, so without it a page of fifty rows is a hundred queries.

The tint is amber rather than red. Red is what a table uses for something that went
wrong, and a row somebody is editing has not gone wrong. It carries a stripe as well,
because colour alone is no use to a reader who cannot see that colour.

`RecordLockColumn::make()` still exists if you would rather spend a column on it.
