---
title: Writes from outside the panel
nav_order: 5
---

# Writes from outside the panel

The API, a console command and a queued job write whenever they like. None of them
consult a lock, and none of them should: an API client cannot hold one, and blocking it
would break whatever the frontend is doing.

That leaves the other half of the problem. A form filled ten minutes ago holds values
the record no longer has, and saving it puts them back over whatever wrote in the
meantime, silently, because nothing is watching.

So a page remembers what `updated_at` said when it filled the form, and:

- the heartbeat tells you as soon as it notices the record moved, with a notification
  that does not time out and offers to reload
- the save refuses, which is the part that actually protects the other writer. A write
  landing two seconds before the button is pressed never reaches the heartbeat.

The form is not cleared. Ten minutes of typing thrown away because something else
touched the row is its own kind of data loss, and refusing the save already protects the
other write. Reloading is offered rather than imposed.

Slide overs are covered too, since most of a panel is edited in one. A stale save closes
the modal and asks you to reopen it.

## Compared for difference, not age

A clock skew between servers, or a writer setting the column explicitly, can leave the
stored value earlier than the snapshot. That is still somebody else's write about to be
lost.

## What it cannot see

- **A writer that does not touch `updated_at`.** A raw `DB::table()->update()`, or a
  model with `$timestamps = false`. Eloquent always touches it, so the API, console
  commands and jobs are covered.
- **A write landing in the same second as the snapshot.** Laravel's timestamps are
  second precision, so those two moments are indistinguishable. Hashing every column on
  every heartbeat would close it and cost far more than the gap is worth. If a
  particular model needs it closed, give it a `datetime(3)` `updated_at`.
