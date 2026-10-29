<?php

declare(strict_types=1);

namespace Modules\Review\Http\Controllers\Backend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Review\Exceptions\ReviewException;
use Modules\Review\Models\ProductReview;
use Modules\Review\Repositories\Eloquent\EloquentProductReviewRepository;
use Modules\Review\Repositories\ProductReviewRepository;
use Modules\Review\Services\ReviewModerationService;
use Modules\Review\Support\ReviewOptions;
use Modules\Review\Support\ReviewSettings;
use Modules\User\Models\User;

/**
 * Review moderation (FR-REV-002, plan M16 §5): the queue of waiting reviews and published ones
 * reported report_threshold times or more (or every review), the detail with photos, checks
 * and reports, and publish (keep) / reject with a reason / hide / delete.
 */
class ProductReviewController extends BackendController
{
    public function __construct(private ProductReviewRepository $reviewRepository)
    {
        parent::__construct();
    }

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('review.cache.product_review'), $request->input('per_page'))]);

            return view('review::backend.product_review.index', $this->gridData($request, getActiveMenuId($request)));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function filters(Request $request): JsonResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('review.cache.product_review'), $request->input('per_page'))]);
            setFilterSession(config('review.cache.product_review'), $request);

            $content = view('review::backend.product_review.partials.grid', $this->gridData($request, getActiveMenuId($request, 'admin.product_review.index')));

            return response()->json([
                'type' => 'success',
                'content' => ['element' => 'collection', 'html' => $content->render()],
                'message' => $request->input('message'),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function show(Request $request): View|RedirectResponse
    {
        try {
            $review = $this->findReview($request);
            $review->load(['images', 'reports.customer', 'orderItem', 'salesOrder', 'customer', 'seller', 'product.translations']);

            return view('review::backend.product_review.show', [
                'review' => $review,
                'statuses' => ReviewOptions::statuses(),
                'flags' => ReviewOptions::flags(),
                'reportReasons' => ReviewOptions::reportReasons(),
                'reportThreshold' => ReviewSettings::reportThreshold(),
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.product_review.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function publish(Request $request, ReviewModerationService $moderation): RedirectResponse
    {
        return $this->act($request, fn (ProductReview $review) => $moderation->publish($review, $this->admin($request)), 'published');
    }

    public function reject(Request $request, ReviewModerationService $moderation): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:250']], [], ['reason' => trans('review::product_review.labels.reason')]);

        return $this->act($request, fn (ProductReview $review) => $moderation->reject($review, (string) $validated['reason'], $this->admin($request)), 'rejected');
    }

    public function hide(Request $request, ReviewModerationService $moderation): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:250']]);

        return $this->act($request, fn (ProductReview $review) => $moderation->hide($review, $validated['reason'] ?? null, $this->admin($request)), 'hidden');
    }

    public function delete(Request $request, ReviewModerationService $moderation): RedirectResponse
    {
        try {
            $moderation->delete($this->findReview($request));

            return redirect()->route('admin.product_review.index', updateUrlParams())->with('success', trans('review::product_review.messages.deleted'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * @param  callable(ProductReview): ProductReview  $action
     */
    private function act(Request $request, callable $action, string $message): RedirectResponse
    {
        try {
            $review = $action($this->findReview($request));

            return redirect()->route('admin.product_review.show', updateUrlParams([$review->id]))
                ->with('success', trans('review::product_review.messages.'.$message));
        } catch (ReviewException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage())->withErrors($e->errors);
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    private function admin(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }

    private function findReview(Request $request): ProductReview
    {
        $review = $this->reviewRepository->find((int) $request->route('id'));

        if (! $review instanceof ProductReview) {
            throw new \RuntimeException(trans('review::product_review.messages.not_found'));
        }

        return $review;
    }

    /**
     * @return array<string, mixed>
     */
    private function gridData(Request $request, mixed $menuId): array
    {
        $options = [
            'queues' => [
                EloquentProductReviewRepository::QUEUE_MODERATION => trans('review::product_review.queues.moderation'),
                EloquentProductReviewRepository::QUEUE_ALL => trans('review::product_review.queues.all'),
            ],
            'statuses' => ReviewOptions::statuses(),
            'ratings' => ReviewOptions::ratings(),
            'flags' => ReviewOptions::flags(),
        ];

        return [
            'request' => $request,
            'activeMenuId' => $menuId,
            'collection' => $this->reviewRepository->pagination($request),
            'columns' => getColumnObject()->getColumns($menuId),
            'filters' => $this->reviewRepository->getFilters($request, $options),
            'reportThreshold' => ReviewSettings::reportThreshold(),
        ] + $options;
    }
}
