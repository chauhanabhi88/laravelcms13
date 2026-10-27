<?php

declare(strict_types=1);

namespace Modules\Product\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Product\Events\VariantLowStock;
use Modules\Product\Exceptions\InsufficientStock;
use Modules\Product\Models\InventoryMovement;
use Modules\Product\Models\ProductVariant;

/**
 * The only writer of variant stock (FR-PRD-008/009).
 *
 * Every method runs in one transaction, locks the variant rows with lockForUpdate() in id
 * order (so concurrent multi-line orders cannot deadlock), writes an inventory_movement
 * row per bucket change, refreshes the product aggregates after commit and fires
 * VariantLowStock when available stock crosses the threshold downward. Either every line
 * applies or none does.
 *
 * $lines are lists of ['variant_id' => int, 'qty' => int]; repeated variants are summed.
 */
class InventoryService
{
    public function __construct(protected ProductAggregateService $aggregates) {}

    /**
     * Hold stock for an order (M12): reserved += qty, refused when available < qty.
     *
     * @param  list<array{variant_id: int, qty: int}>  $lines
     *
     * @throws InsufficientStock
     */
    public function reserve(array $lines, ?Model $reference = null, ?Model $actor = null, ?string $note = null): void
    {
        $this->apply($lines, InventoryMovement::TYPE_RESERVE, $reference, $actor, $note, function (ProductVariant $variant, int $qty): array {
            if ($variant->available < $qty) {
                throw new InsufficientStock($variant->id, $qty, $variant->available);
            }

            $variant->reserved += $qty;

            return [InventoryMovement::BUCKET_RESERVED => $qty];
        });
    }

    /**
     * Give held stock back (payment timeout, cancellation before dispatch): reserved -= qty,
     * never below zero.
     *
     * @param  list<array{variant_id: int, qty: int}>  $lines
     */
    public function release(array $lines, ?Model $reference = null, ?Model $actor = null, ?string $note = null): void
    {
        $this->apply($lines, InventoryMovement::TYPE_RELEASE, $reference, $actor, $note, function (ProductVariant $variant, int $qty): array {
            $released = min($qty, $variant->reserved);
            $variant->reserved -= $released;

            return [InventoryMovement::BUCKET_RESERVED => -$released];
        });
    }

    /**
     * The goods left the warehouse (M13/M14): reserved -= qty and stock -= qty.
     *
     * @param  list<array{variant_id: int, qty: int}>  $lines
     *
     * @throws InsufficientStock when the physical stock cannot cover the quantity
     */
    public function commit(array $lines, ?Model $reference = null, ?Model $actor = null, ?string $note = null): void
    {
        $this->apply($lines, InventoryMovement::TYPE_COMMIT, $reference, $actor, $note, function (ProductVariant $variant, int $qty): array {
            if ($variant->stock < $qty) {
                throw new InsufficientStock($variant->id, $qty, max(0, $variant->stock));
            }

            $released = min($qty, $variant->reserved);
            $variant->reserved -= $released;
            $variant->stock -= $qty;

            return [InventoryMovement::BUCKET_RESERVED => -$released, InventoryMovement::BUCKET_STOCK => -$qty];
        });
    }

    /**
     * Goods came back sellable (return QC passed, RTO received): stock += qty.
     */
    public function restock(ProductVariant|int $variant, int $qty, ?Model $reference = null, ?Model $actor = null, ?string $note = null): void
    {
        $this->apply([['variant_id' => $this->variantId($variant), 'qty' => $qty]], InventoryMovement::TYPE_RESTOCK, $reference, $actor, $note, function (ProductVariant $variant, int $qty): array {
            $variant->stock += $qty;

            return [InventoryMovement::BUCKET_STOCK => $qty];
        });
    }

    /**
     * Set the physical stock to a counted value (seller inventory edit, M05). The movement
     * records the difference. Stock may not drop below what orders already hold.
     *
     * @throws InsufficientStock
     */
    public function adjust(ProductVariant|int $variant, int $newStock, ?Model $actor = null, ?string $note = null, string $type = InventoryMovement::TYPE_ADJUST): void
    {
        if ($newStock < 0) {
            throw new InvalidArgumentException('Stock cannot be negative.');
        }

        if (! in_array($type, [InventoryMovement::TYPE_ADJUST, InventoryMovement::TYPE_CORRECTION], true)) {
            throw new InvalidArgumentException("[{$type}] is not a stock adjustment type.");
        }

        $this->apply([['variant_id' => $this->variantId($variant), 'qty' => 1]], $type, null, $actor, $note, function (ProductVariant $variant) use ($newStock): array {
            if ($newStock < $variant->reserved) {
                throw new InsufficientStock($variant->id, $variant->reserved, $newStock);
            }

            $delta = $newStock - $variant->stock;
            $variant->stock = $newStock;

            return $delta === 0 ? [] : [InventoryMovement::BUCKET_STOCK => $delta];
        });
    }

