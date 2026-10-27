<?php

declare(strict_types=1);

namespace Modules\Seller\Events;

/**
 * The primary bank account of an active seller changed: payouts are on hold until it
 * verifies again (M17).
 */
class SellerBankAccountChanged extends SellerEvent {}
