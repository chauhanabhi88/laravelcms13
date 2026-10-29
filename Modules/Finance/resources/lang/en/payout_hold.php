<?php

return [
    'hint' => 'Manual holds stop a seller\'s payouts until released. Sellers are also held automatically while their bank account is unverified, while suspended, and (when set) with too many open claims.',
    'place_title' => 'Hold a seller\'s payouts',
    'reason' => 'Reason',
    'confirm_release' => 'Release this hold?',

    'buttons' => [
        'place' => 'Hold payouts',
        'release' => 'Release',
    ],

    'messages' => [
        'placed' => 'Payouts of :seller are on hold.',
        'released' => 'The hold was released.',
        'already_held' => 'This seller already has an active manual hold.',
        'not_active' => 'This hold was already released.',
    ],
];
