<?php

return [
    'locked' => 'This record is read-only: someone else is editing it.',
    'locked_by' => ':name is editing this record.',
    'take_over' => 'Take over editing',
    'take_over_warning' => 'Whatever the current editor has typed and not saved will be lost.',
    'manager' => [
        'label' => 'Record lock',
        'plural_label' => 'Record locks',
        'record' => 'Record',
        'type' => 'Type',
        'owner' => 'Held by',
        'since' => 'Held since',
        'renewed' => 'Last renewed',
        'status' => 'Status',
        'active' => 'Active',
        'expired' => 'Expired',
        'unlock' => 'Unlock',
        'unlocked' => 'Unlocked',
    ],
    'stale' => [
        'title' => 'This record changed while you had it open',
        'body' => 'Something else wrote to it — the API, a command, or a background job. Nothing you changed has been saved, and saving it now would put it back over that write. Reload to start from what the record says now.',
        'modal_body' => 'Something else wrote to it while this was open, so nothing was saved. Open it again to work from what the record says now.',
        'reload' => 'Reload the record',
        'reloaded' => 'Reloaded from the database',
    ],
    'column' => [
        'label' => 'Lock',
        'by_you' => 'You are editing this',
        'by' => ':name is editing this',
        'by_other' => 'Someone else is editing this',
    ],
];
