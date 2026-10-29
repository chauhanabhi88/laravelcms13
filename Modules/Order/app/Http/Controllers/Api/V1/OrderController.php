<?php

declare(strict_types=1);

namespace Modules\Order\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Api\ApiController;
use Modules\Customer\Models\Customer;
use Modules\Order\Exceptions\OrderException;
use Modules\Order\Http\Controllers\Concerns\ResolvesOwnOrder;
use Modules\Order\Http\Requests\CancelOrderRequest;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Services\OrderCancellationService;
use Modules\Order\Transformers\OrderResource;

/**
 * The customer's orders for the mobile apps: list, detail and cancellation before shipment.
 * Another customer's order is a 404. The list is page-based; send ?cursor= (empty for the first
 * page) to page by cursor instead - stable while new orders arrive.
 */
class OrderController extends ApiController
{
    use ResolvesOwnOrder;

    private const RELATIONS = ['shippingAddress', 'sellerOrders.seller', 'sellerOrders.items'];

    public function __construct(private readonly OrderCancellationService $cancellations) {}

    public function index(Request $request): JsonResponse
    {
        $query = SalesOrder::query()
            ->forCustomer($this->customer($request)->id)
            ->with(self::RELATIONS)
            ->orderByDesc('placed_at')
            ->orderByDesc('id');

        if ($request->has('cursor')) {
            $total = (clone $query)->toBase()->getCountForPagination();
            $orders = $query->cursorPaginate($this->perPage());

            return $this->ok(
                OrderResource::collection($orders->getCollection())->resolve($request),
                ['total' => $total] + $this->pagination($orders),
            );
        }

        $orders = $query->paginate($this->perPage());

        return $this->ok(
            OrderResource::collection($orders->getCollection())->resolve($request),
            [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        );
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
            return $this->failReason($e->reasonCode, $e->getMessage());
        }

        return $this->order($request, $order->refresh()->load(self::RELATIONS), trans_choice('order::front.cancel.done', $count, ['count' => $count]));
    }

    protected function order(Request $request, SalesOrder $order, ?string $message): JsonResponse
    {
        return $this->ok([
            'order' => (new OrderResource($order))->resolve($request),
            'cancellable_item_ids' => $this->cancellations->cancellableItemIds($order),
        ], message: $message);
    }

    protected function customer(Request $request): Customer
    {
        /** @var Customer $customer */
        $customer = $request->user();

        return $customer;
    }
}
