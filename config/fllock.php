<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Lock lifetime
    |--------------------------------------------------------------------------
    |
    | Seconds a lock survives without a heartbeat from the browser holding it.
    | It must stay comfortably above `heartbeat.interval`, or a lock dies
    | between two beats and a second editor walks in on a live one.
    |
    */

    'timeout' => (int) env('FLLOCK_TIMEOUT', 120),

    /*
    |--------------------------------------------------------------------------
    | Heartbeat
    |--------------------------------------------------------------------------
    |
    | A Filament panel in SPA mode never fires a page unload, so polling is not
    | an optimisation here: it is the only thing that both renews a live
    | editor's lock and lets an abandoned one lapse.
    |
    | `keep_alive` polls on in a backgrounded tab. Leaving an edit page open in
    | a background tab is ordinary, and without this the lock expires under
    | someone who is still working. Turn it off only if you would rather hand
    | the record over than keep it warm.
    |
    */

    'heartbeat' => [
        'interval' => (int) env('FLLOCK_HEARTBEAT', 20),
        'keep_alive' => false,
        'only_when_visible' => false,

        /*
        | Seconds of no keyboard, mouse or touch input after which the browser
        | stops renewing the lock, letting it expire on its own.
        |
        | Without this the lock survives exactly as long as the tab does, which
        | is not the same thing at all: someone who opens a record, wanders off
        | and leaves the tab open holds it overnight, and the timeout above
        | never gets a chance to mean anything. The point of a heartbeat is to
        | say somebody is still there.
        |
        | Null keeps a lock alive for as long as the page is open.
        */
        'idle_after' => (int) env('FLLOCK_IDLE_AFTER', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    */

    'table' => 'record_locks',
    'user_model' => 'App\Models\User',
    'owner_name_attribute' => 'name',

    /*
    |--------------------------------------------------------------------------
    | Taking a record off someone
    |--------------------------------------------------------------------------
    |
    | A forced unlock discards whatever the current editor has typed and not
    | saved, so it is a staff decision rather than a convenience. Null means
    | anyone who can reach the page can do it.
    |
    */

    'unlock_gate' => env('FLLOCK_UNLOCK_GATE'),

    /*
    |--------------------------------------------------------------------------
    | The lock manager page
    |--------------------------------------------------------------------------
    */

    'manager' => [
        'enabled' => true,
        'gate' => env('FLLOCK_MANAGER_GATE'),
        'navigation_group' => null,
        'navigation_icon' => 'heroicon-o-lock-closed',
        'navigation_sort' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Actions allowed to run against a record someone else is editing
    |--------------------------------------------------------------------------
    |
    | An allowlist, deliberately. Written as a denylist of the built-in names it
    | misses every custom action an app adds -- and a custom action is exactly
    | the one nobody remembers to list. Anything not named here is refused while
    | another editor holds the lock.
    |
    */

    'permitted_actions' => ['view'],

    /*
    |--------------------------------------------------------------------------
    | Methods allowed to run while someone else holds the lock
    |--------------------------------------------------------------------------
    |
    | Every other Livewire call on a locked component is refused, which is the
    | only way to cover a page that writes through methods of its own invention
    | rather than through anything Filament recognises.
    |
    | These are the reads. A locked page still has to be readable, so paging,
    | sorting and searching go through -- take one out and read-only becomes
    | broken. Add your own page's read-only methods here.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Sweeping expired locks
    |--------------------------------------------------------------------------
    |
    | The package schedules `fllock:clear-expired` hourly. Nothing depends on it
    | -- an expired lock is ignored on sight -- but without it the lock manager
    | fills with rows that mean nothing. Set false to schedule it yourself.
    |
    */

    'sweep_expired' => true,

    'permitted_methods' => [
        'gotoPage',
        'nextPage',
        'previousPage',
        'setPage',
        'resetPage',
        'sortTable',
        'toggleTableReordering',
        'resetTableFiltersForm',
        'removeTableFilter',
        'removeTableFilters',
        'applyTableFilters',
        'toggleTableColumn',
        'openFilamentModal',
        'closeFilamentModal',
    ],

];
