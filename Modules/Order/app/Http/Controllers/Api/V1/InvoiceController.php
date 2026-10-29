<?php

declare(strict_types=1);

namespace Modules\Order\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Modules\Core\Http\Controllers\Api\ApiController;
use Modules\Customer\Models\Customer;
use Modules\Order\Http\Controllers\Concerns\ResolvesOwnOrder;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Services\OrderFulfillmentService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tax invoices for the mobile apps (plan M20 §4): the authenticated link call answers with a
 * short-lived signed URL, which the app opens or downloads without sending the token (a PDF
 * viewer cannot). Another customer's order, and a package without an invoice yet, are 404s.
 */
class InvoiceController extends ApiController
{
    use ResolvesOwnOrder;

    private const LINK_MINUTES = 10;

    public function __construct(private readonly OrderFulfillmentService $fulfilment) {}

    public function link(Request $request): JsonResponse
    {
        $customer = $request->user();
        $order = $this->ownOrder($request, $customer instanceof Customer ? $customer : null);
        $sellerOrder = $this->sellerOrder($order, (int) $request->route('sellerOrder'));

        if ($sellerOrder === null || $this->fulfilment->invoicePath($sellerOrder) === null) {
            return $this->fail(404, trans('core::core.messages.not_found'));
        }

        $expiresAt = now()->addMinutes(self::LINK_MINUTES);

        return $this->ok([
            'url' => URL::temporarySignedRoute('order_api.orders.invoice.download', $expiresAt, ['orderNo' => $order->order_no, 'sellerOrder' => $sellerOrder->id]),
            'filename' => $this->filename($sellerOrder),
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    /**
     * The PDF itself; the signature on the URL is the authorisation.
     */
    public function download(Request $request): StreamedResponse|JsonResponse
    {
        $order = SalesOrder::query()->where('order_no', (string) $request->route('orderNo'))->first();
        $sellerOrder = $order === null ? null : $this->sellerOrder($order, (int) $request->route('sellerOrder'));
        $path = $sellerOrder === null ? null : $this->fulfilment->invoicePath($sellerOrder);

        if ($sellerOrder === null || $path === null) {
            return $this->fail(404, trans('core::core.messages.not_found'));
        }

        return $this->fulfilment->disk()->download($path, $this->filename($sellerOrder));
    }

    protected function sellerOrder(SalesOrder $order, int $id): ?SellerOrder
    {
        return SellerOrder::query()->where('sales_order_id', $order->id)->find($id);
    }

    protected function filename(SellerOrder $sellerOrder): string
    {
        return str_replace('/', '-', (string) $sellerOrder->invoice_no).'.pdf';
    }
}
