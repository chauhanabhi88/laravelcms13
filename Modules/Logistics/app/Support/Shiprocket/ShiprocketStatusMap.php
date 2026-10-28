<?php

declare(strict_types=1);

namespace Modules\Logistics\Support\Shiprocket;

/**
 * Shiprocket status labels / ids → our shipment status (plan M13 §5.4). The map lives in
 * config('logistics.shiprocket.status_map'|'status_ids') because Shiprocket changes it.
 */
final class ShiprocketStatusMap
{
    /**
     * Our status for a Shiprocket label (or numeric status id), null when unknown.
     */
    public static function map(?string $label, int|string|null $statusId = null): ?string
    {
        $label = self::label($label, $statusId);

        if ($label === null) {
            return null;
        }

        $status = ((array) config('logistics.shiprocket.status_map'))[$label] ?? null;

        return is_string($status) ? $status : null;
    }

    /**
     * The normalised label: upper-case, single spaces, "_" and "-" read as spaces; falls back
     * to the label of $statusId.
     */
    public static function label(?string $label, int|string|null $statusId = null): ?string
    {
        $label = $label !== null ? strtoupper(trim((string) preg_replace('/[\s_-]+/', ' ', $label))) : '';

        if ($label === '' && $statusId !== null && is_numeric($statusId)) {
            $label = (string) (((array) config('logistics.shiprocket.status_ids'))[(int) $statusId] ?? '');
        }

        return $label !== '' ? $label : null;
    }
}
