<?php

declare(strict_types=1);

namespace Modules\Product\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Services\CategoryAttributeResolver;
use Modules\Catalog\Support\ResolvedAttribute;
use Modules\Core\Exceptions\InvalidStateTransition;
use Modules\Core\Support\Money;
use Modules\Product\Enums\ProductStatus;
use Modules\Product\Events\ProductApproved;
use Modules\Product\Events\ProductBlocked;
use Modules\Product\Events\ProductRejected;
use Modules\Product\Events\ProductSubmitted;
use Modules\Product\Exceptions\ProductException;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Support\ProductSettings;
use Modules\Seller\Models\Seller;
use Modules\User\Models\User;

/**
 * The product status machine (FR-PRD-005/006): seller submit / pause / resume, moderator
 * approve / reject (submissions and a live product's pending changes), admin block /
 * unblock. Each action runs in a transaction on a locked row, writes Core's status history
 * and fires its event after commit.
 */
class ProductModerationService
{
    public function __construct(
        protected ProductFactoryService $factory,
        protected ProductAggregateService $aggregates,
        protected CategoryAttributeResolver $attributeResolver,
    ) {}

    /**
     * What still blocks a submission (translated messages; empty = ready).
     *
     * @return list<string>
     */
    public function missingForSubmission(Product $product): array
    {
        $product = $this->factory->load($product->id);
        $missing = [];

        if (blank($product->translations->firstWhere('locale', $this->factory->defaultLocale())?->name)) {
            $missing[] = trans('product::product.submission.name');
        }

        if (! $product->variants->contains(fn (ProductVariant $variant): bool => $variant->isEnabled())) {
            $missing[] = trans('product::product.submission.variant');
        }

        if ($product->images->isEmpty()) {
            $missing[] = trans('product::product.submission.image');
        }

        $category = CatalogCategory::query()->find($product->catalog_category_id);
        if ($category === null || ! $category->isEnabled() || ! $category->isLeaf()) {
            $missing[] = trans('product::product.submission.category');
        } else {
            $filled = $product->attributeValues->pluck('attribute_id')->unique()->all();

            $this->attributeResolver->for($category)
                ->filter(fn (ResolvedAttribute $attribute): bool => ! $attribute->isVariantAxis && $attribute->isRequired && ! in_array($attribute->attributeId, $filled, true))
                ->each(function (ResolvedAttribute $attribute) use (&$missing): void {
                    $missing[] = trans('product::product.submission.attribute', ['attribute' => $attribute->name]);
                });
        }

        return $missing;
    }

    /**
     * Seller sends a draft or a rejected product to QC. Trusted sellers go live directly
     * when the "auto approve" setting is on.
     *
     * @throws ProductException|InvalidStateTransition
     */
    public function submit(Product $product, ?Model $actor = null): Product
    {
        $missing = $this->missingForSubmission($product);

        if ($missing !== []) {
            throw new ProductException(trans('product::product.messages.incomplete', ['missing' => implode('; ', $missing)]));
        }

        return DB::transaction(function () use ($product, $actor): Product {
            $product = $this->lock($product->id);

            if (! in_array($product->status, [ProductStatus::Draft, ProductStatus::Rejected], true)) {
                throw new InvalidStateTransition($product, $product->currentState(), ProductStatus::PendingQc->value);
            }

            $product->submitted_at = now();
            $product->transitionTo(ProductStatus::PendingQc, null, [], $actor);
            event(new ProductSubmitted($product, null, $actor));

            if ($this->isTrustedSeller($product->seller_id)) {
                $this->publish($product, null, trans('product::product.messages.auto_approved'));
                event(new ProductApproved($product, trans('product::product.messages.auto_approved')));
            }

            return $product;
        });
    }

    /**
     * Approve a submission (→ live) or a published product's pending changes.
     *
     * @throws ProductException|InvalidStateTransition
     */
    public function approve(Product $product, User $admin, ?string $note = null): Product
    {
        return DB::transaction(function () use ($product, $admin, $note): Product {
            $product = $this->lock($product->id);
            $note = $this->clean($note);

            if ($product->status === ProductStatus::PendingQc) {
                $this->publish($product, $admin, $note);
                event(new ProductApproved($product, $note, $admin));

                return $product;
            }

            if ($product->isPublished() && $product->hasPendingChanges()) {
                $this->factory->applyPendingChanges($product, $admin);
                $this->markReviewed($product, $admin, null);
                $this->recordDecision($product, 'changes_approved', $note, $admin);
                event(new ProductApproved($product, $note, $admin, changesOnly: true));

                return $product;
            }

            throw ProductException::because('nothing_to_review');
        });
    }

