<?php

declare(strict_types=1);

namespace Modules\Order\Http\Controllers\Seller;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\Order\Http\Requests\Seller\OrderSettingsRequest;
use Modules\Order\Models\LabelBatch;
use Modules\Order\Support\OrderSettings;
use Modules\Seller\Http\Controllers\Seller\SellerPanelController;

/**
 * Supplier panel > Orders > Settings (plan M14 §6): auto-accept new orders and the paper of
 * packing slips (blank = the platform default from admin > Settings > Order).
 */
class OrderSettingsController extends SellerPanelController
{
    public function edit(): View
    {
        $seller = $this->seller();

        return view('order::seller.settings.orders', [
            'seller' => $seller,
            'sellerStoreName' => $seller->displayName(),
            'formats' => array_combine(LabelBatch::FORMATS, array_map(fn (string $format): string => trans('order::fulfilment.label_formats.'.$format), LabelBatch::FORMATS)),
            'defaultFormat' => OrderSettings::labelFormat(),
        ]);
    }

    public function update(OrderSettingsRequest $request): RedirectResponse
    {
        $this->seller()->forceFill([
            'auto_accept_orders' => (int) $request->validated('auto_accept_orders'),
            'label_format' => $request->validated('label_format') ?: null,
        ])->save();

        return redirect()->to(frontRoute('seller.settings.orders'))->with('success', trans('order::fulfilment.settings.saved'));
    }
}
