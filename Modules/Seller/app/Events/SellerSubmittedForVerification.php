<?php

declare(strict_types=1);

namespace Modules\Seller\Events;

/**
 * A seller finished the onboarding wizard and waits for KYC review (notify admins, M09).
 */
class SellerSubmittedForVerification extends SellerEvent {}