    /**
     * Reject a submission (→ rejected) or a published product's pending changes (the live
     * content stays). The reason is shown to the seller.
     *
     * @param  list<string>  $reasonCodes  keys of config('product.qc_reason_templates')
     *
     * @throws ProductException|InvalidStateTransition
     */
    public function reject(Product $product, User $admin, ?string $reason, array $reasonCodes = []): Product
    {
        $reasonCodes = array_values(array_intersect($reasonCodes, (array) config('product.qc_reason_templates')));
        $notes = $this->reasonText($reason, $reasonCodes);

        if ($notes === null) {
            throw ProductException::because('reason_required');
        }

        return DB::transaction(function () use ($product, $admin, $notes, $reasonCodes): Product {
            $product = $this->lock($product->id);

            if ($product->status === ProductStatus::PendingQc) {
                if ($product->hasPendingChanges()) {
                    $this->factory->discardPendingChanges($product);
                }

                $this->markReviewed($product, $admin, $notes);
                $product->transitionTo(ProductStatus::Rejected, $this->historyReason($notes), ['reason_codes' => $reasonCodes], $admin);
                event(new ProductRejected($product, $notes, $admin, $reasonCodes));

                return $product;
            }

            if ($product->isPublished() && $product->hasPendingChanges()) {
                $this->factory->discardPendingChanges($product);
                $this->markReviewed($product, $admin, $notes);
                $this->recordDecision($product, 'changes_rejected', $notes, $admin, ['reason_codes' => $reasonCodes]);
                event(new ProductRejected($product, $notes, $admin, $reasonCodes, changesOnly: true));

                return $product;
            }

            throw ProductException::because('nothing_to_review');
        });
    }

    /**
     * Take a product off the storefront (live, paused or queued). The reason is shown to
     * the seller.
     *
     * @throws ProductException|InvalidStateTransition
     */
    public function block(Product $product, User $admin, ?string $reason): Product
    {
        $reason = $this->clean($reason);

        if ($reason === null) {
            throw ProductException::because('reason_required');
        }

        return DB::transaction(function () use ($product, $admin, $reason): Product {
            $product = $this->lock($product->id);

            $this->markReviewed($product, $admin, $reason);
            $product->transitionTo(ProductStatus::Blocked, $this->historyReason($reason), [], $admin);
            event(new ProductBlocked($product, $reason, $admin));

            return $product;
        });
    }

    /**
     * A blocked product goes back to the QC queue.
     *
     * @throws InvalidStateTransition
     */
    public function unblock(Product $product, User $admin, ?string $reason = null): Product
    {
        return DB::transaction(function () use ($product, $admin, $reason): Product {
            $product = $this->lock($product->id);
            $reason = $this->clean($reason);

            if ($product->status !== ProductStatus::Blocked) {
                throw new InvalidStateTransition($product, $product->currentState(), ProductStatus::PendingQc->value);
            }

            $product->submitted_at = now();
            $product->transitionTo(ProductStatus::PendingQc, $this->historyReason($reason), [], $admin);
            event(new ProductSubmitted($product, $reason, $admin));

            return $product;
        });
    }

    /**
     * Seller hides a live product from the storefront.
     *
     * @throws InvalidStateTransition
     */
    public function pause(Product $product, ?Model $actor = null): Product
    {
        return $this->sellerMove($product, ProductStatus::Live, ProductStatus::Paused, $actor);
    }

    /**
     * Seller shows a paused product again.
     *
     * @throws InvalidStateTransition
     */
    public function resume(Product $product, ?Model $actor = null): Product
    {
        return $this->sellerMove($product, ProductStatus::Paused, ProductStatus::Live, $actor);
    }

    /**
     * Approve several queued products; one failure does not stop the others.
     *
     * @param  list<int>  $ids
     * @return array{done: list<int>, failed: array<int, string>}
     */
    public function bulkApprove(array $ids, User $admin): array
    {
        return $this->bulk($ids, fn (Product $product) => $this->approve($product, $admin));
    }

    /**
     * Reject several queued products with the same reason.
     *
     * @param  list<int>  $ids
     * @param  list<string>  $reasonCodes
     * @return array{done: list<int>, failed: array<int, string>}
     */
    public function bulkReject(array $ids, User $admin, ?string $reason, array $reasonCodes = []): array
    {
        if ($this->reasonText($reason, array_values(array_intersect($reasonCodes, (array) config('product.qc_reason_templates')))) === null) {
            throw ProductException::because('reason_required');
        }

        return $this->bulk($ids, fn (Product $product) => $this->reject($product, $admin, $reason, $reasonCodes));
    }

