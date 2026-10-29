<?php

declare(strict_types=1);

namespace Modules\Customer\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Token;
use Modules\Core\Http\Controllers\Api\ApiController;
use Modules\Customer\Models\Customer;

/**
 * Signed-in devices of the customer (plan M20 §3.2): every live personal access token is one
 * device, named by the device_name sent to otp/verify. GET lists them (flagging the caller's own),
 * DELETE signs one out. Another customer's token is a 404.
 */
class DeviceController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $current = $this->currentTokenId($request);

        $devices = $this->customer($request)->tokens()
            ->where('revoked', false)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Token $token): array => [
                'id' => $token->id,
                'name' => $token->name,
                'scopes' => $token->scopes,
                'current' => $token->id === $current,
                'created_at' => $token->created_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return $this->ok($devices);
    }

    public function destroy(Request $request): JsonResponse
    {
        $token = $this->customer($request)->tokens()
            ->where('revoked', false)
            ->where('id', (string) $request->route('device'))
            ->first();

        if ($token === null) {
            return $this->fail(404, trans('core::api.messages.device_not_found'));
        }

        $token->revoke();

        return $this->ok(['current' => $token->id === $this->currentTokenId($request)], message: trans('core::api.messages.device_revoked'));
    }

    protected function currentTokenId(Request $request): ?string
    {
        $token = $this->customer($request)->token();

        // A transient token (Passport::actingAs in tests) has no row.
        return $token instanceof AccessToken ? ($token->toArray()['oauth_access_token_id'] ?? null) : null;
    }

    protected function customer(Request $request): Customer
    {
        /** @var Customer */
        return $request->user('api');
    }
}
