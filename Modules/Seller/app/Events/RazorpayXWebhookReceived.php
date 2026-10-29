<?php

declare(strict_types=1);

namespace Modules\Seller\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A signed RazorpayX webhook other than fund account validation (e.g. payout.processed), for
 * the modules that own those entities (Finance, M17). $entity is the payload's main entity.
 */
class RazorpayXWebhookReceived
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $entity
     */
    public function __construct(
        public string $event,
        public array $entity,
        public ?string $eventId = null,
    ) {}
}
