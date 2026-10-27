<?php

/*
| Supplier agreement shown in onboarding step 5. Placeholder wording for legal review:
| when the text changes, bump config('seller.agreement_version') so sellers accept again.
*/

return [
    'title' => 'Supplier Agreement',
    'sections' => [
        [
            'heading' => '1. Parties and scope',
            'body' => 'This agreement is between the marketplace operator and the business registering as a supplier. It covers listing products on the marketplace, fulfilling orders and receiving payouts.',
        ],
        [
            'heading' => '2. Your business details',
            'body' => 'You confirm that the business, tax and bank details you provide are true and belong to your business, and you will keep them up to date. We may verify them with third parties and suspend the account if they prove incorrect.',
        ],
        [
            'heading' => '3. Products and listings',
            'body' => 'You list only products you are legally allowed to sell, that are genuine, safe and accurately described, with correct prices, MRP, tax rates and legally required details such as country of origin and manufacturer information. Listings are reviewed before they go live.',
        ],
        [
            'heading' => '4. Orders and dispatch',
            'body' => 'You accept or cancel orders promptly and pack accepted orders for courier pickup within the dispatch time shown in your panel. Repeated cancellations or late dispatch may lead to penalties or suspension.',
        ],
        [
            'heading' => '5. Returns',
            'body' => 'Buyers may return or exchange products under the return policy of the category. Returned parcels are delivered back to your pickup address; you may raise a claim for a wrong or damaged return within the time allowed.',
        ],
        [
            'heading' => '6. Fees, taxes and payouts',
            'body' => 'The commission on sales is 0% unless we notify you of a change in advance. Applicable taxes collected at source, shipping charges and penalties are deducted as shown in your statement. Payouts are made to your verified bank account after the return window closes.',
        ],
        [
            'heading' => '7. Conduct and data',
            'body' => 'You will not contact buyers outside the marketplace, use buyer data for any other purpose or manipulate ratings. You will protect buyer data you receive in line with applicable data protection law.',
        ],
        [
            'heading' => '8. Suspension and termination',
            'body' => 'We may suspend or close your account for breach of this agreement or the seller policies. You may close your account at any time after fulfilling open orders and settling dues.',
        ],
        [
            'heading' => '9. Changes',
            'body' => 'We may update this agreement. We will ask you to accept the new version before you continue using the supplier panel.',
        ],
    ],
];
