<?php

declare(strict_types=1);

namespace Modules\Returns\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Api\ApiController;
use Modules\Customer\Models\Customer;
use Modules\Order\Http\Controllers\Concerns\ResolvesOwnOrder;
use Modules\Returns\DTO\ReturnableItem;
use Modules\Returns\DTO\ReturnSubmission;
use Modules\Returns\Exceptions\ReturnException;
use Modules\Returns\Http\Requests\Api\StoreReturnRequest;
use Modules\Returns\Models\ReturnRequest;
use Modules\Returns\Services\ReturnEligibilityService;
use Modules\Returns\Services\ReturnService;
use Modules\Returns\Support\ReturnPhotoStore;
use Modules\Returns\Support\Validation\RefundDetailsRules;
use Modules\Returns\Transformers\ReturnResource;

/**
 * Returns for the mobile apps (plan M15 §6): returnable items of an order, raise a return
 * (multipart with photos[]), list, detail and cancel before pickup. Other customers' orders
 * and returns answer 404.
 */
class ReturnController extends ApiController
{
    use ResolvesOwnOrder;

    private const RELATIONS = ['items.orderItem', 'images', 'sellerOrder', 'reverseShipment'];

    public function __construct(
        private readonly ReturnEligibilityService $eligibility,
        private readonly ReturnService $returns,
        private readonly ReturnPhotoStore $photos,
    ) {}

    public function returnableItems(Request $request): JsonResponse
    {
        $customer = $this->customer($request);
        $order = $this->ownOrder($request, $customer, ['sellerOrders.items']);
        $packages = [];

        foreach ($this->eligibility->forOrder($order, $customer) as $sellerOrderId => $items) {
            $packages[] = [
                'seller_order_id' => $sellerOrderId,
                'order_no' => $order->sellerOrders->firstWhere('id', $sellerOrderId)?->sub_order_no,
                'items' => $items->map(fn (ReturnableItem $item): array => $item->toArray())->values()->all(),
            ];
        }

        return $this->ok([
            'packages' => $packages,
            'reasons' => (array) config('returns.reasons', []),
            'photo_required_reasons' => (array) config('returns.photo_required_reasons', []),
            'payment_method' => $order->payment_method,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $returns = ReturnRequest::query()
            ->forCustomer($this->customer($request)->id)
            ->with(self::RELATIONS)
            ->orderByDesc('id')
            ->paginate($this->perPage());

        return $this->ok(
            ReturnResource::collection($returns->getCollection())->resolve($request),
            [
                'current_page' => $returns->currentPage(),
                'last_page' => $returns->lastPage(),
                'per_page' => $returns->perPage(),
                'total' => $returns->total(),
            ],
        );
    }

    public function store(StoreReturnRequest $request): JsonResponse
    {
        $customer = $this->customer($request);
        $validated = $request->validated();
        $stored = [];

        try {
            $stored = $this->photos->storeMany($request->photos(), 'drafts/'.$customer->id);

            $return = $this->returns->create($customer, new ReturnSubmission(
                (int) $validated['seller_order_id'],
                (string) $validated['type'],
                (string) $validated['reason_code'],
                $validated['comment'] ?? null,
                $request->items(),
                $stored,
                $validated['refund_mode'] ?? null,
                RefundDetailsRules::beneficiary($validated),
                isset($validated['pickup_address_id']) ? (int) $validated['pickup_address_id'] : null,
            ));
        } catch (ReturnException $e) {
            foreach ($stored as $photo) {
                $this->photos->delete($photo['path']);
            }

            return $this->failReason($e->reasonCode, $e->getMessage(), $e->apiErrors());
        }

        return $this->single($request, $return->load(self::RELATIONS), trans('returns::front.messages.created', ['return_no' => $return->return_no]), 201);
    }

    public function show(Request $request): JsonResponse
    {
        return $this->single($request, $this->ownReturn($request), null);
    }

    public function cancel(Request $request): JsonResponse
    {
        $return = $this->ownReturn($request);

        try {
            $this->returns->cancel($return, $this->customer($request), trans('returns::front.cancel.reason'));
        } catch (ReturnException $e) {
            return $this->failReason($e->reasonCode, $e->getMessage());
        }

        return $this->single($request, $return->refresh()->load(self::RELATIONS), trans('returns::front.messages.cancelled'));
    }

    protected function single(Request $request, ReturnRequest $return, ?string $message, int $status = 200): JsonResponse
    {
        return $this->ok((new ReturnResource($return))->resolve($request), message: $message, status: $status);
    }

    protected function ownReturn(Request $request): ReturnRequest
    {
        $return = ReturnRequest::query()
            ->forCustomer($this->customer($request)->id)
            ->with(self::RELATIONS)
            ->where('return_no', (string) $request->route('returnNo'))
            ->first();

        abort_if($return === null, 404);

        return $return;
    }

    protected function customer(Request $request): Customer
    {
        /** @var Customer $customer */
        $customer = $request->user();

        return $customer;
    }
}
