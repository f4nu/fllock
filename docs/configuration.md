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

`owner_name_attribute` is what the banner and tooltips show. Use `email` if names are
not unique enough to identify a colleague.

## Permissions

```php
'unlock_gate' => null,
'manager' => [
    'enabled' => true,
    'gate' => null,
    'navigation_group' => null,
    'navigation_icon' => 'heroicon-o-lock-closed',
    'navigation_sort' => null,
],
```

`unlock_gate` guards taking a record over. Null means anyone who can reach the page can
do it. Taking a record discards whatever the current editor has typed and not saved, so
this is worth setting.

Both gates go through `Gate::allows`, so a Spatie permission name works directly, and a
Super Admin passing `Gate::before` gets through without being granted anything.

`manager.enabled` false drops the lock manager page and keeps the locking.

## What may run against a locked record

```php
'permitted_actions' => ['view'],
'permitted_methods' => ['gotoPage', 'nextPage', ...],
```

`permitted_actions` is an allowlist, deliberately. Written as a denylist it misses every
custom action an app adds, which is exactly the one nobody remembers to list. Add an
action here when it writes something other than the locked record: a vote on a
submission writes a vote row, not the submission, so it collides with nobody.

`permitted_methods` are the reads that still go through on a page that refuses every
other Livewire call: paging, sorting, filtering. Take one out and read only becomes
broken. Add your own page's read only methods.

## The sweep

```php
'sweep_expired' => true,
```

Schedules `fllock:clear-expired` hourly. Set false to schedule it yourself. Nothing
depends on it running; see [releasing a lock](releasing.md#the-sweep).

## Translations

The package ships `en` and `it`. Override either the normal way:

```bash
php artisan vendor:publish --tag=fllock-translations
```
