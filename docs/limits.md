---
title: Limits
nav_order: 8
---

# Limits

## What it does not do

- **No optimistic locking between two panel editors.** Two people are not merged; the
  second is kept out.
- **No live presence.** The heartbeat is polling, not websockets. It is a lock, not a
  collaborative editor.
- **Nothing outside Filament.** A JSON API or a console command writing to the same
  record does not consult the lock and should not be assumed to. See
  [external writes](external-writes.md) for what protects you there.

## Blind spots

- **A component that writes only from a `Livewire.dispatch()` listener.** `__dispatch`
  has to stay permitted, or the lock's own events could never reach the page. Such a
  listener is nearly always reachable as an action or a property too, and those are
  guarded.
- **A writer that does not touch `updated_at`**, and **a write landing in the same
  second as the snapshot**. Both covered in
  [external writes](external-writes.md#what-it-cannot-see).
- **A tab closed without warning** releases its lock on a best effort basis. The timeout
  is the guarantee.
- **Bulk actions are refused wholesale**, not row by row. If one record in a selection
  is locked the whole action is refused, because quietly doing most of what was asked is
  worse than doing none of it and saying why.

## Cost

The indicator asks every row whether it is locked, so `EagerLoadsRecordLocks` on the
list page is not optional in practice.

The heartbeat is one request per open page per `heartbeat.interval`, paused while the
tab is hidden and while the viewer is idle. On a small server, raise the interval rather
than turning the heartbeat off; without it nothing renews a lock and nothing lets an
abandoned one lapse.
