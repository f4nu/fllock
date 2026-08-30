# fllock

Record locking for Filament. One editor at a time, on every path Filament can
write through.

Open a record for editing and it is locked. Anyone else who opens it gets the
page read-only, with a banner naming who has it, and — if they are allowed —
one button to take it anyway. The lock is renewed by a heartbeat from the
browser holding it and expires on its own, so a closed laptop does not hold a
record forever.

## Why this exists

Every record-locking package for Filament guards the edit page. That is not
where most of an admin panel gets written to.

A Filament table writes through a row modal, an inline `ToggleColumn`, a bulk
action, drag-to-reorder, and any custom action somebody added last month. None
of those are the edit page, and a relation manager's `isReadOnly()` does not
cover them either — Filament consults that only for its own built-in action
classes. Guard the edit page alone and a second editor cannot save a field but
can flip the record's published toggle from the row it sits in, or delete it in
a bulk selection.

So this guards all of them, and the guard is an **allowlist**: anything not
named in `permitted_actions` is refused while another editor holds the lock. A
denylist of built-in names is how a custom action walks straight through, and a
custom action is exactly the one nobody remembers to list.

## Install

```bash
composer require f4nu/fllock
php artisan vendor:publish --tag=fllock-migrations
php artisan vendor:publish --tag=fllock-config   # optional
php artisan migrate
```

Register the plugin on your panel:

```php
use F4nu\Fllock\FllockPlugin;

$panel->plugin(FllockPlugin::make());
```

Everything configurable lives in `config/fllock.php`, with the reasoning for
each value written beside it — there is no fluent setter per option to keep in
step with the config file that already says the same thing.

## Use

Three traits and one on the model:

```php
// The model
use F4nu\Fllock\Models\Concerns\HasRecordLock;

class Post extends Model {
    use HasRecordLock;
}

// The edit page
use F4nu\Fllock\Filament\Concerns\LocksRecordWhileEditing;

class EditPost extends EditRecord {
    use LocksRecordWhileEditing;
}

// Every list page, manage page and relation manager
use F4nu\Fllock\Filament\Concerns\LocksRecordsEditedInModals;

class ListPosts extends ListRecords {
    use LocksRecordsEditedInModals;
}

// Relation managers also take this, so their rows follow the lock on the
// record the tabs hang off
use F4nu\Fllock\Filament\Concerns\ReadOnlyWhenOwnerRecordIsLocked;
```

Pages that are not a resource's edit page need one of the other two:

```php
// A hand-written page routed as /{record}/something — a planner, a dashboard.
// It takes the record's own lock, so every page editing one record contends
// for one lock.
use F4nu\Fllock\Filament\Concerns\LocksRecordOnPage;

class EventPlanning extends Page {
    use LocksRecordOnPage;

    // Only if the page does not call it $record.
    protected function lockedRecord(): ?Model {
        return $this->event;
    }
}

// A settings page, with no record at all. The lock is keyed on the page.
use F4nu\Fllock\Filament\Concerns\LocksPageWhileEditing;

class TwitchSettings extends Page {
    use LocksPageWhileEditing;
}
```

Both of those refuse **every** Livewire call while someone else holds the lock,
rather than trying to name the ways such a page writes — see
`config/fllock.php`'s `permitted_methods` for the reads that still go through,
and add your page's own.

Showing which rows are taken:

```php
use F4nu\Fllock\Filament\Concerns\EagerLoadsRecordLocks;  // on the list page
use F4nu\Fllock\Filament\Tables\RecordLockIndicator;

public static function table(Table $table): Table {
    return RecordLockIndicator::mark($table->columns([...]));
}
```

That tints locked rows, marks them with a stripe, and puts a lock before the
value of the column that identifies the row — naming the holder on hover.

Which column that is, in order: whatever you name (`mark($table, 'game_title')`),
else the table's own `recordTitleAttribute`, else the first text column. So the
usual case needs no configuration and the override is one argument. If there is
no column to hang it on, the row tint still says everything except who has it.

The tint is amber rather than red, because red is what a table uses for
something that went wrong and a row somebody is editing has not gone wrong; and
it carries a stripe as well, because colour alone is no use to a reader who
cannot see that colour.

