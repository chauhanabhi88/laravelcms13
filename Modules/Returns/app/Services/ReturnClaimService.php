<?php

declare(strict_types=1);

namespace Modules\Returns\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\Money;
use Modules\Order\Models\SellerOrder;
use Modules\Returns\Events\ReturnClaimDecided;
use Modules\Returns\Exceptions\ReturnException;
use Modules\Returns\Models\ReturnClaim;
use Modules\Returns\Models\ReturnRequest;
use Modules\Returns\Support\ReturnNotifier;
use Modules\Returns\Support\ReturnPhotoStore;
use Modules\Returns\Support\ReturnSettings;

/**
 * Seller claims (FR-RET-006, plan M15 §5.6): on a customer return that failed QC, within the
 * claim window after receipt, or from an RTO package the seller confirmed as damaged or wrong
 * in the order desk (M14). One open claim per return / package. Admin accepts (with the
 * amount M17 credits as claim_credit) or rejects.
 */
class ReturnClaimService
{
    public function __construct(
        private readonly ReturnPhotoStore $photos,
        private readonly ReturnNotifier $notifier,
    ) {}

    /**
     * Whether the seller may still claim on the return.
     */
    public function canClaim(ReturnRequest $return, ?Carbon $now = null): bool
    {
        if (! in_array($return->status, [ReturnRequest::STATUS_RECEIVED, ReturnRequest::STATUS_QC_FAILED, ReturnRequest::STATUS_QC_PASSED, ReturnRequest::STATUS_REFUND_INITIATED, ReturnRequest::STATUS_REFUNDED, ReturnRequest::STATUS_CLOSED], true)) {
            return false;
        }

        $receivedAt = $return->received_at ?? $return->qc_at;

        if ($receivedAt === null || ($now ?? Carbon::now())->greaterThan($receivedAt->copy()->addHours(ReturnSettings::claimWindowHours()))) {
            return false;
        }

        return ! ReturnClaim::query()->where('return_request_id', $return->id)->where('status', ReturnClaim::STATUS_OPEN)->exists();
    }

    /**
     * @param  list<UploadedFile|string>  $photos  uploads, or private-disk paths already stored
     *
     * @throws ReturnException
     */
    public function openForReturn(ReturnRequest $return, string $reason, ?string $description, array $photos = []): ReturnClaim
    {
        if (! in_array($reason, (array) config('returns.claim_reasons', []), true)) {
            throw ReturnException::field('reason', 'claim_reason');
        }

        if (! $this->canClaim($return)) {
            throw new ReturnException(trans('returns::seller.errors.claim_closed'), 'claim_closed');
        }

        $claim = ReturnClaim::query()->create([
            'return_request_id' => $return->id,
            'seller_order_id' => $return->seller_order_id,
            'seller_id' => $return->seller_id,
            'source' => ReturnClaim::SOURCE_RETURN,
            'reason' => $reason,
            'description' => $this->text($description),
            'images' => $this->storePhotos($photos, 'claims/'.$return->return_no),
            'status' => ReturnClaim::STATUS_OPEN,
        ]);

        DB::afterCommit(fn () => $this->notifier->claimOpened($claim));

        return $claim;
    }

    /**
     * M14's ReturnReceiptConfirmed for an RTO package received damaged or wrong (idempotent per
     * package).
     *
     * @param  list<string>  $photos  private-disk paths
     */
    public function openForRto(SellerOrder $sellerOrder, string $condition, ?string $note, array $photos): ReturnClaim
    {
        $existing = ReturnClaim::query()->where('seller_order_id', $sellerOrder->id)->whereNull('return_request_id')->first();

        if ($existing !== null) {
            return $existing;
        }

        $claim = ReturnClaim::query()->create([
            'return_request_id' => null,
            'seller_order_id' => $sellerOrder->id,
            'seller_id' => $sellerOrder->seller_id,
            'source' => ReturnClaim::SOURCE_RTO,
            'reason' => $condition === 'wrong_item' ? 'wrong_item_returned' : 'damaged_return',
            'description' => $this->text($note),
            'images' => $photos === [] ? null : array_values($photos),
            'status' => ReturnClaim::STATUS_OPEN,
        ]);

        DB::afterCommit(fn () => $this->notifier->claimOpened($claim));

        return $claim;
    }

    /**
     * @throws ReturnException
     */
    public function decide(ReturnClaim $claim, bool $accept, ?Money $amount, ?string $note, ?Model $actor = null): ReturnClaim
    {
        DB::transaction(function () use ($claim, $accept, $amount, $note, $actor): void {
            $locked = ReturnClaim::query()->lockForUpdate()->findOrFail($claim->id);

            if (! $locked->isOpen()) {
                throw new ReturnException(trans('returns::return_claim.messages.already_decided'), 'already_decided');
            }

            if ($accept && ($amount === null || ! $amount->isPositive())) {
                throw ReturnException::field('resolution_amount', 'claim_amount');
            }

            $locked->forceFill([
                'status' => $accept ? ReturnClaim::STATUS_ACCEPTED : ReturnClaim::STATUS_REJECTED,
                'resolution_amount' => $accept ? $amount : null,
                'decision_note' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 500) : null,
                'decided_by' => $actor?->getKey(),
                'decided_at' => Carbon::now(),
            ])->save();

            ReturnClaimDecided::dispatch($locked);
        });

        return $claim->refresh();
    }

    /**
     * @param  list<UploadedFile|string>  $photos
     * @return list<string>|null
     */
    protected function storePhotos(array $photos, string $folder): ?array
    {
        $paths = [];

        foreach (array_slice($photos, 0, ReturnSettings::maxImages()) as $photo) {
            $paths[] = $photo instanceof UploadedFile ? $this->photos->store($photo, $folder)['path'] : (string) $photo;
        }

        return $paths === [] ? null : $paths;
    }

    protected function text(?string $value): ?string
    {
        return $value !== null && trim($value) !== '' ? mb_substr(trim($value), 0, 1000) : null;
    }
}
