<?php

declare(strict_types=1);

namespace Modules\Seller\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Seller\Contracts\BankVerifier;
use Modules\Seller\Drivers\Bank\RazorpayXBankVerifier;
use Modules\Seller\Enums\SellerStatus;
use Modules\Seller\Events\SellerBankAccountChanged;
use Modules\Seller\Exceptions\BankVerificationUnavailable;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerBankAccount;
use Modules\Seller\Support\BankVerificationResult;
use Modules\Seller\Support\NameMatcher;
use Modules\Seller\Support\SellerSettings;
use Modules\User\Models\User;

/**
 * The seller's primary payout account: saving, verification (penny drop or cheque) and
 * the payout hold.
 *
 * During onboarding the primary account is edited in place. Once a seller has been
 * approved, a new account number creates a new primary row (the old one stays for payout
 * history), puts payouts on hold and fires SellerBankAccountChanged.
 */
class BankAccountService
{
    public function __construct(
        private readonly BankVerifier $verifier,
        private readonly KycDocumentService $documents,
    ) {}

    /**
     * @param  array{account_holder_name: string, account_number: string, ifsc: string, account_type: string, bank_name?: string|null, branch?: string|null}  $data
     */
    public function save(Seller $seller, array $data, ?UploadedFile $cheque = null): SellerBankAccount
    {
        [$account, $needsVerification] = DB::transaction(function () use ($seller, $data, $cheque): array {
            $seller = Seller::query()->lockForUpdate()->findOrFail($seller->id);
            $current = SellerBankAccount::query()
                ->where('seller_id', $seller->id)
                ->where('is_primary', config('core.yes'))
                ->latest('id')
                ->first();

            $details = [
                'account_holder_name' => trim($data['account_holder_name']),
                'account_number' => $data['account_number'],
                'account_last4' => substr($data['account_number'], -4),
                'ifsc' => strtoupper($data['ifsc']),
                'bank_name' => $data['bank_name'] ?? null,
                'branch' => $data['branch'] ?? null,
                'account_type' => $data['account_type'],
            ];

            $chequeId = $cheque ? $this->documents->store($seller, 'cancelled_cheque', $cheque)->id : null;
            $sameAccount = $current !== null && $current->account_number === $details['account_number'] && $current->ifsc === $details['ifsc'];

            if ($sameAccount) {
                $current->fill($details);

                if ($chequeId !== null) {
                    $current->cheque_document_id = $chequeId;

                    if (! $current->isVerified()) {
                        $current->verification_status = SellerBankAccount::STATUS_MANUAL_REVIEW;
                        $current->verification_method = SellerBankAccount::METHOD_CHEQUE;
                    }
                }

                if ($current->verified_name !== null && $current->isDirty('account_holder_name')) {
                    $current->name_match_score = $this->nameScore($current, $seller);
                }

                $current->save();

                return [$current, false];
            }

            $approvedBefore = $seller->verified_at !== null;

            if ($current !== null && $approvedBefore) {
                $current->update(['is_primary' => config('core.no')]);
                $current = null;
            }

            $account = $current ?? new SellerBankAccount(['seller_id' => $seller->id]);
            $account->fill($details + $this->resetVerification() + [
                'is_primary' => config('core.yes'),
                'cheque_document_id' => $chequeId,
            ]);
            $account->save();

            if ($approvedBefore) {
                $seller->update(['payout_hold' => config('core.yes')]);
                event(new SellerBankAccountChanged($seller));
            }

            return [$account, true];
        });

        return $needsVerification ? $this->verify($account) : $account->refresh();
    }

    /**
     * Run the configured verifier and store its answer.
     */
    public function verify(SellerBankAccount $account): SellerBankAccount
    {
        try {
            $result = $this->verifier->verify($account);
        } catch (BankVerificationUnavailable $e) {
            report($e);
            $result = null;
        }

        return $this->applyResult($account, $result, $this->verifier->method());
    }

