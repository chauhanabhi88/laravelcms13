<?php

declare(strict_types=1);

namespace Modules\Returns\DTO;

/**
 * What the customer asks for (wizard or API), validated by ReturnService::create().
 */
final readonly class ReturnSubmission
{
    /**
     * @param  array<int, array{quantity: int, exchange_variant_id: int|null}>  $items  keyed by sales_order_item_id
     * @param  list<array{path: string, width: int|null, height: int|null}>  $photos  stored on the private disk
     * @param  array<string, string>|null  $beneficiary  COD refunds: ['account_holder', 'account_number', 'ifsc'] or ['vpa']
     */
    public function __construct(
        public int $sellerOrderId,
        public string $type,
        public string $reasonCode,
        public ?string $comment,
        public array $items,
        public array $photos,
        public ?string $refundMode = null,
        public ?array $beneficiary = null,
        public ?int $pickupAddressId = null,
    ) {}
}
