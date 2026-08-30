---
title: Testing your panel
nav_order: 6
---

# Testing your panel

The suite that proves the guards ships with the package, and it enumerates. It reads
your `app/Filament` off the filesystem rather than naming resources, so a resource added
next month is covered the day it lands and one that forgets a trait fails by name.

One file in your application:

```php
use F4nu\Fllock\Testing\LockingContract;

LockingContract::for(dirname(__DIR__, 3) . '/app/Filament', 'App\Filament')
    ->editors(fn () => [makeAdmin(), makeAdmin()])
    ->record(fn (string $model) => $model::factory()->create())
    ->run();
```

`dirname(__DIR__, 3)` rather than `app_path()`: Pest builds datasets before the
application boots.

Requires Pest, since the suite is declared through its functional API.

## What it drives

For every record page, list page, relation manager and settings page it finds:

- the lock is taken when the page opens, and the heartbeat renews it without throwing
- a second editor gets a read only page with no header actions and no save button, and
  it stays read only when the schema is rebuilt
- saves, row modals, inline column writes, reordering and header actions are refused on
  a locked record
- an open modal's lock survives the heartbeat
- a save is refused over a write the page never saw
- the take over hands the record on
- every class carries the traits it needs

## Excluding a page

```php
->except(['Backups', 'LiveDashboard'])
```

Use it for a page that genuinely must not lock, and write down why at the call. An
unexplained exclusion is how a surface goes quietly uncovered.

## Why it enumerates

The first panel these guards were fitted to shipped broken. The traits went onto about
thirty classes, three were opened by hand, and every bug that came back was on a page
nobody had touched. Sampling does not work here: the guards are uniform, the surfaces
are not, and the resource that breaks is always the one with the unusual table.

The enumerated suite found, on its own:

- uuid keyed records sharing a single lock row, on its first run
- a page extending `EditRecord` but not named `Edit*`, which a filename glob missed
- a `ViewRecord` page carrying four writing actions inside an infolist section

That last one is why pages that can write are detected by asking the rendered page what
it can be told to do, rather than by what it extends. That assumption was wrong twice.

## Testing the package itself

```bash
composer test
```

The model level suite runs on Testbench. The contract suite in `tests/Contract` does
not: every Livewire render fails there, and a bare component with an empty view
reproduces it, so it is nothing to do with locking or even with Filament. Testbench is a
container and a config array, not a request, and Livewire assumes a request has
happened. That suite runs against a scaffolded application instead, one per Filament
version in the matrix. See `.github/workflows/contract.yml`.