    /**
     * Ledger the starting stock of a variant just created with it (ProductFactoryService).
     * Call inside the transaction that inserted the variant.
     */
    public function recordOpening(ProductVariant $variant, ?Model $actor = null, ?string $note = null): void
    {
        if ($variant->stock === 0) {
            return;
        }

        $this->writeMovement($variant, InventoryMovement::TYPE_OPENING, InventoryMovement::BUCKET_STOCK, $variant->stock, null, $actor, $note);
    }

    /**
     * @param  list<array{variant_id: int, qty: int}>  $lines
     * @param  \Closure(ProductVariant, int): array<string, int>  $mutate  changes the variant, returns bucket => signed delta
     */
    protected function apply(array $lines, string $type, ?Model $reference, ?Model $actor, ?string $note, \Closure $mutate): void
    {
        $quantities = $this->normaliseLines($lines);

        DB::transaction(function () use ($quantities, $type, $reference, $actor, $note, $mutate): void {
            $variants = ProductVariant::query()
                ->whereIn('id', array_keys($quantities))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($variants->count() !== count($quantities)) {
                throw (new ModelNotFoundException)->setModel(ProductVariant::class, array_values(array_diff(array_keys($quantities), $variants->keys()->all())));
            }

            $productIds = [];

            foreach ($quantities as $variantId => $qty) {
                /** @var ProductVariant $variant */
                $variant = $variants->get($variantId);
                $availableBefore = $variant->available;

                $deltas = array_filter($mutate($variant, $qty), fn (int $delta): bool => $delta !== 0);

                if ($deltas === []) {
                    continue;
                }

                $variant->save();

                foreach ($deltas as $bucket => $delta) {
                    $this->writeMovement($variant, $type, $bucket, $delta, $reference, $actor, $note);
                }

                $productIds[] = $variant->product_id;

                if ($availableBefore > $variant->low_stock_threshold && $variant->available <= $variant->low_stock_threshold) {
                    event(new VariantLowStock($variant, $variant->available));
                }
            }

            if ($productIds !== []) {
                $ids = array_values(array_unique($productIds));
                DB::afterCommit(fn () => $this->aggregates->refresh($ids));
            }
        });
    }

    protected function writeMovement(ProductVariant $variant, string $type, string $bucket, int $quantity, ?Model $reference, ?Model $actor, ?string $note): void
    {
        InventoryMovement::query()->create([
            'variant_id' => $variant->id,
            'product_id' => $variant->product_id,
            'type' => $type,
            'quantity' => $quantity,
            'bucket' => $bucket,
            'stock_after' => $variant->stock,
            'reserved_after' => $variant->reserved,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'actor_type' => $this->actorType($actor),
            'actor_id' => $actor?->getKey(),
            'note' => $note !== null ? Str::limit($note, 250, '') : null,
        ]);
    }

    /**
     * @param  list<array{variant_id: int, qty: int}>  $lines
     * @return array<int, int> variant id => total quantity, sorted by id
     */
    protected function normaliseLines(array $lines): array
    {
        $quantities = [];

        foreach ($lines as $line) {
            $variantId = (int) ($line['variant_id'] ?? 0);
            $qty = $line['qty'] ?? null;

            if ($variantId <= 0 || ! is_int($qty) || $qty <= 0) {
                throw new InvalidArgumentException('Each inventory line needs a variant_id and a positive integer qty.');
            }

            $quantities[$variantId] = ($quantities[$variantId] ?? 0) + $qty;
        }

        if ($quantities === []) {
            throw new InvalidArgumentException('No inventory lines given.');
        }

        ksort($quantities);

        return $quantities;
    }

    protected function variantId(ProductVariant|int $variant): int
    {
        return $variant instanceof ProductVariant ? $variant->id : $variant;
    }

    /**
     * Same actor naming as Core's status history (config core.state_machine.actor_types).
     */
    protected function actorType(?Model $actor): string
    {
        if ($actor === null) {
            return 'system';
        }

        foreach ((array) config('core.state_machine.actor_types', []) as $class => $type) {
            if ($actor instanceof $class) {
                return $type;
            }
        }

        return Str::limit(Str::snake(class_basename($actor)), 20, '');
    }
}
