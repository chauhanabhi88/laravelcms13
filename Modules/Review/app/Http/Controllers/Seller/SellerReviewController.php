<?php

declare(strict_types=1);

namespace Modules\Review\Http\Controllers\Seller;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Review\Exceptions\ReviewException;
use Modules\Review\Models\ProductReview;
use Modules\Review\Services\SellerReplyService;
use Modules\Seller\Http\Controllers\Seller\SellerPanelController;

/**
 * Supplier reviews (FR-REV-006): published reviews of the seller's products with their rating
 * summary, filters (stars, replied or not, product search) and a one-time public reply per
 * review. Another seller's review answers 404.
 */
class SellerReviewController extends SellerPanelController
{
    public const REPLY_FILTERS = ['unreplied', 'replied'];

    public function __construct(private readonly SellerReplyService $replies) {}

    public function index(Request $request): View
    {
        $seller = $this->seller();
        $rating = $request->integer('rating');
        $rating = $rating >= 1 && $rating <= 5 ? $rating : null;
        $replied = in_array($request->query('replied'), self::REPLY_FILTERS, true) ? (string) $request->query('replied') : null;
        $search = trim((string) $request->query('search', ''));

        $reviews = ProductReview::query()
            ->forSeller($seller->id)
            ->published()
            ->with(['images', 'orderItem'])
            ->when($rating !== null, fn ($query) => $query->where('rating', $rating))
            ->when($replied === 'replied', fn ($query) => $query->whereNotNull('seller_reply'))
            ->when($replied === 'unreplied', fn ($query) => $query->whereNull('seller_reply'))
            ->when($search !== '', fn ($query) => $query->whereHas('orderItem', fn ($item) => $item
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('seller_sku', 'like', '%'.$search.'%')))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate((int) config('review.seller_per_page', 20))
            ->withQueryString();

        $counts = ProductReview::query()->forSeller($seller->id)->published()->toBase()
            ->selectRaw('rating, COUNT(*) as total')->groupBy('rating')->pluck('total', 'rating');

        return view('review::seller.review.index', [
            'seller' => $seller,
            'reviews' => $reviews,
            'histogram' => collect([5, 4, 3, 2, 1])->mapWithKeys(fn (int $stars): array => [$stars => (int) ($counts[$stars] ?? 0)])->all(),
            'unrepliedCount' => ProductReview::query()->forSeller($seller->id)->published()->whereNull('seller_reply')->count(),
            'rating' => $rating,
            'replied' => $replied,
            'search' => $search,
        ]);
    }

    public function reply(Request $request): RedirectResponse
    {
        // Tenancy before validation: another seller's review is a 404, not a form error.
        $review = ProductReview::query()->find((int) $request->route('id'));
        abort_if($review === null, 404);
        $this->authorizeOwned($review);

        // The page has one reply form per review; review_id tells the view which one failed.
        $request->merge(['review_id' => $review->id]);
        $validated = $request->validate([
            'reply' => ['required', 'string', 'min:'.(int) config('review.reply_min_length', 2), 'max:'.(int) config('review.reply_max_length', 1000)],
        ], [], ['reply' => trans('review::seller.reply.label')]);

        try {
            $this->replies->reply($review, $this->sellerUser(), (string) $validated['reply']);
        } catch (ReviewException $e) {
            if ($e->errors !== []) {
                throw ValidationException::withMessages(['reply' => $e->errors['reply'] ?? [$e->getMessage()]]);
            }

            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', trans('review::seller.messages.replied'));
    }
}
