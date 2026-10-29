<?php

use Laravel\Passport\Http\Middleware\CheckToken;
use Modules\Customer\Services\CustomerAuthService;

/*
|--------------------------------------------------------------------------
| Customer notification API (M09)
|--------------------------------------------------------------------------
|
| api/{version}/customer/notifications, .../{id}/read, .../read-all and the push token
| endpoints, behind the same Passport "customer" scope as the M08 customer API.
|
*/

if ($apiVersions = config('core.api_versions')) {
    foreach ($apiVersions as $version) {
        $upperCaseVersion = strtoupper($version);

        Route::prefix($version.'/customer')
            ->middleware(['auth:api', CheckToken::using(CustomerAuthService::API_SCOPE), 'throttle:api', 'api.envelope'])
            ->group(function () use ($upperCaseVersion) {
                Route::get('/notifications', [
                    'as' => 'notification_api.notifications.index',
                    'uses' => $upperCaseVersion.'\NotificationController@index',
                ]);
                Route::post('/notifications/read-all', [
                    'as' => 'notification_api.notifications.read_all',
                    'uses' => $upperCaseVersion.'\NotificationController@readAll',
                ]);
                Route::post('/notifications/{id}/read', [
                    'as' => 'notification_api.notifications.read',
                    'uses' => $upperCaseVersion.'\NotificationController@read',
                ]);
                Route::post('/device-tokens', [
                    'as' => 'notification_api.device_tokens.store',
                    'uses' => $upperCaseVersion.'\DeviceTokenController@store',
                ]);
                Route::delete('/device-tokens', [
                    'as' => 'notification_api.device_tokens.destroy',
                    'uses' => $upperCaseVersion.'\DeviceTokenController@destroy',
                ]);
            });
    }
}