    /**
     * Turn a verifier answer into the account's status. A null result means the provider
     * could not be used: the seller falls back to a cancelled cheque.
     */
    public function applyResult(SellerBankAccount $account, ?BankVerificationResult $result, string $method): SellerBankAccount
    {
        return DB::transaction(function () use ($account, $result, $method): SellerBankAccount {
            $account = SellerBankAccount::query()->with('seller')->lockForUpdate()->findOrFail($account->id);
            $account->verification_method = $method;
            $account->provider_ref = $result?->providerRef ?? $account->provider_ref;
            $account->razorpayx_contact_id = $result?->contactId ?? $account->razorpayx_contact_id;
            $account->razorpayx_fund_account_id = $result?->fundAccountId ?? $account->razorpayx_fund_account_id;

            switch ($result?->outcome) {
                case BankVerificationResult::PENDING:
                    $account->verification_status = SellerBankAccount::STATUS_PENDING;
                    $account->verification_note = null;
                    break;

                case BankVerificationResult::COMPLETED:
                    $account->verified_name = $result->registeredName ? mb_substr($result->registeredName, 0, 150) : null;

                    if (! $result->accountActive) {
                        $account->verification_status = $this->failedStatus($account);
                        $account->verification_note = trans('seller::seller.bank_notes.inactive_account');
                        break;
                    }

                    $account->name_match_score = $this->nameScore($account, $account->seller);

                    if ($account->name_match_score >= SellerSettings::nameMatchThreshold()) {
                        $account->verification_status = SellerBankAccount::STATUS_VERIFIED;
                        $account->verified_at = Carbon::now();
                        $account->verification_note = null;
                    } else {
                        $account->verification_status = SellerBankAccount::STATUS_MANUAL_REVIEW;
                        $account->verification_note = trans('seller::seller.bank_notes.name_mismatch');
                    }
                    break;

                case BankVerificationResult::FAILED:
                    $account->verification_status = $this->failedStatus($account);
                    $account->verification_note = mb_substr((string) ($result->message ?: trans('seller::seller.bank_notes.failed')), 0, 500);
                    break;

                case BankVerificationResult::MANUAL:
                    $account->verification_status = SellerBankAccount::STATUS_MANUAL_REVIEW;
                    $account->verification_method = SellerBankAccount::METHOD_CHEQUE;
                    $account->verification_note = null;
                    break;

                default:
                    $account->verification_status = SellerBankAccount::STATUS_MANUAL_REVIEW;
                    $account->verification_method = SellerBankAccount::METHOD_CHEQUE;
                    $account->verification_note = trans('seller::seller.bank_notes.unavailable');
            }

            $account->save();
            $this->syncPayoutHold($account->seller, $account);

            return $account;
        });
    }

    /**
     * RazorpayX fund_account.validation webhook. Unknown or already settled validations are
     * ignored, so a redelivered event changes nothing.
     *
     * @param  array<string, mixed>  $validation  the fund_account.validation entity
     */
    public function applyValidationWebhook(array $validation): ?SellerBankAccount
    {
        $reference = (string) ($validation['id'] ?? '');

        if ($reference === '') {
            return null;
        }

        $account = SellerBankAccount::query()->where('provider_ref', $reference)->latest('id')->first();

        if ($account === null || ! $account->isPending()) {
            return $account;
        }

        return $this->applyResult($account, RazorpayXBankVerifier::fromValidation($validation), SellerBankAccount::METHOD_PENNY_DROP);
    }

    /**
     * An admin checked the cancelled cheque (or the name mismatch) and accepts the account.
     */
    public function markVerifiedManually(SellerBankAccount $account, User $admin, ?string $note = null): SellerBankAccount
    {
        return DB::transaction(function () use ($account, $admin, $note): SellerBankAccount {
            $account = SellerBankAccount::query()->with('seller')->lockForUpdate()->findOrFail($account->id);
            $account->fill([
                'verification_status' => SellerBankAccount::STATUS_VERIFIED,
                'verification_method' => $account->verification_method ?: SellerBankAccount::METHOD_CHEQUE,
                'verification_note' => $note ? mb_substr($note, 0, 500) : null,
                'verified_at' => Carbon::now(),
                'verified_by' => $admin->id,
            ])->save();

            $this->syncPayoutHold($account->seller, $account);

            return $account;
        });
    }

    /**
     * Payouts flow only to an active seller with a verified primary account.
     */
    public function syncPayoutHold(Seller $seller, ?SellerBankAccount $primary = null): void
    {
        $primary ??= $seller->primaryBankAccount()->first();

        // A demoted (non-primary) account never decides the hold.
        if ($primary !== null && $primary->is_primary !== (int) config('core.yes')) {
            return;
        }

        $hold = ! ($seller->status === SellerStatus::Active && $primary?->isVerified());
        $value = (int) config($hold ? 'core.yes' : 'core.no');

        if ($seller->payout_hold !== $value) {
            $seller->update(['payout_hold' => $value]);
        }
    }

    private function nameScore(SellerBankAccount $account, Seller $seller): int
    {
        return NameMatcher::bestScore($account->verified_name, [
            $account->account_holder_name,
            $seller->legal_name,
            $seller->gst_legal_name,
            $seller->store_name,
        ]);
    }

    /**
     * A failed check still goes to an admin when a cancelled cheque is on file.
     */
    private function failedStatus(SellerBankAccount $account): string
    {
        return $account->cheque_document_id !== null ? SellerBankAccount::STATUS_MANUAL_REVIEW : SellerBankAccount::STATUS_FAILED;
    }

    /**
     * @return array<string, null|string>
     */
    private function resetVerification(): array
    {
        return [
            'verification_status' => SellerBankAccount::STATUS_PENDING,
            'verification_method' => null,
            'verified_name' => null,
            'name_match_score' => null,
            'provider_ref' => null,
            // The RazorpayX contact is the seller and can be reused; the fund account cannot.
            'razorpayx_fund_account_id' => null,
            'verification_note' => null,
            'verified_at' => null,
            'verified_by' => null,
        ];
    }
}
