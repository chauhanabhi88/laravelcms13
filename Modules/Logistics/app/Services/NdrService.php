<?php

declare(strict_types=1);

namespace Modules\Logistics\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Logistics\Events\NdrRaised;
use Modules\Logistics\Exceptions\CourierException;
use Modules\Logistics\Exceptions\ShipmentException;
use Modules\Logistics\Models\NdrCase;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Support\CourierDriverManager;
use Modules\Logistics\Support\LogisticsSettings;
use Modules\Logistics\Support\ShipmentAlerts;

/**
 * Failed delivery attempts (FR-LOG-008, plan M13 §5.6). raise() keeps one open case per
 * shipment and announces NdrRaised (the customer is asked: re-attempt, update address &
 * phone, or cancel). act() passes the decision to the courier's NDR API (the manual driver
 * leaves it to Logistics Ops). After auto_rto_after_attempts failed attempts the package is
 * sent back automatically.
 */
class NdrService
{
    public function __construct(
        private readonly CourierDriverManager $drivers,
        private readonly ShipmentAlerts $alerts,
    ) {}

    public function raise(Shipment $shipment, ?string $reason, int $attempts): NdrCase
    {
        $case = NdrCase::query()->where('shipment_id', $shipment->id)->open()->lockForUpdate()->first();
        $isNew = $case === null;

        $case ??= new NdrCase(['shipment_id' => $shipment->id, 'status' => NdrCase::STATUS_OPEN]);
        $changed = $isNew || $case->attempts !== max(1, $attempts);
        $case->fill([
            'reason' => $reason !== null ? mb_substr($reason, 0, 255) : $case->reason,
            'attempts' => max(1, $attempts),
        ])->save();

        $threshold = LogisticsSettings::autoRtoAfterAttempts();

        if ($threshold > 0 && $case->attempts >= $threshold) {
            try {
                $this->act($case, NdrCase::ACTION_CANCEL, ['comments' => trans('logistics::ndr_case.messages.auto_rto')], 'system');
            } catch (CourierException|ShipmentException $e) {
                report($e);
                $this->alerts->flag($shipment, trans('logistics::shipment.attention.auto_rto_failed', ['error' => $e->getMessage()]));
            }

            return $case->refresh();
        }

        if ($changed) {
            NdrRaised::dispatch($case);
        }

        return $case;
    }

    /**
     * Apply the action chosen for an open case: reattempt / change_address (re-attempt with
     * the new phone and address in $payload) or cancel (return to origin).
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws ShipmentException|CourierException
     */
    public function act(NdrCase $case, string $action, array $payload, string $actionedBy): NdrCase
    {
        if (! in_array($action, (array) config('logistics.ndr_actions'), true)) {
            throw new ShipmentException(trans('logistics::ndr_case.messages.invalid_action'), 'invalid_action');
        }

        $case->loadMissing('shipment.courier');

        if (! $case->isOpen()) {
            throw new ShipmentException(trans('logistics::ndr_case.messages.not_open'), 'not_open');
        }

        $shipment = $case->shipment;

        if ($shipment->courier !== null) {
            $this->drivers->for($shipment->courier)->ndrAction($shipment, $action === NdrCase::ACTION_CANCEL ? 'return' : 're-attempt', $payload);
        }

        DB::transaction(function () use ($case, $action, $payload, $actionedBy): void {
            $case->forceFill([
                'customer_action' => $action,
                'action_payload' => $payload === [] ? null : $payload,
                'status' => NdrCase::STATUS_ACTIONED,
                'actioned_by' => mb_substr($actionedBy, 0, 40),
                'actioned_at' => Carbon::now(),
            ])->save();
        });

        return $case;
    }
}
