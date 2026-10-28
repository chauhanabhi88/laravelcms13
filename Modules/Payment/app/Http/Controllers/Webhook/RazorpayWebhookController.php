<?php

declare(strict_types=1);

namespace Modules\Payment\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Payment\Jobs\ProcessWebhookEvent;
use Modules\Payment\Models\WebhookEvent;
use Modules\Payment\Support\RazorpaySignature;
use Throwable;

/**
 * POST /webhooks/razorpay (plan M11 §5.2). Answers fast and does the work in a queued job:
 *
 * 1. X-Razorpay-Signature must be the HMAC-SHA256 of the raw body with the webhook secret;
 *    otherwise 400, and a row with signature_valid = 2 (no payload kept, and a namespaced
 *    event id so a forged request can never take a real event's id).
 * 2. insertOrIgnore on (provider, event_id) from X-Razorpay-Event-Id: a redelivery is a 200
 *    with no work.
 * 3. ProcessWebhookEvent is dispatched; a failure there is stored on the row and never
 *    turns into a non-200 (Razorpay would keep redelivering).
 */
class RazorpayWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $body = $request->getContent();
        $payload = json_decode($body, true);
        $eventType = is_array($payload) && is_string($payload['event'] ?? null) ? mb_substr($payload['event'], 0, 60) : null;

        if (! RazorpaySignature::verifyWebhook($body, (string) $request->header('X-Razorpay-Signature'), (string) config('payment.razorpay.webhook_secret'))) {
            $this->recordInvalid($eventType);
            Log::channel('integrations')->warning('razorpay webhook rejected: bad signature', ['ip' => $request->ip(), 'event' => $eventType]);

            return response()->json(['success' => false, 'message' => 'Invalid signature.'], 400);
        }

        if (! is_array($payload)) {
            return response()->json(['success' => false, 'message' => 'Invalid payload.'], 400);
        }

        $eventId = trim((string) $request->header('X-Razorpay-Event-Id'));
        $eventId = $eventId !== '' ? mb_substr($eventId, 0, 80) : 'body:'.hash('sha256', $body);

        $inserted = DB::table('webhook_event')->insertOrIgnore([
            'provider' => WebhookEvent::PROVIDER_RAZORPAY,
            'event_id' => $eventId,
            'event_type' => $eventType,
            'signature_valid' => (int) config('core.yes'),
            'payload' => json_encode($payload),
            'status' => WebhookEvent::STATUS_RECEIVED,
            'attempts' => 0,
            'created_at' => Carbon::now(),
        ]);

        if ($inserted === 0) {
            return response()->json(['success' => true, 'message' => 'Duplicate event.']);
        }

        $id = (int) DB::table('webhook_event')
            ->where('provider', WebhookEvent::PROVIDER_RAZORPAY)
            ->where('event_id', $eventId)
            ->value('id');

        try {
            ProcessWebhookEvent::dispatch($id);
        } catch (Throwable $e) {
            // Sync queue (or a dispatch error): the row keeps the failure for replay.
            report($e);
        }

        return response()->json(['success' => true]);
    }

    private function recordInvalid(?string $eventType): void
    {
        try {
            DB::table('webhook_event')->insert([
                'provider' => WebhookEvent::PROVIDER_RAZORPAY,
                'event_id' => 'invalid:'.Str::uuid(),
                'event_type' => $eventType,
                'signature_valid' => (int) config('core.no'),
                'payload' => null,
                'status' => WebhookEvent::STATUS_IGNORED,
                'attempts' => 0,
                'error' => 'Invalid signature',
                'created_at' => Carbon::now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
