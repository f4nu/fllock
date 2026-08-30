# How it works

## The lock row

One table, `record_locks`, holding a user, a morph to the locked thing, and timestamps.

`lockable_id` is a string, not `morphs()`. `morphs()` is an unsigned big integer, and
plenty of models are keyed by uuid or ulid; MySQL and MariaDB truncate those with a
warning rather than an error, so every such record quietly shares one lock row.

`user_id` cascades on delete. Deleting a member of staff must not fail because they left
a lock behind.

A lock is not held open in the database. It is a row with an `updated_at`, and anything
older than `timeout` is ignored on sight, whether or not something has swept it.

## The heartbeat

A Livewire component mounted once per panel, polling every `heartbeat.interval` seconds
and dispatching an event that whichever page is listening picks up.

This is not an optimisation. A Filament panel in SPA mode never fires a page unload, so
the beat is the only thing that both renews a live editor's lock and lets an abandoned
one lapse.

It does not carry `keep-alive`, so Livewire pauses it while the tab is hidden. That
matters on a phone: switching apps suspends the page and its network, and a beat fired
into that fails and greets the person with an error toast when they come back.

The page reports how long since the last keypress, pointer move or touch, and the
observer stays quiet past `heartbeat.idle_after`. A tab left open is not a person
sitting there.

## Every path Filament writes through

A lock that only covers the edit page is not a lock. Each of these has its own guard:

| Path | Guarded by |
|---|---|
| Save on an edit page | `save()` re-reads the lock |
| Header actions | removed from the page, and refused if called |
| Row modals and slide overs | `mountAction()` and `callMountedAction()` |
| Inline editable columns | `updateTableColumnState()` |
| Bulk actions | the selection is checked before the action runs |
| Drag to reorder | `reorderTable()` |
| Bare Livewire properties | Livewire's `updating` trait hook |
| Anything else, on a settings or record page | Livewire's `call` trait hook |

That last row is the general case. `RefusesWritesWhileLocked` hooks the call Livewire
makes for every method invocation and refuses anything not named as a read, which is the
only way to cover a page that writes through methods of its own invention. A schedule
planner has twenty of those.

Two things must stay permitted or the lock breaks itself: methods beginning `fllock`,
and Livewire's own internals beginning `__`. `__dispatch` is how a dispatched event
reaches its listener, including this package's own.

## Read only

Read only is applied declaratively, evaluated on every render. Stamping `disabled(true)`
into the schema once holds only until something rebuilds it, and switching relation
manager tabs is enough to bring every field back to life on a record somebody else is
editing.

It is applied again from a Livewire trait hook, which a host page cannot shadow. A page
that defines its own `form()` beats a trait's, and the guard would vanish with no error.

## Taking a record over

The take over is offered on the notification, not in the page header. A header action
has to be registered through the page's own `getHeaderActions()`, and any page worth
locking defines that itself, so a trait's version never exists. The notification action
dispatches an event and needs none of that machinery.

It is gated on `unlock_gate`, because taking a record discards whatever the current
editor has typed and not saved.
