<?php

declare(strict_types=1);

namespace Modules\Review\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Modules\Customer\Models\Customer;
use Modules\Review\Exceptions\ReviewException;
use Modules\Review\Http\Requests\ReportReviewRequest;
use Modules\Review\Models\ProductReview;
use Modules\Review\Services\ReviewFeedbackService;

/**
 * Report abuse on a published review (FR-REV-004): JSON for the storefront script, a redirect
 * back otherwise.
 */
class ReviewReportController extends Controller
{
    public function store(ReportReviewRequest $request, ReviewFeedbackService $feedback): JsonResponse|RedirectResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');
        $review = ProductReview::query()->published()->find((int) $request->route('id'));

        abort_if($review === null, 404);

        try {
            $feedback->report($review, $customer, (string) $request->validated('reason'), $request->validated('comment'));
        } catch (ReviewException $e) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $e->getMessage(), 'reason' => $e->reasonCode], 422)
                : back()->with('error', $e->getMessage());
        }

        $message = trans('review::front.messages.reported');

        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => $message])
            : back()->with('success', $message);
    }
}
