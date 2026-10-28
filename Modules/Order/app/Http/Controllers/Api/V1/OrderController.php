<?php

declare(strict_types=1);

namespace Modules\Order\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Customer\Models\Customer;
use Modules\Order\Exceptions\OrderException;
use Modules\Order\Http\Controllers\Concerns\ResolvesOwnOrder;
use Modules\Order\Http\Requests\CancelOrderRequest;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Services\OrderCancellationService;
use Modules\Order\Transformers\OrderResource;

/**
 * The customer's orders for the mobile apps: list, detail and cancellation before shipment.
 * Another customer's order is a 404. Envelope: {success, code, message, data, meta}.
 */
class OrderController extends Controller
{
    use ResolvesOwnOrder;

    private const RELATIONS = ['shippingAddress', 'sellerOrders.seller', 'sellerOrders.items'];

    public function __construct(private readonly OrderCancellationService $cancellations) {}

    public function index(Request $request): JsonResponse
    {
        $orders = SalesOrder::query()
            ->forCustomer($this->customer($request)->id)
            ->with(self::RELATIONS)
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->paginate(min(50, max(1, (int) $request->input('per_page', 15))));

        return response()->json([
            'success' => true,
            'code' => '200',
            'message' => null,
            'data' => OrderResource::collection($orders->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $order = $this->ownOrder($request, $this->customer($request), self::RELATIONS);

        return $this->order($request, $order, null);
    }

    public function cancel(CancelOrderRequest $request): JsonResponse
    {
        $customer = $this->customer($request);
        $order = $this->ownOrder($request, $customer, [], 'cancel');

        try {
            $count = $this->cancellations->cancelForCustomer($order, $request->itemIds(), $request->reasonText(), $customer);
        } catch (OrderException $e) {
            return response()->json(['success' => false, 'code' => '422', 'message' => $e->getMessage(), 'errors' => ['reason' => $e->reasonCode]], 422);
        }

        return $this->order($request, $order->refresh()->load(self::RELATIONS), trans_choice('order::front.cancel.done', $count, ['count' => $count]));
    }

    protected function order(Request $request, SalesOrder $order, ?string $message): JsonResponse
    {
        return response()->json([
            'success' => true,
            'code' => '200',
            'message' => $message,
            'data' => [
                'order' => (new OrderResource($order))->resolve($request),
                'cancellable_item_ids' => $this->cancellations->cancellableItemIds($order),
            ],
        ]);
    }

    protected function customer(Request $request): Customer
    {
        /** @var Customer $customer */
        $customer = $request->user();

        return $customer;
    }
}
