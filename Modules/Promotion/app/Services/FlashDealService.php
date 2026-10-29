<?php

declare(strict_types=1);

namespace Modules\Promotion\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Support\Money;
use Modules\Product\Models\ProductVariant;
use Modules\Promotion\Models\FlashDeal;
use Modules\Promotion\Models\FlashDealItem;

/**
 * Writes flash deals and their variants (admin, plan M18 §6). Existing rows are edited or
 * removed by id; new rows name the variant by its system SKU. A deal price must be above zero
 * and below the variant's own price, and a cap at least the units already sold.
 */
class FlashDealService
{
    /**
     * @param  array<string, mixed>  $data  name, starts_at, ends_at, banner, status
     * @param  array<int|string, array<string, mixed>>  $items  existing item id => {deal_price, stock_cap, remove}
     * @param  list<array<string, mixed>>  $newItems  {sku, deal_price, stock_cap}
     *
     * @throws ValidationException
     */
    public function save(?FlashDeal $deal, array $data, array $items = [], array $newItems = []): FlashDeal
    {
        return DB::transaction(function () use ($deal, $data, $items, $newItems): FlashDeal {
            $deal ??= new FlashDeal;
            $deal->fill($data)->save();

            $errors = [];
            $existing = $deal->items()->with('variant')->get()->keyBy('id');

            foreach ($items as $id => $row) {
                $item = $existing->get((int) $id);

                if (! $item instanceof FlashDealItem) {
                    continue;
                }

                if (! empty($row['remove'])) {
                    $item->delete();

                    continue;
                }

                $price = $this->price($row['deal_price'] ?? null);
                $cap = $this->cap($row['stock_cap'] ?? null);

                if (($error = $this->rowError($item->variant, $price, $cap, $item->sold)) !== null) {
                    $errors['items.'.$id.'.deal_price'] = $error;

                    continue;
                }

                $item->forceFill(['deal_price' => $price, 'stock_cap' => $cap])->save();
            }

            $count = $deal->items()->count();
            $max = (int) config('promotion.flash_deal_max_items', 200);

            foreach ($newItems as $index => $row) {
                $sku = trim((string) ($row['sku'] ?? ''));

                if ($sku === '' && ($row['deal_price'] ?? '') === '') {
                    continue;
                }

                $variant = $sku === '' ? null : ProductVariant::query()->where('sku', $sku)->first();
                $price = $this->price($row['deal_price'] ?? null);
                $cap = $this->cap($row['stock_cap'] ?? null);

                if ($variant === null) {
                    $errors['new_items.'.$index.'.sku'] = trans('promotion::flash_deal.errors.unknown_sku', ['sku' => $sku]);

                    continue;
                }

                if (($error = $this->rowError($variant, $price, $cap, 0)) !== null) {
                    $errors['new_items.'.$index.'.deal_price'] = $error;

                    continue;
                }

                if ($deal->items()->where('variant_id', $variant->id)->exists()) {
                    $errors['new_items.'.$index.'.sku'] = trans('promotion::flash_deal.errors.duplicate', ['sku' => $sku]);

                    continue;
                }

                if ($count >= $max) {
                    $errors['new_items.'.$index.'.sku'] = trans('promotion::flash_deal.errors.too_many', ['max' => $max]);

                    break;
                }

                $deal->items()->create([
                    'product_id' => $variant->product_id,
                    'variant_id' => $variant->id,
                    'deal_price' => $price,
                    'stock_cap' => $cap,
                    'sold' => 0,
                ]);
                $count++;
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            // Items written through the relation do not touch the deal: drop the home cache here.
            $deal->touch();

            return $deal;
        });
    }

    public function delete(FlashDeal $deal): void
    {
        $deal->delete();
    }

    protected function rowError(?ProductVariant $variant, ?Money $price, ?int $cap, int $sold): ?string
    {
        if ($variant === null) {
            return trans('promotion::flash_deal.errors.unknown_sku', ['sku' => '']);
        }

        if ($price === null || ! $price->isPositive()) {
            return trans('promotion::flash_deal.errors.price_required');
        }

        if ($price->compare($variant->price) >= 0) {
            return trans('promotion::flash_deal.errors.price_not_lower', ['sku' => $variant->sku, 'price' => $variant->price->format()]);
        }

        if ($cap !== null && $cap < max(1, $sold)) {
            return trans('promotion::flash_deal.errors.cap_below_sold', ['sold' => $sold]);
        }

        return null;
    }

    protected function price(mixed $value): ?Money
    {
        return is_numeric($value) ? Money::of((string) $value) : null;
    }

    protected function cap(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
