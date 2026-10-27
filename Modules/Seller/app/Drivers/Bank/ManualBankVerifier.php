<?php

declare(strict_types=1);

namespace Modules\Seller\Drivers\Bank;

use Modules\Seller\Contracts\BankVerifier;
use Modules\Seller\Models\SellerBankAccount;
use Modules\Seller\Support\BankVerificationResult;

/**
 * No automatic check: the seller uploads a cancelled cheque and an admin verifies it
 * (admin.seller.bank_verify_manual).
 */
class ManualBankVerifier implements BankVerifier
{
    public function verify(SellerBankAccount $account): BankVerificationResult
    {
        return BankVerificationResult::manual();
    }

    public function method(): string
    {
        return SellerBankAccount::METHOD_CHEQUE;
    }
}
