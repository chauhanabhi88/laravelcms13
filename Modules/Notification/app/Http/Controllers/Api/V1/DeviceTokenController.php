<?php

declare(strict_types=1);

namespace Modules\Notification\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Customer\Models\Customer;
use Modules\Notification\Services\DeviceTokenRegistry;

/**
 * Push token registration for the customer apps: POST /customer/device-tokens
 * {device_token, device_type} (idempotent) and DELETE /customer/device-tokens {device_token}
 * on logout.
 */
class DeviceTokenController extends Controller
{
    public function __construct(private readonly DeviceTokenRegistry $tokens) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_token' => ['required', 'string', 'min:20', 'max:255'],
            'device_type' => ['nullable', 'string', Rule::in((array) config('notification.device_types', []))],
        ]);

        $device = $this->tokens->register($this->customer($request), $validated['device_token'], $validated['device_type'] ?? null);

        return response()->json([
            'success' => true,
            'message' => trans('notification::front.device_tokens.saved'),
            'data' => ['id' => $device->id, 'device_type' => $device->device_type],
        ], $device->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate(['device_token' => ['required', 'string', 'max:255']]);

        $this->tokens->forgetFor($this->customer($request), $validated['device_token']);

        return response()->json(['success' => true, 'message' => trans('notification::front.device_tokens.removed')]);
    }

    protected function customer(Request $request): Customer
    {
        /** @var Customer */
        return $request->user('api');
    }
}
