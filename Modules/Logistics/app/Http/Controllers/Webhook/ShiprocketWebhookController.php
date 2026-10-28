<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Logistics\Jobs\ProcessShiprocketWebhook;
use Modules\Payment\Models\WebhookEvent;
use Throwable;

/**
 * POST /webhooks/shiprocket (plan M13 §5.4). Answers fast and does the work in a queued job:
 *
 * 1. the x-api-key header must equal SHIPROCKET_WEBHOOK_TOKEN (the token set in the Shiprocket
 *    dashboard); otherwise 401 and a row with signature_valid = 2 and no payload;
 * 2. insertOrIgnore into webhook_event (provider "shiprocket", event id = hash of awb +
 *    status id + timestamp): a redelivery is a 200 with no work;
 * 3. ProcessShiprocketWebhook is dispatched; its failures stay on the row and never turn into
 *    a non-200 (Shiprocket would keep redelivering).
 */
class ShiprocketWebhookController extends Controller
{
    public const PROVIDER = 'shiprocket';

    public function handle(Request $request): JsonResponse
    {
        $body = $request->getContent();
        $payload = json_decode($body, true);
        $eventType = is_array($payload) ? $this->eventType($payload) : null;
        $expected = (string) config('logistics.shiprocket.webhook_token');

        if ($expected === '' || ! hash_equals($expected, (string) $request->header('x-api-key'))) {
            $this->recordInvalid($eventType);
            Log::channel('integrations')->warning('shiprocket webhook rejected: bad token', ['ip' => $request->ip(), 'event' => $eventType]);

            return response()->json(['success' => false, 'message' => 'Invalid token.'], 401);
        }

        if (! is_array($payload)) {
            return response()->json(['success' => false, 'message' => 'Invalid payload.'], 400);
        }

        $eventId = self::eventId($payload, $body);

        $inserted = DB::table('webhook_event')->insertOrIgnore([
            'provider' => self::PROVIDER,
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
            ->where('provider', self::PROVIDER)
            ->where('event_id', $eventId)
            ->value('id');

        try {
            ProcessShiprocketWebhook::dispatch($id);
        } catch (Throwable $e) {
            // Sync queue (or a dispatch error): the row keeps the failure.
            report($e);
        }

        return response()->json(['success' => true]);
    }

    /**
     * sha1(awb | status id | timestamp); the whole body when the payload has none of them.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function eventId(array $payload, string $body): string
    {
        $parts = [
            $payload['awb'] ?? '',
            $payload['current_status_id'] ?? $payload['shipment_status_id'] ?? $payload['current_status'] ?? '',
            $payload['current_timestamp'] ?? '',
        ];

        $parts = array_map(fn ($part): string => is_scalar($part) ? (string) $part : '', $parts);

        return implode('', $parts) === '' ? 'body:'.hash('sha256', $body) : sha1(implode('|', $parts));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function eventType(array $payload): ?string
    {
        $type = $payload['current_status'] ?? $payload['shipment_status'] ?? null;

        return is_string($type) && $type !== '' ? mb_substr($type, 0, 60) : null;
    }

    private function recordInvalid(?string $eventType): void
    {
        try {
            DB::table('webhook_event')->insert([
                'provider' => self::PROVIDER,
                'event_id' => 'invalid:'.Str::uuid(),
                'event_type' => $eventType,
                'signature_valid' => (int) config('core.no'),
                'payload' => null,
                'status' => WebhookEvent::STATUS_IGNORED,
                'attempts' => 0,
                'error' => 'Invalid token',
                'created_at' => Carbon::now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