`RecordLockColumn::make()` is still there if you would rather spend a column on
it, but a column is a header, a width and a rule on every table in the panel,
carrying nothing most of the time.

## Test your own panel

The suite that proves the guards ships with the package, and it enumerates —
it reads your `app/Filament` off the filesystem rather than naming resources.
One file in your app:

```php
use F4nu\Fllock\Testing\LockingContract;

LockingContract::for(dirname(__DIR__, 3) . '/app/Filament', 'App\Filament')
    ->editors(fn () => [makeAdmin(), makeAdmin()])
    ->record(fn (string $model) => $model::factory()->create())
    ->run();
```

That drives every record page, list page and relation manager you have: the
lock is taken, the heartbeat does not throw, a second editor is read-only and
stays read-only when the schema rebuilds, saves are refused, row modals are
refused, inline writes and reordering are refused, and every class carries the
traits it needs. A resource added next month is covered the day it lands; one
that forgets a trait fails by name.

This is not decoration. The first panel these guards were fitted to shipped
broken: the traits went onto about thirty classes, three were opened by hand,
and every bug that came back was on a page nobody had touched — including one
where uuid-keyed records silently shared a single lock row. Sampling does not
work here. The guards are uniform and the surfaces are not, and the resource
that breaks is always the one with the unusual table.

Note `dirname(__DIR__, 3)` rather than `app_path()`: Pest builds datasets before
the application boots.

## Testing this package

```bash
composer test                 # the model-level suite, on Testbench
```

The contract suite in `tests/Contract` does **not** run on Testbench. Every
Livewire render fails there inside `SupportValidation` — a bare component with
an empty view is enough to reproduce it, so it is nothing to do with locking or
even with Filament. Testbench is a container and a config array, not a request,
and Livewire v4 assumes a request has happened. That suite runs against a
scaffolded application instead, one per Filament version in the matrix; see
`.github/workflows/contract.yml`.

## When a lock lets go

Four ways, in the order they usually happen:

- **The page is closed or navigated away from.** SPA navigation releases it at
  once; a real unload is best effort.
- **The viewer stops touching the keyboard.** After `heartbeat.idle_after`
  seconds of no input the browser stops renewing, and the lock lapses on its
  own. This is what stops a tab left open overnight from holding a record until
  morning — a heartbeat is meant to say somebody is *there*, and a tab beats
  whether or not anybody is.
- **`timeout` seconds pass with no heartbeat at all.** The lock is ignored from
  that moment; the next person's page picks the record up on their next beat.
- **Somebody takes it.** The read-only banner offers that to whoever passes
  `unlock_gate`, and the lock manager can clear any of them.

Expired rows are swept hourly by a scheduled command the package registers
itself. Nothing depends on the sweep — an expired lock is ignored on sight —
but without it the lock manager fills with rows that mean nothing.

## What it does not do

- **No optimistic locking.** Two people are not merged; the second is kept out.
- **No live presence.** The heartbeat is polling, not websockets. It is a lock,
  not a collaborative editor.
- **Nothing outside Filament.** A JSON API or a console command writing to the
  same record does not consult the lock, and should not be assumed to.
- **A component that writes only from a `Livewire.dispatch()` listener** is not
  covered by the blanket refusal: `__dispatch` has to stay permitted, or the
  lock's own events could never reach the page. Such a listener is nearly
  always reachable as an action or a property too, and those are guarded.
- **A tab closed without warning** releases its lock on a best-effort basis.
  SPA navigation releases it properly — nothing is unloading, so the request
  completes — but a real unload may not finish, which is what the timeout is
  for.

## Lineage

The approach owes its shape to [kenepa/resource-lock][kenepa] and the
Filament v5 fork [blendbyte/filament-resource-lock][blendbyte], both MIT. This
is a rewrite rather than a fork: the guard layer here grew larger than the
package it was patching, and most of what it patched was in the way.

[kenepa]: https://github.com/kenepa/resource-lock
[blendbyte]: https://github.com/blendbyte/filament-resource-lock
