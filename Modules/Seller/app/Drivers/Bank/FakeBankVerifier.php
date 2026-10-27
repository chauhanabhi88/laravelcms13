<?php

declare(strict_types=1);

namespace Modules\Seller\Drivers\Bank;

use Modules\Seller\Contracts\BankVerifier;
use Modules\Seller\Models\SellerBankAccount;
use Modules\Seller\Support\BankVerificationResult;

/**
 * Local / test driver. By default the account validates at once and the bank "returns"
 * the holder name the seller typed. Tests can script other answers:
 *
 *     app(FakeBankVerifier::class)->returnName('SOMEONE ELSE');
 *     app(FakeBankVerifier::class)->failNext('Invalid account');
 *     app(FakeBankVerifier::class)->stayPending();
 */
class FakeBankVerifier implements BankVerifier
{
    private ?string $name = null;

    private ?string $failure = null;

    private bool $pending = false;

    /**
     * @var list<int>
     */
    public array $verifiedAccountIds = [];

    public function verify(SellerBankAccount $account): BankVerificationResult
    {
        $this->verifiedAccountIds[] = $account->id;
        $reference = 'fav_fake'.$account->id;

        if ($this->failure !== null) {
            return BankVerificationResult::failed($this->failure, $reference);
        }

        if ($this->pending) {
            return BankVerificationResult::pending($reference, 'cont_fake'.$account->id, 'fa_fake'.$account->id);
        }

        return BankVerificationResult::completed($this->name ?? $account->account_holder_name, true, $reference);
    }

    public function method(): string
    {
        return SellerBankAccount::METHOD_PENNY_DROP;
    }

    public function returnName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function failNext(string $message = 'Account validation failed'): static
    {
        $this->failure = $message;

        return $this;
    }

    public function stayPending(bool $pending = true): static
    {
        $this->pending = $pending;

        return $this;
    }
}