    /**
     * pending_qc → live: first publication date, reviewer, pending changes folded in.
     */
    protected function publish(Product $product, ?User $admin, ?string $note): void
    {
        if ($product->hasPendingChanges()) {
            $this->factory->applyPendingChanges($product, $admin);
        }

        $product->published_at ??= now();
        $this->markReviewed($product, $admin, null);
        $product->transitionTo(ProductStatus::Live, $note, [], $admin);
        $this->aggregates->refresh($product->id);
    }

    protected function markReviewed(Product $product, ?User $admin, ?string $notes): void
    {
        $product->qc_reviewed_by = $admin?->id;
        $product->qc_reviewed_at = now();
        $product->qc_notes = $notes;
    }

    /**
     * Decisions on pending changes do not move the status, so they are recorded as a
     * history row with the same from / to status.
     *
     * @param  array<string, mixed>  $meta
     */
    protected function recordDecision(Product $product, string $action, ?string $reason, ?User $admin, array $meta = []): void
    {
        $product->save();

        $product->statusHistories()->create([
            'from_status' => $product->currentState(),
            'to_status' => $product->currentState(),
            'actor_type' => $admin !== null ? 'admin' : 'system',
            'actor_id' => $admin?->id,
            'reason' => $this->historyReason($reason),
            'meta' => ['action' => $action] + $meta,
        ]);
    }

    /**
     * status_history.reason holds 500 characters; the full text stays in qc_notes.
     */
    protected function historyReason(?string $reason): ?string
    {
        return $reason === null ? null : mb_substr($reason, 0, 500);
    }

    protected function sellerMove(Product $product, ProductStatus $from, ProductStatus $to, ?Model $actor): Product
    {
        return DB::transaction(function () use ($product, $from, $to, $actor): Product {
            $product = $this->lock($product->id);

            if ($product->status !== $from) {
                throw new InvalidStateTransition($product, $product->currentState(), $to->value);
            }

            return $product->transitionTo($to, null, [], $actor);
        });
    }

    /**
     * @param  list<int>  $ids
     * @param  \Closure(Product): mixed  $action
     * @return array{done: list<int>, failed: array<int, string>}
     */
    protected function bulk(array $ids, \Closure $action): array
    {
        $result = ['done' => [], 'failed' => []];
        $products = Product::query()->whereIn('id', array_values(array_unique(array_map('intval', $ids))))->orderBy('id')->get();

        foreach ($products as $product) {
            try {
                $action($product);
                $result['done'][] = $product->id;
            } catch (ProductException|InvalidStateTransition $e) {
                $result['failed'][$product->id] = $e->getMessage();
            }
        }

        foreach (array_diff(array_map('intval', $ids), $products->pluck('id')->all()) as $missingId) {
            $result['failed'][$missingId] = trans('product::product.messages.not_found');
        }

        return $result;
    }

    protected function isTrustedSeller(int $sellerId): bool
    {
        if (! ProductSettings::autoApproveTrustedSellers()) {
            return false;
        }

        $seller = Seller::query()->find($sellerId, ['id', 'status', 'rating_avg', 'rating_count']);

        // Ratings are decimal(3,2) strings; Money compares them exactly in hundredths.
        return $seller !== null
            && $seller->isActive()
            && $seller->rating_count > 0
            && Money::of((string) $seller->rating_avg)->compare(Money::of(ProductSettings::trustedSellerMinRating())) >= 0;
    }

    /**
     * Reason template labels followed by the moderator's own words.
     *
     * @param  list<string>  $reasonCodes
     */
    protected function reasonText(?string $reason, array $reasonCodes): ?string
    {
        $parts = array_map(fn (string $code): string => trans('product::product.reason_templates.'.$code), $reasonCodes);

        if (($reason = $this->clean($reason)) !== null) {
            $parts[] = $reason;
        }

        return $parts === [] ? null : mb_substr(implode('. ', $parts), 0, 2000);
    }

    protected function clean(?string $text): ?string
    {
        $text = $text !== null ? trim($text) : null;

        return $text === '' ? null : $text;
    }

    protected function lock(int $productId): Product
    {
        return Product::query()->with('translations')->lockForUpdate()->findOrFail($productId);
    }
}
