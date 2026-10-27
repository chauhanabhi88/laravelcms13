<?php

declare(strict_types=1);

namespace Modules\Seller\Drivers\Bank;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Modules\Seller\Contracts\BankVerifier;
use Modules\Seller\Exceptions\BankVerificationUnavailable;
use Modules\Seller\Models\SellerBankAccount;
use Modules\Seller\Support\BankVerificationResult;

/**
 * Penny drop through RazorpayX fund account validation:
 * contact → fund account → validation (₹1 deposit). The validation usually completes
 * asynchronously; the answer arrives on the fund_account.validation.completed / .failed
 * webhook (Webhook\RazorpayXController). Contact and fund account ids are reused on retry.
 */
class RazorpayXBankVerifier implements BankVerifier
{
    /**
     * @param  array<string, mixed>  $config  config('seller.razorpayx')
     */
    public function __construct(private readonly array $config) {}

    public function verify(SellerBankAccount $account): BankVerificationResult
    {
        if (empty($this->config['key_id']) || empty($this->config['key_secret']) || empty($this->config['account_number'])) {
            throw new BankVerificationUnavailable('RazorpayX keys or account number are not configured.');
        }

        try {
            $contactId = $account->razorpayx_contact_id ?: $this->createContact($account);
            $fundAccountId = $account->razorpayx_fund_account_id ?: $this->createFundAccount($account, $contactId);

            $response = $this->client()->post('/v1/fund_accounts/validations', [
                'account_number' => (string) $this->config['account_number'],
                'fund_account' => ['id' => $fundAccountId],
                'amount' => (int) ($this->config['validation_amount'] ?? 100),
                'currency' => 'INR',
                'notes' => ['seller_id' => (string) $account->seller_id, 'bank_account_id' => (string) $account->id],
            ]);
        } catch (ConnectionException $e) {
            throw new BankVerificationUnavailable($e->getMessage(), previous: $e);
        }

        if ($response->clientError()) {
            return BankVerificationResult::failed($this->errorMessage($response), null, $contactId, $fundAccountId);
        }

        $this->ensureSuccessful($response);

        return static::fromValidation((array) $response->json(), $contactId, $fundAccountId);
    }

    public function method(): string
    {
        return SellerBankAccount::METHOD_PENNY_DROP;
    }

    /**
     * Read a fund_account.validation entity (API response or webhook payload).
     *
     * @param  array<string, mixed>  $validation
     */
    public static function fromValidation(array $validation, ?string $contactId = null, ?string $fundAccountId = null): BankVerificationResult
    {
        $id = isset($validation['id']) ? (string) $validation['id'] : null;
        $fundAccountId ??= isset($validation['fund_account']['id']) ? (string) $validation['fund_account']['id'] : null;
        $results = (array) ($validation['results'] ?? []);

        return match ($validation['status'] ?? null) {
            'completed' => BankVerificationResult::completed(
                isset($results['registered_name']) ? (string) $results['registered_name'] : null,
                ($results['account_status'] ?? null) === 'active',
                $id,
                $contactId,
                $fundAccountId,
            ),
            'failed' => BankVerificationResult::failed(
                (string) ($validation['status_details']['description'] ?? $validation['error']['description'] ?? 'Validation failed'),
                $id,
                $contactId,
                $fundAccountId,
            ),
            default => BankVerificationResult::pending((string) $id, $contactId, $fundAccountId),
        };
    }

    private function createContact(SellerBankAccount $account): string
    {
        $response = $this->client()->post('/v1/contacts', [
            'name' => $account->account_holder_name,
            'type' => 'vendor',
            'reference_id' => 'seller-'.$account->seller_id,
        ]);

        $this->ensureSuccessful($response);

        return (string) $response->json('id');
    }

    private function createFundAccount(SellerBankAccount $account, string $contactId): string
    {
        $response = $this->client()->post('/v1/fund_accounts', [
            'contact_id' => $contactId,
            'account_type' => 'bank_account',
            'bank_account' => [
                'name' => $account->account_holder_name,
                'ifsc' => $account->ifsc,
                'account_number' => $account->account_number,
            ],
        ]);

        $this->ensureSuccessful($response);

        return (string) $response->json('id');
    }

    private function ensureSuccessful(Response $response): void
    {
        if (! $response->successful() || ! is_string($response->json('id'))) {
            throw new BankVerificationUnavailable('RazorpayX answered HTTP '.$response->status().': '.$this->errorMessage($response));
        }
    }

    private function errorMessage(Response $response): string
    {
        return (string) ($response->json('error.description') ?? $response->reason());
    }

    private function client(): PendingRequest
    {
        return Http::integration('razorpayx')
            ->baseUrl(rtrim((string) ($this->config['base_url'] ?? 'https://api.razorpay.com'), '/'))
            ->withBasicAuth((string) $this->config['key_id'], (string) $this->config['key_secret'])
            ->asJson();
    }
}
