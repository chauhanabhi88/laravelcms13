<?php

declare(strict_types=1);

namespace Modules\Promotion\Http\Controllers\Backend;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Catalog\Models\CatalogBrand;
use Modules\Catalog\Services\CategoryTreeService;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Promotion\Http\Controllers\Backend\Concerns\RendersPromotionGrid;
use Modules\Promotion\Http\Requests\CouponRequest;
use Modules\Promotion\Models\Coupon;
use Modules\Promotion\Models\CouponUsage;
use Modules\Promotion\Services\CouponService;
use Modules\Promotion\Support\PromotionOptions;
use Modules\Seller\Models\Seller;

/**
 * Coupons and automatic offers (FR-PRM-001/002, plan M18 §6): grid, create, edit (with the
 * latest usages), delete (never-used coupons only) and bulk generation of single-use codes.
 */
class CouponController extends BackendController
{
    use RendersPromotionGrid;

    protected const GRID = 'coupon';

    public function __construct(private readonly CouponService $coupons)
    {
        parent::__construct();
    }

    public function create(): View
    {
        return view('promotion::backend.coupon.form', ['coupon' => null, 'usages' => collect()] + $this->formOptions());
    }

    public function store(CouponRequest $request): RedirectResponse
    {
        try {
            $coupon = $this->coupons->save(null, $request->couponData(), $request->user()?->getKey());

            return $this->afterSave($request, $coupon, 'created');
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function edit(Request $request): View|RedirectResponse
    {
        $coupon = Coupon::query()->with('seller')->find((int) $request->route('id'));

        if ($coupon === null) {
            return redirect()->route('admin.coupon.index', updateUrlParams())->with('error', trans('promotion::coupon.messages.not_found'));
        }

        $usages = CouponUsage::query()->where('coupon_id', $coupon->id)->with(['salesOrder:id,order_no', 'customer'])->latest('id')->limit(20)->get();

        return view('promotion::backend.coupon.form', ['coupon' => $coupon, 'usages' => $usages] + $this->formOptions());
    }

    public function update(CouponRequest $request): RedirectResponse
    {
        try {
            $coupon = Coupon::query()->find((int) $request->route('id'));

            if ($coupon === null) {
                throw new \RuntimeException(trans('promotion::coupon.messages.not_found'));
            }

            $this->coupons->save($coupon, $request->couponData());

            return $this->afterSave($request, $coupon, 'updated');
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function delete(Request $request): RedirectResponse
    {
        try {
            $coupon = Coupon::query()->find((int) $request->route('id'));

            if ($coupon !== null) {
                $this->coupons->delete($coupon);
            }

            return redirect()->route('admin.coupon.index', updateUrlParams())->with('success', trans('promotion::coupon.messages.deleted'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function bulk(): View
    {
        return view('promotion::backend.coupon.bulk', $this->formOptions());
    }

    public function bulkGenerate(CouponRequest $request): RedirectResponse
    {
        try {
            $batch = $this->coupons->bulkGenerate($request->couponData(), (int) $request->validated('count'), (string) $request->validated('prefix'), $request->user()?->getKey());

            return redirect()->route('admin.coupon.index', updateUrlParams())
                ->with('success', trans('promotion::coupon.messages.bulk_generated', ['count' => (int) $request->validated('count'), 'batch' => $batch]));
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return [
            'typeOptions' => PromotionOptions::types(),
            'scopeOptions' => PromotionOptions::scopes(),
            'fundingOptions' => PromotionOptions::funding(),
            'channelOptions' => PromotionOptions::channels(),
            'paymentOptions' => PromotionOptions::paymentMethods(),
            'categoryOptions' => app(CategoryTreeService::class)->options(),
            'brandOptions' => CatalogBrand::query()->with('translations')->orderBy('slug')->limit(2000)->get()
                ->mapWithKeys(fn (CatalogBrand $brand): array => [$brand->id => (string) ($brand->name ?? $brand->slug)])
                ->all(),
            'sellerOptions' => Seller::query()->orderBy('store_name')->limit(1000)->get(['id', 'code', 'store_name', 'legal_name'])
                ->mapWithKeys(fn (Seller $seller): array => [$seller->id => $seller->code.' · '.$seller->displayName()])
                ->all(),
        ];
    }

    protected function afterSave(Request $request, Coupon $coupon, string $action): RedirectResponse
    {
        $route = $request->input('snc') ? 'admin.coupon.edit' : 'admin.coupon.index';
        $params = $request->input('snc') ? updateUrlParams([$coupon->id]) : updateUrlParams();

        return redirect()->route($route, $params)->with('success', trans('promotion::coupon.messages.'.$action));
    }
}
