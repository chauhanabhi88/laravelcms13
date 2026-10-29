<?php

return [
    'hint' => 'TCS collected at :rate% on the net taxable value of GST-registered sellers, by the month the sale or return was posted (GSTR-8 tables 3 and 4). Built from the ledger, so it reconciles with what sellers were charged.',
    'total' => 'Total',

    'columns' => [
        'seller' => 'Seller',
        'gstin' => 'GSTIN',
        'gross' => 'Gross supplies (taxable)',
        'returns' => 'Returns (taxable)',
        'net' => 'Net taxable value',
        'cgst' => 'TCS CGST',
        'sgst' => 'TCS SGST',
        'igst' => 'TCS IGST',
        'tcs' => 'TCS total',
    ],
];
