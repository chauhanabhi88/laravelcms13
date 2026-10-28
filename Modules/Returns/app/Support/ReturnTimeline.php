<?php

declare(strict_types=1);

namespace Modules\Returns\Support;

use Illuminate\Support\Carbon;
use Modules\Core\Models\StatusHistory;
use Modules\Returns\Models\ReturnRequest;

/**
 * The return's progress for <x-theme::timeline> (FR-RET-005): Requested → Approved → Pickup
 * scheduled → Picked up → Received → Quality check → Refunded (or Exchange shipped), with the
 * time each step was reached, plus a closing branch (rejected, cancelled, failed QC, closed).
 */
class ReturnTimeline
{
    public const RETURN_STEPS = [
        ReturnRequest::STATUS_REQUESTED,
        ReturnRequest::STATUS_APPROVED,
        ReturnRequest::STATUS_PICKUP_SCHEDULED,
        ReturnRequest::STATUS_PICKED_UP,
        ReturnRequest::STATUS_RECEIVED,
        ReturnRequest::STATUS_QC_PASSED,
        ReturnRequest::STATUS_REFUNDED,
    ];

    public const EXCHANGE_STEPS = [
        ReturnRequest::STATUS_REQUESTED,
        ReturnRequest::STATUS_APPROVED,
        ReturnRequest::STATUS_PICKUP_SCHEDULED,
        ReturnRequest::STATUS_PICKED_UP,
        ReturnRequest::STATUS_RECEIVED,
        ReturnRequest::STATUS_QC_PASSED,
        ReturnRequest::STATUS_EXCHANGE_ORDER_CREATED,
    ];

    private const BRANCHES = [ReturnRequest::STATUS_REJECTED, ReturnRequest::STATUS_CANCELLED, ReturnRequest::STATUS_QC_FAILED, ReturnRequest::STATUS_CLOSED];

    /**
     * @return list<array{label: string, at: Carbon|null, state: string, note?: string|null}>
     */
    public function build(ReturnRequest $return): array
    {
        $steps = $return->isExchange() ? self::EXCHANGE_STEPS : self::RETURN_STEPS;
        $times = $this->times($return);
        $status = $return->status === ReturnRequest::STATUS_REFUND_INITIATED ? ReturnRequest::STATUS_REFUNDED : $return->status;
        $branch = in_array($status, self::BRANCHES, true);

        $reached = $branch ? $this->lastReached($steps, $times) : (int) array_search($status, $steps, true);
        $finished = in_array($return->status, [ReturnRequest::STATUS_REFUNDED, ReturnRequest::STATUS_EXCHANGE_ORDER_CREATED], true);
        $events = [];

        foreach ($steps as $index => $step) {
            if ($branch && $index > $reached) {
                break;
            }

            $state = match (true) {
                $index < $reached => 'done',
                $index === $reached => ($finished || $branch) ? 'done' : 'current',
                default => 'pending',
            };

            $label = $step === ReturnRequest::STATUS_REFUNDED && $return->status === ReturnRequest::STATUS_REFUND_INITIATED
                ? trans('returns::returns.timeline.refund_initiated')
                : trans('returns::returns.timeline.'.$step);

            $events[] = ['label' => $label, 'at' => $index <= $reached ? ($times[$step] ?? null) : null, 'state' => $state];
        }

        if ($branch) {
            $events[] = [
                'label' => trans('returns::returns.timeline.'.$return->status),
                'at' => $times[$return->status] ?? $return->updated_at,
                'state' => 'current',
                'note' => $return->status === ReturnRequest::STATUS_QC_FAILED ? $return->qc_note : $return->decision_note,
            ];
        }

        return $events;
    }

    /**
     * When each status was first reached.
     *
     * @return array<string, Carbon|null>
     */
    protected function times(ReturnRequest $return): array
    {
        $times = [];

        foreach ($return->statusHistories()->get() as $history) {
            /** @var StatusHistory $history */
            $times[$history->to_status] ??= $history->created_at;
        }

        // A refund at pickup shows on the last step even before QC.
        if ($return->refunded_at !== null) {
            $times[ReturnRequest::STATUS_REFUNDED] ??= $return->refunded_at;
        }

        return $times;
    }

    /**
     * @param  list<string>  $steps
     * @param  array<string, Carbon|null>  $times
     */
    protected function lastReached(array $steps, array $times): int
    {
        $reached = 0;

        foreach ($steps as $index => $step) {
            if (isset($times[$step])) {
                $reached = $index;
            }
        }

        return $reached;
    }
}
