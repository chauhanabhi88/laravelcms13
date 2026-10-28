<?php

declare(strict_types=1);

namespace Modules\Logistics\Support;

use Modules\Logistics\Contracts\CourierDriver;
use Modules\Logistics\Drivers\FakeCourierDriver;
use Modules\Logistics\Drivers\ManualCourierDriver;
use Modules\Logistics\Drivers\ShiprocketDriver;
use Modules\Logistics\Exceptions\CourierException;
use Modules\Logistics\Models\Courier;
use Modules\Logistics\Support\Shiprocket\ShiprocketClient;

/**
 * Resolves the CourierDriver of a courier row. A Shiprocket courier uses its own encrypted
 * credentials when set, else the .env API user.
 */
class CourierDriverManager
{
    /**
     * @throws CourierException
     */
    public function for(Courier $courier): CourierDriver
    {
        return match ($courier->driver) {
            Courier::DRIVER_SHIPROCKET => new ShiprocketDriver($this->shiprocketClient($courier)),
            Courier::DRIVER_MANUAL => app(ManualCourierDriver::class),
            Courier::DRIVER_FAKE => app(FakeCourierDriver::class),
            default => throw new CourierException(trans('logistics::logistics.errors.unknown_driver', ['driver' => $courier->driver]), 'unknown_driver'),
        };
    }

    /**
     * Whether calls to the courier's API can be made at all (credentials present).
     */
    public function isConfigured(Courier $courier): bool
    {
        return match ($courier->driver) {
            Courier::DRIVER_SHIPROCKET => $this->shiprocketClient($courier)->isConfigured(),
            Courier::DRIVER_MANUAL, Courier::DRIVER_FAKE => true,
            default => false,
        };
    }

    public function shiprocketClient(Courier $courier): ShiprocketClient
    {
        return ShiprocketClient::fromConfig($courier->credential('email'), $courier->credential('password'));
    }
}
