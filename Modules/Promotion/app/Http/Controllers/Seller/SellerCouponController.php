<?php

declare(strict_types=1);

namespace Modules\Promotion\Http\Controllers\Seller;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Product\Models\Product;
use Modules\Promotion\Http\Requests\SellerCouponRequest;
use Modules\Promotion\Models\Coupon;
use Modules\Promotion\Services\CouponService;
use Modules\Seller\Http\Controllers\Seller\SellerPanelController;

/**
 * Supplier coupons (plan M18 §6 "Seller (S)"): the seller's own coupons, funded by the seller
 * (the discount lowers their sale credit) and limited to all of their products or chosen ones.
 * Another seller's coupon answers 404.
 */
class SellerCouponController extends SellerPanelController
{
    public function __construct(private readonly CouponService $coupons) {}

    public function index(Request $request): View
    {
        $seller = $this->seller();
        $coupons = Coupon::query()
            ->forSeller($seller->id)
            ->orderByDesc('id')
            ->paginate((int) config('promotion.seller_per_page', 20))
            ->withQueryString();

        return view('promotion::seller.coupon.index', ['seller' => $seller, 'coupons' => $coupons]);
    }

    public function create(): View
    {
        return view('promotion::seller.coupon.form', ['coupon' => null] + $this->formOptions());
    }

    public function store(SellerCouponRequest $request): RedirectResponse
    {
        $data = $request->couponData() + ['status' => (int) config('core.enabled')];
        $this->coupons->save(null, $data);

        return redirect()->to(frontRoute('seller.coupon.index'))->with('success', trans('promotion::seller.messages.created'));
    }

    public function edit(Request $request): View
    {
        $coupon = $this->ownCoupon($request);

        return view('promotion::seller.coupon.form', ['coupon' => $coupon] + $this->formOptions());
    }

    public function update(SellerCouponRequest $request): RedirectResponse
    {
        $coupon = $this->ownCoupon($request);
        $this->coupons->save($coupon, $request->couponData());

        return redirect()->to(frontRoute('seller.coupon.index'))->with('success', trans('promotion::seller.messages.updated'));
    }

    public function toggle(Request $request): RedirectResponse
    {
        $coupon = $this->ownCoupon($request);
        $enabled = ! $coupon->isEnabled();
        $this->coupons->save($coupon, ['status' => $enabled ? (int) config('core.enabled') : (int) config('core.disabled')]);

        return redirect()->to(frontRoute('seller.coupon.index'))->with('success', trans($enabled ? 'promotion::seller.messages.enabled' : 'promotion::seller.messages.disabled'));
    }

    protected function ownCoupon(Request $request): Coupon
    {
        $coupon = Coupon::query()->findOrFail((int) $request->route('id'));
        $this->authorizeOwned($coupon);

        return $coupon;
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        $products = Product::query()
            ->with('translations')
            ->where('seller_id', $this->seller()->id)
            ->orderByDesc('id')
            ->limit(500)
            ->get(['id', 'slug', 'seller_id']);

        return [
            'productOptions' => $products->mapWithKeys(fn (Product $product): array => [$product->id => (string) ($product->name ?? $product->slug)])->all(),
        ];
    }
}
