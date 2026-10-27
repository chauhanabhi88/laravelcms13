<?php

declare(strict_types=1);

namespace Modules\Seller\Events;

/**
 * An admin suspended the seller: M04 pauses live products, M17 holds payouts.
 */
class SellerSuspended extends SellerEvent {}
