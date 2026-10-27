<?php

declare(strict_types=1);

namespace Modules\Seller\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\Exceptions\InvalidStateTransition;
use Modules\Seller\Enums\SellerStatus;
use Modules\Seller\Events\SellerApproved;
use Modules\Seller\Events\SellerChangesRequested;
use Modules\Seller\Events\SellerEvent;
use Modules\Seller\Events\SellerReactivated;
use Modules\Seller\Events\SellerRejected;
use Modules\Seller\Events\SellerSuspended;
use Modules\Seller\Exceptions\SellerException;
use Modules\Seller\Models\Seller;
use Modules\User\Models\User;

/**
 * Admin decisions on a seller. Each runs in a transaction, moves the state machine with the
 * admin as actor (status_history) and fires its event after commit.
 */
class SellerVerificationService
{
    public function __construct(private readonly BankAccountService $bankAccounts) {}

    /**
     * @throws InvalidStateTransition
     */
    public function approve(Seller $seller, User $admin, ?string $reason = null): Seller
    {
        return $this->decide($seller, $admin, SellerStatus::Active, $reason, function (Seller $seller) use ($admin): void {
            $seller->fill([
                'verified_at' => Carbon::now(),
                'verified_by' => $admin->id,
                'status_reason' => null,
            ]);
        }, SellerApproved::class, requireReason: false, onSaved: fn (Seller $seller) => $this->bankAccounts->syncPayoutHold($seller), from: SellerStatus::PendingVerification);
    }

    public function reject(Seller $seller, User $admin, ?string $reason): Seller
    {
        return $this->decide($seller, $admin, SellerStatus::Rejected, $reason, function (Seller $seller) use ($reason): void {
            $seller->status_reason = $reason;
        }, SellerRejected::class);
    }

    public function requestChanges(Seller $seller, User $admin, ?string $reason): Seller
    {
        return $this->decide($seller, $admin, SellerStatus::ChangesRequested, $reason, function (Seller $seller) use ($reason): void {
            $seller->status_reason = $reason;
        }, SellerChangesRequested::class);
    }

    /**
     * Blocks the panel's write actions, holds payouts; M04 pauses live products on the event.
     */
    public function suspend(Seller $seller, User $admin, ?string $reason): Seller
    {
        return $this->decide($seller, $admin, SellerStatus::Suspended, $reason, function (Seller $seller) use ($reason): void {
            $seller->fill([
                'suspended_reason' => $reason,
                'payout_hold' => config('core.yes'),
            ]);
        }, SellerSuspended::class);
    }

    public function reactivate(Seller $seller, User $admin, ?string $reason = null): Seller
    {
        return $this->decide($seller, $admin, SellerStatus::Active, $reason, function (Seller $seller): void {
            $seller->suspended_reason = null;
        }, SellerReactivated::class, requireReason: false, onSaved: fn (Seller $seller) => $this->bankAccounts->syncPayoutHold($seller), from: SellerStatus::Suspended);
    }

    /**
     * @param  \Closure(Seller): void  $apply  changes written together with the transition
     * @param  class-string<SellerEvent>  $event
     * @param  (\Closure(Seller): void)|null  $onSaved
     */
    private function decide(
        Seller $seller,
        User $admin,
        SellerStatus $to,
        ?string $reason,
        \Closure $apply,
        string $event,
        bool $requireReason = true,
        ?\Closure $onSaved = null,
        ?SellerStatus $from = null,
    ): Seller {
        $reason = $reason !== null ? trim($reason) : null;

        if ($requireReason && ($reason === null || $reason === '')) {
            throw new SellerException(trans('seller::seller.messages.reason_required'));
        }

        return DB::transaction(function () use ($seller, $admin, $to, $reason, $apply, $event, $onSaved, $from): Seller {
            $seller = Seller::query()->lockForUpdate()->findOrFail($seller->id);

            if ($from !== null && $seller->status !== $from) {
                throw new InvalidStateTransition($seller, $seller->currentState(), $to->value);
            }

            $apply($seller);
            $seller->transitionTo($to, $reason ?: null, [], $admin);

            if ($onSaved !== null) {
                $onSaved($seller);
            }

            event(new $event($seller, $reason ?: null, $admin));

            return $seller;
        });
    }
}
