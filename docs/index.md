---
title: Home
nav_order: 1
---

# fllock

**F**ilament **L**aravel **Lock**. Record locking for Filament: one editor at a time, on
every path Filament can write through.

Open a record and it is yours while you have it. Anyone else who opens it gets the page
read only, with a banner naming who holds it and a button to take it over if they are
allowed to. Locked rows in tables are tinted, striped, and carry a lock icon on the
column that identifies them.

## Why it exists

Other record locking packages guard the edit page. That is not where most of an admin
panel gets written to. A Filament table writes through row modals, inline editable
columns, bulk actions, drag to reorder, and any custom action somebody added last month,
and a relation manager's `isReadOnly()` covers none of them. Guard the edit page alone
and a second editor cannot save a field but can still flip the record's published toggle
from the row it sits in.

So every write path has its own guard, and the action guard is an allowlist. A denylist
of the built in names misses every custom action an app adds, which is exactly the one
nobody remembers to list.

## Install

```bash
composer require f4nu/fllock
php artisan vendor:publish --tag=fllock-migrations
php artisan migrate
```

Register the plugin on your panel:

```php
use F4nu\Fllock\FllockPlugin;

$panel->plugin(FllockPlugin::make());
```

Then put the traits on your models and pages: see [Usage](usage.md).

## Requirements

PHP 8.3, Laravel 12 or 13, Filament 5.

## Lineage

The approach owes its shape to [kenepa/resource-lock][kenepa] and its Filament v5 fork
[blendbyte/filament-resource-lock][blendbyte], both MIT. This is a rewrite rather than a
fork: the guard layer grew larger than the package it was patching.

[kenepa]: https://github.com/kenepa/resource-lock
[blendbyte]: https://github.com/blendbyte/filament-resource-lock
