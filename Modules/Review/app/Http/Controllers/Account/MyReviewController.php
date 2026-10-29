<?php

declare(strict_types=1);

namespace Modules\Review\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Customer\Models\Customer;
use Modules\Order\Models\SalesOrderItem;
use Modules\Review\Exceptions\ReviewException;
use Modules\Review\Http\Requests\ReviewFormRequest;
use Modules\Review\Models\ProductReview;
use Modules\Review\Services\ReviewEligibilityService;
use Modules\Review\Services\ReviewService;
use Modules\Review\Support\ReviewSettings;
use Modules\Storefront\Support\StorefrontUrls;

/**
 * The customer's reviews (FR-REV-001, plan M16 §6): "To review" (delivered items without a
 * review) and "My reviews" tabs; the write form for an order item (stars prefilled from
 * ?rating= on the order page) and the edit form within the edit window. Other customers'
 * items and reviews answer 404.
 */
class MyReviewController extends Controller
{
    public const TABS = ['to_review', 'reviewed'];

    public function __construct(
        private readonly ReviewEligibilityService $eligibility,
        private readonly ReviewService $reviews,
    ) {}

    public function index(Request $request): View
    {
        $customer = $this->customer();
        $pendingCount = $this->eligibility->pendingCount($customer);
        $tab = in_array($request->query('tab'), self::TABS, true) ? (string) $request->query('tab') : ($pendingCount > 0 ? 'to_review' : 'reviewed');

        return view('review::frontend.account.index', [
            'tab' => $tab,
            'pendingCount' => $pendingCount,
            'pendingItems' => $tab === 'to_review' ? $this->eligibility->pendingItems($customer) : collect(),
            'reviews' => $tab === 'reviewed'
                ? ProductReview::query()
                    ->forCustomer($customer->id)
                    ->with(['images', 'orderItem'])
                    ->orderByDesc('id')
                    ->paginate((int) config('review.customer_per_page', 10))
                    ->withQueryString()
                : null,
            'reviewCount' => ProductReview::query()->forCustomer($customer->id)->count(),
            'editWindowDays' => ReviewSettings::editWindowDays(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $customer = $this->customer();
        $item = $this->ownItem($request, $customer);

        if (($reason = $this->eligibility->reason($item, $customer)) !== null) {
            return $this->cannotReview($item, $reason);
        }

        $rating = $request->integer('rating');

        return view('review::frontend.account.review-form', $this->formData($item) + [
            'review' => null,
            'rating' => $rating >= 1 && $rating <= 5 ? $rating : null,
            'action' => frontRoute('customer.reviews.store', ['orderItem' => $item->id]),
        ]);
    }

    public function store(ReviewFormRequest $request): RedirectResponse
    {
        $customer = $this->customer();
        $item = $this->ownItem($request, $customer);

        try {
            $review = $this->reviews->create($customer, $item, $request->submission());
        } catch (ReviewException $e) {
            if ($e->errors !== []) {
                throw ValidationException::withMessages($e->errors);
            }

            return $this->cannotReview($item, $e->reasonCode);
        }

        return redirect(frontRoute('customer.reviews.index', [['tab' => 'reviewed']]))
            ->with('success', trans($review->isPublished() ? 'review::front.messages.published' : 'review::front.messages.pending'));
    }

    public function edit(Request $request): View|RedirectResponse
    {
        $customer = $this->customer();
        $review = $this->ownReview($request, $customer);

        if (($reason = $this->eligibility->editReason($review, $customer)) !== null) {
            return redirect(frontRoute('customer.reviews.index', [['tab' => 'reviewed']]))->with('error', trans('review::front.errors.'.$reason));
        }

        return view('review::frontend.account.review-form', $this->formData($review->orderItem) + [
            'review' => $review,
            'rating' => $review->rating,
            'action' => frontRoute('customer.reviews.update', ['review' => $review->id]),
        ]);
    }

    public function update(ReviewFormRequest $request): RedirectResponse
    {
        $customer = $this->customer();
        $review = $this->ownReview($request, $customer);

        try {
            $review = $this->reviews->update($review, $customer, $request->submission(), $request->removeImageIds());
        } catch (ReviewException $e) {
            if ($e->errors !== []) {
                throw ValidationException::withMessages($e->errors);
            }

            return redirect(frontRoute('customer.reviews.index', [['tab' => 'reviewed']]))->with('error', $e->getMessage());
        }

        return redirect(frontRoute('customer.reviews.index', [['tab' => 'reviewed']]))
            ->with('success', trans($review->isPublished() ? 'review::front.messages.updated' : 'review::front.messages.pending'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function formData(SalesOrderItem $item): array
    {
        $item->loadMissing(['salesOrder', 'product']);
        $product = $item->product;

        return [
            'item' => $item,
            'productUrl' => $product !== null && ! $product->trashed() ? StorefrontUrls::product($product->slug, $product->id) : null,
            'image' => StorefrontUrls::productImage($item->image, 'thumb'),
            'maxImages' => ReviewSettings::maxImages(),
            'minBodyLength' => ReviewSettings::minBodyLength(),
            'maxBodyLength' => (int) config('review.body_max_length', 2000),
            'maxTitleLength' => (int) config('review.title_max_length', 120),
        ];
    }

    protected function cannotReview(SalesOrderItem $item, string $reason): RedirectResponse
    {
        if ($reason === 'already_reviewed') {
            $review = ProductReview::query()->where('sales_order_item_id', $item->id)->first(['id']);

            if ($review !== null) {
                return redirect(frontRoute('customer.reviews.edit', ['review' => $review->id]))->with('error', trans('review::front.errors.already_reviewed'));
            }
        }

        return redirect(frontRoute('customer.reviews.index'))->with('error', trans('review::front.errors.'.$reason));
    }

    /**
     * The {orderItem} route parameter as an item of the customer's orders, else 404.
     */
    protected function ownItem(Request $request, Customer $customer): SalesOrderItem
    {
        $item = SalesOrderItem::query()->with(['salesOrder', 'sellerOrder'])->find((int) $request->route('orderItem'));

        abort_if($item === null || $item->salesOrder === null || (int) $item->salesOrder->customer_id !== (int) $customer->id, 404);

        return $item;
    }

    protected function ownReview(Request $request, Customer $customer): ProductReview
    {
        $review = ProductReview::query()->forCustomer($customer->id)->with(['images', 'orderItem'])->find((int) $request->route('review'));

        abort_if($review === null, 404);

        return $review;
    }

    protected function customer(): Customer
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        return $customer;
    }
}
