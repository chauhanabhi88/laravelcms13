<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Modules\Core\Contracts\ProvidesAppConfig;
use Modules\Core\Http\Controllers\Api\ApiController;
use Throwable;

/**
 * GET /api/v1/app/config: what a mobile app needs before it can show anything - API and app
 * versions, currency, paging limits and the sections modules contribute (payment keys and COD
 * limits, delivery fee rules, support contacts, feature flags). Public: it holds no secrets, only
 * the Razorpay *key id*.
 */
class AppConfigController extends ApiController
{
    public function show(): JsonResponse
    {
        $config = [
            'app' => [
                'name' => (string) config('app.name'),
                'api_versions' => array_values((array) config('core.api_versions', ['v1'])),
                'maintenance' => (bool) config('core.mobile.maintenance', false),
                'min_version' => (array) config('core.mobile.min_version', []),
                'latest_version' => (array) config('core.mobile.latest_version', []),
            ],
            'currency' => ['code' => 'INR', 'symbol' => '₹', 'decimals' => 2],
            'locale' => [
                'default' => (string) config('app.locale'),
                'accept_language' => (bool) config('core.translation_api'),
            ],
            'limits' => ['per_page_max' => 50],
        ];

        foreach (app()->tagged(ProvidesAppConfig::TAG) as $provider) {
            /** @var ProvidesAppConfig $provider */
            try {
                $config[$provider->appConfigKey()] = $provider->appConfig();
            } catch (Throwable $e) {
                Log::warning('App config section skipped: '.$provider::class.': '.$e->getMessage());
            }
        }

        return $this->ok($config);
    }
}
