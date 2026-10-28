<?php

declare(strict_types=1);

namespace Modules\Order\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Order\Models\SellerOrder;

/**
 * The seller (or the auto-confirm job) confirmed the condition of a package returned to them
 * (plan M14 §5.7). A damaged or wrong item is the claim hand-off: Returns (M15) opens the claim
 * for the admin queue from the note and photos (private-disk paths).
 */
class ReturnReceiptConfirmed implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /**
     * @param  list<string>  $photos
     */
    public function __construct(
        public SellerOrder $sellerOrder,
        public string $condition,
        public bool $restocked,
        public ?string $note,
        public array $photos,
        public bool $automatic,
    ) {}

    public function raisesClaim(): bool
    {
        return ! $this->restocked;
    }
}
