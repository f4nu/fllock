<?php

return [
    'locked' => 'Questo record è in sola lettura: qualcun altro lo sta modificando.',
    'locked_by' => ':name sta modificando questo record.',
    'take_over' => 'Prendi il controllo',
    'take_over_warning' => 'Le modifiche non salvate di chi lo sta modificando andranno perse.',
    'manager' => [
        'label' => 'Blocco',
        'plural_label' => 'Blocchi in corso',
        'record' => 'Record',
        'type' => 'Tipo',
        'owner' => 'Bloccato da',
        'since' => 'Bloccato dalle',
        'renewed' => 'Ultimo rinnovo',
        'status' => 'Stato',
        'active' => 'Attivo',
        'expired' => 'Scaduto',
        'unlock' => 'Sblocca',
        'unlocked' => 'Sbloccato',
    ],
    'stale' => [
        'title' => 'Il record è cambiato mentre lo avevi aperto',
        'body' => "Qualcos'altro lo ha modificato: le API, un comando o un job in background. Le tue modifiche non sono state salvate, e salvarle ora le rimetterebbe sopra quella scrittura. Ricarica per ripartire da ciò che dice il record adesso.",
        'modal_body' => "Qualcos'altro lo ha modificato mentre era aperto, quindi non è stato salvato nulla. Riaprilo per lavorare su ciò che dice il record adesso.",
        'reload' => 'Ricarica il record',
        'reloaded' => 'Ricaricato dal database',
    ],
    'column' => [
        'label' => 'Blocco',
        'by_you' => 'Lo stai modificando tu',
        'by' => ':name lo sta modificando',
        'by_other' => 'Qualcun altro lo sta modificando',
    ],
];
