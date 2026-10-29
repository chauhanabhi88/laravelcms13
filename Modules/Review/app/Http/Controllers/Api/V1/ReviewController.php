<?php

declare(strict_types=1);

namespace Modules\Review\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Customer\Models\Customer;
use Modules\Order\Models\SalesOrderItem;
use Modules\Product\Models\Product;
use Modules\Review\Exceptions\ReviewException;
use Modules\Review\Http\Requests\Api\StoreReviewRequest;
use Modules\Review\Models\ProductReview;
use Modules\Review\Services\ReviewEligibilityService;
use Modules\Review\Services\ReviewFeedbackService;
use Modules\Review\Services\ReviewListService;
use Modules\Review\Services\ReviewService;
use Modules\Review\Transformers\ReviewResource;
use Modules\Storefront\Contracts\ReviewSummaryProvider;
use Modules\Storefront\Support\StorefrontUrls;

/**
 * Reviews for the mobile apps (plan M16 §6): a product's published reviews with the summary,
 * filters and sorts (public); the customer's own reviews and items waiting for one; write a
 * review (multipart with photos[]); toggle "Helpful". Envelope: {success, code, message, data, meta}.
 */
class ReviewController extends Controller
{
    public function index(Request $request, ReviewListService $list, ReviewSummaryProvider $summaries): JsonResponse
    {
        $product = Product::query()->find((int) $request->route('id'));

        abort_if($product === null || ! $product->isVisibleOnStorefront(), 404);

        $filters = $list->filters($request);
        $reviews = $list->forProduct($product->id, $filters, $request->integer('per_page') ?: null);
        $customer = Auth::guard('api')->user();
        $votedIds = $list->votedIds($customer instanceof Customer ? $customer : null, $reviews->getCollection()->modelKeys());

        return response()->json([
            'success' => true,
            'code' => '200',
            'message' => null,
            'data' => [
                'summary' => $summaries->forProduct($product->id)->toArray(),
                'filters' => $filters,
                'reviews' => $reviews->getCollection()->map(fn (ProductReview $review): array => (new ReviewResource($review, $votedIds))->resolve($request))->all(),
            ],
            'meta' => $this->meta($reviews),
        ]);
    }

    public function mine(Request $request, ReviewEligibilityService $eligibility): JsonResponse
    {
        $customer = $this->customer($request);
        $reviews = ProductReview::query()->forCustomer($customer->id)->with('images')->orderByDesc('id')
            ->paginate(min(50, max(1, $request->integer('per_page') ?: 15)));

        return response()->json([
            'success' => true,
            'code' => '200',
            'message' => null,
            'data' => [
                'to_review' => $eligibility->pendingItems($customer)->map(fn (SalesOrderItem $item): array => [
                    'order_item_id' => $item->id,
                    'order_no' => $item->salesOrder?->order_no,
                    'product_id' => $item->product_id,
                    'name' => $item->name,
                    'variant' => $item->variant_label,
                    'image' => StorefrontUrls::productImage($item->image, 'thumb'),
                    'delivered_at' => $item->sellerOrder?->delivered_at?->toIso8601String(),
                ])->values()->all(),
                'reviews' => $reviews->getCollection()->map(fn (ProductReview $review): array => (new ReviewResource($review, [], true))->resolve($request))->all(),
            ],
            'meta' => $this->meta($reviews),
        ]);
    }

    public function store(StoreReviewRequest $request, ReviewService $service): JsonResponse
    {
        $customer = $this->customer($request);
        $item = SalesOrderItem::query()->with(['salesOrder', 'sellerOrder'])->find((int) $request->validated('order_item_id'));

        if ($item === null || $item->salesOrder === null || (int) $item->salesOrder->customer_id !== (int) $customer->id) {
            return response()->json(['success' => false, 'code' => '404', 'message' => trans('review::front.errors.not_found')], 404);
        }

        try {
            $review = $service->create($customer, $item, $request->submission());
        } catch (ReviewException $e) {
            return response()->json(['success' => false, 'code' => '422', 'message' => $e->getMessage(), 'errors' => $e->errors + ['reason' => $e->reasonCode]], 422);
        }

        return response()->json([
            'success' => true,
            'code' => '201',
            'message' => trans($review->isPublished() ? 'review::front.messages.published' : 'review::front.messages.pending'),
            'data' => (new ReviewResource($review->load('images'), [], true))->resolve($request),
        ], 201);
    }

    public function vote(Request $request, ReviewFeedbackService $feedback): JsonResponse
    {
        $review = ProductReview::query()->published()->find((int) $request->route('id'));

        if ($review === null) {
            return response()->json(['success' => false, 'code' => '404', 'message' => trans('review::front.errors.not_found')], 404);
        }

        try {
            $result = $feedback->toggleVote($review, $this->customer($request));
        } catch (ReviewException $e) {
            return response()->json(['success' => false, 'code' => '422', 'message' => $e->getMessage(), 'errors' => ['reason' => $e->reasonCode]], 422);
        }

        return response()->json([
            'success' => true,
            'code' => '200',
            'message' => trans($result['voted'] ? 'review::front.messages.voted' : 'review::front.messages.unvoted'),
            'data' => $result,
        ]);
    }

    /**
     * @param  LengthAwarePaginator<int, ProductReview>  $paginator
     * @return array{current_page: int, last_page: int, per_page: int, total: int}
     */
    protected function meta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    protected function customer(Request $request): Customer
    {
        /** @var Customer $customer */
        $customer = $request->user();

        return $customer;
    }
}
