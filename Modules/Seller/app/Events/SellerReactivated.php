<?php

declare(strict_types=1);

namespace Modules\Seller\Events;

/**
 * A suspended seller is active again: M04 restores storefront visibility (products the
 * seller paused stay paused).
 */
class SellerReactivated extends SellerEvent {}
