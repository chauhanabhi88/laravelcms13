<?php

declare(strict_types=1);

namespace Modules\Seller\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Modules\Seller\Events\RazorpayXWebhookReceived;
use Modules\Seller\Services\BankAccountService;
use Throwable;

/**
 * RazorpayX webhooks: fund_account.validation.completed / .failed settle a penny drop.
 *
 * The X-Razorpay-Signature header is the HMAC-SHA256 of the raw body with the webhook
 * secret. Other events are acknowledged and ignored; repeated deliveries are no-ops
 * because only pending validations are updated.
 */
class RazorpayXController extends Controller
{
    private const EVENTS = ['fund_account.validation.completed', 'fund_account.validation.failed'];

    public function handle(Request $request, BankAccountService $bankAccounts): JsonResponse
    {
        $secret = (string) config('seller.razorpayx.webhook_secret');
        $signature = (string) $request->header('X-Razorpay-Signature');

        if ($secret === '' || $signature === '' || ! hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature)) {
            Log::channel('integrations')->warning('razorpayx webhook rejected: bad signature', ['ip' => $request->ip()]);

            return response()->json(['success' => false, 'message' => 'Invalid signature.'], 401);
        }

        $event = (string) $request->json('event');

        if (! in_array($event, self::EVENTS, true)) {
            return $this->forward($request, $event);
        }

        // The payload key is literally "fund_account.validation", so no dot-notation lookup.
        $payload = (array) $request->json('payload', []);
        $validation = (array) ($payload['fund_account.validation']['entity'] ?? $payload['fund_account']['validation']['entity'] ?? []);
        $account = $bankAccounts->applyValidationWebhook($validation);

        Log::channel('integrations')->info('razorpayx webhook '.$event, [
            'event_id' => $request->header('X-Razorpay-Event-Id'),
            'validation_id' => $validation['id'] ?? null,
            'bank_account_id' => $account?->id,
            'status' => $account?->verification_status,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Other signed events (payout.* for Finance, M17) go to whichever module listens; with no
     * listener they are acknowledged and ignored. A listener failure answers 500 so RazorpayX
     * delivers the event again.
     */
    private function forward(Request $request, string $event): JsonResponse
    {
        $prefix = explode('.', $event, 2)[0];
        $payload = (array) $request->json('payload', []);
        $entity = (array) ($payload[$prefix]['entity'] ?? []);

        if ($event === '' || $entity === [] || ! Event::hasListeners(RazorpayXWebhookReceived::class)) {
            return response()->json(['success' => true, 'message' => 'Ignored.']);
        }

        try {
            RazorpayXWebhookReceived::dispatch($event, $entity, $request->header('X-Razorpay-Event-Id'));
        } catch (Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Processing failed.'], 500);
        }

        Log::channel('integrations')->info('razorpayx webhook '.$event, ['event_id' => $request->header('X-Razorpay-Event-Id'), 'entity_id' => $entity['id'] ?? null]);

        return response()->json(['success' => true]);
    }
}
