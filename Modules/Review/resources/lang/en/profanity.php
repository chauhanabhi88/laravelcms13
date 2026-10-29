<?php

/*
| Words that hold a review or block a seller reply (Modules\Review\Support\ProfanityFilter).
| Whole words, case-insensitive; common letter swaps (0 → o, @ → a…) and repeated letters are
| folded before matching. Every installed locale's list is applied. Extend per market.
*/

return [
    'words' => [
        'fuck', 'fucking', 'fucked', 'fucker', 'motherfucker', 'fck', 'fuk', 'wtf',
        'shit', 'shitty', 'bullshit', 'crap',
        'bitch', 'bitches', 'bastard', 'asshole', 'arsehole', 'dickhead',
        'cunt', 'twat', 'wanker', 'slut', 'whore', 'pussy', 'cock', 'jackass', 'dumbass',
        'retard', 'retarded', 'scumbag', 'piss off', 'son of a bitch',
    ],
];
