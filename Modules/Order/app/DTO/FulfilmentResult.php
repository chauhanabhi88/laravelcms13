<?php

declare(strict_types=1);

namespace Modules\Order\DTO;

/**
 * Per-order outcome of a bulk desk action (accept, pack): each seller order succeeds or fails
 * on its own, so one bad row never blocks the rest.
 */
final class FulfilmentResult
{
    public const OK = 'ok';

    public const AWB_PENDING = 'awb_pending';

    public const FAILED = 'failed';

    /**
     * @var array<int, array{sub_order_no: string, outcome: string, message: string|null}>
     */
    private array $rows = [];

    public function add(int $sellerOrderId, string $subOrderNo, string $outcome, ?string $message = null): void
    {
        $this->rows[$sellerOrderId] = ['sub_order_no' => $subOrderNo, 'outcome' => $outcome, 'message' => $message];
    }

    /**
     * @return array<int, array{sub_order_no: string, outcome: string, message: string|null}>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    public function count(string $outcome): int
    {
        return count(array_filter($this->rows, fn (array $row): bool => $row['outcome'] === $outcome));
    }

    public function outcome(int $sellerOrderId): ?string
    {
        return $this->rows[$sellerOrderId]['outcome'] ?? null;
    }

    /**
     * "<sub-order>: <reason>" lines of the failed and pending rows.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        return array_values(array_map(
            fn (array $row): string => $row['sub_order_no'].': '.$row['message'],
            array_filter($this->rows, fn (array $row): bool => $row['outcome'] !== self::OK && $row['message'] !== null),
        ));
    }
}
