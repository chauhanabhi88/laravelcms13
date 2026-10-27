<?php

return [
    'gstin' => [
        'format' => 'Enter a valid 15-character GSTIN, for example 29ABCPE1234F1Z7.',
        'state' => 'The first two digits of the GSTIN are not a valid state code.',
        'checksum' => 'This GSTIN fails its check digit. Please re-check every character.',
        'pan_mismatch' => 'This GSTIN belongs to a different PAN than the one in your business details.',
        'taken' => 'This GSTIN is already registered with another seller account.',
    ],
    'pan' => [
        'format' => 'Enter a valid 10-character PAN, for example ABCPE1234F.',
    ],
    'ifsc' => [
        'format' => 'Enter a valid 11-character IFSC, for example HDFC0001234.',
        'unknown' => 'We could not find this IFSC. Please check it against your cheque book or passbook.',
    ],
    'pincode' => [
        'unknown' => 'We do not recognise this pincode.',
        'not_serviceable' => 'Pickups are not available from this pincode yet. Please use another address.',
    ],
    'phone' => [
        'invalid' => 'Enter a valid 10-digit Indian mobile number.',
        'taken' => 'This mobile number is already registered as a seller login.',
    ],
    'email' => [
        'taken' => 'This email is already registered as a seller login.',
    ],
    'store_name' => [
        'taken' => 'This store name is taken. Try adding your city or a word that describes your products.',
        'format' => 'Use letters, numbers, spaces and . & \' - only, starting with a letter or number.',
    ],
    'account_number' => 'Enter the account number using 9 to 18 digits.',
    'account_number_confirmed' => 'The account numbers do not match.',
    'holder_name' => 'Use the name exactly as printed on the passbook or cheque (letters only).',
    'enrolment_id' => 'Enter the enrolment ID using 8 to 20 letters and digits.',
    'accept_terms' => 'Please agree to the terms and privacy notice to continue.',
    'accept_agreement' => 'Please read and accept the supplier agreement.',
    'agreement_outdated' => 'The agreement has been updated. Please read the new version and accept it again.',
];
