<?php

declare(strict_types=1);

namespace Modules\Order\Http\Controllers\Seller;

use Illuminate\Http\RedirectResponse;
use Modules\Order\Exceptions\OrderException;
use Modules\Order\Http\Requests\Seller\ReturnReceiptRequest;
use Modules\Order\Services\OrderFulfillmentService;
use Modules\Seller\Http\Controllers\Seller\SellerPanelController;

/**
 * The seller confirms an RTO package back at the warehouse (plan M14 §5.7): good → restocked;
 * damaged / wrong item → claim with note and photos (handed to Returns, M15).
 */
class ReturnReceiptController extends SellerPanelController
{
    public function __construct(private readonly OrderFulfillmentService $fulfilment) {}

    public function store(ReturnReceiptRequest $request): RedirectResponse
    {
        try {
            $sellerOrder = $this->fulfilment->confirmReturnReceipt(
                $request->sellerOrder(),
                (string) $request->validated('condition'),
                $request->validated('note'),
                $request->photos(),
                $this->sellerUser(),
            );
        } catch (OrderException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', trans($sellerOrder->receipt_condition === 'good' ? 'order::fulfilment.messages.receipt_restocked' : 'order::fulfilment.messages.receipt_claim', ['order' => $sellerOrder->sub_order_no]));
    }
}
