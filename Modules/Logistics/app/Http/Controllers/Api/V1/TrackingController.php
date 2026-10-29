<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Api\ApiController;
use Modules\Customer\Models\Customer;
use Modules\Logistics\Support\TimelineBuilder;
use Modules\Order\Http\Controllers\Concerns\ResolvesOwnOrder;

/**
 * GET api/{version}/orders/{orderNo}/tracking: the shipment timeline of each package of the
 * customer's order (plan M13 §6). Another customer's order is a 404.
 */
class TrackingController extends ApiController
{
    use ResolvesOwnOrder;

    public function __construct(private readonly TimelineBuilder $timelines) {}

    public function show(Request $request): JsonResponse
    {
        $customer = $request->user();
        $order = $this->ownOrder($request, $customer instanceof Customer ? $customer : null);

        return $this->ok([
            'order_no' => $order->order_no,
            'packages' => array_map(fn (array $timeline): array => self::serialize($timeline), $this->timelines->forOrder($order)),
        ]);
    }

    /**
     * Dates as ISO-8601 strings.
     *
     * @param  array<string, mixed>  $timeline
     * @return array<string, mixed>
     */
    public static function serialize(array $timeline): array
    {
        $date = fn ($value): ?string => $value instanceof \DateTimeInterface ? $value->format(DATE_ATOM) : null;

        return [
            'sub_order_no' => $timeline['sub_order_no'],
            'status' => $timeline['status'],
            'courier' => $timeline['courier'],
            'awb' => $timeline['awb'],
            'tracking_url' => $timeline['tracking_url'],
            'edd' => $timeline['edd']?->toDateString(),
            'steps' => array_map(fn (array $step): array => ['key' => $step['key'], 'label' => $step['label'], 'at' => $date($step['at']), 'state' => $step['state']], $timeline['steps']),
            'branch' => $timeline['branch'] === null ? null : [
                'key' => $timeline['branch']['key'],
                'label' => $timeline['branch']['label'],
                'at' => $date($timeline['branch']['at']),
                'note' => $timeline['branch']['note'],
            ],
            'scans' => array_map(fn (array $scan): array => ['label' => $scan['label'], 'location' => $scan['location'], 'remarks' => $scan['remarks'], 'at' => $date($scan['at'])], $timeline['scans']),
        ];
    }
}
