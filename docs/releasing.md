# Releasing a lock

Four ways, in the order they usually happen.

## The page closes or is navigated away from

SPA navigation releases it at once. `wire:navigate` swaps the body without unloading the
document, so `pagehide` never fires; without hooking `livewire:navigating` a lock sat
there until it expired, minutes during which nobody else could edit a record its owner
had already walked away from. Nothing is unloading, so the release is an ordinary
Livewire request and it completes normally.

A real unload, meaning a closed tab or a full page load, is best effort. The request may
not survive it. That is what the timeout is for.

## The viewer stops touching the keyboard

After `heartbeat.idle_after` seconds with no keypress, pointer move or touch, the
browser stops renewing and the lock lapses on its own.

This is the rule that matters in practice. A tab beats whether or not anybody is there,
so without it a lock survived exactly as long as the tab did: open a record, go home,
and you hold it until morning.

## The timeout passes with no heartbeat at all

After `timeout` seconds the lock is ignored on sight. The next person's page picks the
record up on their next beat, and taking it clears the old row.

## Somebody takes it

Offered on the read only notification to anyone passing `unlock_gate`, and available for
any lock from the lock manager page.

## The sweep

`fllock:clear-expired` runs hourly, scheduled by the package unless `sweep_expired` is
false.

Nothing depends on it. An expired lock is already ignored, and acquiring a record clears
whatever row was there. It exists so the table and the lock manager do not fill with
rows that mean nothing, which is how "who is editing what" stops being readable. The
only rows it really collects are for records nobody ever opens again.

Deleting on read would be the alternative and is a bad trade: `isLocked()` runs on every
render, once per row wherever the indicator appears, so a fifty row page would issue
fifty deletes per render.
