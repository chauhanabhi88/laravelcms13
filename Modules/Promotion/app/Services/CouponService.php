<?php

declare(strict_types=1);

namespace Modules\Promotion\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Cart\DTO\CartSnapshot;
use Modules\Cart\DTO\Totals;
use Modules\Cart\DTO\TotalsAdjustment;
use Modules\Cart\Models\Cart;
use Modules\Cart\Services\CartService;
use Modules\Cart\Services\CartTotals;
use Modules\Core\Support\Money;
use Modules\Promotion\DTO\CouponEvaluation;
use Modules\Promotion\Exceptions\CouponException;
use Modules\Promotion\Models\Coupon;
use Modules\Promotion\Support\PromotionContextResolver;
use Modules\Promotion\Support\PromotionTotals;

/**
 * Coupons for buyers and for the people who create them (plan M18 §4-6).
 *
 * Buyers: apply a code to a cart (validated against the cart as it is, then kept in
 * cart.coupon_code; the CouponAdjustment re-validates it on every price summary), remove it, see
 * the "Available offers" list, and the status of the applied code for the coupon box.
 *
 * Admin and sellers: save, delete (only when never used) and bulk-generate unique codes.
 */
class CouponService
{
    /**
     * Characters of generated codes (no 0/O, 1/I lookalikes).
     */
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private readonly CartService $carts,
        private readonly CartTotals $totals,
        private readonly CouponValidator $validator,
        private readonly PromotionContextResolver $contexts,
    ) {}

    /**
     * An entered code (automatic offers have no code to enter).
     */
    public function findByCode(?string $code): ?Coupon
    {
        $code = Coupon::normaliseCode((string) $code);

        return $code === '' ? null : Coupon::query()->withCode()->where('code', $code)->first();
    }

    /**
     * Validate $code against the cart and keep it on the cart.
     *
     * @throws CouponException
     */
    public function apply(Cart $cart, string $code, ?string $paymentMethod = null): CouponEvaluation
    {
        $coupon = $this->findByCode($code) ?? throw CouponException::notFound(Coupon::normaliseCode($code));
        $snapshot = $this->withoutCoupon($this->carts->snapshot($cart));
        $evaluation = $this->evaluate($coupon, $snapshot, $paymentMethod);

        if (! $evaluation->valid) {
            throw CouponException::fromEvaluation($evaluation);
        }

        $cart->forceFill(['coupon_code' => $coupon->code])->save();

        return $evaluation;
    }

    public function remove(Cart $cart): void
    {
        if ($cart->coupon_code !== null) {
            $cart->forceFill(['coupon_code' => null])->save();
        }
    }

    public function evaluate(Coupon $coupon, CartSnapshot $snapshot, ?string $paymentMethod = null): CouponEvaluation
    {
        $snapshot = $this->withoutCoupon($snapshot);

        return $this->validator->evaluate($coupon, $snapshot, $this->totals->calculate($snapshot, $paymentMethod), $this->contexts->for($snapshot, $paymentMethod));
    }

    /**
     * The coupon box state: the code kept on the cart, whether it discounts the summary and, if
     * not, why ("Add ₹120 more to use SAVE50"); the automatic offer applied, if any.
     *
     * @return array{code: string|null, applied: bool, message: string|null, saving: Money|null, offer: array{name: string, saving: Money}|null}
     */
    public function status(CartSnapshot $snapshot, Totals $totals, ?string $paymentMethod = null): array
    {
        $row = $totals->adjustment(TotalsAdjustment::COUPON);
        $offer = $totals->adjustment(TotalsAdjustment::OFFER);
        $message = null;

        if ($snapshot->couponCode !== null && $row === null) {
            $coupon = $this->findByCode($snapshot->couponCode);
            $message = $coupon === null
                ? trans('promotion::front.errors.not_found', ['code' => $snapshot->couponCode])
                : ($this->evaluate($coupon, $snapshot, $paymentMethod)->message ?? trans('promotion::front.errors.not_applicable', ['code' => $coupon->code]));
        }

        return [
            'code' => $snapshot->couponCode,
            'applied' => $row !== null,
            'message' => $message,
            'saving' => $row !== null ? $this->saving($row) : null,
            'offer' => $offer !== null ? ['name' => (string) ($offer->meta['name'] ?? $offer->label), 'saving' => $this->saving($offer)] : null,
        ];
    }

    /**
     * "Available offers" (visible live code coupons, personal ones of this customer included),
     * each evaluated against the cart; usable ones first, the biggest saving first.
     *
     * @return list<array{coupon: Coupon, evaluation: CouponEvaluation}>
     */
    public function offers(CartSnapshot $snapshot, ?string $paymentMethod = null): array
    {
        $coupons = Coupon::query()
            ->live()
            ->withCode()
            ->where('is_visible', config('core.yes'))
            ->where(fn ($query) => $query->whereNull('customer_id')->when($snapshot->customerId !== null, fn ($query) => $query->orWhere('customer_id', $snapshot->customerId)))
            ->orderByDesc('id')
            ->limit((int) config('promotion.offers_limit', 20))
            ->get();

        if ($coupons->isEmpty()) {
            return [];
        }

        $base = $this->withoutCoupon($snapshot);
        $totals = $this->totals->calculate($base, $paymentMethod);
        $context = $this->contexts->for($base, $paymentMethod);

        $rows = $coupons->map(fn (Coupon $coupon): array => [
            'coupon' => $coupon,
            'evaluation' => $this->validator->evaluate($coupon, $base, $totals, $context),
        ])->all();

        usort($rows, fn (array $a, array $b): int => [$b['evaluation']->valid, $b['evaluation']->benefit()->paise] <=> [$a['evaluation']->valid, $a['evaluation']->benefit()->paise]);

        return $rows;
    }

    /**
     * Create or update a coupon from validated form data (admin and seller forms).
     *
     * @param  array<string, mixed>  $data
     */
    public function save(?Coupon $coupon, array $data, ?int $actorId = null): Coupon
    {
        $coupon ??= new Coupon(['created_by' => $actorId]);

        if (array_key_exists('code', $data)) {
            $data['code'] = Coupon::normaliseCode((string) $data['code']);
        }

        foreach (['scope_ids', 'channels', 'payment_methods'] as $list) {
            if (array_key_exists($list, $data)) {
                $values = array_values(array_unique(array_filter((array) $data[$list], fn ($value): bool => $value !== null && $value !== '')));
                $data[$list] = $values === [] ? null : ($list === 'scope_ids' ? array_map('intval', $values) : array_map('strval', $values));
            }
        }

        if (($data['scope'] ?? $coupon->scope) === Coupon::SCOPE_ALL) {
            $data['scope_ids'] = null;
        }

        $coupon->fill($data)->save();

        return $coupon;
    }

    /**
     * A coupon that was ever used stays for the order history; disable it instead.
     *
     * @throws CouponException
     */
    public function delete(Coupon $coupon): void
    {
        if ($coupon->usages()->exists()) {
            throw new CouponException(trans('promotion::coupon.messages.in_use'), 'in_use');
        }

        $coupon->delete();
    }

    /**
     * $count single-use coupons sharing $data, with unique codes PREFIX-XXXXXXXX, in one batch.
     *
     * @param  array<string, mixed>  $data
     * @return string the batch id
     */
    public function bulkGenerate(array $data, int $count, string $prefix, ?int $actorId = null): string
    {
        $count = max(1, min((int) config('promotion.bulk.max_count', 1000), $count));
        $prefix = Coupon::normaliseCode($prefix);
        $length = (int) config('promotion.bulk.random_length', 8);
        $length = max(4, min(29 - strlen($prefix), $length));
        $batch = Str::upper(Str::random(4)).Carbon::now()->format('ymdHis');
        $data['usage_limit_total'] ??= 1;
        $data['is_auto'] = config('core.no');

        $codes = [];
        while (count($codes) < $count) {
            $candidates = [];
            for ($i = count($codes); $i < $count; $i++) {
                $candidates[] = ($prefix !== '' ? $prefix.'-' : '').$this->randomCode($length);
            }
            $taken = Coupon::query()->whereIn('code', $candidates)->pluck('code')->all();
            $codes = array_values(array_unique(array_merge($codes, array_diff($candidates, $taken))));
        }

        DB::transaction(function () use ($codes, $data, $batch, $actorId): void {
            foreach ($codes as $code) {
                $this->save(null, ['code' => $code, 'batch' => $batch] + $data, $actorId);
            }
        });

        return $batch;
    }

    protected function randomCode(int $length): string
    {
        $code = '';
        $max = strlen(self::CODE_ALPHABET) - 1;

        for ($i = 0; $i < $length; $i++) {
            $code .= self::CODE_ALPHABET[random_int(0, $max)];
        }

        return $code;
    }

    /**
     * Item discount + delivery waived of a promotion row.
     */
    protected function saving(TotalsAdjustment $row): Money
    {
        return Money::paise(-$row->amount->paise)->plus(PromotionTotals::deliveryWaived($row));
    }

    /**
     * The snapshot without its coupon code, to price the cart as it is before the coupon.
     */
    protected function withoutCoupon(CartSnapshot $snapshot): CartSnapshot
    {
        if ($snapshot->couponCode === null) {
            return $snapshot;
        }

        return new CartSnapshot($snapshot->cartId, $snapshot->type, $snapshot->groups, $snapshot->unavailable, $snapshot->savedForLater, $snapshot->deliveryPincode, null, $snapshot->customerId);
    }
}
