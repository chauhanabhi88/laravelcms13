<?php

declare(strict_types=1);

namespace Modules\Returns\Support;

use Modules\Core\Contracts\ProvidesAppConfig;

/**
 * The "returns" section of GET /api/v1/app/config: the reasons offered and the photo limit.
 */
final class ReturnsAppConfig implements ProvidesAppConfig
{
    public function appConfigKey(): string
    {
        return 'returns';
    }

    public function appConfig(): array
    {
        return [
            'reasons' => (array) config('returns.reasons', []),
            'photo_required_reasons' => (array) config('returns.photo_required_reasons', []),
            'max_photos' => ReturnSettings::maxImages(),
        ];
    }
}
