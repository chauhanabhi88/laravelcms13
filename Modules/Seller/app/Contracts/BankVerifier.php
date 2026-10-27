<?php

declare(strict_types=1);

namespace Modules\Seller\Contracts;

use Modules\Seller\Models\SellerBankAccount;
use Modules\Seller\Support\BankVerificationResult;

/**
 * Checks that a bank account exists and who holds it (setting seller.bank_verifier):
 * razorpayx (penny drop, answers asynchronously by webhook), manual (cheque reviewed by
 * an admin) or fake (local / tests).
 */
interface BankVerifier
{
    public function verify(SellerBankAccount $account): BankVerificationResult;

    /**
     * Stored as seller_bank_account.verification_method: penny_drop or cheque.
     */
    public function method(): string;
}
