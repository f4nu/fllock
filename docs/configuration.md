---
title: Configuration
nav_order: 7
---

# Configuration

```bash
php artisan vendor:publish --tag=fllock-config
```

Every value below is the default.

## Lock lifetime

```php
'timeout' => 120,
```

Seconds a lock survives without a heartbeat. It must stay comfortably above
`heartbeat.interval`, or a lock dies between two beats and a second editor walks in on a
live one.

## Heartbeat

```php
'heartbeat' => [
    'interval' => 20,
    'keep_alive' => false,
    'only_when_visible' => false,
    'idle_after' => 300,
],
```

`interval` is one request per open page. Raise it on a small server rather than turning
the heartbeat off.

`keep_alive` keeps the beat firing while the tab is hidden. **Leave it off.** Livewire
pauses polling on a hidden tab by default and that default is right: a phone suspends
the page and its network when you switch apps, so a beat fired in the background fails
and the person meets an error toast when they return. It also buys little now that
`idle_after` exists, since a hidden tab produces no input and goes idle anyway.

`only_when_visible` is not the alternative it sounds like. Livewire's `visible` modifier
means in the viewport, and the observer sits at the end of the body, so it would stop
beating whenever somebody scrolled.

`idle_after` is seconds of no keypress, pointer move or touch before the browser stops
renewing. Null keeps a lock alive for as long as the page is open, which means as long
as the tab is open, which is not the same as somebody being there.

## Models and storage

```php
'table' => 'record_locks',
'user_model' => 'App\Models\User',
'owner_name_attribute' => 'name',
```

`owner_name_attribute` is the column on your user model that the banner and tooltips
show. `name` by default; use `email` if names are not unique enough to identify a
colleague, or any other column your users table has.

## Permissions

```php
'unlock_gate' => 'manage-locks',
'manager' => [
    'enabled' => true,
    'gate' => 'manage-locks',
    'navigation_group' => 'Settings',
    'navigation_icon' => 'heroicon-o-lock-closed',
    'navigation_sort' => 20,
],
```

`unlock_gate` guards taking a record over. Give it the name of a Gate ability or a
Spatie permission, as above. Both go through `Gate::allows($name)`, so either works
without further wiring, and a Super Admin who passes `Gate::before` gets through without
being granted anything.

Taking a record discards whatever the current editor has typed and not saved, so this is
worth setting. Left null, anyone who can reach the page can do it.

`manager.gate` guards the lock manager page the same way. Clearing a lock there is the
same forced unlock, so it usually wants the same name.

`manager.navigation_group` and `navigation_sort` place the page in your sidebar, and
take whatever your panel already uses for its groups. Left null the page sits ungrouped,
at the end.

`manager.enabled` false drops the page and keeps the locking.

## What may run against a locked record

`permitted_actions` holds action names, as passed to `Action::make()`:

```php
'permitted_actions' => ['view', 'vote', 'export'],
```

It is an allowlist deliberately. Written as a denylist it misses every custom action an
app adds, which is exactly the one nobody remembers to list. Add an action here when it
writes something other than the locked record. An action recording a vote in its own
table collides with nobody editing the record; one clearing a flag on the record itself
does.

`permitted_methods` holds Livewire method names, and applies to the pages that refuse
every other call:

```php
'permitted_methods' => [
    'gotoPage', 'nextPage', 'previousPage', 'setPage', 'resetPage',
    'sortTable', 'applyTableFilters',
    'exportSchedule',   // your own read only methods go here
],
```

These are the reads. Take one out and read only becomes broken rather than read only.

## The sweep

```php
'sweep_expired' => true,
```

Schedules `fllock:clear-expired` hourly. Set it false to choose your own frequency:

```php
// routes/console.php
Schedule::command('fllock:clear-expired')->everySixHours();
```

Nothing depends on it running; see [releasing a lock](releasing.md#the-sweep).

## Translations

The package ships `en` and `it`. Override either the normal way:

```bash
php artisan vendor:publish --tag=fllock-translations
```
