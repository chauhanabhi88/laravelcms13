<?php

declare(strict_types=1);

namespace Modules\Review\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Customer\Models\Customer;
use Modules\Review\Exceptions\ReviewException;
use Modules\Review\Models\ProductReview;
use Modules\Review\Services\ReviewFeedbackService;

/**
 * "Helpful" toggle on a published review (FR-REV-004): JSON for the storefront script, a
 * redirect back otherwise.
 */
class ReviewVoteController extends Controller
{
    public function toggle(Request $request, ReviewFeedbackService $feedback): JsonResponse|RedirectResponse
    {
        /** @var Customer $customer */
        $customer = $request->user('customer');
        $review = ProductReview::query()->published()->find((int) $request->route('id'));

        abort_if($review === null, 404);

        try {
            $result = $feedback->toggleVote($review, $customer);
        } catch (ReviewException $e) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $e->getMessage(), 'reason' => $e->reasonCode], 422)
                : back()->with('error', $e->getMessage());
        }

        $message = trans($result['voted'] ? 'review::front.messages.voted' : 'review::front.messages.unvoted');

        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => $message] + $result)
            : back()->with('success', $message);
    }
}
